<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function sponsorReply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
// Only events currently confirmed in the live Admin Events area.
// Replace with an authenticated admin-published event query when the events CMS launches.
$events=['bubba-me-2027'=>'Bubba & Me Baby & Pregnancy Expo — 9 January 2027'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){sponsorReply(200,['ok'=>true,'events'=>array_map(static fn($key,$name)=>['key'=>$key,'name'=>$name],array_keys($events),array_values($events))]);}
if($method!=='POST'){header('Allow: GET, POST');sponsorReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
// Expo sponsorship checkout is temporarily disabled on live until launch checks pass.
sponsorReply(503,['ok'=>false,'error'=>'coming_soon','message'=>'Expo sponsorship payments are Coming Soon.']);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')sponsorReply(403,['ok'=>false,'error'=>'https_required']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??$_SERVER['HTTP_REFERER']??'');
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)sponsorReply(403,['ok'=>false,'error'=>'invalid_origin']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>8000)sponsorReply(413,['ok'=>false,'error'=>'too_large']);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))sponsorReply(400,['ok'=>false,'error'=>'invalid_json']);
$get=static fn(string $k,int $max=190):string=>mb_substr(trim((string)(is_string($input[$k]??null)?$input[$k]:'')),0,$max);
$event=$get('event_key',80);$tier=$get('tier',4);
$name=$get('business_name',150);$contact=$get('contact_name',120);$email=$get('email');
if(!isset($events[$event])||!in_array($tier,['25','50','100'],true)||!$name||!$contact||!filter_var($email,FILTER_VALIDATE_EMAIL)||($input['consent']??'')!=='on')sponsorReply(422,['ok'=>false,'error'=>'invalid_sponsorship_details']);
$website=$get('website',500);$logo=$get('logo_url',500);
foreach([$website,$logo] as $url){if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'))sponsorReply(422,['ok'=>false,'error'=>'invalid_url']);}
if(!function_exists('curl_init'))sponsorReply(503,['ok'=>false,'error'=>'payments_unavailable']);
// Resolve the same private Stripe key used by the working subscription checkout.
// Never bootstrap WordPress from a standalone JSON payment API.
$config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
$secret=defined('BUBBAHUB_STRIPE_SECRET_KEY')?trim((string)constant('BUBBAHUB_STRIPE_SECRET_KEY')):'';
if($secret===''){
 foreach(['/public_html/wp-config.php',dirname(__DIR__).'/wp-config.php',dirname(__DIR__,2).'/wp-config.php'] as $file){
  if(!is_file($file))continue;
  $contents=@file_get_contents($file);
  if($contents===false)continue;
  $pattern='/define\\s*\\(\\s*[\\x27\\x22 ]BUBBAHUB_STRIPE_SECRET_KEY[\\x27\\x22 ]\\s*,\\s*[\\x27\\x22 ](.*?)[\\x27\\x22 ]\\s*\\)\\s*;/s';
  if(preg_match($pattern,$contents,$matches)){$secret=stripcslashes($matches[1]);break;}
 }
}
if($secret==='')$secret=trim((string)($config['stripe']['secret_key']??getenv('STRIPE_SECRET_KEY')?:''));
if($secret==='')sponsorReply(503,['ok'=>false,'error'=>'stripe_not_configured']);
$labels=['25'=>'Community Supporter','50'=>'Event Supporter','100'=>'Event Partner'];
$params=['mode'=>'payment','payment_method_types[0]'=>'card','customer_email'=>$email,
'line_items[0][price_data][currency]'=>'gbp','line_items[0][price_data][unit_amount]'=>(string)((int)$tier*100),
'line_items[0][price_data][product_data][name]'=>'Bubba Hub '.$labels[$tier].' sponsorship',
'line_items[0][price_data][product_data][description]'=>$events[$event],
'line_items[0][quantity]'=>'1','metadata[type]'=>'event_sponsorship','metadata[event_key]'=>$event,'metadata[tier]'=>$tier,
'metadata[business_name]'=>$name,'metadata[contact_name]'=>$contact,'metadata[email]'=>$email,
'metadata[website]'=>$website,'metadata[logo_url]'=>$logo,
'success_url'=>'https://bubbahub.co.uk/sponsor.html?payment=success&session_id={CHECKOUT_SESSION_ID}',
'cancel_url'=>'https://bubbahub.co.uk/sponsor.html?payment=cancelled'];
$ch=curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$secret]]);
$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
$result=is_string($raw)?json_decode($raw,true):null;
if($code<200||$code>=300||!is_array($result)||empty($result['url'])){error_log('Sponsor checkout session creation failed, HTTP '.$code.'; Stripe type '.(string)($result['error']['type']??'unknown'));sponsorReply(503,['ok'=>false,'error'=>'stripe_checkout_unavailable','message'=>'Stripe could not start checkout. Please contact Bubba Hub.']);}
sponsorReply(200,['ok'=>true,'url'=>$result['url']]);
