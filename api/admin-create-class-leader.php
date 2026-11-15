<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1'); session_name('BUBBAHUB_ADMINSESSID'); session_start();
function admin_leader_create_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))admin_leader_create_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')admin_leader_create_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if(!is_array($origin)||strtolower((string)($origin['host']??''))!==$host||strtolower((string)($origin['scheme']??''))!=='https'||isset($origin['port']))admin_leader_create_reply(403,['ok'=>false,'error'=>'origin_forbidden']);
if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))admin_leader_create_reply(415,['ok'=>false,'error'=>'json_required']);
$input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))admin_leader_create_reply(422,['ok'=>false,'error'=>'invalid_body']);
$first=trim((string)($input['first_name']??''));$last=trim((string)($input['last_name']??''));$email=strtolower(trim((string)($input['email']??'')));$org=trim((string)($input['organisation_name']??''));$phone=trim((string)($input['phone']??''));$town=trim((string)($input['town']??''));
if($first===''||mb_strlen($first)>120||$last===''||mb_strlen($last)>120)admin_leader_create_reply(422,['ok'=>false,'error'=>'Enter the leader first and last name.']);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)admin_leader_create_reply(422,['ok'=>false,'error'=>'Enter a valid email address.']);
if($org===''||mb_strlen($org)>190)admin_leader_create_reply(422,['ok'=>false,'error'=>'Enter a class or organisation name.']);
if(mb_strlen($phone)>80||mb_strlen($town)>120)admin_leader_create_reply(422,['ok'=>false,'error'=>'Phone or location is too long.']);
try{
 require_once __DIR__.'/db.php'; require_once __DIR__.'/leader-welcome-email.php'; $db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_account_activation_tokens (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_activation_user(user_id),INDEX idx_activation_expiry(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 foreach(["ALTER TABLE bh_account_activation_tokens ADD COLUMN organisation_name VARCHAR(190) NULL","ALTER TABLE bh_account_activation_tokens ADD COLUMN first_name VARCHAR(120) NULL","ALTER TABLE bh_account_activation_tokens ADD COLUMN code_hash CHAR(64) NULL","ALTER TABLE bh_account_activation_tokens ADD COLUMN email_sent_at DATETIME NULL"] as $sql){try{$db->exec($sql);}catch(Throwable $ignored){}}
 $q=$db->prepare("SELECT id,role FROM bh_users WHERE LOWER(email)=? LIMIT 1");$q->execute([$email]);
 if($q->fetch(PDO::FETCH_ASSOC))admin_leader_create_reply(409,['ok'=>false,'error'=>'email_already_exists','message'=>'An account already uses this email. Find the existing account instead of creating a duplicate.']);
 $token=bin2hex(random_bytes(32));$code=(string)random_int(100000,999999);$userId=0;$organiserId=0;
 $db->beginTransaction();
 $q=$db->prepare("INSERT INTO bh_users(email,password_hash,role,status) VALUES(?,?, 'leader','active')");$q->execute([$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);$userId=(int)$db->lastInsertId();
 $columnRows=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_ASSOC);$orgCols=[];
 foreach($columnRows as $row){$field=(string)($row['Field']??'');if($field!=='')$orgCols[$field]=$row;}
 $nameField=isset($orgCols['organisation_name'])?'organisation_name':(isset($orgCols['name'])?'name':(isset($orgCols['organisation'])?'organisation':(isset($orgCols['business_name'])?'business_name':'')));
 if($nameField==='')throw new RuntimeException('Organiser table has no supported organisation name field.');
 $slugBase=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$org),'-'));if($slugBase==='')$slugBase='organiser';
 $fields=[$nameField];$values=[$org];
 if(isset($orgCols['slug'])){$slug=$slugBase;$n=2;while(true){$q=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");$q->execute([$slug]);if(!$q->fetchColumn())break;$slug=$slugBase.'-'.$n++;}$fields[]='slug';$values[]=$slug;}
 if(isset($orgCols['user_id'])){$fields[]='user_id';$values[]=$userId;}
 if(isset($orgCols['email'])){$fields[]='email';$values[]=$email;}
 if(isset($orgCols['contact_name'])){$fields[]='contact_name';$values[]=$first.' '.$last;}
 if(isset($orgCols['phone'])){$fields[]='phone';$values[]=$phone!==''?$phone:null;}
 if(isset($orgCols['town'])){$fields[]='town';$values[]=$town!==''?$town:null;}
 if(isset($orgCols['status'])){
  $type=(string)($orgCols['status']['Type']??'');$statusValue='published';
  if(stripos($type,'enum(')!==false&&!str_contains($type,"'published'"))$statusValue=str_contains($type,"'active'")?'active':(str_contains($type,"'approved'")?'approved':(str_contains($type,"'pending'")?'pending':''));
  if($statusValue!==''){$fields[]='status';$values[]=$statusValue;}
 }
 foreach($orgCols as $field=>$meta){
  $required=(($meta['Null']??'YES')==='NO')&&($meta['Default']??null)===null&&stripos((string)($meta['Extra']??''),'auto_increment')===false;
  if(!$required||in_array($field,$fields,true))continue;
  $lower=strtolower($field);$value='';
  if(in_array($lower,['created_by','owner_user_id','leader_user_id','account_user_id'],true))$value=$userId;
  elseif(in_array($lower,['terms_accepted','terms_agreed','organiser_terms_accepted','active','is_active','enabled'],true))$value=1;
  elseif(in_array($lower,['terms_accepted_at','terms_agreed_at'],true))$value=date('Y-m-d H:i:s');
  elseif(str_contains($lower,'email'))$value=$email;
  elseif(str_contains($lower,'phone'))$value=$phone;
  elseif(str_contains($lower,'name')||str_contains($lower,'title')||str_contains($lower,'address')||str_contains($lower,'town')||str_contains($lower,'city')||str_contains($lower,'region')||str_contains($lower,'county')||str_contains($lower,'description')||str_contains($lower,'about')||str_contains($lower,'notes'))$value='';
  else throw new RuntimeException('Organiser table has an unsupported required field: '.$field);
  $fields[]=$field;$values[]=$value;
 }
 $quoted=implode(',',array_map(static fn($field)=>'`'.$field.'`', $fields));$marks=implode(',',array_fill(0,count($fields),'?'));
 $q=$db->prepare("INSERT INTO bh_organisers($quoted) VALUES($marks)");$q->execute($values);$organiserId=(int)$db->lastInsertId();
  $q=$db->prepare("INSERT INTO bh_account_activation_tokens(user_id,token_hash,code_hash,expires_at,organisation_name,first_name) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY),?,?)");
  $q->execute([$userId,hash('sha256',$token),hash('sha256',$code),$org,$first]);$db->commit();
 }
 $sent=bh_send_leader_welcome($email,$first,$org,'',$token,$code);
 if($sent)$db->prepare("UPDATE bh_account_activation_tokens SET email_sent_at=NOW() WHERE token_hash=?")->execute([hash('sha256',$token)]);
 admin_leader_create_reply(201,['ok'=>true,'user_id'=>$userId,'organiser_id'=>$organiserId,'status'=>'active','organiser_status'=>'published','invitation_sent'=>$sent,'message'=>$sent?'Leader account created, approved and active. Invitation email sent.':'Account created, approved and active, but the invitation email could not be sent. Check email configuration and arrange a resend.']);
}catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('Admin create class leader: '.$e->getMessage());admin_leader_create_reply(500,['ok'=>false,'error'=>'leader_account_create_failed','message'=>'The class leader account could not be created.']);}
