<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function le_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function le_table(PDO $db,string $name):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$name]);return (int)$q->fetchColumn()>0;}
require_once __DIR__.'/db.php';
if(empty($_SESSION['bh_user_id']))le_reply(401,['ok'=>false,'error'=>'login_required']);
$db=bh_mysql();$uid=(int)$_SESSION['bh_user_id'];
$q=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");$q->execute([$uid]);$user=$q->fetch(PDO::FETCH_ASSOC);
if(!$user||$user['status']!=='active'||$user['role']!=='leader')le_reply(403,['ok'=>false,'error'=>'leader_account_required']);
$org=null;
try{$q=$db->prepare("SELECT id,organisation_name FROM bh_organisers WHERE user_id=? LIMIT 1");$q->execute([$uid]);$org=$q->fetch(PDO::FETCH_ASSOC)?:null;}catch(Throwable $e){}
if(!$org){$q=$db->prepare("SELECT id,organisation_name FROM bh_organisers WHERE LOWER(email)=LOWER(?) LIMIT 1");$q->execute([$user['email']]);$org=$q->fetch(PDO::FETCH_ASSOC)?:null;}
if(!$org)le_reply(403,['ok'=>false,'error'=>'organiser_required','message'=>'Your leader account is not linked to an organisation yet.']);
$oid=(int)$org['id'];
if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_directory_event_csrf']??=bin2hex(random_bytes(32));
 $listings=[];try{$q=$db->prepare("SELECT id,title FROM bh_activities WHERE organiser_id=? AND status IN ('published','pending','draft') ORDER BY title");$q->execute([$oid]);$listings=$q->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
 le_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_directory_event_csrf'],'organisation'=>$org,'listings'=>$listings,'schema_ready'=>le_table($db,'bh_directory_events')&&le_table($db,'bh_directory_event_occurrences')&&le_table($db,'bh_directory_event_listings')]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')le_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)le_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body)||empty($_SESSION['bh_directory_event_csrf'])||!is_string($body['csrf']??null)||!hash_equals($_SESSION['bh_directory_event_csrf'],$body['csrf']))le_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
foreach(['bh_directory_events','bh_directory_event_occurrences','bh_directory_event_listings'] as $table)if(!le_table($db,$table))le_reply(503,['ok'=>false,'error'=>'events_schema_not_installed','message'=>'The Events database setup has not yet been installed on staging. Your details have not been submitted.']);
$length=static fn($v)=>function_exists('mb_strlen')?mb_strlen($v):strlen($v);
$title=trim((string)($body['title']??''));$summary=trim((string)($body['summary']??''));$description=trim((string)($body['description']??''));$category=trim((string)($body['category']??''));$venue=trim((string)($body['venue_name']??''));$town=trim((string)($body['town']??''));$region=trim((string)($body['region']??''));$postcode=trim((string)($body['postcode']??''));$address=trim((string)($body['address']??''));$booking=trim((string)($body['booking_url']??''));$status=($body['intent']??'draft')==='submit'?'pending':'draft';
$start=trim((string)($body['starts_at']??''));$end=trim((string)($body['ends_at']??''));
if($title===''||$description===''||$start===''||$venue===''||$town==='')le_reply(422,['ok'=>false,'error'=>'required_fields','message'=>'Add an event title, description, start date and time, venue and town.']);
if($length($title)>190||$length($summary)>500||$length($description)>20000||$length($venue)>190||$length($town)>120||$length($region)>120||$length($postcode)>20||$length($address)>500)le_reply(422,['ok'=>false,'error'=>'field_too_long','message'=>'One or more fields are too long.']);
$parse=static function(string $v):?string{$d=DateTime::createFromFormat('Y-m-d\\TH:i',$v,new DateTimeZone('Europe/London'));return $d?$d->format('Y-m-d H:i:s'):null;};
$starts=$parse($start);$ends=$end!==''?$parse($end):null;
if(!$starts||($end!==''&&!$ends)||($ends&&$ends<=$starts))le_reply(422,['ok'=>false,'error'=>'invalid_event_dates','message'=>'Check the event start and end date/time. The end must be after the start.']);
if($booking!==''&&(!filter_var($booking,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($booking,PHP_URL_SCHEME)),['http','https'],true)))le_reply(422,['ok'=>false,'error'=>'invalid_booking_url','message'=>'Enter a valid https:// or http:// booking link.']);
$ageMin=($body['age_min_months']??'')===''?null:(int)$body['age_min_months'];$ageMax=($body['age_max_months']??'')===''?null:(int)$body['age_max_months'];
if(($ageMin!==null&&($ageMin<0||$ageMin>216))||($ageMax!==null&&($ageMax<0||$ageMax>216))||($ageMin!==null&&$ageMax!==null&&$ageMax<$ageMin))le_reply(422,['ok'=>false,'error'=>'invalid_age_range','message'=>'Check the age range.']);
$linked=array_values(array_unique(array_filter(array_map('intval',is_array($body['listing_ids']??null)?$body['listing_ids']:[]),fn($v)=>$v>0)));
if($linked){$marks=implode(',',array_fill(0,count($linked),'?'));$q=$db->prepare("SELECT id FROM bh_activities WHERE organiser_id=? AND id IN ($marks)");$q->execute(array_merge([$oid],$linked));if(count($q->fetchAll(PDO::FETCH_COLUMN))!==count($linked))le_reply(403,['ok'=>false,'error'=>'listing_link_not_owned','message'=>'You can only link events to your own listings.']);}
$base=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));if($base==='')$base='event';$slug=$base.'-'.bin2hex(random_bytes(3));
try{
 $db->beginTransaction();
 $q=$db->prepare("INSERT INTO bh_directory_events (organiser_id,created_by_user_id,title,slug,summary,description,category,age_min_months,age_max_months,booking_url,venue_name,address,town,region,postcode,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
 $q->execute([$oid,$uid,$title,$slug,$summary?:null,$description,$category?:null,$ageMin,$ageMax,$booking?:null,$venue,$address?:null,$town,$region?:null,$postcode?:null,$status,$status==='pending'?date('Y-m-d H:i:s'):null]);
 $eventId=(int)$db->lastInsertId();
 $q=$db->prepare("INSERT INTO bh_directory_event_occurrences (event_id,starts_at,ends_at,timezone) VALUES (?,?,?,'Europe/London')");$q->execute([$eventId,$starts,$ends]);
 if($linked){$q=$db->prepare("INSERT INTO bh_directory_event_listings (event_id,activity_id) VALUES (?,?)");foreach($linked as $id)$q->execute([$eventId,$id]);}
 $db->commit();
 le_reply(201,['ok'=>true,'event_id'=>$eventId,'status'=>$status,'message'=>$status==='pending'?'Your event has been sent to Bubba Hub for review.':'Your event draft has been saved.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Leader event submit: '.$e->getMessage());le_reply(500,['ok'=>false,'error'=>'event_save_failed','message'=>'The event could not be saved. Please try again.']);}
