<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
session_name('BUBBAHUB_ADMINSESSID');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
function done(int $s,array $d):never{http_response_code($s);echo json_encode($d);exit;}
if(empty($_SESSION['bh_admin_authenticated'])||empty($_SESSION['bh_admin_last_activity'])||time()-(int)$_SESSION['bh_admin_last_activity']>1800)done(401,['ok'=>false,'error'=>'admin_required']);
$_SESSION['bh_admin_last_activity']=time();$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))done(405,['ok'=>false]);
if($method==='POST'){$origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);if(parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)done(403,['ok'=>false,'error'=>'origin']);if(empty($_SESSION['bh_public_donation_csrf'])||empty($_SERVER['HTTP_X_CSRF_TOKEN'])||!hash_equals((string)$_SESSION['bh_public_donation_csrf'],(string)$_SERVER['HTTP_X_CSRF_TOKEN']))done(403,['ok'=>false,'error'=>'csrf']);}
try{require_once __DIR__.'/db.php';$db=bh_mysql();
if($method==='GET'){$_SESSION['bh_public_donation_csrf']=bin2hex(random_bytes(32));$q=$db->query("SELECT * FROM bh_expo_public_prize_donations ORDER BY (status='pending') DESC,id DESC LIMIT 200");done(200,['ok'=>true,'csrf'=>$_SESSION['bh_public_donation_csrf'],'submissions'=>$q->fetchAll(PDO::FETCH_ASSOC)]);}
$v=json_decode((string)file_get_contents('php://input'),true);$id=(int)($v['id']??0);$action=(string)($v['action']??'');if($id<1||!in_array($action,['approve','reject'],true))done(422,['ok'=>false,'error'=>'invalid_request']);
$db->beginTransaction();$q=$db->prepare("SELECT * FROM bh_expo_public_prize_donations WHERE id=? FOR UPDATE");$q->execute([$id]);$s=$q->fetch(PDO::FETCH_ASSOC);if(!$s||$s['status']!=='pending'){$db->rollBack();done(409,['ok'=>false,'error'=>'not_pending']);}
if($action==='approve'){$q=$db->prepare("INSERT INTO bh_expo_raffle_prizes(event_key,title,description,donor_name,donor_url,prize_value,entry_type,status) VALUES(?,?,?,?,?,?,'raffle','draft')");$q->execute([$s['event_key'],$s['title'],$s['description'],$s['public_credit']?$s['donor_name']:null,$s['public_credit']?$s['donor_url']:null,$s['prize_value']]);}
$q=$db->prepare('UPDATE bh_expo_public_prize_donations SET status=? WHERE id=?');$q->execute([$action==='approve'?'approved':'rejected',$id]);$db->commit();done(200,['ok'=>true,'result'=>$action==='approve'?'approved_as_draft':'rejected']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();done(503,['ok'=>false,'error'=>'review_unavailable']);}
