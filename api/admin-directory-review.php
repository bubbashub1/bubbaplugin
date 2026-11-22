<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_name('BUBBAHUB_ADMINSESSID');session_start();
function dar(int $c,array $d):never{http_response_code($c);echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
$map=[
'event'=>['bh_directory_events',['title','summary','description','category','age_min_months','age_max_months','booking_url','venue_name','address','town','region','postcode']],
'business'=>['bh_directory_businesses',['title','category','summary','description','town','region','address','postcode','website_url','contact_email','contact_phone']],
'childcare'=>['bh_directory_childcare',['title','category','summary','description','age_min','age_max','town','region','address','postcode','website_url','registration_body','registration_reference','verification_url','verified_on']],
'place'=>['bh_directory_places',['title','category','summary','description','town','region','address','postcode','website_url','facilities']],
'school_club'=>['bh_directory_school_clubs',['title','category','summary','description','age_min','age_max','school_name','collection_arrangements','days_times','holiday_provision','price_details','town','region','address','postcode','website_url','registration_details','verification_url','verified_on']]
];
if(empty($_SESSION['bh_admin_authenticated']))dar(401,['ok'=>false,'error'=>'admin_login_required']);
require_once __DIR__.'/db.php';$db=bh_mysql();
$has=function(string $t)use($db):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$t]);return (int)$q->fetchColumn()>0;};

