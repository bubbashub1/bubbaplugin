<?php
declare(strict_types=1);
/**
 * Staging-only, one-record, explicitly gated activity write API.
 * Not a public importer. Requires private bearer token AND server enable flag.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function syncReply(int $code,array $payload): void { http_response_code($code); echo json_encode($payload,JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') syncReply(405,['ok'=>false,'error'=>'post_required']);
if (getenv('BH_SHEETS_WRITE_ENABLED') !== '1') syncReply(503,['ok'=>false,'error'=>'writes_disabled']);
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($host !== 'staging.bubbahub.co.uk') syncReply(403,['ok'=>false,'error'=>'staging_only']);
$config = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
$secret = (string)(getenv('BH_SYNC_TOKEN') ?: (is_array($config) ? ($config['sheets_sync_token'] ?? '') : ''));
$header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!$secret || !preg_match('/^Bearer (.+)$/i',$header,$m) || !hash_equals($secret,trim($m[1]))) syncReply(401,['ok'=>false,'error'=>'unauthorized']);
$body = file_get_contents('php://input',false,null,0,16385);
if ($body === false || strlen($body)>16384) syncReply(413,['ok'=>false,'error'=>'payload_too_large']);
$data = json_decode($body,true);
if (!is_array($data) || ($data['action'] ?? '') !== 'create_one' || !is_array($data['values'] ?? null)) syncReply(400,['ok'=>false,'error'=>'invalid_payload']);
$values = $data['values'];
$id = trim((string)($values['id'] ?? ''));
if ($id !== '' && (!ctype_digit($id) || (int)$id < 1)) syncReply(400,['ok'=>false,'error'=>'invalid_id']);
$title = trim((string)($values['title'] ?? ''));
if ($title === '' || mb_strlen($title)>200) syncReply(422,['ok'=>false,'error'=>'title_required']);
$allowId = (string)(getenv('BH_SHEETS_TEST_ACTIVITY_ID') ?: '');
if ($id === '' || $id !== $allowId) syncReply(403,['ok'=>false,'error'=>'test_id_not_allowed']);
try {
    require_once __DIR__.'/db.php';
    $pdo = bh_mysql();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id FROM bh_activities WHERE id = ? FOR UPDATE');
    $stmt->execute([(int)$id]);
    if ($stmt->fetch()) { $pdo->rollBack(); syncReply(409,['ok'=>false,'error'=>'id_already_exists']); }
    // Schema-sensitive production inserts are intentionally not attempted here.
    // Validate required bh_activities columns before enabling a full insert implementation.
    $pdo->rollBack();
    syncReply(501,['ok'=>false,'error'=>'schema_mapping_not_approved','requested_id'=>(int)$id]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Sheet write gate: '.$e->getMessage());
    syncReply(500,['ok'=>false,'error'=>'write_preflight_failed']);
}
