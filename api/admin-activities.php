<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'); session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']); ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_admin_response(int $status,array $data): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
if(empty($_SESSION['bh_admin_authenticated']))bh_admin_response(401,['ok'=>false,'error'=>'Admin login required.']);
try{require __DIR__.'/db.php';$db=bh_mysql();
 $_SESSION['bh_listing_csrf']??=bin2hex(random_bytes(32));
 // Activity-specific social URLs: preserve independently of shared organiser details.
 $socialCols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);
 foreach(['facebook_url','instagram_url'] as $socialCol){if(!in_array($socialCol,$socialCols,true)){$db->exec("ALTER TABLE bh_activities ADD COLUMN `".$socialCol."` VARCHAR(2048) NULL DEFAULT NULL");}}

 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='categories'){
  $names=[];try{$q=$db->query("SELECT name FROM bh_categories ORDER BY name");$names=$q->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $ignored){}
  $q=$db->query("SELECT DISTINCT category FROM bh_activities WHERE category IS NOT NULL AND category<>''");
  foreach($q->fetchAll(PDO::FETCH_COLUMN) as $entry){foreach(explode(',',(string)$entry) as $name){$name=trim($name);if($name!=='')$names[]=$name;}}
  $unique=[];foreach($names as $name){$key=mb_strtolower(trim((string)$name));if($key!=='')$unique[$key]=trim((string)$name);}
  natcasesort($unique);bh_admin_response(200,['ok'=>true,'data'=>array_values($unique)]);
 }
 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='registered_venues'){
  // Read-only directory: a venue row belongs to its activity. Never reassign its ID.
  $q=$db->query("SELECT id,venue_name,address,town,region,postcode,latitude,longitude FROM bh_venues WHERE venue_name IS NOT NULL AND TRIM(venue_name)<>'' ORDER BY venue_name,town,postcode,id LIMIT 5000");
  bh_admin_response(200,['ok'=>true,'data'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='organisations'){ $q=$db->query("SELECT id,organisation_name FROM bh_organisers WHERE organisation_name IS NOT NULL AND organisation_name<>'' ORDER BY organisation_name,id LIMIT 5000");bh_admin_response(200,['ok'=>true,'data'=>$q->fetchAll(PDO::FETCH_ASSOC)]); }
 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='leaders'){
  $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
  $join=$hasUserId?'u.id=o.user_id':'u.email=o.email';
  $organiserStatusFilter=$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'status'")->fetch()?" AND o.status<>'suspended'":"";
  $q=$db->query("SELECT o.id,o.organisation_name,u.email FROM bh_organisers o JOIN bh_users u ON ".$join." WHERE u.role='leader' AND u.status='active'".$organiserStatusFilter." ORDER BY o.organisation_name,o.id");
  bh_admin_response(200,['ok'=>true,'data'=>$q->fetchAll(),'csrf'=>$_SESSION['bh_listing_csrf']]);
 }
 $venueBookingColumn=false;try{$venueBookingColumn=(bool)$db->query("SHOW COLUMNS FROM bh_venues LIKE 'booking_url'")->fetch();if(!$venueBookingColumn&&$_SERVER['REQUEST_METHOD']==='POST'){$db->exec("ALTER TABLE bh_venues ADD COLUMN booking_url VARCHAR(2048) NULL DEFAULT NULL");$venueBookingColumn=true;}}catch(Throwable $ignored){}
 $accessibilityColumn=false;
 try{$ac=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='accessibility'");$accessibilityColumn=((int)$ac->fetchColumn())>0;}catch(Throwable $ignored){}
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $id=isset($_GET['id'])?(int)$_GET['id']:0;
  if($id>0){
   $countyColumn=false;
   try{$cc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$countyColumn=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
   $countySelect=$countyColumn?'a.county':'NULL AS county';
   $q=$db->prepare("SELECT a.*,$countySelect,o.organisation_name,o.email,o.phone,o.website,v.id venue_id,v.venue_name,v.address,v.town,v.region,v.postcode,v.latitude,v.longitude FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_venues v ON v.activity_id=a.id WHERE a.id=? ORDER BY v.id LIMIT 1");$q->execute([$id]);$a=$q->fetch();if(!$a)bh_admin_response(404,['ok'=>false,'error'=>'Activity not found.']);
   $q=$db->prepare("SELECT id,venue_name,address,town,region,postcode,latitude,longitude FROM bh_venues WHERE activity_id=? ORDER BY id");$q->execute([$id]);$a['venues']=$q->fetchAll();$galleryTable=$db->query("SHOW TABLES LIKE 'bh_activity_gallery'")->fetchColumn();if($galleryTable){$g=$db->prepare("SELECT position,image_url FROM bh_activity_gallery WHERE activity_id=? ORDER BY position");$g->execute([$id]);foreach($g->fetchAll(PDO::FETCH_ASSOC) as $image)$a['image_'.(int)$image['position']]=$image['image_url'];}$galleryCols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);$galleryFields=array_values(array_filter(array_map(static fn($n)=>'image_'.$n,range(1,10)),static fn($name)=>in_array($name,$galleryCols,true)));if($galleryFields){$galleryQuery=$db->prepare("SELECT ".implode(',',array_map(static fn($name)=>'`'.$name.'`',$galleryFields))." FROM bh_activities WHERE id=?");$galleryQuery->execute([$id]);foreach((array)$galleryQuery->fetch(PDO::FETCH_ASSOC) as $field=>$value)if(!isset($a[$field])||$a[$field]==='')$a[$field]=$value;}$rawAccessibility=$a['accessibility']??null;$a['accessibility']=[];if($accessibilityColumn&&$rawAccessibility!==null){$decoded=json_decode((string)$rawAccessibility,true);$a['accessibility']=is_array($decoded)?$decoded:[];}$a['sessions']=[];foreach($a['venues'] as $vi=>$venue){$sessionCols=$db->query("SHOW COLUMNS FROM bh_sessions")->fetchAll(PDO::FETCH_COLUMN);$sessionNameCol=in_array('class_name',$sessionCols,true)?'class_name':(in_array('title',$sessionCols,true)?'title':null);$nameSelect=$sessionNameCol?",`".$sessionNameCol."` AS class_name":",'' AS class_name";$sq=$db->prepare("SELECT id,venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date".$nameSelect." FROM bh_sessions WHERE venue_id=? ORDER BY day_of_week,start_time");$sq->execute([(int)$venue['id']]);foreach($sq->fetchAll() as $session){$session['venue_index']=$vi;$a['sessions'][]=$session;}}$a['id']=(int)$a['id'];$a['price_from']=$a['price_from']!==null?(float)$a['price_from']:null;$a['latitude']=$a['latitude']!==null?(float)$a['latitude']:null;$a['longitude']=$a['longitude']!==null?(float)$a['longitude']:null;
   bh_admin_response(200,['ok'=>true,'data'=>$a,'csrf'=>$_SESSION['bh_listing_csrf']]);
  }
  $countyColumn=false;
  try{$cc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$countyColumn=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
  $countySelect=$countyColumn?'a.county':'NULL AS county';
  $tagsSelect=$db->query("SHOW COLUMNS FROM bh_activities LIKE 'tags'")->fetch()?'MAX(a.tags) AS tags':'NULL AS tags';
  $stmt=$db->query("SELECT a.id,a.title,a.category,$tagsSelect,a.age_range,$countySelect,a.price_from,a.status,o.id organiser_id,COALESCE(o.organisation_name,'') AS organisation_name,MIN(v.town) AS town,MIN(v.region) AS region,COUNT(DISTINCT v.id) venue_count,(SELECT GROUP_CONCAT(DISTINCT CONCAT(CASE s.day_of_week WHEN 1 THEN 'Mon' WHEN 2 THEN 'Tue' WHEN 3 THEN 'Wed' WHEN 4 THEN 'Thu' WHEN 5 THEN 'Fri' WHEN 6 THEN 'Sat' WHEN 7 THEN 'Sun' END,' ',LEFT(s.start_time,5)) ORDER BY s.day_of_week,s.start_time SEPARATOR ', ') FROM bh_sessions s INNER JOIN bh_venues sv ON sv.id=s.venue_id WHERE sv.activity_id=a.id) AS session_summary FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_venues v ON v.activity_id=a.id WHERE 1=1 GROUP BY a.id,a.title,a.category,a.age_range,a.price_from,a.status,o.id,o.organisation_name".($countyColumn?",a.county":"")." ORDER BY a.updated_at DESC,a.id DESC");bh_admin_response(200,['ok'=>true,'data'=>$stmt->fetchAll(),'csrf'=>$_SESSION['bh_listing_csrf']]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')bh_admin_response(405,['ok'=>false,'error'=>'GET or POST required.']);
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))bh_admin_response(400,['ok'=>false,'error'=>'Invalid JSON.']);
 if(($input['action']??'')==='bulk_update'){
  if(!hash_equals($_SESSION['bh_listing_csrf'],(string)($input['csrf']??'')))bh_admin_response(403,['ok'=>false,'error'=>'Please refresh the page.']);
  $ids=array_values(array_unique(array_map('intval',(array)($input['ids']??[]))));
  if(!$ids||count($ids)>500||min($ids)<1)bh_admin_response(422,['ok'=>false,'error'=>'Select between 1 and 500 listings.']);
  $changes=$input['changes']??null;
  if(!is_array($changes)||!$changes||array_diff(array_keys($changes),['status','category','tags']))bh_admin_response(422,['ok'=>false,'error'=>'Unsupported change.']);
  $cols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);
  $sets=[];$values=[];
  foreach($changes as $field=>$value){
   if(!in_array($field,$cols,true))bh_admin_response(422,['ok'=>false,'error'=>'This database does not support the '.$field.' field.']);
   if(!is_string($value))bh_admin_response(422,['ok'=>false,'error'=>'Invalid '.$field.' value.']);
   $value=trim($value);
   if($field==='status'&&!in_array($value,['draft','published'],true))bh_admin_response(422,['ok'=>false,'error'=>'Invalid listing status.']);
   if($field==='category'&&($value===''||mb_strlen($value)>255))bh_admin_response(422,['ok'=>false,'error'=>'Enter a category of at most 255 characters.']);
   if($field==='tags'&&mb_strlen($value)>1000)bh_admin_response(422,['ok'=>false,'error'=>'Tags are too long.']);
   $sets[]="`".$field."`=?";$values[]=$value;
  }
  $placeholders=implode(',',array_fill(0,count($ids),'?'));
  $db->beginTransaction();
  try{
   $sql="UPDATE bh_activities SET ".implode(',',$sets)." WHERE id IN (".$placeholders.") AND status<>'archived'";
   $stmt=$db->prepare($sql);$stmt->execute([...$values,...$ids]);$updated=$stmt->rowCount();
   $db->commit();bh_admin_response(200,['ok'=>true,'updated'=>$updated]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 $id=(int)($input['id']??0);
 $listingAction=$input['action']??'';
 if($listingAction==='remove_saved_venue'){
  if(!hash_equals($_SESSION['bh_listing_csrf'],(string)($input['csrf']??'')))bh_admin_response(403,['ok'=>false,'error'=>'Refresh the editor and try again.']);
  $venueId=(int)($input['venue_id']??0);$targetId=(int)($input['target_venue_id']??0);
  if($id<1||$venueId<1||$targetId<1||$venueId===$targetId)bh_admin_response(422,['ok'=>false,'error'=>'Choose a different saved location to keep.']);
  $db->beginTransaction();
  try{
   $q=$db->prepare("SELECT id FROM bh_venues WHERE activity_id=? ORDER BY id FOR UPDATE");$q->execute([$id]);$venueIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
   if(count($venueIds)<2||!in_array($venueId,$venueIds,true)||!in_array($targetId,$venueIds,true))throw new RuntimeException('Invalid venue selection. Keep at least one location.');
   $q=$db->prepare("SELECT COUNT(*) FROM bh_sessions WHERE venue_id=?");$q->execute([$venueId]);$moved=(int)$q->fetchColumn();
   $db->prepare("UPDATE bh_sessions SET venue_id=? WHERE venue_id=?")->execute([$targetId,$venueId]);
   $db->prepare("DELETE FROM bh_venues WHERE id=? AND activity_id=?")->execute([$venueId,$id]);
   $db->commit();bh_admin_response(200,['ok'=>true,'sessions_reassigned'=>$moved]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 if($listingAction==='consolidate_duplicate_venues'){
  if(!hash_equals($_SESSION['bh_listing_csrf'],(string)($input['csrf']??'')))bh_admin_response(403,['ok'=>false,'error'=>'Refresh and try again.']);
  if($id<1)bh_admin_response(422,['ok'=>false,'error'=>'Listing ID required.']);
  $db->beginTransaction();
  try{
   $q=$db->prepare("SELECT id,venue_name,postcode FROM bh_venues WHERE activity_id=? ORDER BY id FOR UPDATE");$q->execute([$id]);$venues=$q->fetchAll(PDO::FETCH_ASSOC);
   $keepers=[];$removed=0;$moved=0;
   foreach($venues as $venue){
    $name=mb_strtolower(trim((string)$venue['venue_name']));$postcodeKey=strtoupper(preg_replace('/\\s+/', '',(string)$venue['postcode']));
    if($name===''||$postcodeKey==='')continue; // Never merge uncertain locations.
    $key=$name.'|'.$postcodeKey;$vid=(int)$venue['id'];
    if(!isset($keepers[$key])){$keepers[$key]=$vid;continue;}
    $target=$keepers[$key];
    $count=$db->prepare("SELECT COUNT(*) FROM bh_sessions WHERE venue_id=?");$count->execute([$vid]);$moved+=(int)$count->fetchColumn();
    $db->prepare("UPDATE bh_sessions SET venue_id=? WHERE venue_id=?")->execute([$target,$vid]);
    $db->prepare("DELETE FROM bh_venues WHERE id=? AND activity_id=?")->execute([$vid,$id]);$removed++;
   }
   $db->commit();bh_admin_response(200,['ok'=>true,'removed'=>$removed,'sessions_reassigned'=>$moved]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }

 if(in_array($listingAction,['delete','assign_leader','restore'],true)){
  if(!hash_equals($_SESSION['bh_listing_csrf'],(string)($input['csrf']??'')))bh_admin_response(403,['ok'=>false,'error'=>'Please refresh the page and try again.']);
  if($id<1)bh_admin_response(422,['ok'=>false,'error'=>'Choose a listing.']);
 $db->beginTransaction();
  try{
   $q=$db->prepare("SELECT id FROM bh_activities WHERE id=? FOR UPDATE");$q->execute([$id]);
   if(!$q->fetch()){$db->rollBack();bh_admin_response(404,['ok'=>false,'error'=>'Listing not found.']);}
   if($listingAction==='restore'){
    $db->prepare("UPDATE bh_activities SET status='published' WHERE id=? AND status='archived'")->execute([$id]);
   }elseif($listingAction==='delete'){
    $db->prepare("UPDATE bh_activities SET status='archived' WHERE id=?")->execute([$id]);
   }else{
    $organiserId=(int)($input['organiser_id']??0);
    $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
    $join=$hasUserId?'u.id=o.user_id':'u.email=o.email';
    $organiserStatusFilter=$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'status'")->fetch()?" AND o.status<>'suspended'":"";
    $q=$db->prepare("SELECT o.id FROM bh_organisers o JOIN bh_users u ON ".$join." WHERE o.id=? AND u.role='leader' AND u.status='active'".$organiserStatusFilter." LIMIT 1");$q->execute([$organiserId]);
    if(!$q->fetch()){$db->rollBack();bh_admin_response(422,['ok'=>false,'error'=>'Choose an active registered leader organisation.']);}
    $db->prepare("UPDATE bh_activities SET organiser_id=? WHERE id=?")->execute([$organiserId,$id]);
   }
   // Persist booking sources for scheduled six-week checks. An empty submission
  // does not remove previously saved sources.
  $sourceUrls=(array)($input['import_source_urls']??[]);
  if($sourceUrls){
   $saveSource=$db->prepare("INSERT INTO bh_import_sources (activity_id,source_url,url_hash,next_check_at) VALUES (?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 42 DAY)) ON DUPLICATE KEY UPDATE source_url=VALUES(source_url)");
   foreach(array_unique($sourceUrls) as $sourceUrl){
    $sourceUrl=trim((string)$sourceUrl);
    $parts=parse_url($sourceUrl);
    if(!$parts||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||strlen($sourceUrl)>2048)continue;
    $saveSource->execute([$id,$sourceUrl,hash('sha256',$sourceUrl)]);
   }
  }
  $db->commit();bh_admin_response(200,['ok'=>true]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }

 $activityCountyColumn=false;
 try{$cc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$activityCountyColumn=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}// Decode HTML entities in plain-text listing fields, not rich HTML or URLs.
 $decodePlain=static fn($value)=>trim(html_entity_decode((string)$value,ENT_QUOTES|ENT_HTML5,'UTF-8'));
 foreach(['title','organisation_name','venue_name','address','town','region','postcode','category','age_range','county'] as $field){if(isset($input[$field])&&is_string($input[$field]))$input[$field]=$decodePlain($input[$field]);}
 $title=trim((string)($input['title']??''));$description=trim((string)($input['description']??''));$category=trim((string)($input['category']??''));$ageRange=trim((string)($input['age_range']??''));$county=trim((string)($input['county']??''));if($county!==''&&!in_array($county,['Devon','Cornwall','Plymouth','Torbay'],true))$county='';$priceFrom=($input['price_from']??'')===''?null:(float)$input['price_from'];$bookingUrl=trim((string)($input['booking_url']??''));$organisationName=trim((string)($input['organisation_name']??''));$email=trim((string)($input['email']??''));$phone=trim((string)($input['phone']??''));$website=trim((string)($input['website']??''));$facebookUrl=trim((string)($input['facebook_url']??''));$instagramUrl=trim((string)($input['instagram_url']??''));foreach([$facebookUrl,$instagramUrl] as $socialUrl){if($socialUrl!==''&&(strlen($socialUrl)>2048||!filter_var($socialUrl,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($socialUrl,PHP_URL_SCHEME)),['http','https'],true)))bh_admin_response(422,['ok'=>false,'error'=>'Enter valid HTTPS or HTTP social media URLs.']);}$venueName=trim((string)($input['venue_name']??''));$address=trim((string)($input['address']??''));$town=trim((string)($input['town']??''));$region=trim((string)($input['region']??''));$postcode=trim((string)($input['postcode']??''));$latitude=($input['latitude']??'')===''?null:(float)$input['latitude'];$longitude=($input['longitude']??'')===''?null:(float)$input['longitude'];$imagePath=trim((string)($input['image_path']??''));$status=in_array(($input['status']??'published'),['published','draft'],true)?$input['status']:'published';$accessibility=json_encode(array_values(array_unique(array_filter((array)($input['accessibility']??[]),'is_string'))),JSON_UNESCAPED_SLASHES);
 if($title===''||$organisationName===''||$venueName===''||$town===''||$category==='')bh_admin_response(422,['ok'=>false,'error'=>'Title, organisation, category, venue and town are required.']);
 $tags=implode(', ',array_slice(array_unique(array_filter(array_map('trim',explode(',',(string)($input['tags']??''))))),0,20));
 $categoryNames=[];foreach(explode(',',$category) as $name){$name=trim(preg_replace('/\\s+/u',' ',$name));if($name==='')continue;$key=mb_strtolower($name);$categoryNames[$key]=$name;}
 $category=implode(', ',array_values($categoryNames));
 if(count($categoryNames)>30)bh_admin_response(422,['ok'=>false,'error'=>'Too many categories.']);
 $ageMin=isset($input['age_min_months'])&&$input['age_min_months']!==''?(int)$input['age_min_months']:null;
 $ageMax=isset($input['age_max_months'])&&$input['age_max_months']!==''?(int)$input['age_max_months']:null;
 $free=!empty($input['price_free']);$unit=(string)($input['pricing_unit']??'per_session');
 if(!in_array($unit,['per_session','per_block','per_family'],true))$unit='per_session';
 if($free)$priceFrom=0.0;
 $blockWeeks=null;
 if(!$free&&$unit==='per_block'){$rawWeeks=$input['block_length_weeks']??null;if(!is_numeric($rawWeeks)||(int)$rawWeeks!=(float)$rawWeeks||(int)$rawWeeks<1||(int)$rawWeeks>104)bh_admin_response(422,['ok'=>false,'error'=>'Enter a block length between 1 and 104 weeks.']);$blockWeeks=(int)$rawWeeks;}
 // Keep this field persistent for new and existing listings.
 $blockCol=$db->query("SHOW COLUMNS FROM bh_activities LIKE 'block_length_weeks'")->fetch();
 if(!$blockCol){$db->exec("ALTER TABLE bh_activities ADD COLUMN block_length_weeks SMALLINT UNSIGNED NULL DEFAULT NULL");}
 $optional=['age_min_months'=>$ageMin,'age_max_months'=>$ageMax,'price_free'=>$free?1:0,'price_per_family'=>!$free&&$unit==='per_family'?1:0,'price_per_session'=>!$free&&$unit==='per_session'?1:0,'pricing_unit'=>$unit,'block_length_weeks'=>$blockWeeks];
 $cols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);
 for($n=1;$n<=10;$n++){ $field='image_'.$n;if(in_array($field,$cols,true)){ $url=trim((string)($input[$field]??''));if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))bh_admin_response(422,['ok'=>false,'error'=>'Invalid image URL for image '.$n]);$optional[$field]=$url; }}
 for($n=1;$n<=10;$n++){ $url=trim((string)($input['image_'.$n]??''));if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)||strlen($url)>2048))bh_admin_response(422,['ok'=>false,'error'=>'Image '.$n.' needs a valid HTTP(S) URL of at most 2048 characters.']);}
 $optional=array_intersect_key($optional,array_flip($cols));
  $db->exec("CREATE TABLE IF NOT EXISTS bh_activity_gallery (activity_id BIGINT UNSIGNED NOT NULL,position TINYINT UNSIGNED NOT NULL,image_url VARCHAR(2048) NOT NULL,PRIMARY KEY(activity_id,position)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $db->exec("CREATE TABLE IF NOT EXISTS bh_import_sources (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,activity_id BIGINT UNSIGNED NOT NULL,source_url VARCHAR(2048) NOT NULL,url_hash CHAR(64) NOT NULL,last_checked_at DATETIME NULL,next_check_at DATETIME NOT NULL,check_status VARCHAR(32) NOT NULL DEFAULT 'pending',last_error TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY unique_source (activity_id,url_hash),KEY due_sources (next_check_at,check_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 // Preserve per-session names on older staging databases lacking the column.
 $sessionSchema=$db->query("SHOW COLUMNS FROM bh_sessions")->fetchAll(PDO::FETCH_COLUMN);
 if(!in_array('class_name',$sessionSchema,true)){
  $db->exec("ALTER TABLE bh_sessions ADD COLUMN class_name VARCHAR(160) NULL DEFAULT NULL");
 }

 // Reject duplicate locations before starting a database transaction (new and edited listings).
 $venueKeys=[];
 foreach(array_merge([['venue_name'=>$venueName,'postcode'=>$postcode]],(array)($input['venues']??[])) as $venueRow){
  $name=mb_strtolower(trim((string)($venueRow['venue_name']??'')));
  $zip=strtoupper(preg_replace('/\\s+/', '',(string)($venueRow['postcode']??'')));
  if($name==='')continue;
  $key=$name.'|'.$zip;
  if(isset($venueKeys[$key]))bh_admin_response(422,['ok'=>false,'error'=>'Duplicate location: '.($venueRow['venue_name']??$venueName).'. Keep one location and assign its sessions there.']);
  $venueKeys[$key]=true;
 }
 $submittedSessions=(array)($input['sessions']??[]);
 $seenSessionKeys=[];
 foreach($submittedSessions as $index=>$session){
  $day=(int)($session['day_of_week']??0);
  $start=trim((string)($session['start_time']??''));
  if($day<1||$day>7||!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/',$start)){
   bh_admin_response(422,['ok'=>false,'error'=>'Class '.($index+1).' needs a valid weekday and start time. No sessions were deleted or saved.']);
  }

  $venueIndex=(int)($session['venue_index']??0);
  $sessionKey=implode('|',[$venueIndex,$day,substr($start,0,5),strtolower(trim((string)($session['class_name']??''))),(string)($session['start_date']??''),(string)($session['end_date']??'')]);
  if(isset($seenSessionKeys[$sessionKey])){
   bh_admin_response(422,['ok'=>false,'error'=>'Duplicate class rows '.($seenSessionKeys[$sessionKey]+1).' and '.($index+1).' have identical venue, name, weekday, time and dates. Remove one before saving. No sessions were changed.']);
  }
  $seenSessionKeys[$sessionKey]=$index; }
 $db->beginTransaction();
 try{
  if($id>0){$q=$db->prepare("SELECT organiser_id FROM bh_activities WHERE id=?");$q->execute([$id]);$existing=$q->fetch();if(!$existing)bh_admin_response(404,['ok'=>false,'error'=>'Activity not found.']);$organiserId=(int)$existing['organiser_id'];$q=$db->prepare("SELECT organisation_name FROM bh_organisers WHERE id=?");$q->execute([$organiserId]);$savedOrganisation=(string)$q->fetchColumn();if($savedOrganisation!==$organisationName){$q=$db->prepare("SELECT id FROM bh_organisers WHERE organisation_name=? LIMIT 1");$q->execute([$organisationName]);$matched=$q->fetchColumn();if($matched){$organiserId=(int)$matched;}else{$q=$db->prepare("SELECT COUNT(*) FROM bh_activities WHERE organiser_id=? AND id<>?");$q->execute([$organiserId,$id]);if((int)$q->fetchColumn()===0){$db->prepare("UPDATE bh_organisers SET organisation_name=? WHERE id=?")->execute([$organisationName,$organiserId]);}else{$available=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);$values=['organisation_name'=>$organisationName,'slug'=>strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$organisationName),'-')),'description'=>'','email'=>$email,'phone'=>$phone,'website'=>$website,'status'=>'published'];$columns=array_values(array_intersect(array_keys($values),$available));if(!in_array('organisation_name',$columns,true))throw new RuntimeException('Organiser table is missing organisation_name.');$names=implode(',',array_map(static fn($name)=>'`'.$name.'`',$columns));$marks=implode(',',array_fill(0,count($columns),'?'));$q=$db->prepare("INSERT INTO bh_organisers (".$names.") VALUES (".$marks.")");$q->execute(array_map(static fn($name)=>$values[$name],$columns));$organiserId=(int)$db->lastInsertId();}}$db->prepare("UPDATE bh_activities SET organiser_id=? WHERE id=?")->execute([$organiserId,$id]);}$q=$db->prepare("UPDATE bh_organisers SET email=?,phone=?,website=? WHERE id=?");$q->execute([$email,$phone,$website,$organiserId]);if($activityCountyColumn){$q=$db->prepare("UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,county=?,price_from=?,booking_url=?,image_path=?,status=?,accessibility=? WHERE id=?");$q->execute([$title,$description,$category,$ageRange,$county,$priceFrom,$bookingUrl,$imagePath,$status,$accessibility,$id]);}else{$q=$db->prepare("UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,price_from=?,booking_url=?,image_path=?,status=?,accessibility=? WHERE id=?");$q->execute([$title,$description,$category,$ageRange,$priceFrom,$bookingUrl,$imagePath,$status,$accessibility,$id]);}$q=$db->prepare("SELECT id FROM bh_venues WHERE activity_id=? ORDER BY id LIMIT 1");$q->execute([$id]);$venueId=(int)$q->fetchColumn();if(!$venueId){$q=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$q->execute([$id,$venueName,$address,$town,$region,$postcode,$latitude,$longitude,'']);$venueId=(int)$db->lastInsertId();}else{$q=$db->prepare("UPDATE bh_venues SET venue_name=?,address=?,town=?,region=?,postcode=?,latitude=?,longitude=? WHERE id=?");$q->execute([$venueName,$address,$town,$region,$postcode,$latitude,$longitude,$venueId]);}
   $additionalVenues=[];$seenVenueKeys=[];$seenVenueKeys[mb_strtolower(trim($venueName)).'|'.strtoupper(preg_replace('/\\s+/', '',$postcode))]=true;foreach((array)($input['venues']??[]) as $venue){$vn=trim((string)($venue['venue_name']??''));$vt=trim((string)($venue['town']??''));if($vn===''||$vt==='')continue;$venueKey=mb_strtolower($vn).'|'.strtoupper(preg_replace('/\\s+/', '',(string)($venue['postcode']??'')));if(isset($seenVenueKeys[$venueKey]))throw new RuntimeException('Duplicate location detected during save.');$seenVenueKeys[$venueKey]=true;$vid=(int)($venue['id']??0);$va=trim((string)($venue['address']??''));$vr=trim((string)($venue['region']??''));$vp=trim((string)($venue['postcode']??''));$vlat=($venue['latitude']??'')===''?null:(float)$venue['latitude'];$vlon=($venue['longitude']??'')===''?null:(float)$venue['longitude'];if($vid>0){$vq=$db->prepare("UPDATE bh_venues SET venue_name=?,address=?,town=?,region=?,postcode=?,latitude=?,longitude=? WHERE id=? AND activity_id=?");$vq->execute([$vn,$va,$vt,$vr,$vp,$vlat,$vlon,$vid,$id]);}else{$vq=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$vq->execute([$id,$vn,$va,$vt,$vr,$vp,$vlat,$vlon,'']);$vid=(int)$db->lastInsertId();}$additionalVenues[]=$vid;}$venueIds=[$venueId,...$additionalVenues];$incomingSessions=$submittedSessions;
   // Recover existing class names by stable session ID before replacing rows.
   // Never silently erase an existing name when the editor submits an empty one.
   if($incomingSessions){
    $nameCols=$db->query("SHOW COLUMNS FROM bh_sessions")->fetchAll(PDO::FETCH_COLUMN);
    if(in_array('class_name',$nameCols,true)){
     $previous=$db->prepare("SELECT id,class_name FROM bh_sessions WHERE venue_id=?");
     $oldNames=[];
     foreach($venueIds as $vid){$previous->execute([$vid]);foreach($previous->fetchAll(PDO::FETCH_ASSOC) as $previousRow){$oldNames[(int)$previousRow['id']]=(string)($previousRow['class_name']??'');}}
     foreach($incomingSessions as &$incomingSession){
      $oldId=(int)($incomingSession['id']??0);
      if(trim((string)($incomingSession['class_name']??''))===''&&$oldId>0&&!empty($oldNames[$oldId]))$incomingSession['class_name']=$oldNames[$oldId];
     }
     unset($incomingSession);
    }
    foreach($venueIds as $vid)$db->prepare("DELETE FROM bh_sessions WHERE venue_id=?")->execute([$vid]);
   }$action='updated';
  }else{
   $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));$baseSlug=$slug;$n=2;while(true){$q=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");$q->execute([$slug]);if(!$q->fetch())break;$slug=$baseSlug.'-'.$n++;}
   $q=$db->prepare("SELECT id FROM bh_organisers WHERE organisation_name=? LIMIT 1");$q->execute([$organisationName]);$organiserId=$q->fetchColumn();
   if(!$organiserId){
    // Organiser schemas vary between deployments: insert only columns that exist.
    $available=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);
    $values=['organisation_name'=>$organisationName,'slug'=>strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$organisationName),'-')),'description'=>'','email'=>$email,'phone'=>$phone,'website'=>$website,'status'=>'published'];
    $columns=array_values(array_intersect(array_keys($values),$available));
    if(!in_array('organisation_name',$columns,true))throw new RuntimeException('Organiser table is missing organisation_name.');
    $names=implode(',',array_map(static fn($name)=>'`'.$name.'`',$columns));
    $marks=implode(',',array_fill(0,count($columns),'?'));
    $q=$db->prepare("INSERT INTO bh_organisers (".$names.") VALUES (".$marks.")");
    $q->execute(array_map(static fn($name)=>$values[$name],$columns));
    $organiserId=(int)$db->lastInsertId();
   }
   if($activityCountyColumn){$q=$db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,county,price_from,booking_url,image_path,status,accessibility) VALUES (?,?,?,?,?,?,?,?,?,?,'published',?)");$q->execute([$organiserId,$title,$slug,$description,$category,$ageRange,$county,$priceFrom,$bookingUrl,$imagePath,$accessibility]);}else{$q=$db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,price_from,booking_url,image_path,status,accessibility) VALUES (?,?,?,?,?,?,?,?,?,'published',?)");$q->execute([$organiserId,$title,$slug,$description,$category,$ageRange,$priceFrom,$bookingUrl,$imagePath,$accessibility]);}$id=(int)$db->lastInsertId();if($id<1)throw new RuntimeException('Activity ID was not generated. Check bh_activities primary key AUTO_INCREMENT before saving.');$q=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$q->execute([$id,$venueName,$address,$town,$region,$postcode,$latitude,$longitude,'']);$venueId=(int)$db->lastInsertId();$additionalVenues=[];foreach((array)($input['venues']??[]) as $venue){$vn=trim((string)($venue['venue_name']??''));$vt=trim((string)($venue['town']??''));if($vn===''||$vt==='')continue;$vq=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$vq->execute([$id,$vn,trim((string)($venue['address']??'')),$vt,trim((string)($venue['region']??'')),trim((string)($venue['postcode']??'')),($venue['latitude']??'')===''?null:(float)$venue['latitude'],($venue['longitude']??'')===''?null:(float)$venue['longitude'],'']);$additionalVenues[]=(int)$db->lastInsertId();}$action='created';
  }
  $db->prepare("UPDATE bh_activities SET facebook_url=?,instagram_url=? WHERE id=?")->execute([$facebookUrl,$instagramUrl,$id]);
  if($venueBookingColumn){
   $db->prepare("UPDATE bh_venues SET booking_url=? WHERE id=? AND activity_id=?")->execute([trim((string)($input['booking_url']??'')),$venueId,$id]);
   foreach((array)($input['venues']??[]) as $index=>$venue){
    if(!isset($additionalVenues[$index]))continue;
    $db->prepare("UPDATE bh_venues SET booking_url=? WHERE id=? AND activity_id=?")->execute([trim((string)($venue['booking_url']??'')),$additionalVenues[$index],$id]);
   }
  }
  $tagColumn=$db->query("SHOW COLUMNS FROM bh_activities LIKE 'tags'")->fetch();
  if($tagColumn){$db->prepare("UPDATE bh_activities SET tags=? WHERE id=?")->execute([$tags,$id]);}
  if($optional){$set=implode(',',array_map(static fn($k)=>"`".$k."`=?",array_keys($optional)));$q=$db->prepare("UPDATE bh_activities SET ".$set." WHERE id=?");$q->execute([...array_values($optional),$id]);}
  $db->prepare("DELETE FROM bh_activity_gallery WHERE activity_id=?")->execute([$id]);$saveGallery=$db->prepare("INSERT INTO bh_activity_gallery (activity_id,position,image_url) VALUES (?,?,?)");for($n=1;$n<=10;$n++){ $url=trim((string)($input['image_'.$n]??''));if($url!==''){$saveGallery->execute([$id,$n,$url]);}}
  $sessionVenueIds=[$venueId,...$additionalVenues];$savedSessionCount=0;foreach($incomingSessions as $s){$venueIndex=(int)($s['venue_index']??0);$sessionVenueId=$sessionVenueIds[$venueIndex]??$venueId;$day=(int)($s['day_of_week']??0);$start=trim((string)($s['start_time']??''));$end=trim((string)($s['end_time']??''));if($day<1||$day>7||$start==='')continue;$duration=null;if($end!==''){$aa=strtotime($start);$bb=strtotime($end);if($aa!==false&&$bb!==false){$duration=(int)(($bb-$aa)/60);if($duration<0)$duration+=1440;}}$price=($s['price']??'')===''?$priceFrom:(float)$s['price'];$term=!empty($s['term_time_only'])?1:0;$frequency=trim((string)($s['frequency']??'weekly'))?:'weekly';$startDate=($s['start_date']??'')!==''?$s['start_date']:null;$endDate=($s['end_date']??'')!==''?$s['end_date']:null;$className=mb_substr(trim((string)($s['class_name']??'')),0,160);
  $sessionCols=$db->query("SHOW COLUMNS FROM bh_sessions")->fetchAll(PDO::FETCH_COLUMN);
  $nameCol=in_array('class_name',$sessionCols,true)?'class_name':(in_array('title',$sessionCols,true)?'title':null);
  if($nameCol!==null){
   $q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date,`".$nameCol."`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
   $q->execute([$sessionVenueId,$day,$start,$end!==''?$end:null,$duration,$price,$term,$frequency,$startDate,$endDate,$className]);
  }else{
   $q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
   $q->execute([$sessionVenueId,$day,$start,$end!==''?$end:null,$duration,$price,$term,$frequency,$startDate,$endDate]);
  }$savedSessionCount++;}
  // Synchronise the canonical taxonomy and its activity relationship inside this transaction.
  if($categoryNames){
   $categoryCols=$db->query("SHOW COLUMNS FROM bh_categories")->fetchAll(PDO::FETCH_COLUMN);
   $relationCols=$db->query("SHOW COLUMNS FROM bh_activity_categories")->fetchAll(PDO::FETCH_COLUMN);
   if(in_array('id',$categoryCols,true)&&in_array('name',$categoryCols,true)&&in_array('activity_id',$relationCols,true)&&in_array('category_id',$relationCols,true)){
    $db->prepare("DELETE FROM bh_activity_categories WHERE activity_id=?")->execute([$id]);
    foreach($categoryNames as $name){
     $q=$db->prepare("SELECT id FROM bh_categories WHERE LOWER(name)=LOWER(?) LIMIT 1");$q->execute([$name]);$categoryId=(int)$q->fetchColumn();
     if(!$categoryId){
      $fields=['name'];$values=[$name];
      if(in_array('slug',$categoryCols,true)){$fields[]='slug';$values[]=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-');}
      $sql="INSERT INTO bh_categories (".implode(',',$fields).") VALUES (".implode(',',array_fill(0,count($fields),'?')).")";
      $db->prepare($sql)->execute($values);$categoryId=(int)$db->lastInsertId();
     }
     $db->prepare("INSERT INTO bh_activity_categories (activity_id,category_id) VALUES (?,?)")->execute([$id,$categoryId]);
    }
   }
  }
  $db->commit();bh_admin_response(200,['ok'=>true,'id'=>$id,'action'=>$action,'sessions_saved'=>$savedSessionCount]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}catch(Throwable $e){bh_admin_response(500,['ok'=>false,'error_type'=>get_class($e),'error'=>$e->getMessage()]);}
?>