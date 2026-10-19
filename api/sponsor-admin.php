<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function sponsorAdminReply(int $code,array $body):never{http_response_code($code);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');sponsorAdminReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
$secure=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';
if(session_status()!==PHP_SESSION_ACTIVE){ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_name('BUBBAHUB_ADMINSESSID');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
if(empty($_SESSION['bh_admin_authenticated']))sponsorAdminReply(401,['ok'=>false,'error'=>'admin_required']);
// Admin authentication is established by admin-auth.php using the dedicated session.
// Do not apply a second, unsynchronised last-activity timeout here: admin-auth.php
// does not refresh bh_admin_last_activity and valid sessions were rejected.
if(!$secure)sponsorAdminReply(403,['ok'=>false,'error'=>'https_required']);
if(!function_exists('curl_init'))sponsorAdminReply(503,['ok'=>false,'error'=>'stripe_unavailable']);
$secret=(string)(defined('BUBBAHUB_STRIPE_SECRET_KEY')?constant('BUBBAHUB_STRIPE_SECRET_KEY'):'');
if($secret===''){
 // Read the private Stripe constant from wp-config.php without executing WordPress.
 // Loading wp-load.php inside a JSON API can emit HTML or terminate the request.
 foreach(['/public_html/wp-config.php',dirname(__DIR__).'/wp-config.php',dirname(__DIR__,2).'/wp-config.php'] as $path){
  if(!is_file($path))continue;
  $contents=@file_get_contents($path);
  if($contents===false)continue;
  $pattern = '~define\\s*\\(\\s*[\\x27\\x22]BUBBAHUB_STRIPE_SECRET_KEY[\\x27\\x22]\\s*,\\s*[\\x27\\x22]([^\\x27\\x22]*)[\\x27\\x22]\\s*\\)\\s*;~';
  if(preg_match($pattern,$contents,$match)){$secret=stripcslashes($match[1]);break;}
 }
}
if($secret===''){ $config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];$secret=(string)(getenv('STRIPE_SECRET_KEY')?:($config['stripe']['secret_key']??''));}
if($secret==='')sponsorAdminReply(503,['ok'=>false,'error'=>'stripe_not_configured']);
$ch=curl_init('https://api.stripe.com/v1/checkout/sessions?limit=100');
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$secret]]);
$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
$data=is_string($raw)?json_decode($raw,true):null;
if($code!==200||!is_array($data)||!is_array($data['data']??null))sponsorAdminReply(503,['ok'=>false,'error'=>'stripe_query_failed']);
$items=[];
foreach($data['data'] as $s){$m=$s['metadata']??[];if(($m['type']??'')!=='event_sponsorship')continue;
 $items[]=['session_id'=>(string)($s['id']??''),'event_key'=>(string)($m['event_key']??''),'tier'=>(string)($m['tier']??''),'business_name'=>(string)($m['business_name']??''),'contact_name'=>(string)($m['contact_name']??''),'email'=>(string)($m['email']??''),'website'=>(string)($m['website']??''),'logo_url'=>(string)($m['logo_url']??''),'payment_status'=>(string)($s['payment_status']??''),'amount_total'=>(int)($s['amount_total']??0),'created'=>(int)($s['created']??0)];}
sponsorAdminReply(200,['ok'=>true,'sponsors'=>$items,'has_more'=>(bool)($data['has_more']??false),'note'=>'Stripe Checkout sessions, latest 100 only. Paid status is verified directly from Stripe; not an approval or publication record.']);
