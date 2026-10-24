<?php
declare(strict_types=1);
/** Expo exhibitor review API. Uses existing dedicated admin session. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_name('BUBBAHUB_ADMINSESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
function reply(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(empty($_SESSION['bh_admin_authenticated']))reply(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity']) && time()-(int)$_SESSION['bh_admin_last_activity']>1800){
 unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_last_activity']);
 reply(401,['ok'=>false,'error'=>'admin_session_expired']);
}
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if($method==='POST'){
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 $host=explode(':',(string)($_SERVER['HTTP_HOST']??''))[0];
 $referer=(string)($_SERVER['HTTP_REFERER']??'');
 $sameOrigin=$origin!=='' && parse_url($origin,PHP_URL_SCHEME)==='https' && strcasecmp((string)parse_url($origin,PHP_URL_HOST),$host)===0;
 $sameReferer=$origin==='' && $referer!=='' && parse_url($referer,PHP_URL_SCHEME)==='https' && strcasecmp((string)parse_url($referer,PHP_URL_HOST),$host)===0;
 if(!$sameOrigin && !$sameReferer)reply(403,['ok'=>false,'error'=>'invalid_origin']);
 $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(empty($_SESSION['bh_expo_csrf'])||!hash_equals($_SESSION['bh_expo_csrf'],$token))reply(403,['ok'=>false,'error'=>'invalid_csrf']);
}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 if($method==='GET'){
  if(empty($_SESSION['bh_expo_csrf']))$_SESSION['bh_expo_csrf']=bin2hex(random_bytes(32));
  $stmt=$db->prepare("SELECT id,business_name,contact_name,contact_email,website_url,logo_url,description,participation_json,preferred_table,assigned_table,application_status,public_visible,submitted_at FROM bh_expo_exhibitors WHERE event_key=? ORDER BY submitted_at DESC LIMIT 250");
  $stmt->execute(['bubba-me-2027']);
  $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row){$details=json_decode((string)($row['participation_json']??''),true);$social=is_array($details)?($details['social_links']??[]):[];foreach(['facebook','instagram','tiktok'] as $network)$row[$network.'_url']=is_array($social)?(string)($social[$network]??''):'';$row['application_details']=is_array($details)?$details:[];unset($row['participation_json']);}unset($row);reply(200,['ok'=>true,'csrf_token'=>$_SESSION['bh_expo_csrf'],'exhibitors'=>$rows]);
 }
 if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)reply(413,['ok'=>false,'error'=>'request_too_large']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input))reply(400,['ok'=>false,'error'=>'invalid_json']);
 $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(($input['action']??'')==='manage_table'){
  if(!$id)reply(422,['ok'=>false,'error'=>'invalid_exhibitor']);
  $mode=(string)($input['mode']??'');
  if(!in_array($mode,['reassign','release'],true))reply(422,['ok'=>false,'error'=>'invalid_table_action']);
  $db->beginTransaction();
  $q=$db->prepare("SELECT id,business_name,preferred_table,application_status,public_visible FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027' FOR UPDATE");$q->execute([$id]);$current=$q->fetch(PDO::FETCH_ASSOC);
  if(!$current){$db->rollBack();reply(404,['ok'=>false,'error'=>'not_found']);}
  if($mode==='release'){
   $q=$db->prepare("UPDATE bh_expo_exhibitors SET application_status='cancelled',public_visible=0,assigned_table=NULL,reviewed_at=NOW() WHERE id=? AND event_key='bubba-me-2027'");$q->execute([$id]);
   $db->commit();reply(200,['ok'=>true,'released_table'=>$current['preferred_table'],'application_status'=>'cancelled']);
  }
  $table=filter_var($input['table']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>150]]);
  if(!$table){$db->rollBack();reply(422,['ok'=>false,'error'=>'invalid_table']);}
  $q=$db->prepare("SELECT layout_json FROM bh_expo_floor_plans WHERE event_key='bubba-me-2027' FOR UPDATE");$q->execute();$layout=$q->fetchColumn();$items=json_decode((string)$layout,true);
  $valid=false;foreach(is_array($items)?$items:[] as $item){if(($item['type']??'')==='table'&&(int)($item['id']??0)===$table){$valid=true;break;}}
  if(!$valid){$db->rollBack();reply(422,['ok'=>false,'error'=>'table_not_in_floor_plan']);}
  $q=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE event_key='bubba-me-2027' AND id<>? AND application_status IN ('pending','approved','confirmed') AND (preferred_table=? OR assigned_table=?) LIMIT 1 FOR UPDATE");$q->execute([$id,$table,$table]);
  if($q->fetchColumn()){$db->rollBack();reply(409,['ok'=>false,'error'=>'table_already_reserved']);}
  $q=$db->prepare("UPDATE bh_expo_exhibitors SET preferred_table=?,assigned_table=?,reviewed_at=NOW() WHERE id=? AND event_key='bubba-me-2027'");$q->execute([$table,$table,$id]);
  $db->commit();reply(200,['ok'=>true,'preferred_table'=>$table,'assigned_table'=>$table]);
 }
 if(($input['action']??'')==='delete'){
  $confirmation=(string)($input['confirm_business_name']??'');
  $lookup=$db->prepare("SELECT business_name FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027'");$lookup->execute([$id]);$current=$lookup->fetchColumn();
  if(!$id||$current===false)reply(404,['ok'=>false,'error'=>'not_found']);
  if(!hash_equals((string)$current,$confirmation))reply(422,['ok'=>false,'error'=>'confirmation_mismatch']);
  $delete=$db->prepare("DELETE FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027'");$delete->execute([$id]);
  reply(200,['ok'=>true,'deleted_id'=>$id]);
 }
 $status=(string)($input['application_status']??'');
 $visible=$input['public_visible']??null;
 if(!$id||!in_array($status,['pending','approved','rejected','cancelled'],true)||!in_array($visible,[0,1],true))reply(422,['ok'=>false,'error'=>'invalid_update']);
 if($status!=='approved' && $visible===1)reply(422,['ok'=>false,'error'=>'approval_required_for_publication']);
 $name=trim((string)($input['business_name']??''));$description=trim((string)($input['description']??''));$website=trim((string)($input['website_url']??''));$logo=trim((string)($input['logo_url']??''));
 if(mb_strlen($name)<1||mb_strlen($name)>150||mb_strlen($description)>2000)reply(422,['ok'=>false,'error'=>'invalid_exhibitor_details']);
 foreach([$website,$logo] as $url){if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('~^https://~i',$url)))reply(422,['ok'=>false,'error'=>'invalid_url']);}
 $social=[];foreach(['facebook','instagram','tiktok'] as $network){$url=trim((string)($input[$network.'_url']??''));if(strlen($url)>500||($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https')))reply(422,['ok'=>false,'error'=>'invalid_social_url','field'=>$network]);$social[$network]=$url;}
 $existing=$db->prepare("SELECT participation_json FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027'");$existing->execute([$id]);$existingJson=$existing->fetchColumn();if($existingJson===false)reply(404,['ok'=>false,'error'=>'not_found']);$details=json_decode((string)$existingJson,true);if(!is_array($details))$details=[];$details['social_links']=$social;$updatedJson=json_encode($details,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 $stmt=$db->prepare("UPDATE bh_expo_exhibitors SET application_status=?,public_visible=?,business_name=?,description=?,website_url=?,logo_url=?,participation_json=?,reviewed_at=NOW() WHERE id=? AND event_key='bubba-me-2027'");
 $stmt->execute([$status,$visible,$name,$description,$website?:null,$logo?:null,$updatedJson,$id]);
 if($stmt->rowCount()===0){
  $check=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027'");$check->execute([$id]);
  if(!$check->fetchColumn())reply(404,['ok'=>false,'error'=>'not_found']);
 }
 reply(200,['ok'=>true,'id'=>$id,'application_status'=>$status,'public_visible'=>$visible]);
}catch(Throwable $e){error_log('Expo admin exhibitor API failure: '.get_class($e));reply(503,['ok'=>false,'error'=>'temporarily_unavailable']);}
