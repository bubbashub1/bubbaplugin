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
if(!$org){
 // Legacy reconciliation only: use email only when it identifies exactly one organiser.
 // Ambiguous matches must never attach a Leader to an arbitrary organisation.
 $q=$db->prepare("SELECT COUNT(*) FROM bh_organisers WHERE LOWER(email)=LOWER(?)");
 $q->execute([$user['email']]);
 if((int)$q->fetchColumn()===1){
  $q=$db->prepare("SELECT id,organisation_name FROM bh_organisers WHERE LOWER(email)=LOWER(?) LIMIT 1");
  $q->execute([$user['email']]);$org=$q->fetch(PDO::FETCH_ASSOC)?:null;
 }
}
if(!$org)le_reply(403,['ok'=>false,'error'=>'organiser_required','message'=>'Your leader account is not linked to an organisation yet.']);
$oid=(int)$org['id'];
if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_directory_event_csrf']??=bin2hex(random_bytes(32));
 $listings=[];try{$q=$db->prepare("SELECT id,title FROM bh_activities WHERE organiser_id=? AND status IN ('published','pending','draft') ORDER BY title");$q->execute([$oid]);$listings=$q->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){}
 $event=null;$eventId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
 if($eventId&&le_table($db,'bh_directory_events')){
  $q=$db->prepare("SELECT * FROM bh_directory_events WHERE id=? AND organiser_id=? AND status IN ('published','draft','rejected') LIMIT 1");$q->execute([$eventId,$oid]);$event=$q->fetch(PDO::FETCH_ASSOC)?:null;
  if($event){
   $q=$db->prepare("SELECT starts_at,ends_at FROM bh_directory_event_occurrences WHERE event_id=? AND occurrence_status='scheduled' ORDER BY starts_at");$q->execute([$eventId]);$event['occurrences']=$q->fetchAll(PDO::FETCH_ASSOC);
   $q=$db->prepare("SELECT activity_id FROM bh_directory_event_listings WHERE event_id=?");$q->execute([$eventId]);$event['listing_ids']=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  }
 }
 le_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_directory_event_csrf'],'organisation'=>$org,'listings'=>$listings,'event'=>$event,'schema_ready'=>le_table($db,'bh_directory_events')&&le_table($db,'bh_directory_event_occurrences')&&le_table($db,'bh_directory_event_listings')&&le_table($db,'bh_directory_record_revisions')]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')le_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)le_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body)||empty($_SESSION['bh_directory_event_csrf'])||!is_string($body['csrf']??null)||!hash_equals($_SESSION['bh_directory_event_csrf'],$body['csrf']))le_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
