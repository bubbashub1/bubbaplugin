<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function approval_reply(int $code,array $body):never{http_response_code($code);echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))approval_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')approval_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $_SESSION['bh_approval_csrf']??=bin2hex(random_bytes(32));
 $queues=[
  ['key'=>'leaders','label'=>'Leader registrations','description'=>'Pending class leader accounts awaiting review.','url'=>'/admin/admin-organisers-class-leaders.html','count'=>0,'items'=>[]],
  ['key'=>'listings','label'=>'Draft activity listings','description'=>'Draft activities that need an editorial review before publication.','url'=>'/admin/admin-activities-listings.html','count'=>0,'items'=>[]],
  ['key'=>'duplicate_reviews','label'=>'Possible duplicate submissions','description'=>'Leader submissions held back because the title and venue match an existing listing.','url'=>'/admin/admin-activities-listings.html','count'=>0,'items'=>[]]
 ];
 $q=$db->query("SELECT COUNT(*) FROM bh_users WHERE role='leader' AND status='pending'");
 $queues[0]['count']=(int)$q->fetchColumn();
 $q=$db->query("SELECT id,email,status FROM bh_users WHERE role='leader' AND status='pending' ORDER BY id DESC LIMIT 50");
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $u){$queues[0]['items'][]=['id'=>(int)$u['id'],'title'=>$u['email'],'status'=>'pending','url'=>'/admin/admin-organisers-class-leaders.html'];}
 $q=$db->query("SELECT COUNT(*) FROM bh_activities WHERE status='draft'");
 $queues[1]['count']=(int)$q->fetchColumn();
 $q=$db->query("SELECT id,title,status FROM bh_activities WHERE status='draft' ORDER BY updated_at DESC,id DESC LIMIT 50");
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $a){$queues[1]['items'][]=['id'=>(int)$a['id'],'title'=>$a['title'],'status'=>'draft','url'=>'/admin/admin-activities-listings.html'];}
 $duplicateTable=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_duplicate_review_requests'");
 $duplicateTable->execute();
 if((int)$duplicateTable->fetchColumn()>0){
  $q=$db->query("SELECT COUNT(*) FROM bh_duplicate_review_requests WHERE status='pending'");
  $queues[2]['count']=(int)$q->fetchColumn();
  $q=$db->query("SELECT r.id,r.activity_id,a.title,r.status,r.matched_activity_ids_json FROM bh_duplicate_review_requests r JOIN bh_activities a ON a.id=r.activity_id WHERE r.status='pending' ORDER BY r.created_at ASC LIMIT 50");
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
   $queues[2]['items'][]=['id'=>(int)$r['id'],'activity_id'=>(int)$r['activity_id'],'title'=>(string)$r['title'],'status'=>'pending duplicate review','matched_activity_ids'=>json_decode((string)$r['matched_activity_ids_json'],true)?:[],'url'=>'/admin/admin-activities-add-listing.html?id='.(int)$r['activity_id']];
  }
 }
 $history=[];
 $exists=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_admin_approval_reviews'");
 if((int)$exists->fetchColumn()>0){
  $stmt=$db->query("SELECT id,item_type,item_id,decision,note,created_at FROM bh_admin_approval_reviews ORDER BY created_at DESC,id DESC LIMIT 100");
  foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry){
   $history[]=['id'=>(int)$entry['id'],'type'=>$entry['item_type'],'item_id'=>(int)$entry['item_id'],'decision'=>$entry['decision'],'note'=>$entry['note'],'created_at'=>$entry['created_at']];
  }
 }
 $duplicateHistoryTable=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_duplicate_review_requests'");
 $duplicateHistoryTable->execute();
 if((int)$duplicateHistoryTable->fetchColumn()>0){
  $stmt=$db->query("SELECT r.activity_id,r.status,r.review_reason,r.reviewed_at,a.title FROM bh_duplicate_review_requests r JOIN bh_activities a ON a.id=r.activity_id WHERE r.status IN ('approved','rejected') ORDER BY r.reviewed_at DESC LIMIT 50");
  foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry){
   $history[]=['id'=>(int)$entry['activity_id'],'type'=>'duplicate_review','item_id'=>(int)$entry['activity_id'],'decision'=>(string)$entry['status'],'note'=>(string)($entry['review_reason']??''),'created_at'=>(string)($entry['reviewed_at']??'')];
  }
  usort($history,static fn($a,$b)=>strcmp((string)$b['created_at'],(string)$a['created_at']));
  $history=array_slice($history,0,100);
 }
 approval_reply(200,['ok'=>true,'read_only'=>false,'review_only'=>true,'csrf'=>$_SESSION['bh_approval_csrf'],'queues'=>$queues,'history'=>$history,'note'=>'Counts reflect database status, not a formal submission workflow.']);
}catch(Throwable $e){error_log('Admin approval centre: '.$e->getMessage());approval_reply(500,['ok'=>false,'error'=>'approval_centre_unavailable']);}
