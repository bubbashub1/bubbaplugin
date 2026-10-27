<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'bubbahub.co.uk') { http_response_code(403); exit; }
require_once __DIR__.'/admin-session-guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); exit; }
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $query=$db->prepare('SELECT sheet_value,received_at_utc,source_run_id FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id=? AND field_name=?');
 $query->execute([955,'price_from']);
 $row=$query->fetch(PDO::FETCH_ASSOC);
 if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'snapshot_missing']); exit; }
 echo json_encode(['ok'=>true,'activity_id'=>955,'field'=>'price_from','snapshot'=>$row,'mode'=>'read_only','activity_writes_performed'=>0]);
} catch (Throwable $error) {
 error_log('Snapshot identity lookup failed: '.$error->getMessage());
 http_response_code(503);
 echo json_encode(['ok'=>false,'error'=>'snapshot_unavailable','activity_writes_performed'=>0]);
}
