<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function invoiceReply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')invoiceReply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')invoiceReply(403,['ok'=>false,'error'=>'https_required']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)invoiceReply(403,['ok'=>false,'error'=>'invalid_origin']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>8000)invoiceReply(413,['ok'=>false,'error'=>'too_large']);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))invoiceReply(400,['ok'=>false,'error'=>'invalid_json']);
$get=static fn(string $key,int $max=190):string=>mb_substr(trim((string)(is_string($input[$key]??null)?$input[$key]:'')),0,$max);
if($get('payment_method')!=='invoice'||$get('event_key')!=='bubba-me-2027'||!in_array($get('tier'),['25','50','100'],true)||$get('consent')!=='on')invoiceReply(422,['ok'=>false,'error'=>'invalid_invoice_request']);
$name=$get('business_name',150);$contact=$get('contact_name',120);$email=$get('email');
if($name===''||$contact===''||!filter_var($email,FILTER_VALIDATE_EMAIL))invoiceReply(422,['ok'=>false,'error'=>'missing_contact_details']);
$website=$get('website',500);$logo=$get('logo_url',500);
foreach([$website,$logo] as $url){if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https'))invoiceReply(422,['ok'=>false,'error'=>'invalid_url']);}
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $db->exec("CREATE TABLE IF NOT EXISTS bh_sponsor_invoice_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(80) NOT NULL,
 tier SMALLINT UNSIGNED NOT NULL,
 business_name VARCHAR(150) NOT NULL,
 contact_name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL,
 website VARCHAR(500) NOT NULL DEFAULT '',
 logo_url VARCHAR(500) NOT NULL DEFAULT '',
 message VARCHAR(800) NOT NULL DEFAULT '',
 status VARCHAR(30) NOT NULL DEFAULT 'requested',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_event_created (event_key,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $q=$db->prepare("INSERT INTO bh_sponsor_invoice_requests(event_key,tier,business_name,contact_name,email,website,logo_url,message) VALUES(?,?,?,?,?,?,?,?)");
 $q->execute(['bubba-me-2027',(int)$get('tier'),$name,$contact,$email,$website,$logo,$get('message',800)]);
 invoiceReply(201,['ok'=>true,'request_id'=>(int)$db->lastInsertId(),'status'=>'requested']);
}catch(Throwable $e){error_log('Sponsor invoice request failed: '.get_class($e));invoiceReply(503,['ok'=>false,'error'=>'invoice_request_unavailable']);}
