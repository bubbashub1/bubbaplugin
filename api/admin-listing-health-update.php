<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');session_start();
function bh_health_update_reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))bh_health_update_reply(401,['ok'=>false,'error'=>'Admin login required.']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$_SESSION['bh_health_update_csrf']??=bin2hex(random_bytes(32));
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET')bh_health_update_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_health_update_csrf']]);
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')bh_health_update_reply(405,['ok'=>false,'error'=>'GET or POST required.']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input))bh_health_update_reply(400,['ok'=>false,'error'=>'Invalid JSON.']);
 if(!hash_equals($_SESSION['bh_health_update_csrf'],(string)($input['csrf']??'')))bh_health_update_reply(403,['ok'=>false,'error'=>'Refresh the page and try again.']);
 $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $field=(string)($input['field']??'');$value=$input['value']??null;
 if(!$id||!is_string($value))bh_health_update_reply(422,['ok'=>false,'error'=>'A listing ID and text value are required.']);
 $activityMap=['title'=>190,'description'=>10000,'short_description'=>2000,'category'=>255,'age_range'=>120,'website'=>2048,'booking_url'=>2048,'contact_email'=>190,'contact_phone'=>60,'facebook_url'=>2048,'instagram_url'=>2048,'image_path'=>2048,'town'=>120,'region'=>120,'postcode'=>20];
 $venueMap=['venue_name'=>190,'address'=>1000,'town'=>120,'region'=>120,'postcode'=>20];
 $value=trim($value);$db->beginTransaction();
 $lock=$db->prepare('SELECT id FROM bh_activities WHERE id=? FOR UPDATE');$lock->execute([$id]);if(!$lock->fetchColumn()){$db->rollBack();bh_health_update_reply(404,['ok'=>false,'error'=>'Listing not found.']);}
 $activityCols=$db->query('SHOW COLUMNS FROM bh_activities')->fetchAll(PDO::FETCH_COLUMN);
 $venueCols=$db->query('SHOW COLUMNS FROM bh_venues')->fetchAll(PDO::FETCH_COLUMN);
 if(isset($activityMap[$field])&&in_array($field,$activityCols,true)){
  if(mb_strlen($value)>$activityMap[$field])throw new InvalidArgumentException('That value is too long.');
  if(in_array($field,['website','booking_url','facebook_url','instagram_url'],true)&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true)))throw new InvalidArgumentException('Enter a valid HTTP or HTTPS web address.');
  if($field==='contact_email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
  $db->prepare('UPDATE bh_activities SET `'.$field.'`=? WHERE id=?')->execute([$value,$id]);
 }elseif(in_array($field,['website','contact_email','contact_phone'],true)){$organiserCols=$db->query('SHOW COLUMNS FROM bh_organisers')->fetchAll(PDO::FETCH_COLUMN);$organiserField=$field==='website'?'website':$field;if(in_array($organiserField,$organiserCols,true)){$q=$db->prepare('SELECT organiser_id FROM bh_activities WHERE id=?');$q->execute([$id]);$organiserId=(int)$q->fetchColumn();if($organiserId<1)bh_health_update_reply(409,['ok'=>false,'error'=>'This listing has no organiser profile. Open the full listing editor instead.']);$db->prepare('UPDATE bh_organisers SET `'.$organiserField.'`=? WHERE id=?')->execute([$value,$organiserId]);}else bh_health_update_reply(422,['ok'=>false,'error'=>'This field is not available in the current organiser schema.']);
 }elseif(isset($venueMap[$field])&&in_array($field,$venueCols,true)){
  if(mb_strlen($value)>$venueMap[$field])throw new InvalidArgumentException('That value is too long.');
  $q=$db->prepare('SELECT id FROM bh_venues WHERE activity_id=? ORDER BY id LIMIT 1 FOR UPDATE');$q->execute([$id]);$venueId=(int)$q->fetchColumn();
  if($venueId){$db->prepare('UPDATE bh_venues SET `'.$field.'`=? WHERE id=? AND activity_id=?')->execute([$value,$venueId,$id]);}
  else bh_health_update_reply(409,['ok'=>false,'error'=>'This listing has no venue record. Open the listing editor to add its first venue safely.']);
 }else{
  $db->rollBack();bh_health_update_reply(422,['ok'=>false,'error'=>'This field cannot be edited directly from Listing Health. Open the full listing editor instead.']);
 }
 $db->commit();bh_health_update_reply(200,['ok'=>true,'id'=>$id,'field'=>$field,'message'=>'Listing updated. Refreshing health results will recalculate its missing information.']);
}catch(InvalidArgumentException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();bh_health_update_reply(422,['ok'=>false,'error'=>$e->getMessage()]);}
catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Listing health update: '.$e->getMessage());bh_health_update_reply(500,['ok'=>false,'error'=>'The listing could not be updated. No partial changes were saved.']);}
