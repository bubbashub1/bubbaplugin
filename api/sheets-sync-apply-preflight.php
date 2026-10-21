<?php
declare(strict_types=1);
// Phase 3 foundation: preview only. This endpoint NEVER writes to activities.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function phase3_reply(int $status,array $data):never {http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');phase3_reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))phase3_reply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=$db->query("SELECT q.id,q.activity_id,q.field_name,q.website_value,q.sheet_value,q.website_fingerprint,q.sheet_fingerprint,q.reviewed_by_admin_username FROM bh_sheets_sync_review_queue q WHERE q.environment='staging' AND q.status='approved' ORDER BY q.id ASC LIMIT 100");
 $items=[];$safe=0;$conflicts=0;
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
   if(!in_array($r['field_name'],['title','description','age_range','price_from','booking_url'],true))continue;
   $s=$db->prepare('SELECT `'.$r['field_name'].'` AS value FROM bh_activities WHERE id=?');
   $s->execute([$r['activity_id']]);$row=$s->fetch(PDO::FETCH_ASSOC);
   $current=$row?(string)($row['value']??''):null;
   $valid=$current!==null&&hash_equals((string)$r['website_fingerprint'],hash('sha256',$current))&&hash_equals((string)$r['website_fingerprint'],hash('sha256',(string)($r['website_value']??'')))&&hash_equals((string)$r['sheet_fingerprint'],hash('sha256',(string)($r['sheet_value']??'')));
   $status=$valid?'ready':'conflict';if($valid)$safe++;else $conflicts++;
   $items[]=['review_id'=>(int)$r['id'],'activity_id'=>(int)$r['activity_id'],'field_name'=>$r['field_name'],'status'=>$status,'reviewed_by'=>$r['reviewed_by_admin_username']];
 }
 phase3_reply(200,['ok'=>true,'mode'=>'apply_preflight_only','activity_writes_performed'=>0,'apply_enabled'=>false,'ready_count'=>$safe,'conflict_count'=>$conflicts,'reviews'=>$items]);
}catch(Throwable $e){phase3_reply(503,['ok'=>false,'error'=>'preflight_unavailable','activity_writes_performed'=>0]);}
