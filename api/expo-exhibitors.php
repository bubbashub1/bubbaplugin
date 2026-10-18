<?php
declare(strict_types=1);
/**
 * Expo application submission, production-based RELEASE CANDIDATE.
 * CLOSED BY DEFAULT: set BH_EXPO_APPLICATIONS_OPEN=true only after schema,
 * anti-abuse, notification, and end-to-end acceptance gates are passed.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expoResponse(int $code, array $body): never {
 http_response_code($code); echo json_encode($body, JSON_UNESCAPED_SLASHES); exit;
}
$open = filter_var(getenv('BH_EXPO_APPLICATIONS_OPEN') ?: 'false', FILTER_VALIDATE_BOOLEAN);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) {
 header('Allow: GET, POST'); expoResponse(405,['ok'=>false,'error'=>'method_not_allowed']);
}
if (!$open) expoResponse(503,['ok'=>false,'applications_open'=>false,'error'=>'applications_not_yet_open']);
$secure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
if (!$secure) expoResponse(403,['ok'=>false,'error'=>'https_required']);
if (session_status() !== PHP_SESSION_ACTIVE) {
 ini_set('session.use_strict_mode','1');
 ini_set('session.use_only_cookies','1');
 session_name('BH_EXPO_APPLY');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
if ($method === 'GET') {
 $_SESSION['expo_apply_csrf'] = bin2hex(random_bytes(32));
 expoResponse(200,['ok'=>true,'applications_open'=>true,'csrf_token'=>$_SESSION['expo_apply_csrf']]);
}
$host = strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
$origin = (string)($_SERVER['HTTP_ORIGIN']??'');
$referer = (string)($_SERVER['HTTP_REFERER']??'');
$source = $origin !== '' ? $origin : $referer;
if ($source === '' || parse_url($source,PHP_URL_SCHEME) !== 'https' || strtolower((string)parse_url($source,PHP_URL_HOST)) !== $host)
 expoResponse(403,['ok'=>false,'error'=>'invalid_origin']);
$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
if (empty($_SESSION['expo_apply_csrf']) || !hash_equals((string)$_SESSION['expo_apply_csrf'],$csrf))
 expoResponse(403,['ok'=>false,'error'=>'invalid_csrf']);
if ((int)($_SERVER['CONTENT_LENGTH']??0)>16000) expoResponse(413,['ok'=>false,'error'=>'request_too_large']);
if (time()-(int)($_SESSION['expo_apply_last']??0)<120) expoResponse(429,['ok'=>false,'error'=>'please_wait_before_retrying']);
$body=json_decode((string)file_get_contents('php://input'),true);
if (!is_array($body)) expoResponse(400,['ok'=>false,'error'=>'invalid_json']);
$get=static fn(string $key,int $max=2000):string=>mb_substr(trim((string)(is_scalar($body[$key]??null)?$body[$key]:'')),0,$max);
if ($get('contact_consent')!=='yes'||$get('conditions_accepted')!=='yes'||$get('insurance_confirm')!=='yes'||$get('visitor_numbers_acknowledged')!=='yes')
 expoResponse(422,['ok'=>false,'error'=>'consent_required']);
$name=$get('business_name',150);$contact=$get('contact_name',120);$email=strtolower($get('email',190));$phone=$get('phone',30);$description=$get('description',2000);
if (!$name||!$contact||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$phone||mb_strlen($description)<20)
 expoResponse(422,['ok'=>false,'error'=>'required_fields']);
$preferred=filter_var($body['preferred_table']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>26]]);
if (!$preferred) expoResponse(422,['ok'=>false,'error'=>'table_required']);
$policy=$get('insurance_policy',120);$expiry=$get('insurance_expiry',20);
$date=DateTimeImmutable::createFromFormat('!Y-m-d',$expiry);
if (!$policy||!$date||$date->format('Y-m-d')!==$expiry)
 expoResponse(422,['ok'=>false,'error'=>'insurance_required']);
$website=$get('website',500);$logo=$get('logo_url',500);
foreach ([$website,$logo] as $url) {
 if ($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'))
  expoResponse(422,['ok'=>false,'error'=>'invalid_url']);
}
$donation=$get('donation_interest',10);
if (!in_array($donation,['yes','no','maybe'],true)) expoResponse(422,['ok'=>false,'error'=>'invalid_donation_choice']);
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 // Canonical foundation schema, shared with admin approvals. Never auto-approve.
 $stmt=$db->prepare("INSERT INTO bh_expo_exhibitors
 (event_key,business_name,contact_name,contact_email,contact_phone,website_url,logo_url,description,participation_json,preferred_table,donation_interest,insurance_policy,insurance_expiry,requirements,application_status,public_visible,submitted_at)
 VALUES ('bubba-me-2027',?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',0,NOW())");
 $participation=$body['participation']??[];
 if (!is_array($participation)) $participation=[];
 $participation=array_values(array_intersect($participation,['exhibitor_stall','taster_session','talk_qa','sponsor']));
 $stmt->execute([$name,$contact,$email,$phone,$website?:null,$logo?:null,$description,json_encode($participation),$preferred,$donation,$policy,$expiry,$get('requirements',1500)]);
 $id=(int)$db->lastInsertId();
 $_SESSION['expo_apply_last']=time();
 unset($_SESSION['expo_apply_csrf']);
 expoResponse(201,['ok'=>true,'application_id'=>$id,'status'=>'pending','table_reserved'=>false]);
} catch(Throwable $e) {
 error_log('Expo application submission failed: '.get_class($e));
 expoResponse(503,['ok'=>false,'error'=>'booking_temporarily_unavailable']);
}
