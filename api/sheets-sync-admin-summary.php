<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $code,array $payload): never {http_response_code($code);echo json_encode($payload);exit;}
if (($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if (!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??'')))) reply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
try {
  require_once __DIR__.'/db.php';
  $db=bh_mysql();
  $count=(int)$db->query("SELECT COUNT(*) FROM bh_sheets_sync_baselines WHERE environment='staging'")->fetchColumn();
  $pending=(int)$db->query("SELECT COUNT(*) FROM bh_sheets_sync_review_queue WHERE environment='staging' AND status='pending'")->fetchColumn();
  $stmt=$db->query("SELECT id,activity_id,field_name,website_value,sheet_value,status,created_at_utc FROM bh_sheets_sync_review_queue WHERE environment='staging' AND status='pending' ORDER BY created_at_utc ASC,id ASC LIMIT 100");
  $reviews=$stmt->fetchAll(PDO::FETCH_ASSOC);
  reply(200,['ok'=>true,'environment'=>'staging','trusted_baseline_count'=>$count,'pending_review_count'=>$pending,'reviews'=>$reviews,'review_queue_connected'=>true,'writes_enabled'=>false]);
} catch (Throwable $e) {
  reply(503,['ok'=>false,'error'=>'baseline_storage_unavailable','writes_enabled'=>false]);
}
