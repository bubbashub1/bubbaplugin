<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID'); session_start();
function sector_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))sector_reply(401,['ok'=>false,'message'=>'Please sign in to the admin area.']);
require_once __DIR__.'/db.php'; $db=bh_mysql();
$sectors=[
 'childcare'=>['table'=>'bh_directory_childcare','label'=>'Childcare & Schools','extra'=>['age_min','age_max','registration_body','registration_reference','verification_url','verified_on']],
 'places'=>['table'=>'bh_directory_places','label'=>'Places & Days Out','extra'=>['facilities']],
 'clubs'=>['table'=>'bh_directory_school_clubs','label'=>'School Clubs','extra'=>['school_name','age_min','age_max','days_times','collection_arrangements','holiday_provision','price_details','registration_details','verification_url','verified_on']]
];
$key=(string)($_GET['sector']??''); if($_SERVER['REQUEST_METHOD']==='POST'){ $raw=(string)file_get_contents('php://input');if(strlen($raw)>100000)sector_reply(413,['ok'=>false,'message'=>'Request too large.']);$input=json_decode($raw,true);if(!is_array($input))sector_reply(400,['ok'=>false,'message'=>'Invalid request.']);$key=(string)($input['sector']??'');}
if(!isset($sectors[$key]))sector_reply(422,['ok'=>false,'message'=>'Choose a valid directory section.']);
$table=$sectors[$key]['table'];
$exists=static function()use($db,$table):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);return (int)$q->fetchColumn()>0;};
if(!$exists())sector_reply(503,['ok'=>false,'message'=>'This directory table is not installed yet.']);
$_SESSION['bh_admin_sector_csrf']??=bin2hex(random_bytes(32));
if($_SERVER['REQUEST_METHOD']==='GET'){
 $action=(string)($_GET['action']??'list');
 if($action==='list'){
  $q=trim((string)($_GET['q']??''));$sql="SELECT id,title,category,town,region,status,updated_at FROM `$table`";$args=[];
  if($q!==''){$sql.=" WHERE title LIKE ? OR category LIKE ? OR town LIKE ? OR region LIKE ?";$like='%'.$q.'%';$args=[$like,$like,$like,$like];}
  $sql.=" ORDER BY updated_at DESC,id DESC LIMIT 300";$st=$db->prepare($sql);$st->execute($args);
  sector_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_sector_csrf'],'records'=>$st->fetchAll(PDO::FETCH_ASSOC),'label'=>$sectors[$key]['label']]);
 }
 if($action==='get'){
  $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)sector_reply(422,['ok'=>false,'message'=>'Invalid record ID.']);
  $st=$db->prepare("SELECT * FROM `$table` WHERE id=?");$st->execute([$id]);$record=$st->fetch(PDO::FETCH_ASSOC);if(!$record)sector_reply(404,['ok'=>false,'message'=>'Record not found.']);
  sector_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_sector_csrf'],'record'=>$record]);
 }
 sector_reply(422,['ok'=>false,'message'=>'Unknown action.']);
}
if($_SERVER['REQUEST_METHOD']!=='POST')sector_reply(405,['ok'=>false,'message'=>'Method not allowed.']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)sector_reply(403,['ok'=>false,'message'=>'Invalid request origin.']);
if(empty($_SESSION['bh_admin_sector_csrf'])||!is_string($input['csrf']??null)||!hash_equals($_SESSION['bh_admin_sector_csrf'],$input['csrf']))sector_reply(403,['ok'=>false,'message'=>'Your session expired. Refresh the page and try again.']);
$common=['title'=>190,'category'=>120,'summary'=>500,'description'=>100000,'town'=>120,'region'=>120,'address'=>500,'postcode'=>20,'website_url'=>1000,'contact_email'=>190,'contact_phone'=>60];
$limits=['registration_body'=>190,'registration_reference'=>190,'verification_url'=>1000,'verified_on'=>10,'facilities'=>10000,'school_name'=>190,'age_min'=>3,'age_max'=>3,'days_times'=>500,'collection_arrangements'=>5000,'holiday_provision'=>500,'price_details'=>500,'registration_details'=>10000];
$allowed=array_merge(array_keys($common),$sectors[$key]['extra']);
$id=(int)($input['id']??0);$values=[];
foreach($allowed as $field){
 $max=$common[$field]??$limits[$field]??10000;
 $v=$input[$field]??null;
 if(in_array($field,['age_min','age_max'],true)){if($v===''||$v===null){$values[$field]=null;continue;}$n=filter_var($v,FILTER_VALIDATE_INT);$maxAge=$key==='childcare'?240:18;if($n===false||$n<0||$n>$maxAge)sector_reply(422,['ok'=>false,'message'=>$key==='childcare'?'Childcare ages must be between 0 and 240 months.':'School Club ages must be between 0 and 18 years.']);if($key==='clubs'&&$field==='age_min'&&$n<3)sector_reply(422,['ok'=>false,'message'=>'School Clubs must be for children aged 3 or over.']);$values[$field]=$n;continue;}
 $v=trim((string)($v??''));if(mb_strlen($v)>$max)sector_reply(422,['ok'=>false,'message'=>'The '.$field.' field is too long.']);
 if(in_array($field,['title','category','town'],true)&&$v==='')sector_reply(422,['ok'=>false,'message'=>'Please complete the title, category and town fields.']);
 if(in_array($field,['website_url','verification_url'],true)&&$v!==''&&(!filter_var($v,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($v,PHP_URL_SCHEME)),['http','https'],true)))sector_reply(422,['ok'=>false,'message'=>'Enter a valid website URL including https://.']);
 if($field==='contact_email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))sector_reply(422,['ok'=>false,'message'=>'Enter a valid contact email address.']);
 if($field==='verified_on'&&$v!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))sector_reply(422,['ok'=>false,'message'=>'Enter the verification date as YYYY-MM-DD.']);
 $values[$field]=$v===''?null:$v;
}
if(isset($values['age_min'],$values['age_max'])&&$values['age_min']!==null&&$values['age_max']!==null&&$values['age_min']>$values['age_max'])sector_reply(422,['ok'=>false,'message'=>'Minimum age cannot be greater than maximum age.']);
$status=($input['publish']??false)===true?'published':'draft';
try{
 $db->beginTransaction();
 if($id>0){$st=$db->prepare("SELECT id FROM `$table` WHERE id=? FOR UPDATE");$st->execute([$id]);if(!$st->fetchColumn()){$db->rollBack();sector_reply(404,['ok'=>false,'message'=>'Record not found.']);}
  $sets=array_map(static fn($f)=>"`$f`=?",array_keys($values));$sql="UPDATE `$table` SET ".implode(',',$sets).",status=?,reviewed_at=?,reviewed_by_user_id=? WHERE id=?";
  $db->prepare($sql)->execute([...array_values($values),$status,$status==='published'?date('Y-m-d H:i:s'):null,$status==='published'?((int)($_SESSION['bh_user_id']??0)?:null):null,$id]);
 }else{
  $fields=array_keys($values);$sql="INSERT INTO `$table` (".implode(',',array_map(static fn($f)=>"`$f`",$fields)).",status,reviewed_at,reviewed_by_user_id) VALUES (".implode(',',array_fill(0,count($fields)+3,'?')).")";
  $db->prepare($sql)->execute([...array_values($values),$status,$status==='published'?date('Y-m-d H:i:s'):null,$status==='published'?((int)($_SESSION['bh_user_id']??0)?:null):null]);$id=(int)$db->lastInsertId();
 }
 $db->commit();sector_reply(200,['ok'=>true,'id'=>$id,'status'=>$status,'message'=>$status==='published'?'Entry saved and published.':'Entry saved as a draft.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Family directory admin save: '.$e->getMessage());sector_reply(500,['ok'=>false,'message'=>'The entry could not be saved. No changes were committed.']);}
