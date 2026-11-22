<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID'); session_start();
function business_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))business_reply(401,['ok'=>false,'error'=>'admin_login_required']);
require_once __DIR__.'/db.php'; $db=bh_mysql();
$exists=static function(string $table)use($db):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);return (int)$q->fetchColumn()>0;};
if(!$exists('bh_directory_businesses')||!$exists('bh_directory_business_listings'))business_reply(503,['ok'=>false,'error'=>'business_schema_not_installed','message'=>'The Businesses directory schema has not been installed on staging. No records have been changed.']);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_admin_business_csrf']??=bin2hex(random_bytes(32));$action=(string)($_GET['action']??'list');
 if($action==='list'){
  $q=trim((string)($_GET['q']??''));$sql="SELECT b.id,b.title,b.category,b.town,b.region,b.status,b.updated_at,o.organisation_name FROM bh_directory_businesses b LEFT JOIN bh_organisers o ON o.id=b.organiser_id";$args=[];
  if($q!==''){$sql.=" WHERE b.title LIKE ? OR b.category LIKE ? OR b.town LIKE ? OR o.organisation_name LIKE ?";$like='%'.$q.'%';$args=[$like,$like,$like,$like];}
  $sql.=" ORDER BY b.updated_at DESC,b.id DESC LIMIT 300";$s=$db->prepare($sql);$s->execute($args);business_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_business_csrf'],'businesses'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($action==='get'){
  $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)business_reply(422,['ok'=>false,'error'=>'invalid_id']);
  $s=$db->prepare("SELECT b.*,o.organisation_name FROM bh_directory_businesses b LEFT JOIN bh_organisers o ON o.id=b.organiser_id WHERE b.id=?");$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)business_reply(404,['ok'=>false,'error'=>'business_not_found']);
  $s=$db->prepare("SELECT activity_id FROM bh_directory_business_listings WHERE business_id=? ORDER BY activity_id");$s->execute([$id]);$row['activity_ids']=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));business_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_business_csrf'],'business'=>$row]);
 }
 business_reply(422,['ok'=>false,'error'=>'invalid_action']);
}
if($_SERVER['REQUEST_METHOD']!=='POST')business_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)business_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$raw=(string)file_get_contents('php://input');if(strlen($raw)>100000)business_reply(413,['ok'=>false,'error'=>'request_too_large']);
$b=json_decode($raw,true);if(!is_array($b)||empty($_SESSION['bh_admin_business_csrf'])||!is_string($b['csrf']??null)||!hash_equals($_SESSION['bh_admin_business_csrf'],$b['csrf']))business_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$title=trim((string)($b['title']??''));$category=trim((string)($b['category']??''));$town=trim((string)($b['town']??''));$description=trim((string)($b['description']??''));$summary=trim((string)($b['summary']??''));
if($title===''||mb_strlen($title)>190||$category===''||mb_strlen($category)>120||$town===''||mb_strlen($town)>120||mb_strlen($summary)>500||mb_strlen($description)>100000)business_reply(422,['ok'=>false,'error'=>'required_fields_invalid','message'=>'Enter a business name, category and town. Check field lengths.']);
$fields=['region'=>120,'address'=>500,'postcode'=>20,'website_url'=>1000,'contact_email'=>190,'contact_phone'=>60];$values=[];
foreach($fields as $key=>$max){$v=trim((string)($b[$key]??''));if(mb_strlen($v)>$max)business_reply(422,['ok'=>false,'error'=>'field_too_long','message'=>'The '.$key.' field is too long.']);$values[$key]=$v?:null;}
if($values['website_url']!==null&&(!filter_var($values['website_url'],FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($values['website_url'],PHP_URL_SCHEME)),['http','https'],true)))business_reply(422,['ok'=>false,'error'=>'invalid_website']);
if($values['contact_email']!==null&&!filter_var($values['contact_email'],FILTER_VALIDATE_EMAIL))business_reply(422,['ok'=>false,'error'=>'invalid_email']);
$organiserId=isset($b['organiser_id'])&&$b['organiser_id']!==''?(int)$b['organiser_id']:null;
if($organiserId!==null){$s=$db->prepare("SELECT id FROM bh_organisers WHERE id=?");$s->execute([$organiserId]);if(!$s->fetchColumn())business_reply(422,['ok'=>false,'error'=>'organiser_not_found']);}
$activityIds=$b['activity_ids']??[];if(!is_array($activityIds)||count($activityIds)>100)business_reply(422,['ok'=>false,'error'=>'invalid_activity_links']);
$activityIds=array_values(array_unique(array_filter(array_map('intval',$activityIds),static fn($v)=>$v>0)));
if($activityIds){$marks=implode(',',array_fill(0,count($activityIds),'?'));$s=$db->prepare("SELECT id FROM bh_activities WHERE id IN ($marks)");$s->execute($activityIds);if(count($s->fetchAll(PDO::FETCH_COLUMN))!==count($activityIds))business_reply(422,['ok'=>false,'error'=>'activity_link_not_found']);}
$id=isset($b['id'])?(int)$b['id']:0;$publish=($b['publish']??false)===true;
try{
 $db->beginTransaction();
 if($id>0){$s=$db->prepare("SELECT id FROM bh_directory_businesses WHERE id=? FOR UPDATE");$s->execute([$id]);if(!$s->fetchColumn()){$db->rollBack();business_reply(404,['ok'=>false,'error'=>'business_not_found']);}
  $sql="UPDATE bh_directory_businesses SET organiser_id=?,title=?,category=?,summary=?,description=?,town=?,region=?,address=?,postcode=?,website_url=?,contact_email=?,contact_phone=?,status=?,reviewed_at=?,reviewed_by_user_id=? WHERE id=?";
  $db->prepare($sql)->execute([$organiserId,$title,$category,$summary?:null,$description?:null,$town,$values['region'],$values['address'],$values['postcode'],$values['website_url'],$values['contact_email'],$values['contact_phone'],$publish?'published':'draft',$publish?date('Y-m-d H:i:s'):null,$publish?((int)($_SESSION['bh_user_id']??0)?:null):null,$id]);
 }else{
  $sql="INSERT INTO bh_directory_businesses (organiser_id,created_by_user_id,title,category,summary,description,town,region,address,postcode,website_url,contact_email,contact_phone,status,reviewed_at,reviewed_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $db->prepare($sql)->execute([$organiserId,(int)($_SESSION['bh_user_id']??0)?:null,$title,$category,$summary?:null,$description?:null,$town,$values['region'],$values['address'],$values['postcode'],$values['website_url'],$values['contact_email'],$values['contact_phone'],$publish?'published':'draft',$publish?date('Y-m-d H:i:s'):null,$publish?((int)($_SESSION['bh_user_id']??0)?:null):null]);$id=(int)$db->lastInsertId();
 }
 $db->prepare("DELETE FROM bh_directory_business_listings WHERE business_id=?")->execute([$id]);
 if($activityIds){$s=$db->prepare("INSERT INTO bh_directory_business_listings (business_id,activity_id) VALUES (?,?)");foreach($activityIds as $activityId)$s->execute([$id,$activityId]);}
 $db->commit();business_reply(200,['ok'=>true,'id'=>$id,'status'=>$publish?'published':'draft','message'=>$publish?'Business saved and published.':'Business saved as a draft.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Admin business manager: '.$e->getMessage());business_reply(500,['ok'=>false,'error'=>'business_save_failed']);}
