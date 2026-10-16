<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');session_start();
function organiser_admin_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))organiser_admin_reply(401,['ok'=>false,'error'=>'admin_login_required']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $hasOwner=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
 if(($_SERVER['REQUEST_METHOD']??'')==='GET'){
  $q=trim((string)($_GET['q']??''));if(mb_strlen($q)>100)organiser_admin_reply(422,['ok'=>false,'error'=>'invalid_search']);
  $sql="SELECT o.id,o.organisation_name,o.email,o.status,".($hasOwner?"o.user_id":"NULL AS user_id").",(SELECT COUNT(*) FROM bh_activities a WHERE a.organiser_id=o.id AND a.status<>'archived') AS listing_count FROM bh_organisers o";
  $params=[];if($q!==''){$sql.=" WHERE o.organisation_name LIKE ? OR o.email LIKE ?";$params=['%'.$q.'%','%'.$q.'%'];}
  $sql.=" ORDER BY o.id DESC LIMIT 150";$stmt=$db->prepare($sql);$stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $owner=$db->prepare("SELECT id,email,status FROM bh_users WHERE role='leader' AND (id=? OR LOWER(email)=LOWER(?)) ORDER BY CASE WHEN id=? THEN 0 ELSE 1 END LIMIT 1");
  foreach($items as &$item){$owner->execute([(int)($item['user_id']??0),$item['email']??'',(int)($item['user_id']??0)]);$u=$owner->fetch(PDO::FETCH_ASSOC);$item['leader']=$u?:null;$item['listing_count']=(int)$item['listing_count'];}unset($item);
  organiser_admin_reply(200,['ok'=>true,'organisers'=>$items,'limited'=>count($items)===150]);
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')organiser_admin_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
 if($origin===''||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host||parse_url($origin,PHP_URL_SCHEME)!=='https')organiser_admin_reply(403,['ok'=>false,'error'=>'invalid_origin']);
 $body=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($body)||!isset($_SESSION['bh_approval_csrf'])||!is_string($body['csrf']??null)||!hash_equals($_SESSION['bh_approval_csrf'],$body['csrf']))organiser_admin_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
 $id=filter_var($body['id']??null,FILTER_VALIDATE_INT);$action=(string)($body['action']??'');
 if(!$id||$id<1||!in_array($action,['archive','restore'],true))organiser_admin_reply(422,['ok'=>false,'error'=>'invalid_action']);
 $db->beginTransaction();$stmt=$db->prepare("SELECT id,organisation_name,status FROM bh_organisers WHERE id=? FOR UPDATE");$stmt->execute([$id]);$item=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$item){$db->rollBack();organiser_admin_reply(404,['ok'=>false,'error'=>'organiser_not_found']);}
 $count=$db->prepare("SELECT COUNT(*) FROM bh_activities WHERE organiser_id=? AND status<>'archived'");$count->execute([$id]);$linked=(int)$count->fetchColumn();
 if($action==='archive'&&$linked>0){$db->rollBack();organiser_admin_reply(409,['ok'=>false,'error'=>'linked_listings_prevent_archive','listing_count'=>$linked]);}
 if($action==='archive'&&($body['confirmation']??'')!==$item['organisation_name']){$db->rollBack();organiser_admin_reply(422,['ok'=>false,'error'=>'name_confirmation_required']);}
 $status=$action==='archive'?'archived':'published';
 $db->prepare("UPDATE bh_organisers SET status=? WHERE id=?")->execute([$status,$id]);
 $db->commit();organiser_admin_reply(200,['ok'=>true,'status'=>$status,'organiser_id'=>$id]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Admin organiser management: '.$e->getMessage());organiser_admin_reply(500,['ok'=>false,'error'=>'organiser_management_failed']);}
