<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
function respond(int $s,array $v):never{http_response_code($s);echo json_encode($v);exit;}
if(empty($_SESSION['bh_admin_authenticated']))respond(401,['ok'=>false,'error'=>'admin_required']);
if(empty($_SESSION['bh_admin_last_activity'])||time()-(int)$_SESSION['bh_admin_last_activity']>1800)respond(401,['ok'=>false,'error'=>'session_expired']);
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))respond(405,['ok'=>false,'error'=>'method_not_allowed']);
if($method==='POST'){
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 if(parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)respond(403,['ok'=>false,'error'=>'invalid_origin']);
 if(empty($_SESSION['bh_raffle_review_csrf'])||!hash_equals($_SESSION['bh_raffle_review_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))respond(403,['ok'=>false,'error'=>'invalid_csrf']);
}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 if($method==='GET'){
  $_SESSION['bh_raffle_review_csrf']??=bin2hex(random_bytes(32));
  $q=$db->prepare("SELECT id,leader_id,title,description,image_url,donor_name,donor_url,prize_value,status,created_at FROM bh_expo_raffle_submissions WHERE event_key=? ORDER BY (status='pending') DESC,id DESC LIMIT 200");
  $q->execute(['bubba-me-2027']);respond(200,['ok'=>true,'csrf'=>$_SESSION['bh_raffle_review_csrf'],'submissions'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 $v=json_decode((string)file_get_contents('php://input'),true);
 $id=(int)($v['id']??0);$action=(string)($v['action']??'');
 if($id<1||!in_array($action,['approve','reject'],true))respond(422,['ok'=>false,'error'=>'invalid_request']);
 $db->beginTransaction();
 $q=$db->prepare('SELECT * FROM bh_expo_raffle_submissions WHERE id=? AND event_key=? FOR UPDATE');$q->execute([$id,'bubba-me-2027']);$s=$q->fetch(PDO::FETCH_ASSOC);
 if(!$s||$s['status']!=='pending'){$db->rollBack();respond(409,['ok'=>false,'error'=>'not_pending']);}
 if($action==='approve'){
  $q=$db->prepare("INSERT INTO bh_expo_raffle_prizes(event_key,title,description,image_url,donor_name,donor_url,prize_value,entry_type,status,sort_order) VALUES(?,?,?,?,?,?,?,'raffle','draft',0)");
  $q->execute(['bubba-me-2027',$s['title'],$s['description'],$s['image_url'],$s['donor_name'],$s['donor_url'],$s['prize_value']]);
 }
 $q=$db->prepare('UPDATE bh_expo_raffle_submissions SET status=?,reviewed_at=NOW() WHERE id=?');
 $q->execute([$action==='approve'?'approved':'rejected',$id]);$db->commit();
 respond(200,['ok'=>true,'result'=>$action==='approve'?'approved_as_draft':'rejected']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();respond(503,['ok'=>false,'error'=>'review_unavailable']);}
