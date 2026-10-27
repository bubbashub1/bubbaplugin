<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
function reply(int $code,array $data):never{http_response_code($code);echo json_encode($data);exit;}
if(empty($_SESSION['bh_admin_authenticated']))reply(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity'])&&time()-(int)$_SESSION['bh_admin_last_activity']>1800)reply(401,['ok'=>false,'error'=>'session_expired']);
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if($method==='POST'){
 $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??$_SERVER['HTTP_REFERER']??'');
 if(!$secure||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)reply(403,['ok'=>false,'error'=>'invalid_origin']);
 if(empty($_SESSION['bh_raffle_verification_csrf'])||!hash_equals($_SESSION['bh_raffle_verification_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))reply(403,['ok'=>false,'error'=>'invalid_csrf']);
}
if($method==='GET'&&empty($_SESSION['bh_raffle_verification_csrf']))$_SESSION['bh_raffle_verification_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['bh_raffle_verification_csrf']??'';
session_write_close();
$root=dirname(__DIR__);
if(!is_file($root.'/wp-load.php'))reply(503,['ok'=>false,'error'=>'wordpress_unavailable']);
require_once $root.'/wp-load.php';
global $wpdb;
$table=$wpdb->prefix.'bh_raffle_manual_verifications';
$entries=$wpdb->prefix.'frmt_form_entry';
$meta=$wpdb->prefix.'frmt_form_entry_meta';
foreach([$table,$entries,$meta] as $name){
 if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name))!==$name)reply(503,['ok'=>false,'error'=>'required_table_missing','table'=>$name===$table?'raffle_manual_verifications':'forminator']);
}
if($method==='GET'){
 $rows=$wpdb->get_results("SELECT entry_id,status,quantity,amount_pence,method,reference,verified_at FROM {$table} ORDER BY id DESC LIMIT 200",ARRAY_A);
 reply(200,['ok'=>true,'csrf'=>$csrf,'verifications'=>$rows,'notice'=>'Manual confirmations only. Check funds independently in Stripe or PayPal.']);
}
if((int)($_SERVER['CONTENT_LENGTH']??0)>5000)reply(413,['ok'=>false,'error'=>'too_large']);
$data=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($data))reply(400,['ok'=>false,'error'=>'invalid_json']);
$id=filter_var($data['entry_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$status=(string)($data['status']??'');
$methodName=(string)($data['method']??'');
$reference=trim((string)($data['reference']??''));
$note=trim((string)($data['note']??''));
if(!$id||!in_array($status,['paid','refunded'],true)||!in_array($methodName,['stripe','paypal'],true)||!preg_match('/^[A-Za-z0-9_:\\-]{6,160}$/',$reference)||strlen($note)>500)reply(422,['ok'=>false,'error'=>'invalid_fields']);
if(($data['independently_verified']??false)!==true)reply(422,['ok'=>false,'error'=>'verification_confirmation_required']);
try{
 $wpdb->query('START TRANSACTION');
 $entry=$wpdb->get_row($wpdb->prepare("SELECT entry_id FROM {$entries} WHERE entry_id=%d AND form_id=76 FOR UPDATE",$id),ARRAY_A);
 if(!$entry){$wpdb->query('ROLLBACK');reply(404,['ok'=>false,'error'=>'entry_not_found']);}
 $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE entry_id=%d FOR UPDATE",$id),ARRAY_A);
 if($existing){
  if($existing['status']===$status&&$existing['reference']===$reference){$wpdb->query('ROLLBACK');reply(409,['ok'=>false,'error'=>'already_recorded']);}
  if($existing['status']!=='paid'||$status!=='refunded'||$existing['reference']!==$reference||$existing['method']!==$methodName){$wpdb->query('ROLLBACK');reply(409,['ok'=>false,'error'=>'existing_verification_conflict']);}
  $ok=$wpdb->update($table,['status'=>'refunded','note'=>$note,'verified_at'=>current_time('mysql',true)],['entry_id'=>$id],['%s','%s','%s'],['%d']);
 }else{
  if($status!=='paid'){$wpdb->query('ROLLBACK');reply(422,['ok'=>false,'error'=>'paid_verification_required_first']);}
  $raw=$wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$meta} WHERE entry_id=%d AND meta_key='select-1' LIMIT 1",$id));
  $qty=filter_var(maybe_unserialize($raw),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100]]);
  if(!$qty){$wpdb->query('ROLLBACK');reply(422,['ok'=>false,'error'=>'invalid_ticket_quantity']);}
  $ok=$wpdb->insert($table,['entry_id'=>$id,'status'=>'paid','quantity'=>$qty,'amount_pence'=>$qty*200,'method'=>$methodName,'reference'=>$reference,'note'=>$note,'verified_at'=>current_time('mysql',true)],['%d','%s','%d','%d','%s','%s','%s','%s']);
 }
 if($ok===false){$wpdb->query('ROLLBACK');reply(409,['ok'=>false,'error'=>'duplicate_reference_or_write_failed']);}
 $wpdb->query('COMMIT');
 reply(200,['ok'=>true,'status'=>$status]);
}catch(Throwable $e){$wpdb->query('ROLLBACK');error_log('Raffle manual verification failed: '.get_class($e));reply(503,['ok'=>false,'error'=>'verification_unavailable']);}
