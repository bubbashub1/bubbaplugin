<?php
declare(strict_types=1);
/** Authenticated, paginated, read-only activity export for Sheets reconciliation. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhSheetsExportReply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bhSheetsExportReply(405,['ok'=>false,'error'=>'post_required']);
$secretFile = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) bhSheetsExportReply(503,['ok'=>false,'error'=>'sync_not_configured']);
require_once $secretFile;
$secret = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($secret) < 32) bhSheetsExportReply(503,['ok'=>false,'error'=>'sync_not_configured']);
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches) || !hash_equals($secret,trim($matches[1]))) {
    bhSheetsExportReply(401,['ok'=>false,'error'=>'unauthorized']);
}
$raw = file_get_contents('php://input',false,null,0,4097);
if (!is_string($raw) || strlen($raw)>4096) bhSheetsExportReply(413,['ok'=>false,'error'=>'payload_too_large']);
$body = json_decode($raw,true);
if (!is_array($body) || ($body['action'] ?? '') !== 'export') bhSheetsExportReply(400,['ok'=>false,'error'=>'invalid_action']);
$after = $body['after_id'] ?? 0;
$limit = $body['limit'] ?? 100;
if (!is_int($after) || $after < 0 || !is_int($limit) || $limit < 1 || $limit > 100) bhSheetsExportReply(400,['ok'=>false,'error'=>'invalid_pagination']);
try {
    require_once __DIR__ . '/db.php';
    $db = bh_mysql();
    $stmt = $db->prepare('SELECT id, title, price_from, age_range, status FROM bh_activities WHERE id > :after ORDER BY id ASC LIMIT :limit');
    $stmt->bindValue(':after',$after,PDO::PARAM_INT);
    $stmt->bindValue(':limit',$limit+1,PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows)>$limit;
    if ($hasMore) array_pop($rows);
    $records = array_map(static function(array $row): array {
        return ['id'=>(int)$row['id'],'title'=>(string)($row['title']??''),
            'price_from'=>(string)($row['price_from']??''),'age_range'=>(string)($row['age_range']??''),
            'status'=>(string)($row['status']??'')];
    },$rows);
    $next = $records ? $records[count($records)-1]['id'] : $after;
    bhSheetsExportReply(200,['ok'=>true,'mode'=>'read_only_export','fields'=>['id','title','price_from','age_range','status'],
        'records'=>$records,'has_more'=>$hasMore,'next_after_id'=>$next,'writes_performed'=>false]);
} catch (Throwable $e) {
    error_log('Sheets export failed: '.$e->getMessage());
    bhSheetsExportReply(500,['ok'=>false,'error'=>'export_failed']);
}
