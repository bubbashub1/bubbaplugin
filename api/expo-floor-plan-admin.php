<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function answer(int $code,array $body):never{http_response_code($code);echo json_encode($body);exit;}
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_name('BUBBAHUB_ADMINSESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
}
if(empty($_SESSION['bh_admin_authenticated']))answer(401,['ok'=>false,'error'=>'admin_required']);
if(!empty($_SESSION['bh_admin_last_activity'])&&time()-(int)$_SESSION['bh_admin_last_activity']>1800){
 unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_last_activity']);answer(401,['ok'=>false,'error'=>'session_expired']);
}
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))answer(405,['ok'=>false,'error'=>'method_not_allowed']);
if($method==='POST'){
 $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 $source=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($source==='')$source=(string)($_SERVER['HTTP_REFERER']??'');
 if(!$secure||parse_url($source,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($source,PHP_URL_HOST))!==$host)answer(403,['ok'=>false,'error'=>'invalid_origin']);
 $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(empty($_SESSION['bh_floor_plan_csrf'])||!hash_equals($_SESSION['bh_floor_plan_csrf'],$token))answer(403,['ok'=>false,'error'=>'invalid_csrf']);
}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 if($method==='GET'){
  if(empty($_SESSION['bh_floor_plan_csrf']))$_SESSION['bh_floor_plan_csrf']=bin2hex(random_bytes(32));
  $stmt=$db->prepare('SELECT layout_json,revision FROM bh_expo_floor_plans WHERE event_key=?');
  $stmt->execute(['bubba-me-2027']);$row=$stmt->fetch(PDO::FETCH_ASSOC);
  $pub=$db->prepare('SELECT published FROM bh_expo_floor_plan_publication WHERE event_key=?');$pub->execute(['bubba-me-2027']);
  answer(200,['ok'=>true,'items'=>$row?json_decode($row['layout_json'],true):null,'revision'=>$row?(int)$row['revision']:0,'published'=>(bool)$pub->fetchColumn(),'csrf_token'=>$_SESSION['bh_floor_plan_csrf']]);
 }
 if(isset($_GET['publication'])){
  if((int)($_SERVER['CONTENT_LENGTH']??0)>1000)answer(413,['ok'=>false,'error'=>'too_large']);
  $body=json_decode((string)file_get_contents('php://input'),true);
  if(!is_array($body)||!is_bool($body['published']??null))answer(422,['ok'=>false,'error'=>'invalid_publication']);
  if($body['published']){
   if(($body['confirmation']??null)!=='PUBLISH')answer(422,['ok'=>false,'error'=>'publication_confirmation_required']);
   $requestedRevision=filter_var($body['revision']??null,FILTER_VALIDATE_INT);
   $check=$db->prepare('SELECT revision FROM bh_expo_floor_plans WHERE event_key=?');$check->execute(['bubba-me-2027']);
   $currentRevision=$check->fetchColumn();
   if($currentRevision===false)answer(409,['ok'=>false,'error'=>'save_layout_first']);
   if($requestedRevision===false||$requestedRevision!==(int)$currentRevision)answer(409,['ok'=>false,'error'=>'layout_changed_reload']);
  }
  $up=$db->prepare('INSERT INTO bh_expo_floor_plan_publication(event_key,published) VALUES (?,?) ON DUPLICATE KEY UPDATE published=VALUES(published)');
  $up->execute(['bubba-me-2027',(int)$body['published']]);
  answer(200,['ok'=>true,'published'=>$body['published']]);
 }
 if((int)($_SERVER['CONTENT_LENGTH']??0)>50000)answer(413,['ok'=>false,'error'=>'too_large']);
 $data=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($data)||!isset($data['items'])||!is_array($data['items'])||count($data['items'])>150)answer(422,['ok'=>false,'error'=>'invalid_items']);
 $seen=[];
 foreach($data['items'] as $item){
  if(!is_array($item)||!isset($item['id'],$item['type'],$item['x'],$item['y'],$item['angle'],$item['label']))answer(422,['ok'=>false,'error'=>'invalid_item']);
  $id=filter_var($item['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id||isset($seen[$id])||!in_array($item['type'],['table','desk'],true)||!in_array($item['angle'],[0,90],true)||!is_numeric($item['x'])||!is_numeric($item['y'])||!is_string($item['label'])||mb_strlen($item['label'])>35||!is_string($item['exhibitor']??'')||mb_strlen($item['exhibitor']??'')>100)answer(422,['ok'=>false,'error'=>'invalid_item']);
  if((float)$item['x']<0||(float)$item['x']>1200||(float)$item['y']<0||(float)$item['y']>780)answer(422,['ok'=>false,'error'=>'out_of_bounds']);
  $seen[$id]=true;
 }
 $revision=filter_var($data['revision']??null,FILTER_VALIDATE_INT);
 if($revision===false||$revision<0)answer(422,['ok'=>false,'error'=>'invalid_revision']);
 // Serialize layout updates against table reservations and preserve table identity.
 $db->beginTransaction();
 $oldQuery=$db->prepare('SELECT layout_json,revision FROM bh_expo_floor_plans WHERE event_key=? FOR UPDATE');
 $oldQuery->execute(['bubba-me-2027']);$oldRow=$oldQuery->fetch(PDO::FETCH_ASSOC);
 if($revision!==($oldRow?(int)$oldRow['revision']:0)){$db->rollBack();answer(409,['ok'=>false,'error'=>'layout_changed_reload']);}
 $assigned=$db->prepare("SELECT DISTINCT preferred_table FROM bh_expo_exhibitors WHERE event_key=? AND preferred_table IS NOT NULL AND application_status IN ('pending','approved','confirmed')");
 $assigned->execute(['bubba-me-2027']);$ids=array_map('intval',$assigned->fetchAll(PDO::FETCH_COLUMN));
 if($ids){
  $previous=[];foreach(json_decode((string)($oldRow['layout_json']??'[]'),true)?:[] as $item)if(($item['type']??'')==='table')$previous[(int)$item['id']]=$item;
  $incoming=[];foreach($data['items'] as $item)if($item['type']==='table')$incoming[(int)$item['id']]=$item;
  foreach($ids as $id){
   if(!isset($previous[$id],$incoming[$id])||(string)$previous[$id]['label']!==(string)$incoming[$id]['label']){
    $db->rollBack();answer(409,['ok'=>false,'error'=>'reserved_table_identity_locked','table_id'=>$id]);
   }
  }
 }
 $json=json_encode($data['items'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 if($json===false){$db->rollBack();answer(422,['ok'=>false,'error'=>'invalid_items']);}
 if($revision===0){
  try{$stmt=$db->prepare('INSERT INTO bh_expo_floor_plans(event_key,layout_json,revision) VALUES(?,?,1)');$stmt->execute(['bubba-me-2027',$json]);$db->commit();answer(200,['ok'=>true,'revision'=>1]);}
  catch(PDOException $e){if($db->inTransaction())$db->rollBack();if((string)$e->getCode()==='23000')answer(409,['ok'=>false,'error'=>'layout_changed_reload']);throw $e;}
 }
 $stmt=$db->prepare('UPDATE bh_expo_floor_plans SET layout_json=?,revision=revision+1 WHERE event_key=? AND revision=?');
 $stmt->execute([$json,'bubba-me-2027',$revision]);
 if($stmt->rowCount()!==1){$db->rollBack();answer(409,['ok'=>false,'error'=>'layout_changed_reload']);}
 $db->commit();
 answer(200,['ok'=>true,'revision'=>$revision+1]);
}catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('expo_floor_plan: '.$e->getMessage());answer(503,['ok'=>false,'error'=>'floor_plan_storage_unavailable']);}
