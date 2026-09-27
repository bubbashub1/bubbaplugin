<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bh_profile_response(int $status,array $data): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
try{
 require __DIR__.'/db.php';
 $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
 if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
 if(empty($_SESSION['bh_user_id']))bh_profile_response(401,['ok'=>false,'message'=>'Please sign in to manage your profile.']);
 $db=bh_mysql();
 foreach(['ALTER TABLE bh_users ADD COLUMN first_name VARCHAR(80) NULL','ALTER TABLE bh_users ADD COLUMN last_name VARCHAR(80) NULL','ALTER TABLE bh_users ADD COLUMN phone VARCHAR(40) NULL','ALTER TABLE bh_users ADD COLUMN date_of_birth DATE NULL'] as $sql){try{$db->exec($sql);}catch(Throwable $ignored){}}
 if(!isset($_SESSION['bh_csrf']))$_SESSION['bh_csrf']=bin2hex(random_bytes(24));
 try{$db->exec("CREATE TABLE IF NOT EXISTS bh_user_addresses (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,address_type VARCHAR(30) NOT NULL DEFAULT 'home',address_line1 VARCHAR(160) NULL,address_line2 VARCHAR(160) NULL,city VARCHAR(100) NULL,county VARCHAR(100) NULL,postcode VARCHAR(20) NULL,country VARCHAR(80) NOT NULL DEFAULT 'United Kingdom',latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_user_address_type(user_id,address_type),KEY idx_user_id(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(Throwable $ignored){}
 $id=(int)$_SESSION['bh_user_id'];
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $addr=$db->prepare("SELECT address_line1,address_line2,city,county,postcode,country,latitude,longitude FROM bh_user_addresses WHERE user_id=? AND address_type='home' LIMIT 1");$addr->execute([$id]);$a=$addr->fetch() ?: [];
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
 $stmt=$db->prepare('UPDATE bh_users SET first_name=?,last_name=?,phone=?,date_of_birth=? WHERE id=?');$stmt->execute([$first?:null,$last?:null,$phone?:null,$dob?:null,$id]);
 $stmt=$db->prepare("INSERT INTO bh_user_addresses (user_id,address_type,address_line1,address_line2,city,county,postcode,country,latitude,longitude) VALUES (?,'home',?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE address_line1=VALUES(address_line1),address_line2=VALUES(address_line2),city=VALUES(city),county=VALUES(county),postcode=VALUES(postcode),country=VALUES(country),latitude=VALUES(latitude),longitude=VALUES(longitude)");
 $stmt->execute([$id,$addressLine1?:null,$addressLine2?:null,$city?:null,$county?:null,$postcode?:null,$country?:'United Kingdom',$latitude!==''?$latitude:null,$longitude!==''?$longitude:null]);
 bh_profile_response(200,['ok'=>true,'message'=>'Your profile has been saved.']);
}catch(Throwable $e){bh_profile_response(500,['ok'=>false,'message'=>'Unable to save your profile right now.']);}
?>