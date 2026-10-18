<?php
declare(strict_types=1);
/** Staging-only authenticated, read-only website listing export for Sheets reconciliation. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function exportReply(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') exportReply(405, ['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') exportReply(403, ['ok'=>false,'error'=>'staging_only']);
$config = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
$secret = (string)(getenv('BH_SYNC_TOKEN') ?: (is_array($config) ? ($config['sheets_sync_token'] ?? '') : ''));
if ($secret === '') {
    $wpConfig = dirname(__DIR__).'/wp-config.php';
    if (is_readable($wpConfig)) {
        $source = file_get_contents($wpConfig);
        if (is_string($source) && preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])BH_SYNC_TOKEN=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/', $source, $matches)) $secret = $matches[2];
    }
}
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($secret === '' || !preg_match('/^Bearer (.+)$/i', $auth, $m) || !hash_equals($secret, trim($m[1]))) exportReply(401, ['ok'=>false,'error'=>'unauthorized']);
$raw = file_get_contents('php://input', false, null, 0, 4097);
if ($raw === false || strlen($raw)>4096) exportReply(413, ['ok'=>false,'error'=>'payload_too_large']);
$input = json_decode($raw, true);
if (!is_array($input) || ($input['action'] ?? '') !== 'export' || !isset($input['after_id']) || !is_int($input['after_id']) || $input['after_id'] < 0) exportReply(400, ['ok'=>false,'error'=>'invalid_payload']);
$limit = $input['limit'] ?? 100;
if (!is_int($limit) || $limit < 1 || $limit > 100) exportReply(400, ['ok'=>false,'error'=>'invalid_limit']);
try {
    require_once __DIR__.'/db.php';
    $pdo = bh_mysql();
    $available = $pdo->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
    $allowed = ['id','title','slug','description','category','age_range','price_from','booking_url','image_path','status','organiser_id','featured','county','age_min_months','age_max_months','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know'];
    $fields = array_values(array_intersect($allowed, $available));
    if (!in_array('id', $fields, true)) exportReply(503, ['ok'=>false,'error'=>'activity_id_missing']);
    $quoted = array_map(static fn($field) => '`'.$field.'`', $fields);
    $stmt = $pdo->prepare('SELECT '.implode(',', $quoted).' FROM bh_activities WHERE id > :after_id ORDER BY id ASC LIMIT :row_limit');
    $stmt->bindValue(':after_id', $input['after_id'], PDO::PARAM_INT);
    $stmt->bindValue(':row_limit', $limit + 1, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($records) > $limit;
    if ($hasMore) array_pop($records);
    $last = $records ? (int)$records[count($records)-1]['id'] : $input['after_id'];
    exportReply(200, ['ok'=>true,'mode'=>'read_only_export','records'=>$records,'fields'=>$fields,'next_after_id'=>$last,'has_more'=>$hasMore]);
} catch (Throwable $e) {
    error_log('Sheets staging export failed: '.$e->getMessage());
    exportReply(500, ['ok'=>false,'error'=>'export_failed']);
}
