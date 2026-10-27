<?php
declare(strict_types=1);
/**
 * Administrator-only, read-only live Sheets sync schema inspection.
 * Reports column metadata, not data values, credentials, or SQL statements.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function schemaReply(int $code,array $body):never {
 http_response_code($code);
 echo json_encode($body+['mode'=>'schema_read_only','activity_writes_performed'=>0],JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='bubbahub.co.uk')
 schemaReply(403,['ok'=>false,'error'=>'live_host_required']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')
 schemaReply(405,['ok'=>false,'error'=>'get_required']);
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $tables=['bh_sheets_sync_change_audit','bh_sheets_sync_live_baselines','bh_sheets_sync_live_sheet_snapshots','bh_activities'];
 $result=[];
 foreach($tables as $table) {
   $q=$db->prepare('SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
   $q->execute([$table]);
   $cols=$q->fetchAll(PDO::FETCH_ASSOC);
   $result[$table]=['exists'=>count($cols)>0,'columns'=>$cols];
 }
 schemaReply(200,['ok'=>true,'tables'=>$result,'note'=>'Schema only. No row contents or data changes.']);
} catch(Throwable $e) {
 error_log('Live sync schema inspection failed: '.$e->getMessage());
 schemaReply(503,['ok'=>false,'error'=>'schema_inspection_unavailable']);
}
