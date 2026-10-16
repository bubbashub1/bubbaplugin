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
  ['key'=>'listings','label'=>'Draft activity listings','description'=>'Draft activities that need an editorial review before publication.','url'=>'/admin/admin-activities-listings.html','count'=>0,'items'=>[]]
 ];
 $q=$db->query("SELECT COUNT(*) FROM bh_users WHERE role='leader' AND status='pending'");
 $queues[0]['count']=(int)$q->fetchColumn();
 $q=$db->query("SELECT id,email,status FROM bh_users WHERE role='leader' AND status='pending' ORDER BY id DESC LIMIT 50");
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $u){$queues[0]['items'][]=['id'=>(int)$u['id'],'title'=>$u['email'],'status'=>'pending','url'=>'/admin/admin-organisers-class-leaders.html'];}
 $q=$db->query("SELECT COUNT(*) FROM bh_activities WHERE status='draft'");
 $queues[1]['count']=(int)$q->fetchColumn();
 $q=$db->query("SELECT id,title,status FROM bh_activities WHERE status='draft' ORDER BY updated_at DESC,id DESC LIMIT 50");
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $a){$queues[1]['items'][]=['id'=>(int)$a['id'],'title'=>$a['title'],'status'=>'draft','url'=>'/admin/admin-activities-listings.html'];}
 $history=[];
 $exists=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_admin_approval_reviews'");
 if((int)$exists->fetchColumn()>0){
  $stmt=$db->query("SELECT id,item_type,item_id,decision,note,created_at FROM bh_admin_approval_reviews ORDER BY created_at DESC,id DESC LIMIT 100");
  foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry){
   $history[]=['id'=>(int)$entry['id'],'type'=>$entry['item_type'],'item_id'=>(int)$entry['item_id'],'decision'=>$entry['decision'],'note'=>$entry['note'],'created_at'=>$entry['created_at']];
  }
 }
 approval_reply(200,['ok'=>true,'read_only'=>false,'review_only'=>true,'csrf'=>$_SESSION['bh_approval_csrf'],'queues'=>$queues,'history'=>$history,'note'=>'Counts reflect database status, not a formal submission workflow.']);
}catch(Throwable $e){error_log('Admin approval centre: '.$e->getMessage());approval_reply(500,['ok'=>false,'error'=>'approval_centre_unavailable']);}
