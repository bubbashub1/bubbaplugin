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
// WordPress configuration contains the existing BUBBAHUB Turnstile constants.
// Load it through WordPress's supported bootstrap rather than directly
// requiring wp-config.php (which itself loads wp-settings.php).
// Buffer incidental output to keep this JSON endpoint well formed.
$wpLoader = dirname(__DIR__) . '/wp-load.php';
if (is_file($wpLoader) && is_readable($wpLoader)) {
 ob_start();
 try { require_once $wpLoader; }
 finally { ob_end_clean(); }
}
// CLOSED by default. A constant takes precedence over environment settings.
$openFlag = defined('BH_EXPO_APPLICATIONS_OPEN') ? constant('BH_EXPO_APPLICATIONS_OPEN') : (getenv('BH_EXPO_APPLICATIONS_OPEN') ?: 'false');
$open = filter_var($openFlag, FILTER_VALIDATE_BOOLEAN);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST'], true)) {
 header('Allow: GET, POST'); expoResponse(405,['ok'=>false,'error'=>'method_not_allowed']);
}
// Require configured Cloudflare Turnstile before accepting any public applications.
$turnstileSecret = trim((string)(
 (defined('BUBBAHUB_TURNSTILE_SECRET_KEY') ? constant('BUBBAHUB_TURNSTILE_SECRET_KEY') : null)
 ?: (defined('BH_TURNSTILE_SECRET_KEY') ? constant('BH_TURNSTILE_SECRET_KEY') : null)
 ?: (defined('TURNSTILE_SECRET_KEY') ? constant('TURNSTILE_SECRET_KEY') : null)
 ?: getenv('BUBBAHUB_TURNSTILE_SECRET_KEY') ?: getenv('BH_TURNSTILE_SECRET_KEY') ?: getenv('TURNSTILE_SECRET_KEY') ?: ''
));
$turnstileSiteKey = trim((string)(
 (defined('BUBBAHUB_TURNSTILE_SITE_KEY') ? constant('BUBBAHUB_TURNSTILE_SITE_KEY') : null)
 ?: (defined('BH_TURNSTILE_SITE_KEY') ? constant('BH_TURNSTILE_SITE_KEY') : null)
 ?: (defined('TURNSTILE_SITE_KEY') ? constant('TURNSTILE_SITE_KEY') : null)
 ?: getenv('BUBBAHUB_TURNSTILE_SITE_KEY') ?: getenv('BH_TURNSTILE_SITE_KEY') ?: getenv('TURNSTILE_SITE_KEY') ?: ''
));
if ($open && ($turnstileSecret === '' || $turnstileSiteKey === '')) expoResponse(503,['ok'=>false,'applications_open'=>false,'error'=>'spam_protection_not_configured']);
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
 if (empty($_SESSION['expo_apply_csrf'])) $_SESSION['expo_apply_csrf'] = bin2hex(random_bytes(32));
 expoResponse(200,['ok'=>true,'applications_open'=>true,'csrf_token'=>$_SESSION['expo_apply_csrf'],'turnstile_site_key'=>$turnstileSiteKey]);
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
// A hidden field catches unsophisticated bot submissions.
if (!empty($body['website_confirm'])) expoResponse(422,['ok'=>false,'error'=>'invalid_submission']);
$captcha = is_string($body['turnstile_token']??null) ? trim($body['turnstile_token']) : '';
if ($captcha==='' || strlen($captcha)>2048) expoResponse(422,['ok'=>false,'error'=>'verification_required']);
$verifyBody=http_build_query(['secret'=>$turnstileSecret,'response'=>$captcha,'remoteip'=>$_SERVER['REMOTE_ADDR']??'']);
$verifyContext=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>$verifyBody,'timeout'=>6,'ignore_errors'=>true]]);
$verifyResult=@file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify',false,$verifyContext);
$verifyData=is_string($verifyResult)?json_decode($verifyResult,true):null;
if (!is_array($verifyData)||($verifyData['success']??false)!==true) expoResponse(403,['ok'=>false,'error'=>'verification_failed']);
$verifiedHostname = strtolower((string)($verifyData['hostname']??''));
if ($verifiedHostname==='' || !hash_equals($host,$verifiedHostname))
 expoResponse(403,['ok'=>false,'error'=>'verification_hostname_mismatch']);
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
if (!$policy||!$date||$date->format('Y-m-d')!==$expiry||$date <= new DateTimeImmutable('today'))
 expoResponse(422,['ok'=>false,'error'=>'insurance_required']);
$website=$get('website',500);$logo=$get('logo_url',500);
$social=[];foreach(['facebook','instagram','tiktok'] as $network){$social[$network]=$get($network.'_url',500);}
foreach (array_merge(['website'=>$website,'logo_url'=>$logo],$social) as $field=>$url) {
 if ($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'))
  expoResponse(422,['ok'=>false,'error'=>'invalid_url','field'=>$field]);
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
 $participation=array_values(array_filter($participation,'is_string'));
 $participation=array_values(array_intersect($participation,['exhibitor_stall','taster_session','talk_qa','sponsor']));
 $extra=[];
 foreach (['town','service_category','oversize','oversize_details','electricity','chair','goody_bag','payment_preference','invoice_requested','notes'] as $key) {
  $extra[$key]=$get($key,500);
 }
 $details=json_encode(['participation'=>$participation,'additional_application_details'=>$extra,'social_links'=>$social],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 $stmt->execute([$name,$contact,$email,$phone,$website?:null,$logo?:null,$description,$details,$preferred,$donation,$policy,$expiry,$get('requirements',1500)]);
 $id=(int)$db->lastInsertId();
 $_SESSION['expo_apply_last']=time();
 unset($_SESSION['expo_apply_csrf']);
 expoResponse(201,['ok'=>true,'application_id'=>$id,'status'=>'pending','table_reserved'=>false]);
} catch(Throwable $e) {
 error_log('Expo application submission failed: '.get_class($e));
 expoResponse(503,['ok'=>false,'error'=>'booking_temporarily_unavailable']);
}
