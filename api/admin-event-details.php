<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function out(int $code,array $data):never{http_response_code($code);echo json_encode($data);exit;}
$secure=!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off';
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_name('BUBBAHUB_ADMINSESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
if(empty($_SESSION['bh_admin_authenticated']))out(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity']) && time()-(int)$_SESSION['bh_admin_last_activity']>1800){
 unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_last_activity']);
 out(401,['ok'=>false,'error'=>'session_expired']);
}
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))out(405,['ok'=>false,'error'=>'method_not_allowed']);
if($method==='POST'){
 $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 $referer=(string)($_SERVER['HTTP_REFERER']??'');
 $source=$origin!==''?$origin:$referer;
 if(!$secure || !$source || parse_url($source,PHP_URL_SCHEME)!=='https' || strtolower((string)parse_url($source,PHP_URL_HOST))!==$host)out(403,['ok'=>false,'error'=>'invalid_origin']);
 $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(empty($_SESSION['bh_event_edit_csrf'])||!hash_equals($_SESSION['bh_event_edit_csrf'],$token))out(403,['ok'=>false,'error'=>'invalid_csrf']);
}
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 if($method==='GET'){
  if(empty($_SESSION['bh_event_edit_csrf']))$_SESSION['bh_event_edit_csrf']=bin2hex(random_bytes(32));
  $q=$db->prepare('SELECT event_key,title,description,starts_at,ends_at,venue,image_url,image_alt,revision FROM bh_event_details WHERE event_key=?');
  $q->execute(['bubba-me-2027']);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row)out(404,['ok'=>false,'error'=>'event_not_found']);
  out(200,['ok'=>true,'event'=>$row,'csrf_token'=>$_SESSION['bh_event_edit_csrf']]);
 }
 if((int)($_SERVER['CONTENT_LENGTH']??0)>16000)out(413,['ok'=>false,'error'=>'too_large']);
 $data=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($data))out(400,['ok'=>false,'error'=>'invalid_json']);
 $fields=['title'=>180,'description'=>8000,'venue'=>250,'image_url'=>1000,'image_alt'=>200];
 $values=[];
 foreach($fields as $field=>$max){
  if(!isset($data[$field])||!is_string($data[$field]))out(422,['ok'=>false,'error'=>'invalid_'.$field]);
  $values[$field]=trim($data[$field]);
  if(mb_strlen($values[$field])>$max)out(422,['ok'=>false,'error'=>'invalid_'.$field]);
 }
 if($values['title']===''||$values['venue']==='')out(422,['ok'=>false,'error'=>'required_fields']);
 if($values['image_url']!=='' && (!filter_var($values['image_url'],FILTER_VALIDATE_URL)||parse_url($values['image_url'],PHP_URL_SCHEME)!=='https'))out(422,['ok'=>false,'error'=>'invalid_image_url']);
 $dates=[];
 foreach(['starts_at','ends_at'] as $field){
  $raw=$data[$field]??'';
  if(!is_string($raw)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$raw))out(422,['ok'=>false,'error'=>'invalid_'.$field]);
  $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new DateTimeZone('Europe/London'));
  if(!$dt||$dt->format('Y-m-d\TH:i')!==$raw)out(422,['ok'=>false,'error'=>'invalid_'.$field]);
  $dates[$field]=$dt;
 }
 if($dates['ends_at']<=$dates['starts_at'])out(422,['ok'=>false,'error'=>'invalid_date_range']);
 $revision=filter_var($data['revision']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$revision)out(422,['ok'=>false,'error'=>'invalid_revision']);
 $q=$db->prepare('UPDATE bh_event_details SET title=?,description=?,starts_at=?,ends_at=?,venue=?,image_url=?,image_alt=?,revision=revision+1 WHERE event_key=? AND revision=?');
 $q->execute([$values['title'],$values['description'],$dates['starts_at']->format('Y-m-d H:i:s'),$dates['ends_at']->format('Y-m-d H:i:s'),$values['venue'],$values['image_url'],$values['image_alt'],'bubba-me-2027',$revision]);
 if($q->rowCount()!==1)out(409,['ok'=>false,'error'=>'stale_revision_reload']);
 out(200,['ok'=>true,'revision'=>$revision+1]);
}catch(Throwable $e){error_log('event_edit_api: '.$e->getMessage());out(503,['ok'=>false,'error'=>'event_storage_unavailable']);}
