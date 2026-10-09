<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function boostReply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
try{
require_once __DIR__.'/db.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
$uid=(int)($_SESSION['bh_user_id']??0);
if(!$uid)boostReply(401,['ok'=>false,'message'=>'Sign in as a class leader.']);
$db=bh_mysql();
$q=$db->prepare("SELECT role,status,email FROM bh_users WHERE id=?");$q->execute([$uid]);$user=$q->fetch();
if(!$user||$user['role']!=='leader'||$user['status']!=='active')boostReply(403,['ok'=>false,'message'=>'Leader access required.']);
$cols=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers'")->fetchAll(PDO::FETCH_COLUMN);
if(in_array('user_id',$cols,true)){$q=$db->prepare("SELECT id FROM bh_organisers WHERE user_id=?");$q->execute([$uid]);}
else{$q=$db->prepare("SELECT id FROM bh_organisers WHERE email=?");$q->execute([$user['email']]);}
$oid=(int)$q->fetchColumn();if(!$oid)boostReply(403,['ok'=>false,'message'=>'Organiser profile required.']);
$db->exec("CREATE TABLE IF NOT EXISTS bh_listing_boosts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,organiser_id BIGINT UNSIGNED NOT NULL,activity_id BIGINT UNSIGNED NOT NULL,package_key VARCHAR(20) NOT NULL,amount_pence INT NOT NULL,stripe_session_id VARCHAR(255) NULL UNIQUE,status VARCHAR(20) NOT NULL DEFAULT 'pending',starts_at DATETIME NULL,ends_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_boost_activity (activity_id,status,ends_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$packages=['starter'=>['days'=>3,'amount'=>300],'popular'=>['days'=>7,'amount'=>500],'extended'=>['days'=>14,'amount'=>1000]];
function boostStripe(string $path,string $key,?array $params=null):array{
 $ch=curl_init('https://api.stripe.com/v1/'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key]]);
 if($params!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params)]);
 $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=json_decode((string)$raw,true);
 if($code<200||$code>=300||!is_array($data))throw new RuntimeException('Stripe could not complete the request.');
 return $data;
}
function boostKey():string{
 $config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
 $key=defined('BUBBAHUB_STRIPE_SECRET_KEY')?(string)constant('BUBBAHUB_STRIPE_SECRET_KEY'):(string)($config['stripe']['secret_key']??getenv('STRIPE_SECRET_KEY')?:'');
 if($key===''){foreach([dirname(__DIR__).'/wp-config.php',dirname(__DIR__,2).'/wp-config.php'] as $f){if(!is_file($f))continue;$s=file_get_contents($f);if(preg_match('/define\\s*\\(\\s*[\'"]BUBBAHUB_STRIPE_SECRET_KEY[\'"]\\s*,\\s*[\'"]([^\'"]+)[\'"]/',(string)$s,$m)){$key=$m[1];break;}}}
 return trim($key);
}
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET'){
 $q=$db->prepare("SELECT b.id,b.activity_id,a.title,b.package_key,b.amount_pence,b.status,b.starts_at,b.ends_at FROM bh_listing_boosts b JOIN bh_activities a ON a.id=b.activity_id WHERE b.organiser_id=? ORDER BY b.id DESC LIMIT 100");$q->execute([$oid]);
 $a=$db->prepare("SELECT id,title FROM bh_activities WHERE organiser_id=? AND status='published' ORDER BY title");$a->execute([$oid]);
 boostReply(200,['ok'=>true,'boosts'=>$q->fetchAll(),'activities'=>$a->fetchAll(),'packages'=>$packages]);
}
if($method!=='POST')boostReply(405,['ok'=>false,'message'=>'Method not allowed.']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''&&parse_url($origin,PHP_URL_HOST)!==($_SERVER['HTTP_HOST']??''))boostReply(403,['ok'=>false,'message'=>'Invalid origin.']);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body))boostReply(400,['ok'=>false,'message'=>'Invalid request.']);
$key=boostKey();if($key==='')boostReply(503,['ok'=>false,'message'=>'Stripe is not configured.']);
if(($body['action']??'')==='confirm'){
 $id=(int)($body['boost_id']??0);$q=$db->prepare("SELECT * FROM bh_listing_boosts WHERE id=? AND organiser_id=?");$q->execute([$id,$oid]);$boost=$q->fetch();
 if(!$boost||empty($boost['stripe_session_id']))boostReply(404,['ok'=>false,'message'=>'Boost not found.']);
 $session=boostStripe('checkout/sessions/'.rawurlencode($boost['stripe_session_id']),$key);
 if(($session['payment_status']??'')!=='paid'||($session['metadata']['boost_id']??'')!==(string)$id||($session['mode']??'')!=='payment'||(int)($session['amount_total']??0)!==(int)$boost['amount_pence'])boostReply(409,['ok'=>false,'message'=>'Payment has not been verified.']);
 $days=$packages[$boost['package_key']]['days']??0;if(!$days)boostReply(422,['ok'=>false,'message'=>'Unknown boost package.']);
 $db->beginTransaction();
 $q=$db->prepare("SELECT status FROM bh_listing_boosts WHERE id=? AND organiser_id=? FOR UPDATE");$q->execute([$id,$oid]);
 if($q->fetchColumn()==='pending'){$q=$db->prepare("UPDATE bh_listing_boosts SET status='active',starts_at=UTC_TIMESTAMP(),ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? DAY) WHERE id=?");$q->execute([$days,$id]);}
 $db->commit();boostReply(200,['ok'=>true,'message'=>'Your boost payment is verified.']);
}
if(($body['action']??'')!=='checkout')boostReply(400,['ok'=>false,'message'=>'Unknown action.']);
$activity=(int)($body['activity_id']??0);$package=(string)($body['package']??'');
if(!isset($packages[$package]))boostReply(422,['ok'=>false,'message'=>'Choose a valid boost.']);
$q=$db->prepare("SELECT id FROM bh_activities WHERE id=? AND organiser_id=? AND status='published'");$q->execute([$activity,$oid]);
if(!$q->fetchColumn())boostReply(403,['ok'=>false,'message'=>'Select one of your published activities.']);
$amount=$packages[$package]['amount'];$days=$packages[$package]['days'];
$q=$db->prepare("INSERT INTO bh_listing_boosts (organiser_id,activity_id,package_key,amount_pence) VALUES (?,?,?,?)");$q->execute([$oid,$activity,$package,$amount]);$id=(int)$db->lastInsertId();
$params=['mode'=>'payment','line_items[0][price_data][currency]'=>'gbp','line_items[0][price_data][product_data][name]'=>'Bubba Hub Featured Boost - '.$days.' days','line_items[0][price_data][unit_amount]'=>$amount,'line_items[0][quantity]'=>1,'metadata[boost_id]'=>(string)$id,'metadata[organiser_id]'=>(string)$oid,'client_reference_id'=>(string)$uid,'customer_email'=>$user['email'],'success_url'=>'https://bubbahub.co.uk/leader/boosts.html?boost_id='.$id.'&checkout=success','cancel_url'=>'https://bubbahub.co.uk/leader/boosts.html?checkout=cancel'];
$session=boostStripe('checkout/sessions',$key,$params);
$db->prepare("UPDATE bh_listing_boosts SET stripe_session_id=? WHERE id=?")->execute([$session['id'],$id]);
boostReply(200,['ok'=>true,'url'=>$session['url']??'']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Boost checkout: '.$e->getMessage());boostReply(500,['ok'=>false,'message'=>'Unable to process the boost request.']);}
