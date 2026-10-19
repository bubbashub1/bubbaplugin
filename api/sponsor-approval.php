<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')reply(403,['ok'=>false,'error'=>'https_required']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))reply(401,['ok'=>false,'error'=>'admin_required']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==strtolower((string)($_SERVER['HTTP_HOST']??'')))reply(403,['ok'=>false,'error'=>'invalid_origin']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>2000)reply(413,['ok'=>false,'error'=>'too_large']);
$body=json_decode((string)file_get_contents('php://input'),true);
$id=(string)($body['session_id']??'');$decision=(string)($body['decision']??'');
if(!in_array($decision,['approved','rejected'],true))reply(422,['ok'=>false,'error'=>'invalid_decision']);
try{
 require_once __DIR__.'/sponsor-records.php';
 $session=bh_sponsor_session($id);
 if(!$session||!bh_sponsor_verified($session))reply(422,['ok'=>false,'error'=>'payment_not_verified']);
 $m=$session['metadata'];
 $name=trim((string)($m['business_name']??''));
 if($name==='')reply(422,['ok'=>false,'error'=>'missing_business_name']);
 $website=(string)($m['website']??'');$logo=(string)($m['logo_url']??'');
 foreach([$website,$logo] as $url){if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'))reply(422,['ok'=>false,'error'=>'invalid_sponsor_url']);}
 $db=bh_sponsor_db();
 $q=$db->prepare("INSERT INTO bh_event_sponsor_approvals (stripe_session_id,event_key,business_name,tier,website,logo_url,approval_status) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE approval_status=VALUES(approval_status),reviewed_at=CURRENT_TIMESTAMP");
 $q->execute([$id,'bubba-me-2027',mb_substr($name,0,150),(int)$m['tier'],$website,$logo,$decision]);
 reply(200,['ok'=>true,'status'=>$decision]);
}catch(Throwable $e){error_log('Sponsor approval failed: '.get_class($e));reply(503,['ok'=>false,'error'=>'approval_unavailable']);}
