<?php
declare(strict_types=1);
/** Read-only batch comparison for Bubba Hub Google Sheets. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok'=>false,'error'=>'post_required']);
$config = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
$secret = (string)(getenv('BH_SYNC_TOKEN') ?: (is_array($config) ? ($config['sheets_sync_token'] ?? '') : ''));
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($secret === '') respond(503, ['ok'=>false,'error'=>'token_not_configured']);
if (!preg_match('/^Bearer (.+)$/i', $auth, $match) || !hash_equals($secret, trim($match[1]))) {
    respond(401, ['ok'=>false,'error'=>'unauthorized']);
}
$raw = file_get_contents('php://input', false, null, 0, 200001);
if ($raw === false || strlen($raw) > 200000) respond(413, ['ok'=>false,'error'=>'payload_too_large']);
$input = json_decode($raw, true);
if (!is_array($input) || ($input['action'] ?? '') !== 'compare' ||
    !isset($input['rows']) || !is_array($input['rows']) || count($input['rows']) > 25) {
    respond(400, ['ok'=>false,'error'=>'invalid_payload']);
}
try {
    require_once __DIR__.'/db.php';
    $db = bh_mysql();
    $columns = $db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
    $fields = array_values(array_intersect(
        ['title','description','status','booking_url','price','price_from','website','contact_email',
         'contact_phone','age_min_months','age_max_months','term_time_only','featured','image_path'],
        $columns
    ));
    $ids = [];
    foreach ($input['rows'] as $row) {
        if (!is_array($row) || !is_array($row['values'] ?? null)) respond(400, ['ok'=>false,'error'=>'invalid_row']);
        $id = trim((string)($row['values']['id'] ?? ''));
        if ($id !== '' && (!ctype_digit($id) || (int)$id <= 0)) respond(400, ['ok'=>false,'error'=>'invalid_id']);
        if ($id !== '') $ids[] = (int)$id;
    }
    $existing = [];
    if ($ids) {
        $ids = array_values(array_unique($ids));
        $stmt = $db->prepare('SELECT * FROM bh_activities WHERE id IN ('.implode(',', array_fill(0,count($ids),'?')).')');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) $existing[(int)$record['id']] = $record;
    }
    $results = [];
    $summary = ['matching'=>0,'different'=>0,'missing_on_website'=>0,'new_sheet_row'=>0];
    foreach ($input['rows'] as $row) {
        $values = $row['values'];
        $id = trim((string)($values['id'] ?? ''));
        $differences = [];
        if ($id === '') $status = 'new_sheet_row';
        elseif (!isset($existing[(int)$id])) $status = 'missing_on_website';
        else {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $values)) continue;
                $sheet = trim((string)($values[$field] ?? ''));
                $site = trim((string)($existing[(int)$id][$field] ?? ''));
                if ($sheet !== $site) $differences[] = ['field'=>$field,'sheet_value'=>$sheet,'website_value'=>$site];
            }
            $status = $differences ? 'different' : 'matching';
        }
        $summary[$status]++;
        $results[] = ['sheet_row'=>(int)($row['sheet_row'] ?? 0),'id'=>$id,'status'=>$status,'differences'=>$differences];
    }
    respond(200, ['ok'=>true,'mode'=>'read_only_compare','summary'=>$summary,'results'=>$results,'compared_fields'=>$fields]);
} catch (Throwable $error) {
    error_log('Sheets compare: '.$error->getMessage());
    respond(500, ['ok'=>false,'error'=>'comparison_failed']);
}