if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_directory_review_csrf']??=bin2hex(random_bytes(32));$all=[];
 foreach($map as $key=>$spec){if($key==='event'||!$has($spec[0]))continue;$q=$db->query("SELECT id,title,category,summary,description,town,region,status,created_at FROM ".$spec[0]." WHERE status='pending' ORDER BY created_at ASC LIMIT 150");foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['section']=$key;$row['kind']='submission';$all[]=$row;}}
 if($has('bh_directory_record_revisions')){
  foreach($map as $key=>$spec){if(!$has($spec[0]))continue;$q=$db->prepare("SELECT r.id AS revision_id,r.record_id,r.submitted_at,r.payload,t.* FROM bh_directory_record_revisions r JOIN ".$spec[0]." t ON t.id=r.record_id WHERE r.section_key=? AND r.status='pending' ORDER BY r.submitted_at ASC LIMIT 150");$q->execute([$key]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$row['section']=$key;$row['kind']='revision';$row['proposed']=json_decode((string)$row['payload'],true)?:[];unset($row['payload']);if($key==='event'){$z=$db->prepare("SELECT starts_at,ends_at FROM bh_directory_event_occurrences WHERE event_id=? AND occurrence_status='scheduled' ORDER BY starts_at");$z->execute([(int)$row['record_id']]);$row['current_occurrences']=$z->fetchAll(PDO::FETCH_ASSOC);$z=$db->prepare("SELECT activity_id FROM bh_directory_event_listings WHERE event_id=? ORDER BY activity_id");$z->execute([(int)$row['record_id']]);$row['current_listing_ids']=array_map('intval',$z->fetchAll(PDO::FETCH_COLUMN));}$all[]=$row;}}
 }
 usort($all,static fn($a,$b)=>strcmp((string)($a['submitted_at']??$a['created_at']??''),(string)($b['submitted_at']??$b['created_at']??'')));
 dar(200,['ok'=>true,'csrf'=>$_SESSION['bh_directory_review_csrf'],'records'=>$all]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')dar(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)dar(403,['ok'=>false,'error'=>'invalid_origin']);
$b=json_decode((string)file_get_contents('php://input'),true);if(!is_array($b)||empty($_SESSION['bh_directory_review_csrf'])||!is_string($b['csrf']??null)||!hash_equals($_SESSION['bh_directory_review_csrf'],$b['csrf']))dar(403,['ok'=>false,'error'=>'invalid_csrf']);
$section=(string)($b['section']??'');$id=filter_var($b['record_id']??null,FILTER_VALIDATE_INT);$decision=(string)($b['decision']??'');$note=trim((string)($b['note']??''));$kind=(string)($b['kind']??'submission');
if(!isset($map[$section])||!$id||$id<1||!in_array($decision,['approved','rejected','returned_for_edits'],true)||strlen($note)>2000||!in_array($kind,['submission','revision'],true)||($section==='event'&&$kind!=='revision'))dar(422,['ok'=>false,'error'=>'invalid_review']);
$status=$decision==='approved'?'published':($decision==='rejected'?'rejected':'draft');[$table,$fields]=$map[$section];if(!$has($table))dar(503,['ok'=>false,'error'=>'section_schema_not_installed','message'=>'This Directory section is not installed.']);if(!$has('bh_directory_record_reviews'))dar(503,['ok'=>false,'error'=>'review_schema_not_installed','message'=>'Run the Directory workflow setup before reviewing submissions.']);
try{
 $db->beginTransaction();
 if($kind==='revision'){
  if(!$has('bh_directory_record_revisions')){ $db->rollBack();dar(503,['ok'=>false,'error'=>'revision_schema_not_installed']);}
  $q=$db->prepare("SELECT id,record_id,payload,status FROM bh_directory_record_revisions WHERE id=? AND section_key=? FOR UPDATE");$q->execute([$id,$section]);$rev=$q->fetch(PDO::FETCH_ASSOC);
  if(!$rev||$rev['status']!=='pending'){$db->rollBack();dar(409,['ok'=>false,'error'=>'revision_not_reviewable']);}
  if($decision==='approved'){
   $payload=json_decode((string)$rev['payload'],true);if(!is_array($payload))throw new RuntimeException('Invalid revision payload');
   if($section==='event'){
    $q=$db->prepare("SELECT organiser_id,status FROM bh_directory_events WHERE id=? FOR UPDATE");$q->execute([(int)$rev['record_id']]);$eventOwner=$q->fetch(PDO::FETCH_ASSOC);
    if(!$eventOwner||$eventOwner['status']!=='published')throw new RuntimeException('Published event no longer exists');
    $sets=[];$values=[];foreach($fields as $field){if(array_key_exists($field,$payload)){$sets[]=$field."=?";$values[]=$payload[$field];}}
    if(!$sets)throw new RuntimeException('Empty event revision payload');
    $q=$db->prepare("UPDATE bh_directory_events SET ".implode(',',$sets).",reviewed_at=NOW(),reviewed_by_user_id=? WHERE id=? AND status='published'");$q->execute(array_merge($values,[(int)($_SESSION['bh_user_id']??0)?:null,(int)$rev['record_id']]));
    $occurrences=$payload['occurrences']??null;$listingIds=$payload['listing_ids']??null;
    if(!is_array($occurrences)||!count($occurrences)||!is_array($listingIds))throw new RuntimeException('Event dates or links missing from revision');
    $db->prepare("DELETE FROM bh_directory_event_occurrences WHERE event_id=? AND occurrence_status='scheduled'")->execute([(int)$rev['record_id']]);
    $q=$db->prepare("INSERT INTO bh_directory_event_occurrences (event_id,starts_at,ends_at,timezone,occurrence_status) VALUES (?,?,?,'Europe/London','scheduled')");
    foreach($occurrences as $occ){if(!is_array($occ)||empty($occ['start']))throw new RuntimeException('Invalid event occurrence');$q->execute([(int)$rev['record_id'],$occ['start'],$occ['end']??null]);}
    $ids=array_values(array_unique(array_filter(array_map('intval',$listingIds),static fn($v)=>$v>0)));
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$check=$db->prepare("SELECT id FROM bh_activities WHERE organiser_id=? AND id IN ($marks)");$check->execute(array_merge([(int)$eventOwner['organiser_id']],$ids));if(count($check->fetchAll(PDO::FETCH_COLUMN))!==count($ids))throw new RuntimeException('One or more linked listings are no longer owned by this organiser');}
    $db->prepare("DELETE FROM bh_directory_event_listings WHERE event_id=?")->execute([(int)$rev['record_id']]);
    if($ids){$q=$db->prepare("INSERT INTO bh_directory_event_listings (event_id,activity_id) VALUES (?,?)");foreach($ids as $activityId)$q->execute([(int)$rev['record_id'],$activityId]);}
   }else{
    $sets=[];$values=[];foreach($fields as $field){if(!array_key_exists($field,$payload))continue;$sets[]=$field."=?";$values[]=$payload[$field];}
    if(!$sets)throw new RuntimeException('Empty revision payload');
    $q=$db->prepare("UPDATE ".$table." SET ".implode(',',$sets)." WHERE id=? AND status='published'");$q->execute(array_merge($values,[(int)$rev['record_id']]));
    if($q->rowCount()<1){$check=$db->prepare("SELECT status FROM ".$table." WHERE id=?");$check->execute([(int)$rev['record_id']]);if($check->fetchColumn()!=='published')throw new RuntimeException('Published record no longer exists');}
   }
  }
  $q=$db->prepare("UPDATE bh_directory_record_revisions SET status=?,reviewed_at=NOW(),reviewed_by_user_id=?,review_note=? WHERE id=?");$q->execute([$decision,(int)($_SESSION['bh_user_id']??0)?:null,$note?:null,(int)$rev['id']]);
  $q=$db->prepare("INSERT INTO bh_directory_record_reviews (section_key,record_id,reviewer_user_id,decision,note) VALUES (?,?,?,?,?)");$q->execute([$section,(int)$rev['record_id'],(int)($_SESSION['bh_user_id']??0)?:null,'revision_'.$decision,$note?:null]);
 }else{
  $q=$db->prepare("SELECT id,status FROM ".$table." WHERE id=? FOR UPDATE");$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row||$row['status']!=='pending'){$db->rollBack();dar(409,['ok'=>false,'error'=>'record_not_reviewable']);}
  $q=$db->prepare("UPDATE ".$table." SET status=?,reviewed_at=NOW(),reviewed_by_user_id=?,review_note=? WHERE id=?");$q->execute([$status,(int)($_SESSION['bh_user_id']??0)?:null,$note?:null,$id]);
  $q=$db->prepare("INSERT INTO bh_directory_record_reviews (section_key,record_id,reviewer_user_id,decision,note) VALUES (?,?,?,?,?)");$q->execute([$section,$id,(int)($_SESSION['bh_user_id']??0)?:null,$decision,$note?:null]);
 }
 $db->commit();dar(200,['ok'=>true,'status'=>$kind==='revision'?$decision:$status,'message'=>$kind==='revision'?($decision==='approved'?'Changes approved and published.':'Revision decision saved; the live entry has not been changed.'):'Directory review saved.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Directory review: '.$e->getMessage());dar(500,['ok'=>false,'error'=>'review_failed']);}
