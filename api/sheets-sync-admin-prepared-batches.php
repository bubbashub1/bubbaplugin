<?php
declare(strict_types=1);
// Staging-only, authenticated, read-only prepared batch browser.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function batch_reply(int $status,array $data):never {http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))batch_reply(403,['ok'=>false,'error'=>'staging_only']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');batch_reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
require_once __DIR__.'/admin-session-guard.php';
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $count=(int)$db->query("SELECT COUNT(*) FROM bh_sheets_override_batches WHERE status='prepared'")->fetchColumn();
 $stmt=$db->query("SELECT id,source_spreadsheet,source_tab,row_count,rows_json,status,created_at_utc FROM bh_sheets_override_batches WHERE status='prepared' ORDER BY id DESC LIMIT 20");
 $batches=[];
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $record){
  $rows=json_decode((string)$record['rows_json'],true);
  $preview=[];if(is_array($rows)){
   foreach(array_slice($rows,0,100) as $row){
    if(!is_array($row))continue;
    $fields=[];foreach(['title','description','age_range','price_from','booking_url'] as $field)if(array_key_exists($field,$row))$fields[$field]=is_scalar($row[$field])?(string)$row[$field]:'';
    $preview[]=['activity_id'=>(string)($row['activity_id']??''),'fields'=>$fields];
   }
  }
  $batches[]=['batch_id'=>(int)$record['id'],'spreadsheet'=>(string)$record['source_spreadsheet'],'tab'=>(string)$record['source_tab'],'row_count'=>(int)$record['row_count'],'status'=>'prepared','created_at_utc'=>(string)$record['created_at_utc'],'rows'=>$preview,'invalid_payload'=>!is_array($rows)];
 }
 batch_reply(200,['ok'=>true,'environment'=>'staging','mode'=>'prepared_batches_read_only','activity_writes_performed'=>0,'total_prepared_batches'=>$count,'batches'=>$batches]);
}catch(Throwable $e){batch_reply(503,['ok'=>false,'error'=>'prepared_batch_storage_unavailable','activity_writes_performed'=>0]);}
