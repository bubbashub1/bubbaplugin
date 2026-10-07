<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';

function bh_leader_signup_response(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
function bh_leader_columns(PDO $db,string $table):array{
 $q=$db->prepare("SELECT COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
 $q->execute([$table]);
 $out=[];
 foreach($q->fetchAll() as $row) $out[(string)$row['COLUMN_NAME']=$row;
 return $out;
}
function bh_leader_col(array $cols,string $name):bool{return isset($cols[$name]);}
function bh_leader_required(array $cols):array{
 $out=[];
 foreach($cols as $name=>$meta){
   if(($meta['IS_NULLABLE']??'YES')==='NO' && $meta['COLUMN_DEFAULT']===null && stripos((string)($meta['EXTRA']??''),'auto_increment')===false) $out[]=$name;
 }
 return $out;
}
try{
 $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
 if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
 if($_SERVER['REQUEST_METHOD']!=='POST')bh_leader_signup_response(405,['ok'=>false,'error'=>'method_not_allowed']);
 $body=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($body))bh_leader_signup_response(400,['ok'=>false,'error'=>'invalid_json','message'=>'The signup request could not be read.']);
 $csrf=trim((string)($body['csrf']??''));
 if($csrf===''||empty($_SESSION['bh_csrf'])||!hash_equals((string)$_SESSION['bh_csrf'],$csrf))bh_leader_signup_response(403,['ok'=>false,'error'=>'csrf_invalid','message'=>'Please refresh the page and try again.']);

 $organisation=trim((string)($body['organisation_name']??'')); $email=strtolower(trim((string)($body['email']??'')));
 $phone=trim((string)($body['phone']??'')); $website=trim((string)($body['website']??''));
 $password=(string)($body['password']??''); $confirm=(string)($body['confirm_password']??''); $terms=!empty($body['terms']);

 if($organisation===''||mb_strlen($organisation)>190)bh_leader_signup_response(422,['ok'=>false,'error'=>'organisation_required','message'=>'Please enter the name of your class, business or organisation.']);
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)bh_leader_signup_response(422,['ok'=>false,'error'=>'invalid_email','message'=>'Please enter a valid email address.']);
 if($phone!==''&&mb_strlen($phone)>80)bh_leader_signup_response(422,['ok'=>false,'error'=>'invalid_phone','message'=>'Please check your phone number.']);
 if($website!==''&&(!filter_var($website,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$website)))bh_leader_signup_response(422,['ok'=>false,'error'=>'invalid_website','message'=>'Please enter a full website address starting with http:// or https://.']);
 if(strlen($password)<8)bh_leader_signup_response(422,['ok'=>false,'error'=>'password_too_short','message'=>'Please choose a password with at least 8 characters.']);
 if($password!==$confirm)bh_leader_signup_response(422,['ok'=>false,'error'=>'password_mismatch','message'=>'The passwords do not match.']);
 if(!$terms)bh_leader_signup_response(422,['ok'=>false,'error'=>'terms_required','message'=>'Please confirm that you agree to the Bubba Hub organiser terms.']);

 $db=bh_mysql(); $userCols=bh_leader_columns($db,'bh_users'); $orgCols=bh_leader_columns($db,'bh_organisers');
 foreach(['email','password_hash','role'] as $c)if(!bh_leader_col($userCols,$c))throw new RuntimeException('The live bh_users table is missing '.$c.'.');
 foreach(['organisation_name','slug','status'] as $c)if(!bh_leader_col($orgCols,$c))throw new RuntimeException('The live bh_organisers table is missing '.$c.'.');

 $q=$db->prepare("SELECT id,role,status FROM bh_users WHERE email=? LIMIT 1");$q->execute([$email]);$user=$q->fetch();
 if($user)bh_leader_signup_response(409,['ok'=>false,'error'=>'email_exists','message'=>(($user['role']??'')==='leader')?'A leader account already exists for this email. Please sign in instead.':'This email is already linked to a Bubba Hub account. Leader accounts are separate from family accounts, so please use a different email address.']);

 $hasOrgUserId=bh_leader_col($orgCols,'user_id'); $org=false;
 if(bh_leader_col($orgCols,'email')){$q=$db->prepare("SELECT * FROM bh_organisers WHERE LOWER(email)=? LIMIT 1");$q->execute([$email]);$org=$q->fetch();}
 if($org){
   if($hasOrgUserId&&!empty($org['user_id']))bh_leader_signup_response(409,['ok'=>false,'error'=>'organiser_claimed','message'=>'An organiser profile is already linked to this email. Please sign in or contact Bubba Hub support.']);
   if(($org['status']??'')==='suspended')bh_leader_signup_response(403,['ok'=>false,'error'=>'organiser_suspended','message'=>'This organiser profile is currently suspended. Please contact Bubba Hub support.']);
 }

 $slugBase=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$organisation),'-'));$slugBase=$slugBase!==''?$slugBase:'leader';$slug=$slugBase;$n=2;
 while(!$org){$q=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");$q->execute([$slug]);if(!$q->fetch())break;$slug=$slugBase.'-'.$n++;}

 $db->beginTransaction();
 try{
   $passwordHash=password_hash($password,PASSWORD_DEFAULT);
   $fields=['email','password_hash','role'];$values=[$email,$passwordHash,'leader'];
   if(bh_leader_col($userCols,'status')){$fields[]='status';$values[]='active';}
   $q=$db->prepare("INSERT INTO bh_users (".implode(',',$fields).") VALUES (".implode(',',array_fill(0,count($fields),'?')).")");$q->execute($values);$userId=(int)$db->lastInsertId();

   if($org){
     $sets=[];$values=[];
     if($hasOrgUserId){$sets[]='user_id=?';$values[]=$userId;}
     if(bh_leader_col($orgCols,'phone')&&$phone!==''){$sets[]='phone=?';$values[]=$phone;}
     if(bh_leader_col($orgCols,'website')&&$website!==''){$sets[]='website=?';$values[]=$website;}
     if($sets){$values[]=(int)$org['id'];$q=$db->prepare("UPDATE bh_organisers SET ".implode(',',$sets)." WHERE id=?");$q->execute($values);}
     $organiserId=(int)$org['id'];
   }else{
     $fields=['organisation_name','slug','status'];$values=[$organisation,$slug,'pending'];
     if($hasOrgUserId){array_unshift($fields,'user_id');array_unshift($values,$userId);}
     foreach(['description'=>'','email'=>$email,'phone'=>$phone!==''?$phone:null,'website'=>$website!==''?$website:null] as $field=>$value)if(bh_leader_col($orgCols,$field)){$fields[]=$field;$values[]=$value;}
     // Some older live installs used `name` instead of `organisation_name`. Keep those
     // schemas usable without requiring a manual database rebuild.
     foreach(['name','organisation','business_name'] as $alias){
       if(bh_leader_col($orgCols,$alias) && !in_array($alias,$fields,true)){$fields[]=$alias;$values[]=$organisation;}
     }
     foreach(bh_leader_required($orgCols) as $required){
       if(!in_array($required,$fields,true)) throw new RuntimeException('The live bh_organisers table requires an unsupported field: '.$required.'.');
     }
     foreach(bh_leader_required($userCols) as $required){
       if(!in_array($required,$fields,true) && !in_array($required,['created_at','updated_at'],true)) {
         // User required fields should be covered by the standard account columns.
         throw new RuntimeException('The live bh_users table requires an unsupported field: '.$required.'.');
       }
     }
     $q=$db->prepare("INSERT INTO bh_organisers (".implode(',',$fields).") VALUES (".implode(',',array_fill(0,count($fields),'?')).")");$q->execute($values);$organiserId=(int)$db->lastInsertId();
   }
   $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}

 session_regenerate_id(true);$_SESSION['bh_user_id']=$userId;$_SESSION['bh_leader_authenticated']=true;unset($_SESSION['bh_family_authenticated']);$_SESSION['bh_csrf']=bin2hex(random_bytes(24));
 bh_leader_signup_response(201,['ok'=>true,'authenticated'=>true,'pending_review'=>true,'organiser_id'=>$organiserId,'user'=>['id'=>$userId,'email'=>$email,'role'=>'leader','status'=>'active'],'csrf'=>$_SESSION['bh_csrf'],'message'=>'Your leader account is ready. Your organiser profile is pending review.']);
}catch(Throwable $e){error_log('Bubba Hub leader signup failed: '.$e->getMessage());bh_leader_signup_response(500,['ok'=>false,'error'=>'leader_signup_error','message'=>'We could not create your leader account just yet. Please try again.']);}
?>