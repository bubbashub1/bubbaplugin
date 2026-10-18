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
// Live admin-auth.php sets bh_admin_authenticated but not bh_admin_last_activity.
// Initialise Expo's own idle timer on first authorised request, then enforce 30 minutes.
if(empty($_SESSION['bh_admin_authenticated']))reply(401,['ok'=>false,'error'=>'admin_required']);
if(isset($_SESSION['bh_expo_admin_last_activity']) && time()-(int)$_SESSION['bh_expo_admin_last_activity']>1800){
 unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_expo_admin_last_activity'],$_SESSION['bh_expo_csrf']);
 reply(401,['ok'=>false,'error'=>'admin_session_expired']);
}
$_SESSION['bh_expo_admin_last_activity']=time();

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
  $stmt=$db->prepare("SELECT id,business_name,contact_name,contact_email,contact_phone,participation_json,donation_interest,insurance_policy,insurance_expiry,requirements,website_url,logo_url,description,preferred_table,assigned_table,application_status,public_visible,submitted_at FROM bh_expo_exhibitors WHERE event_key=? ORDER BY submitted_at DESC LIMIT 250");
  $stmt->execute(['bubba-me-2027']);
  reply(200,['ok'=>true,'csrf_token'=>$_SESSION['bh_expo_csrf'],'exhibitors'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)reply(413,['ok'=>false,'error'=>'request_too_large']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input))reply(400,['ok'=>false,'error'=>'invalid_json']);
 $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $status=(string)($input['application_status']??'');
 $visible=$input['public_visible']??null;
 if(!$id||!in_array($status,['pending','approved','rejected','cancelled'],true)||!in_array($visible,[0,1],true))reply(422,['ok'=>false,'error'=>'invalid_update']);
 if($status!=='approved' && $visible===1)reply(422,['ok'=>false,'error'=>'approval_required_for_publication']);
 $assigned=isset($input['assigned_table'])&&$input['assigned_table']!==''?filter_var($input['assigned_table'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>26]]):null;
 if(isset($input['assigned_table'])&&$input['assigned_table']!==''&&!$assigned)reply(422,['ok'=>false,'error'=>'invalid_assigned_table']);
 $name=trim((string)($input['business_name']??''));$description=trim((string)($input['description']??''));$website=trim((string)($input['website_url']??''));$logo=trim((string)($input['logo_url']??''));
 if(mb_strlen($name)<1||mb_strlen($name)>150||mb_strlen($description)>2000)reply(422,['ok'=>false,'error'=>'invalid_exhibitor_details']);
 foreach([$website,$logo] as $url){if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!preg_match('~^https://~i',$url)))reply(422,['ok'=>false,'error'=>'invalid_url']);}
 // Lock the canonical table row to serialize competing approvals for the same table.
 $db->beginTransaction();
 if($assigned!==null && $status==='approved'){
  $table=$db->prepare("SELECT id,status,exhibitor_id FROM bh_expo_tables WHERE event_key='bubba-me-2027' AND table_number=? FOR UPDATE");
  $table->execute([$assigned]);
  $tableRow=$table->fetch(PDO::FETCH_ASSOC);
  if(!$tableRow || $tableRow['status']==='blocked'){
   $db->rollBack();reply(409,['ok'=>false,'error'=>'table_unavailable']);
  }
  $check=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE event_key='bubba-me-2027' AND assigned_table=? AND application_status='approved' AND id<>? LIMIT 1 FOR UPDATE");
  $check->execute([$assigned,$id]);
  if($check->fetchColumn()){ $db->rollBack();reply(409,['ok'=>false,'error'=>'table_already_assigned']); }
 }
 if($status!=='approved')$assigned=null;
 $stmt=$db->prepare("UPDATE bh_expo_exhibitors SET application_status=?,public_visible=?,business_name=?,description=?,website_url=?,logo_url=?,assigned_table=?,reviewed_at=NOW() WHERE id=? AND event_key='bubba-me-2027'");
 $stmt->execute([$status,$visible,$name,$description,$website?:null,$logo?:null,$assigned,$id]);
 $db->commit();
 if($stmt->rowCount()===0){
  $check=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE id=? AND event_key='bubba-me-2027'");$check->execute([$id]);
  if(!$check->fetchColumn())reply(404,['ok'=>false,'error'=>'not_found']);
 }
 reply(200,['ok'=>true,'id'=>$id,'application_status'=>$status,'public_visible'=>$visible]);
}catch(Throwable $e){error_log('Expo admin exhibitor API failure: '.get_class($e));reply(503,['ok'=>false,'error'=>'temporarily_unavailable']);}
