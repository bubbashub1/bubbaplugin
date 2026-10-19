<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')respond(405,['ok'=>false,'error'=>'method_not_allowed']);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')respond(403,['ok'=>false,'error'=>'https_required']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_name('BUBBAHUB_ADMINSESSID');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);session_start();
if(empty($_SESSION['bh_admin_authenticated']))respond(401,['ok'=>false,'error'=>'admin_required']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if(parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)respond(403,['ok'=>false,'error'=>'invalid_origin']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>1000)respond(413,['ok'=>false,'error'=>'too_large']);
$input=json_decode((string)file_get_contents('php://input'),true);
$id=filter_var($input['request_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)respond(422,['ok'=>false,'error'=>'invalid_request_id']);
function stripePost(string $endpoint,array $fields,string $secret,string $key):array {
 $ch=curl_init('https://api.stripe.com/v1/'.$endpoint);
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$secret,'Idempotency-Key: '.$key]]);
 $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=is_string($raw)?json_decode($raw,true):null;
 if($code<200||$code>=300||!is_array($data))throw new RuntimeException('stripe_api_error');
 return $data;
}
try{
 require_once __DIR__.'/sponsor-records.php';
 $secret=bh_sponsor_stripe_key();if($secret===''||!function_exists('curl_init'))respond(503,['ok'=>false,'error'=>'stripe_unavailable']);
 $db=bh_mysql();$db->beginTransaction();
 $q=$db->prepare("SELECT * FROM bh_sponsor_invoice_requests WHERE id=? FOR UPDATE");$q->execute([$id]);$request=$q->fetch();
 if(!$request){$db->rollBack();respond(404,['ok'=>false,'error'=>'not_found']);}
 if(($request['status']??'')!=='requested'){$db->rollBack();respond(409,['ok'=>false,'error'=>'already_processed']);}
 if(($request['event_key']??'')!=='bubba-me-2027'||!in_array((int)$request['tier'],[25,50,100],true)){$db->rollBack();respond(422,['ok'=>false,'error'=>'invalid_invoice_request']);}
 // Create a Stripe draft invoice first; no customer email is sent until admin
 // confirms delivery separately in Stripe Dashboard.
 $customer=stripePost('customers',['email'=>$request['email'],'name'=>$request['business_name'],'metadata[sponsor_request_id]'=>(string)$id],$secret,'bh-sponsor-'.$id.'-customer');
 $invoice=stripePost('invoices',['customer'=>$customer['id'],'collection_method'=>'send_invoice','days_until_due'=>'14','auto_advance'=>'false','metadata[type]'=>'event_sponsorship','metadata[event_key]'=>'bubba-me-2027','metadata[tier]'=>(string)$request['tier'],'metadata[sponsor_request_id]'=>(string)$id],$secret,'bh-sponsor-'.$id.'-invoice');
 stripePost('invoiceitems',['customer'=>$customer['id'],'invoice'=>$invoice['id'],'currency'=>'gbp','amount'=>(string)((int)$request['tier']*100),'description'=>'Bubba & Me Expo 2027 — £'.$request['tier'].' sponsorship'],$secret,'bh-sponsor-'.$id.'-item');
 $db->prepare("UPDATE bh_sponsor_invoice_requests SET status='draft_created' WHERE id=?")->execute([$id]);
 $db->commit();
 respond(200,['ok'=>true,'status'=>'draft_created','stripe_invoice_id'=>$invoice['id'],'message'=>'Draft created in Stripe. Review and send it from Stripe Dashboard.']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Sponsor invoice draft creation failed: '.get_class($e));respond(503,['ok'=>false,'error'=>'invoice_creation_failed','message'=>'Could not create invoice draft. Check Stripe before retrying.']);}
