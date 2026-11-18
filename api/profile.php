<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bh_profile_response(int $status,array $data): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
try{
 require __DIR__.'/db.php';
 $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
 if(session_status()!==PHP_SESSION_ACTIVE){
  ini_set('session.use_strict_mode','1');
  ini_set('session.use_only_cookies','1');
  session_name('BUBBAHUBSESSID');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
  session_start();
 }
 if(empty($_SESSION['bh_user_id']))bh_profile_response(401,['ok'=>false,'message'=>'Please sign in to manage your profile.']);
 $db=bh_mysql();
 $profileColumns=$db->query("SHOW COLUMNS FROM bh_users")->fetchAll(PDO::FETCH_COLUMN);
 foreach(['first_name','last_name','phone','date_of_birth'] as $requiredProfileColumn){
  if(!in_array($requiredProfileColumn,$profileColumns,true)){
   bh_profile_response(503,['ok'=>false,'message'=>'Profile fields are being prepared. Please contact support if this continues.']);
  }
 }
 if(!isset($_SESSION['bh_csrf']))$_SESSION['bh_csrf']=bin2hex(random_bytes(24));
 // Address storage is installed by migration 012, never created or altered in a request.
 $addressTable=$db->query("SHOW TABLES LIKE 'bh_user_addresses'")->fetchColumn();
 if(!$addressTable){
  bh_profile_response(503,['ok'=>false,'message'=>'Profile address storage is not installed yet. Please contact support.']);
 }
 $addressColumns=$db->query("SHOW COLUMNS FROM bh_user_addresses")->fetchAll(PDO::FETCH_COLUMN);
 foreach(['user_id','address_type','address_line1','address_line2','city','county','postcode','country','latitude','longitude'] as $requiredAddressColumn){
  if(!in_array($requiredAddressColumn,$addressColumns,true)){
   bh_profile_response(503,['ok'=>false,'message'=>'Profile address storage needs a database update. Please contact support.']);
  }
 }
 $id=(int)$_SESSION['bh_user_id'];
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $addr=$db->prepare("SELECT address_line1,address_line2,city,county,postcode,country,latitude,longitude FROM bh_user_addresses WHERE user_id=? AND address_type='home' ORDER BY id ASC LIMIT 1");$addr->execute([$id]);$a=$addr->fetch() ?: [];
  $stmt=$db->prepare('SELECT id,email,role,status,first_name,last_name,phone,date_of_birth FROM bh_users WHERE id=? LIMIT 1');$stmt->execute([$id]);$u=$stmt->fetch();
  if(!$u||$u['status']!=='active')bh_profile_response(401,['ok'=>false,'message'=>'Your account could not be found.']);
  bh_profile_response(200,['ok'=>true,'user'=>['id'=>(int)$u['id'],'email'=>$u['email'],'role'=>$u['role'],'status'=>$u['status'],'first_name'=>$u['first_name']??'','last_name'=>$u['last_name']??'','phone'=>$u['phone']??'','date_of_birth'=>$u['date_of_birth']??'','address'=>['address_line1'=>$a['address_line1']??'','address_line2'=>$a['address_line2']??'','city'=>$a['city']??'','county'=>$a['county']??'','postcode'=>$a['postcode']??'','country'=>$a['country']??'United Kingdom','latitude'=>$a['latitude']??null,'longitude'=>$a['longitude']??null]],'csrf'=>$_SESSION['bh_csrf']]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')bh_profile_response(405,['ok'=>false,'message'=>'Method not allowed.']);
 $body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))bh_profile_response(400,['ok'=>false,'message'=>'Invalid request.']);
 $csrf=(string)($body['csrf']??'');if(!$csrf||!hash_equals((string)$_SESSION['bh_csrf'],$csrf))bh_profile_response(403,['ok'=>false,'message'=>'Security check failed. Please refresh and try again.']);
 $address=is_array($body['address']??null)?$body['address']:[];
 $addressLine1=trim((string)($address['address_line1']??''));$addressLine2=trim((string)($address['address_line2']??''));$city=trim((string)($address['city']??''));$county=trim((string)($address['county']??''));$postcode=trim((string)($address['postcode']??''));$country=trim((string)($address['country']??'United Kingdom'));
 $latitude=$address['latitude']??null;$longitude=$address['longitude']??null;
 if($latitude!==null&&$latitude!==''&&!is_numeric($latitude))$latitude=null;if($longitude!==null&&$longitude!==''&&!is_numeric($longitude))$longitude=null;
 $first=trim((string)($body['first_name']??''));$last=trim((string)($body['last_name']??''));$phone=trim((string)($body['phone']??''));$dob=trim((string)($body['date_of_birth']??''));
 if(strlen($first)>80||strlen($last)>80||strlen($phone)>40||strlen($addressLine1)>160||strlen($addressLine2)>160||strlen($city)>100||strlen($county)>100||strlen($postcode)>20||strlen($country)>80||($dob!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob)))bh_profile_response(422,['ok'=>false,'message'=>'One of the details is too long.']);
 // Lock the owning account row so simultaneous profile saves for this user
 // cannot create duplicate home-address rows, even on legacy tables where the
 // unique address index could not be added because older duplicates exist.
 $db->beginTransaction();
 try {
  $ownerLock=$db->prepare('SELECT id FROM bh_users WHERE id=? FOR UPDATE');
  $ownerLock->execute([$id]);
  if(!$ownerLock->fetch()) {
   $db->rollBack();
   bh_profile_response(401,['ok'=>false,'message'=>'Your account could not be found.']);
  }
  $stmt=$db->prepare('UPDATE bh_users SET first_name=?,last_name=?,phone=?,date_of_birth=? WHERE id=?');
  $stmt->execute([$first?:null,$last?:null,$phone?:null,$dob?:null,$id]);
  $addressFind=$db->prepare("SELECT id FROM bh_user_addresses WHERE user_id=? AND address_type='home' ORDER BY id ASC LIMIT 1 FOR UPDATE");
  $addressFind->execute([$id]);
  $addressId=$addressFind->fetchColumn();
  if($addressId){
   $stmt=$db->prepare('UPDATE bh_user_addresses SET address_line1=?,address_line2=?,city=?,county=?,postcode=?,country=?,latitude=?,longitude=? WHERE id=? AND user_id=?');
   $stmt->execute([$addressLine1?:null,$addressLine2?:null,$city?:null,$county?:null,$postcode?:null,$country?:'United Kingdom',$latitude!==''?$latitude:null,$longitude!==''?$longitude:null,(int)$addressId,$id]);
  }else{
   $stmt=$db->prepare("INSERT INTO bh_user_addresses (user_id,address_type,address_line1,address_line2,city,county,postcode,country,latitude,longitude) VALUES (?,'home',?,?,?,?,?,?,?,?)");
   $stmt->execute([$id,$addressLine1?:null,$addressLine2?:null,$city?:null,$county?:null,$postcode?:null,$country?:'United Kingdom',$latitude!==''?$latitude:null,$longitude!==''?$longitude:null]);
  }
  $db->commit();
 } catch(Throwable $saveError) {
  if($db->inTransaction())$db->rollBack();
  throw $saveError;
 }
 bh_profile_response(200,['ok'=>true,'message'=>'Your profile has been saved.']);
}catch(Throwable $e){bh_profile_response(500,['ok'=>false,'message'=>'Unable to save your profile right now.']);}
?>