foreach(['bh_directory_events','bh_directory_event_occurrences','bh_directory_event_listings'] as $table)if(!le_table($db,$table))le_reply(503,['ok'=>false,'error'=>'events_schema_not_installed','message'=>'The Events database setup has not yet been installed on staging. Your details have not been submitted.']);
$length=static fn($v)=>function_exists('mb_strlen')?mb_strlen($v):strlen($v);
$title=trim((string)($body['title']??''));$summary=trim((string)($body['summary']??''));$description=trim((string)($body['description']??''));$category=trim((string)($body['category']??''));$venue=trim((string)($body['venue_name']??''));$town=trim((string)($body['town']??''));$region=trim((string)($body['region']??''));$postcode=trim((string)($body['postcode']??''));$address=trim((string)($body['address']??''));$booking=trim((string)($body['booking_url']??''));$status=($body['intent']??'draft')==='submit'?'pending':'draft';
$rawOccurrences=is_array($body['occurrences']??null)?array_slice($body['occurrences'],0,40):[['starts_at'=>$body['starts_at']??'','ends_at'=>$body['ends_at']??'']];
if(!$rawOccurrences)$rawOccurrences=[['starts_at'=>$body['starts_at']??'','ends_at'=>$body['ends_at']??'']];
if($title===''||$description===''||$venue===''||$town==='')le_reply(422,['ok'=>false,'error'=>'required_fields','message'=>'Add an event name, description, venue and town.']);
if($length($title)>190||$length($summary)>500||$length($description)>20000||$length($venue)>190||$length($town)>120||$length($region)>120||$length($postcode)>20||$length($address)>500)le_reply(422,['ok'=>false,'error'=>'field_too_long','message'=>'One or more fields are too long.']);
$parse=static function(string $v):?string{$d=DateTime::createFromFormat('Y-m-d\\TH:i',$v,new DateTimeZone('Europe/London'));return $d?$d->format('Y-m-d H:i:s'):null;};
$occurrences=[];$seen=[];
foreach($rawOccurrences as $occ){if(!is_array($occ))continue;$sv=trim((string)($occ['starts_at']??''));$ev=trim((string)($occ['ends_at']??''));if($sv==='')continue;$s=$parse($sv);$e=$ev!==''?$parse($ev):null;if(!$s||($ev!==''&&!$e)||($e&&$e<=$s))le_reply(422,['ok'=>false,'error'=>'invalid_event_dates','message'=>'Check each event date and time. Every end must be after its start.']);if(isset($seen[$s]))continue;$seen[$s]=true;$occurrences[]=['start'=>$s,'end'=>$e];}
if(!$occurrences)le_reply(422,['ok'=>false,'error'=>'required_event_date','message'=>'Add at least one event date and start time.']);
$starts=$occurrences[0]['start'];
if($booking!==''&&(!filter_var($booking,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($booking,PHP_URL_SCHEME)),['http','https'],true)))le_reply(422,['ok'=>false,'error'=>'invalid_booking_url','message'=>'Enter a valid https:// or http:// booking link.']);
$ageMin=($body['age_min_months']??'')===''?null:(int)$body['age_min_months'];$ageMax=($body['age_max_months']??'')===''?null:(int)$body['age_max_months'];
if(($ageMin!==null&&($ageMin<0||$ageMin>216))||($ageMax!==null&&($ageMax<0||$ageMax>216))||($ageMin!==null&&$ageMax!==null&&$ageMax<$ageMin))le_reply(422,['ok'=>false,'error'=>'invalid_age_range','message'=>'Check the age range.']);
$linked=array_values(array_unique(array_filter(array_map('intval',is_array($body['listing_ids']??null)?$body['listing_ids']:[]),fn($v)=>$v>0)));
if($linked){$marks=implode(',',array_fill(0,count($linked),'?'));$q=$db->prepare("SELECT id FROM bh_activities WHERE organiser_id=? AND id IN ($marks)");$q->execute(array_merge([$oid],$linked));if(count($q->fetchAll(PDO::FETCH_COLUMN))!==count($linked))le_reply(403,['ok'=>false,'error'=>'listing_link_not_owned','message'=>'You can only link events to your own listings.']);}
$draftId=filter_var($body['draft_id']??null,FILTER_VALIDATE_INT);
if($draftId){
 try{
  $db->beginTransaction();
  $q=$db->prepare("SELECT id,status FROM bh_directory_events WHERE id=? AND organiser_id=? FOR UPDATE");$q->execute([$draftId,$oid]);$existing=$q->fetch(PDO::FETCH_ASSOC);
  if(!$existing||!in_array($existing['status'],['draft','rejected'],true)){$db->rollBack();le_reply(409,['ok'=>false,'error'=>'event_draft_not_editable','message'=>'This event is no longer an editable draft. Refresh My Directory entries and try again.']);}
  $q=$db->prepare("UPDATE bh_directory_events SET title=?,summary=?,description=?,category=?,age_min_months=?,age_max_months=?,booking_url=?,venue_name=?,address=?,town=?,region=?,postcode=?,status=?,submitted_at=?,reviewed_at=NULL,reviewed_by_user_id=NULL,review_note=NULL WHERE id=? AND organiser_id=?");
  $q->execute([$title,$summary?:null,$description,$category?:null,$ageMin,$ageMax,$booking?:null,$venue,$address?:null,$town,$region?:null,$postcode?:null,$status,$status==='pending'?date('Y-m-d H:i:s'):null,$draftId,$oid]);
  $q=$db->prepare("DELETE FROM bh_directory_event_occurrences WHERE event_id=?");$q->execute([$draftId]);
  $q=$db->prepare("INSERT INTO bh_directory_event_occurrences (event_id,starts_at,ends_at,timezone) VALUES (?,?,?,'Europe/London')");foreach($occurrences as $occurrence)$q->execute([$draftId,$occurrence['start'],$occurrence['end']]);
  $q=$db->prepare("DELETE FROM bh_directory_event_listings WHERE event_id=?");$q->execute([$draftId]);
  if($linked){$q=$db->prepare("INSERT INTO bh_directory_event_listings (event_id,activity_id) VALUES (?,?)");foreach($linked as $id)$q->execute([$draftId,$id]);}
  $db->commit();
  le_reply(200,['ok'=>true,'event_id'=>$draftId,'status'=>$status,'message'=>$status==='pending'?'Your updated event has been sent to Bubba Hub for review.':'Your event draft has been updated.']);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Leader event draft update: '.$e->getMessage());le_reply(500,['ok'=>false,'error'=>'event_draft_update_failed','message'=>'The event draft could not be updated. Please try again.']);}
}
$recordId=filter_var($body['record_id']??null,FILTER_VALIDATE_INT);
if($recordId){
 if(!le_table($db,'bh_directory_record_revisions'))le_reply(503,['ok'=>false,'error'=>'revision_schema_not_installed','message'=>'Published event changes are not enabled until the Directory update is installed.']);
 try{
  $db->beginTransaction();
  $q=$db->prepare("SELECT id FROM bh_directory_events WHERE id=? AND organiser_id=? AND status='published' FOR UPDATE");$q->execute([$recordId,$oid]);
  if(!$q->fetchColumn()){$db->rollBack();le_reply(404,['ok'=>false,'error'=>'published_event_not_found']);}
  $q=$db->prepare("SELECT COUNT(*) FROM bh_directory_record_revisions WHERE section_key='event' AND record_id=? AND status='pending'");$q->execute([$recordId]);
  if((int)$q->fetchColumn()>0){$db->rollBack();le_reply(409,['ok'=>false,'error'=>'revision_already_pending','message'=>'A change request for this event is already awaiting review.']);}
  $revision=['title'=>$title,'summary'=>$summary?:null,'description'=>$description,'category'=>$category?:null,'age_min_months'=>$ageMin,'age_max_months'=>$ageMax,'booking_url'=>$booking?:null,'venue_name'=>$venue,'address'=>$address?:null,'town'=>$town,'region'=>$region?:null,'postcode'=>$postcode,'occurrences'=>$occurrences,'listing_ids'=>$linked];
  $q=$db->prepare("INSERT INTO bh_directory_record_revisions (section_key,record_id,organiser_id,submitted_by_user_id,payload,status) VALUES ('event',?,?,?,?, 'pending')");
  $q->execute([$recordId,$oid,$uid,json_encode($revision,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)]);
  $db->commit();le_reply(200,['ok'=>true,'event_id'=>$recordId,'status'=>'pending_revision','message'=>'Your requested event changes have been sent to Bubba Hub. The published event remains unchanged until approved.']);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Leader event revision: '.$e->getMessage());le_reply(500,['ok'=>false,'error'=>'event_revision_failed','message'=>'The change request could not be saved. Please try again.']);}
}
$base=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));if($base==='')$base='event';$slug=$base.'-'.bin2hex(random_bytes(3));
try{
 $db->beginTransaction();
 $q=$db->prepare("INSERT INTO bh_directory_events (organiser_id,created_by_user_id,title,slug,summary,description,category,age_min_months,age_max_months,booking_url,venue_name,address,town,region,postcode,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
 $q->execute([$oid,$uid,$title,$slug,$summary?:null,$description,$category?:null,$ageMin,$ageMax,$booking?:null,$venue,$address?:null,$town,$region?:null,$postcode?:null,$status,$status==='pending'?date('Y-m-d H:i:s'):null]);
 $eventId=(int)$db->lastInsertId();
 $q=$db->prepare("INSERT INTO bh_directory_event_occurrences (event_id,starts_at,ends_at,timezone) VALUES (?,?,?,'Europe/London')");foreach($occurrences as $occurrence)$q->execute([$eventId,$occurrence['start'],$occurrence['end']]);
 if($linked){$q=$db->prepare("INSERT INTO bh_directory_event_listings (event_id,activity_id) VALUES (?,?)");foreach($linked as $id)$q->execute([$eventId,$id]);}
 $db->commit();
 le_reply(201,['ok'=>true,'event_id'=>$eventId,'status'=>$status,'message'=>$status==='pending'?'Your event has been sent to Bubba Hub for review.':'Your event draft has been saved.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Leader event submit: '.$e->getMessage());le_reply(500,['ok'=>false,'error'=>'event_save_failed','message'=>'The event could not be saved. Please try again.']);}
