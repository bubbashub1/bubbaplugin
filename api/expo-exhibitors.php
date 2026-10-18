<?php
declare(strict_types=1);
/** Bubba & Me Expo exhibitor applications — staging feature. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expo_reply(int $code,array $data): never { http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit; }
// Staging-only authenticated test mode; never open to anonymous visitors.
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if($host!=='staging.bubbahub.co.uk')expo_reply(503,['ok'=>false,'error'=>'applications_closed']);
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
 session_name('BUBBAHUB_ADMINSESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
if(empty($_SESSION['bh_admin_authenticated'])||empty($_SESSION['bh_admin_last_activity'])||time()-(int)$_SESSION['bh_admin_last_activity']>1800)expo_reply(401,['ok'=>false,'error'=>'admin_required']);
$_SESSION['bh_admin_last_activity']=time();
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 if(empty($_SESSION['bh_expo_form_csrf']))$_SESSION['bh_expo_form_csrf']=bin2hex(random_bytes(32));
 expo_reply(200,['ok'=>true,'test_mode'=>true,'csrf_token'=>$_SESSION['bh_expo_form_csrf']]);
}
if($method!=='POST')expo_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''&&strcasecmp((string)parse_url($origin,PHP_URL_HOST),$host)!==0)expo_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
if(empty($_SESSION['bh_expo_form_csrf'])||!hash_equals($_SESSION['bh_expo_form_csrf'],$csrf))expo_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) expo_reply(413,['ok'=>false,'error'=>'request_too_large']);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body))expo_reply(400,['ok'=>false,'error'=>'invalid_json']);
$val=static fn(string $key,int $max=2000):string=>mb_substr(trim((string)($body[$key]??'')),0,$max);
if($val('contact_consent')!=='yes'||$val('conditions_accepted')!=='yes'||$val('insurance_confirm')!=='yes')expo_reply(422,['ok'=>false,'error'=>'consent_required']);
$name=$val('business_name',150);$contact=$val('contact_name',120);$email=strtolower($val('email',190));$phone=$val('phone',30);$description=$val('description',2000);
if(!$name||!$contact||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$phone)expo_reply(422,['ok'=>false,'error'=>'required_fields']);
$table=filter_var($body['preferred_table']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>26]]);
if(!$table)expo_reply(422,['ok'=>false,'error'=>'table_required']);
$policy=$val('insurance_policy',120);$expiry=$val('insurance_expiry',20);
if(!$policy||!preg_match('/^20\\d{2}-\\d{2}-\\d{2}$/',$expiry)||!strtotime($expiry))expo_reply(422,['ok'=>false,'error'=>'insurance_required']);
// All applications start pending; publishing requires a separate admin decision.
$status='pending';
try {
 require __DIR__.'/db.php';$db=bh_mysql();
 $db->beginTransaction();
 // Preferred table is a request, not a reservation. No inventory locks or assignments.
 $website=$val('website',500);$logo=$val('logo_url',500);
 if(($website && (!filter_var($website,FILTER_VALIDATE_URL)||!preg_match('~^https://~i',$website)))||
    ($logo && (!filter_var($logo,FILTER_VALIDATE_URL)||!preg_match('~^https://~i',$logo)))){
    $db->rollBack();expo_reply(422,['ok'=>false,'error'=>'invalid_url']);
 }
 $donation=$val('donation_interest',10);
 if(!in_array($donation,['yes','no','maybe'],true)){$db->rollBack();expo_reply(422,['ok'=>false,'error'=>'invalid_donation_choice']);}
 $participation=$body['participation']??[];
 if(!is_array($participation))$participation=[];
 $allowed=['exhibitor_stall','taster_session','talk_qa','sponsor'];
 $participation=array_values(array_intersect($allowed,array_map('strval',$participation)));
 $requirements=$val('requirements',1500);
 $extras=[];
 foreach(['town','service_category','oversize','oversize_details','electricity','chair','goody_bag','payment_preference','invoice_requested','notes'] as $key){$extras[$key]=$val($key,1500);}
 $participationJson=json_encode(['activities'=>$participation,'form_details'=>$extras],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 $stmt=$db->prepare("INSERT INTO bh_expo_exhibitors
 (event_key,business_name,contact_name,contact_email,contact_phone,website_url,logo_url,description,
 participation_json,preferred_table,donation_interest,application_status,public_visible,
 insurance_policy,insurance_expiry,requirements,submitted_at)
 VALUES ('bubba-me-2027',?,?,?,?,?,?,?,?,?,? ,?,'pending',0,?,?,?,NOW())");
 $stmt->execute([$name,$contact,$email,$phone,$website?:null,$logo?:null,$description,
  $participationJson,$table,$donation,$policy,$expiry,$requirements?:null]);
 $id=(int)$db->lastInsertId();$db->commit();
 $subject='Bubba & Me Expo exhibitor application #'.$id;
 $message="New exhibitor application #".$id."\nStatus: pending\nPreferred table: ".$table."\nReview securely in the admin area.";
 $notified=@mail('contact@bubbahub.co.uk',$subject,$message,"From: noreply@bubbahub.co.uk\r\nContent-Type: text/plain; charset=UTF-8");
 error_log('Expo application '.$id.' admin_mail='.($notified?'sent':'failed'));
 expo_reply(201,['ok'=>true,'application_id'=>$id,'status'=>$status,'preferred_table'=>$table,'admin_notification_queued'=>$notified]);
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Expo application failure: '.$e->getMessage());expo_reply(503,['ok'=>false,'error'=>'booking_temporarily_unavailable']);}
