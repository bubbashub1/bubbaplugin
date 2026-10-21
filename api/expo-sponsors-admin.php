<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
function reply(int $code, array $body): never { http_response_code($code); echo json_encode($body); exit; }
if (empty($_SESSION['bh_admin_authenticated'])) reply(401,['ok'=>false,'error'=>'admin_required']);
if (empty($_SESSION['bh_admin_last_activity']) || time()-(int)$_SESSION['bh_admin_last_activity']>1800) reply(401,['ok'=>false,'error'=>'session_expired']);
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if ($method==='POST') {
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 if (parse_url($origin,PHP_URL_SCHEME)!=='https' || strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host) reply(403,['ok'=>false,'error'=>'invalid_origin']);
 if (empty($_SESSION['bh_sponsor_csrf']) || !hash_equals($_SESSION['bh_sponsor_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) reply(403,['ok'=>false,'error'=>'invalid_csrf']);
 if ((int)($_SERVER['CONTENT_LENGTH']??0)>10000) reply(413,['ok'=>false,'error'=>'too_large']);
}
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 if ($method==='GET') {
  $_SESSION['bh_sponsor_csrf']??=bin2hex(random_bytes(32));
  $q=$db->prepare('SELECT id,business_name,website_url,logo_url,approved,public_visible,sort_order FROM bh_expo_public_sponsors WHERE event_key=? ORDER BY sort_order,id LIMIT 200');
  $q->execute(['bubba-me-2027']);
  reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_sponsor_csrf'],'sponsors'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 $v=json_decode((string)file_get_contents('php://input'),true);
 if (!is_array($v)) reply(400,['ok'=>false,'error'=>'invalid_json']);
 $id=(int)($v['id']??0);
 $name=trim((string)($v['business_name']??''));
 if ($id<0 || $name==='' || mb_strlen($name)>160) reply(422,['ok'=>false,'error'=>'invalid_name']);
 $url=static function($value):?string {
  $value=trim((string)$value);
  if ($value==='') return null;
  if (strlen($value)>500 || !filter_var($value,FILTER_VALIDATE_URL) || strtolower((string)parse_url($value,PHP_URL_SCHEME))!=='https') reply(422,['ok'=>false,'error'=>'invalid_url']);
  return $value;
 };
 $website=$url($v['website_url']??'');
 $logo=$url($v['logo_url']??'');
 $approved=($v['approved']??false)===true?1:0;
 $visible=($v['public_visible']??false)===true?1:0;
 $sort=filter_var($v['sort_order']??0,FILTER_VALIDATE_INT);
 if ($sort===false || $sort < -10000 || $sort > 10000) reply(422,['ok'=>false,'error'=>'invalid_order']);
 if ($id>0) {
  $q=$db->prepare('UPDATE bh_expo_public_sponsors SET business_name=?,website_url=?,logo_url=?,approved=?,public_visible=?,sort_order=? WHERE id=? AND event_key=?');
  $q->execute([$name,$website,$logo,$approved,$visible,$sort,$id,'bubba-me-2027']);
  $check=$db->prepare('SELECT id FROM bh_expo_public_sponsors WHERE id=? AND event_key=?');
  $check->execute([$id,'bubba-me-2027']);
  if (!$check->fetchColumn()) reply(404,['ok'=>false,'error'=>'not_found']);
 } else {
  $q=$db->prepare('INSERT INTO bh_expo_public_sponsors(event_key,business_name,website_url,logo_url,approved,public_visible,sort_order) VALUES(?,?,?,?,?,?,?)');
  $q->execute(['bubba-me-2027',$name,$website,$logo,$approved,$visible,$sort]);
  $id=(int)$db->lastInsertId();
 }
 reply(200,['ok'=>true,'id'=>$id]);
} catch (Throwable $e) { reply(503,['ok'=>false,'error'=>'sponsor_admin_unavailable']); }
