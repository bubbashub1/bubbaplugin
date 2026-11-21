<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_lm_reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if(empty($_SESSION['bh_admin_authenticated'])) bh_lm_reply(401,['ok'=>false,'error'=>'Admin login required.']);
try {
 require_once __DIR__.'/db.php'; $db=bh_mysql();
 foreach(['bh_listing_monitor_drafts','bh_listing_check_findings','bh_activities'] as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);if(!$q->fetchColumn())bh_lm_reply(503,['ok'=>false,'error'=>'Listing Monitor database migration is required.']);}
 $_SESSION['bh_listing_monitor_csrf']??=bin2hex(random_bytes(32));
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
  if(isset($_GET['draft_id'])){
   $draftId=max(0,(int)$_GET['draft_id']);
   $q=$db->prepare('SELECT * FROM bh_listing_monitor_drafts WHERE id=?');$q->execute([$draftId]);$draft=$q->fetch(PDO::FETCH_ASSOC);
   if(!$draft) bh_lm_reply(404,['ok'=>false,'error'=>'Review draft not found.']);
   $q=$db->prepare('SELECT * FROM bh_activities WHERE id=?');$q->execute([(int)$draft['activity_id']]);$activity=$q->fetch(PDO::FETCH_ASSOC);
   if(!$activity) bh_lm_reply(404,['ok'=>false,'error'=>'The activity for this draft could not be found.']);
   $draft['original_snapshot']=json_decode((string)$draft['original_snapshot'],true)?:[];
   $draft['proposed_changes']=json_decode((string)$draft['proposed_changes'],true)?:[];
   bh_lm_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_monitor_csrf'],'draft'=>$draft,'activity'=>$activity]);
  }
  if(isset($_GET['drafts'])){
   $q=$db->query("SELECT d.id,d.finding_id,d.activity_id,d.status,d.created_at,d.updated_at,a.title FROM bh_listing_monitor_drafts d INNER JOIN bh_activities a ON a.id=d.activity_id ORDER BY d.updated_at DESC LIMIT 100");
   bh_lm_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_monitor_csrf'],'drafts'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
  }
  $findingId=max(0,(int)($_GET['finding_id']??0));
  if($findingId<1) bh_lm_reply(422,['ok'=>false,'error'=>'Choose a finding to review.']);
  $q=$db->prepare('SELECT id,activity_id,candidate_json,finding_type,confidence,source_url,checked_at FROM bh_listing_check_findings WHERE id=?');$q->execute([$findingId]);$finding=$q->fetch(PDO::FETCH_ASSOC);
  if(!$finding) bh_lm_reply(404,['ok'=>false,'error'=>'Finding not found.']);
  if(empty($finding['activity_id'])) bh_lm_reply(422,['ok'=>false,'error'=>'This is a new discovery, not an existing activity change. New-listing review will be added separately.']);
  $q=$db->prepare('SELECT * FROM bh_activities WHERE id=?');$q->execute([(int)$finding['activity_id']]);$activity=$q->fetch(PDO::FETCH_ASSOC);
  if(!$activity) bh_lm_reply(404,['ok'=>false,'error'=>'The activity linked to this finding no longer exists.']);
  $finding['candidate']=json_decode((string)$finding['candidate_json'],true)?:[];unset($finding['candidate_json']);
  $safeFields=['title','description','short_description','website','booking_url','facebook_url','instagram_url','contact_email','phone','town','region','postcode','category','age_range','price','price_details','opening_times','session_information'];
  $editable=[];foreach($safeFields as $field)if(array_key_exists($field,$activity))$editable[$field]=['current'=>$activity[$field],'suggested'=>$finding['candidate'][$field]??null];
  bh_lm_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_monitor_csrf'],'finding'=>$finding,'activity'=>['id'=>$activity['id'],'title'=>$activity['title']??'Activity'],'fields'=>$editable]);
 }
 if($method!=='POST') bh_lm_reply(405,['ok'=>false,'error'=>'Method not allowed']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=(string)($_SERVER['HTTP_HOST']??'');
 if($origin!==''&&(!filter_var($origin,FILTER_VALIDATE_URL)||strcasecmp((string)parse_url($origin,PHP_URL_HOST),preg_replace('/:\\d+$/','',$host))!==0)) bh_lm_reply(403,['ok'=>false,'error'=>'Cross-origin request blocked']);
 if(in_array((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''),['cross-site'],true)) bh_lm_reply(403,['ok'=>false,'error'=>'Cross-site request blocked']);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>65536) bh_lm_reply(413,['ok'=>false,'error'=>'Request too large']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)||!hash_equals((string)$_SESSION['bh_listing_monitor_csrf'],(string)($input['csrf']??''))) bh_lm_reply(403,['ok'=>false,'error'=>'Security check failed. Refresh and try again.']);
 $action=(string)($input['action']??'');
 if($action==='approve_draft'){
  $draftId=max(0,(int)($input['draft_id']??0));
  if($draftId<1||empty($input['confirm'])) bh_lm_reply(422,['ok'=>false,'error'=>'Explicit approval confirmation is required.']);
  $allowed=['title','description','short_description','website','booking_url','facebook_url','instagram_url','contact_email','phone','town','region','postcode','category','age_range','price','price_details','opening_times','session_information'];
  try{
   $db->beginTransaction();
   $q=$db->prepare("SELECT * FROM bh_listing_monitor_drafts WHERE id=? FOR UPDATE");$q->execute([$draftId]);$draft=$q->fetch(PDO::FETCH_ASSOC);
   if(!$draft||$draft['status']!=='draft') throw new RuntimeException('This draft is no longer awaiting approval.');
   $before=json_decode((string)$draft['original_snapshot'],true);$changes=json_decode((string)$draft['proposed_changes'],true);
   if(!is_array($before)||!is_array($changes)||!$changes) throw new RuntimeException('The saved draft is incomplete.');
   $q=$db->prepare('SELECT * FROM bh_activities WHERE id=? FOR UPDATE');$q->execute([(int)$draft['activity_id']]);$activity=$q->fetch(PDO::FETCH_ASSOC);
   if(!$activity) throw new RuntimeException('The activity could not be found.');
   foreach($changes as $field=>$value){
    if(!in_array($field,$allowed,true)||!array_key_exists($field,$activity)||!array_key_exists($field,$before)) throw new RuntimeException('The draft contains a field that cannot be approved.');
    if((string)($activity[$field]??'')!==(string)($before[$field]??'')) throw new RuntimeException('This activity has changed since the draft was created. Recheck the latest details before approving.');
    if(!is_scalar($value)&&$value!==null) throw new RuntimeException('The draft contains an invalid field value.');
    $text=$value===null?'':trim((string)$value);
    if(strlen($text)>12000) throw new RuntimeException('A draft field is too long.');
    if(str_ends_with($field,'_url')||in_array($field,['website','booking_url'],true)){
     if($text!==''&&(!filter_var($text,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($text,PHP_URL_SCHEME)),['http','https'],true))) throw new RuntimeException('A website or social link is not a valid HTTP or HTTPS URL.');
    }
    $changes[$field]=$text;
   }
   $sets=[];$params=[];foreach($changes as $field=>$value){$sets[]='`'.$field.'`=?';$params[]=$value;}
   $params[]=(int)$draft['activity_id'];$q=$db->prepare('UPDATE bh_activities SET '.implode(',',$sets).' WHERE id=?');$q->execute($params);
   $actor=trim((string)($_SESSION['bh_admin_username']??'admin'));if($actor==='')$actor='admin';
   $q=$db->prepare('INSERT INTO bh_listing_monitor_audit(draft_id,activity_id,admin_username,before_values,approved_values,source_url) VALUES(?,?,?,?,?,?)');
   $q->execute([$draftId,(int)$draft['activity_id'],$actor,json_encode($before,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),json_encode($changes,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),$draft['source_url']]);
   $q=$db->prepare("UPDATE bh_listing_monitor_drafts SET status='approved',reviewed_at=NOW(),reviewed_by=? WHERE id=?");$q->execute([$actor,$draftId]);
   $db->commit();
   bh_lm_reply(200,['ok'=>true,'message'=>'Approved changes have been saved to the activity. The approval has been recorded.']);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)bh_lm_reply(409,['ok'=>false,'error'=>$e->getMessage()]);throw $e;}
 }
 if($action!=='save_draft') bh_lm_reply(422,['ok'=>false,'error'=>'Unknown review action.']);
 $findingId=max(0,(int)($input['finding_id']??0));$changes=$input['changes']??[];
 if($findingId<1||!is_array($changes)||!$changes) bh_lm_reply(422,['ok'=>false,'error'=>'Select at least one suggested change first.']);
 $q=$db->prepare('SELECT id,activity_id,source_url,candidate_json FROM bh_listing_check_findings WHERE id=?');$q->execute([$findingId]);$finding=$q->fetch(PDO::FETCH_ASSOC);
 if(!$finding||empty($finding['activity_id'])) bh_lm_reply(404,['ok'=>false,'error'=>'This finding is not linked to an existing activity.']);
 $q=$db->prepare('SELECT * FROM bh_activities WHERE id=?');$q->execute([(int)$finding['activity_id']]);$activity=$q->fetch(PDO::FETCH_ASSOC);
 if(!$activity) bh_lm_reply(404,['ok'=>false,'error'=>'Activity not found.']);
 $allowed=['title','description','short_description','website','booking_url','facebook_url','instagram_url','contact_email','phone','town','region','postcode','category','age_range','price','price_details','opening_times','session_information'];
 $snapshot=[];$proposed=[];
 foreach($changes as $field=>$value){
  if(!in_array($field,$allowed,true)||!array_key_exists($field,$activity)) bh_lm_reply(422,['ok'=>false,'error'=>'One or more selected fields cannot be edited here.']);
  if(!is_scalar($value)&&$value!==null) bh_lm_reply(422,['ok'=>false,'error'=>'Suggested values must be plain text.']);
  $text=$value===null?'':trim((string)$value);
  if(strlen($text)>12000) bh_lm_reply(422,['ok'=>false,'error'=>'A suggested field is too long.']);
  if($field==='website'||$field==='booking_url'||str_ends_with($field,'_url')){
   if($text!==''&&(!filter_var($text,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($text,PHP_URL_SCHEME)),['http','https'],true))) bh_lm_reply(422,['ok'=>false,'error'=>'Website and social links must be valid HTTP or HTTPS URLs.']);
  }
  $snapshot[$field]=$activity[$field];$proposed[$field]=$text;
 }
 $candidate=json_decode((string)$finding['candidate_json'],true)?:[];
 $evidence=trim((string)($candidate['snippet']??$candidate['evidence_note']??''));
 $encodedSnapshot=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 $encodedChanges=json_encode($proposed,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 $q=$db->prepare("SELECT id FROM bh_listing_monitor_drafts WHERE finding_id=? AND status='draft' ORDER BY id DESC LIMIT 1");$q->execute([$findingId]);$draftId=(int)($q->fetchColumn()?:0);
 if($draftId){
  $q=$db->prepare('UPDATE bh_listing_monitor_drafts SET original_snapshot=?,proposed_changes=?,source_url=?,source_evidence=? WHERE id=?');
  $q->execute([$encodedSnapshot,$encodedChanges,$finding['source_url'],$evidence,$draftId]);
 }else{
  $q=$db->prepare("INSERT INTO bh_listing_monitor_drafts(finding_id,activity_id,status,original_snapshot,proposed_changes,source_url,source_evidence) VALUES(?,?,'draft',?,?,?,?)");
  $q->execute([$findingId,(int)$finding['activity_id'],$encodedSnapshot,$encodedChanges,$finding['source_url'],$evidence]);$draftId=(int)$db->lastInsertId();
 }
 bh_lm_reply(200,['ok'=>true,'draft_id'=>$draftId,'message'=>'Saved to your review drafts. The public activity has not been changed.']);
} catch(Throwable $e){error_log('Listing Monitor review drafts: '.$e->getMessage());bh_lm_reply(500,['ok'=>false,'error'=>'The review draft could not be saved.']);}
