<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
require_once __DIR__.'/db.php';
require_once __DIR__.'/onesignal-email.php';
session_start();
function lp(int $s,array $d): void{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_SLASHES);exit;}
function lpEnsureExpertise(PDO $db): void{
 try{$db->exec("CREATE TABLE IF NOT EXISTS bh_leader_expertise (organiser_id BIGINT UNSIGNED NOT NULL, topic_key VARCHAR(80) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (organiser_id,topic_key), INDEX idx_leader_expertise_topic (topic_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(Throwable $ignored){}
}
$expertiseTopics=[
 'baby-child-health'=>'Baby & child health',
 'feeding-weaning'=>'Feeding & weaning',
 'sleep'=>'Sleep',
 'pregnancy-new-parents'=>'Pregnancy & new parents',
 'family-wellbeing'=>'Family wellbeing',
 'activities-classes'=>'Activities & classes',
 'specialist-send'=>'SEND & additional needs'
];
$adminOnly=!empty($_SESSION['bh_admin_authenticated'])&&empty($_SESSION['bh_user_id']);
if(empty($_SESSION['bh_user_id'])&&!$adminOnly) lp(401,['ok'=>false,'error'=>'login_required']);
$userId=(int)($_SESSION['bh_user_id']??0); $db=bh_mysql();
$roleStmt=$db->prepare("SELECT role,status FROM bh_users WHERE id=? LIMIT 1");$roleStmt->execute([$userId]);$roleUser=$roleStmt->fetch();
if(!$adminOnly && (!$roleUser || $roleUser['status']!=='active' || ($roleUser['role']??'')!=='leader')) lp(403,['ok'=>false,'error'=>'leader_role_required','message'=>'A class leader account is required.']);
if($adminOnly){if($_SERVER['REQUEST_METHOD']==='GET')lp(200,['ok'=>true,'admin_mode'=>true,'organisation'=>null,'classes'=>[],'bookings'=>[],'faqs'=>[],'expertise_topics'=>$expertiseTopics,'expertise'=>[],'accessibility_options'=>$accessibilityOptions,'age_range_options'=>$ageRangeOptions]);lp(403,['ok'=>false,'error'=>'admin_read_only','message'=>'Admin access can view the leader area, but leader account changes require a linked class leader account.']);}
$hasOrgUserId=false;
try{$cc=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");$cc->execute();$hasOrgUserId=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
$org=null;
if($hasOrgUserId){$o=$db->prepare("SELECT * FROM bh_organisers WHERE user_id=? LIMIT 1");$o->execute([$userId]);$org=$o->fetch();}
else{try{$u=$db->prepare("SELECT email FROM bh_users WHERE id=? LIMIT 1");$u->execute([$userId]);$user=$u->fetch();if($user&&!empty($user['email'])){$o=$db->prepare("SELECT * FROM bh_organisers WHERE email=? LIMIT 1");$o->execute([$user['email']]);$org=$o->fetch();}}catch(Throwable $ignored){}}
if(!$org)lp(403,['ok'=>false,'error'=>'organiser_required','message'=>'Your account is not linked to a class leader organisation yet.']);
$oid=(int)$org['id'];$isPro=false;$imageLimit=3;
function lpEnsureActivityAccessibility(PDO $db): void{
 try{
  $db->exec("ALTER TABLE bh_activities ADD COLUMN accessibility TEXT NULL");
 }catch(Throwable $ignored){}
}
lpEnsureActivityAccessibility($db);
$accessibilityOptions=[
 'step_free'=>'Step-free access',
 'accessible_toilet'=>'Accessible toilet',
 'baby_changing'=>'Baby changing',
 'pram_access'=>'Pram / pushchair friendly',
 'parking'=>'Parking available',
 'quiet_space'=>'Quiet / low-sensory space',
 'hearing_loop'=>'Hearing loop / assistive listening',
 'visual_supports'=>'Visual supports',
 'sensory_friendly'=>'Sensory-friendly',
 'send_support'=>'SEND / additional-needs support',
 'outdoor_access'=>'Outdoor access',
 'toilets'=>'Toilets available'
];
$ageRangeOptions=['0-3'=>'0–3 years','1-3'=>'1–3 years','2-4'=>'2–4 years','3-5'=>'3–5 years','3-6'=>'3–6 years','5-plus'=>'5+ years','0-5'=>'0–5 years','all'=>'All ages'];
$leaderEmail='';
try{$eq=$db->prepare("SELECT email FROM bh_users WHERE id=? LIMIT 1");$eq->execute([$userId]);$leaderEmail=strtolower(trim((string)$eq->fetchColumn()));}catch(Throwable $ignored){$leaderEmail=strtolower(trim((string)($org['email']??'')));}
lpEnsureExpertise($db);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $classes=[];try{$a=$db->prepare("SELECT * FROM bh_activities WHERE organiser_id=? AND status<>'archived' ORDER BY title");$a->execute([$oid]);$classes=$a->fetchAll();}catch(Throwable $e){lp(500,['ok'=>false,'error'=>'classes_query_failed','message'=>$e->getMessage()]);}
 foreach($classes as &$c){$c['venues']=[];try{$v=$db->prepare("SELECT * FROM bh_venues WHERE activity_id=? ORDER BY id");$v->execute([(int)$c['id']]);$c['venues']=$v->fetchAll();}catch(Throwable $ignored){}
  foreach($c['venues'] as &$venue){$venue['sessions']=[];try{$s=$db->prepare("SELECT * FROM bh_sessions WHERE venue_id=? ORDER BY day_of_week,start_time");$s->execute([(int)$venue['id']]);$venue['sessions']=$s->fetchAll();}catch(Throwable $ignored){}}
 }unset($c,$venue);
 $bookings=[];try{$bookingCheck=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_booking_reservations','bh_booking_slots','bh_users')");if((int)$bookingCheck->fetchColumn()===3){$r=$db->prepare("SELECT br.id,br.status,br.quantity,br.created_at,bs.starts_at,bs.ends_at,bs.activity_id,bs.venue_id,a.title,v.venue_name,u.email FROM bh_booking_reservations br JOIN bh_booking_slots bs ON bs.id=br.slot_id JOIN bh_activities a ON a.id=bs.activity_id JOIN bh_venues v ON v.id=bs.venue_id JOIN bh_users u ON u.id=br.user_id WHERE a.organiser_id=? ORDER BY bs.starts_at DESC,br.id DESC");$r->execute([$oid]);$bookings=$r->fetchAll();}}catch(Throwable $ignored){}
 $faqs=[];try{$f=$db->prepare("SELECT * FROM bh_leader_faqs WHERE organiser_id=? AND status<>'archived' ORDER BY sort_order,id");$f->execute([$oid]);$faqs=$f->fetchAll();}catch(Throwable $ignored){}
 $expertise=[];try{$e=$db->prepare("SELECT topic_key FROM bh_leader_expertise WHERE organiser_id=? AND enabled=1 ORDER BY topic_key");$e->execute([$oid]);$expertise=$e->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $ignored){}
 $categoryOptions=[];
try{$cq=$db->query("SELECT DISTINCT TRIM(category) AS category FROM bh_activities WHERE category IS NOT NULL AND TRIM(category)<>'' ORDER BY category ASC");$categoryOptions=$cq->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $ignored){}
if(!$categoryOptions)$categoryOptions=['Baby & toddler','Classes & groups','Music & singing','Sport & movement','Arts & crafts','Messy play','Dance','Outdoor activities','Family wellbeing','SEND & additional needs','Pregnancy & new parents','Other'];
$tagOptions=[];try{$tc=$db->query("SELECT DISTINCT TRIM(tags) AS tags FROM bh_activities WHERE tags IS NOT NULL AND TRIM(tags)<>'' ORDER BY tags ASC");foreach($tc->fetchAll(PDO::FETCH_COLUMN) as $raw){foreach(preg_split('/[,|]+/',(string)$raw) as $tag){$tag=trim($tag);if($tag!==''&&!in_array($tag,$tagOptions,true))$tagOptions[]=$tag;}}}catch(Throwable $ignored){}sort($tagOptions,SORT_NATURAL|SORT_FLAG_CASE);lp(200,['ok'=>true,'organisation'=>$org,'classes'=>$classes,'bookings'=>$bookings,'faqs'=>$faqs,'expertise_topics'=>$expertiseTopics,'expertise'=>$expertise,'accessibility_options'=>$accessibilityOptions,'age_range_options'=>$ageRangeOptions,'category_options'=>$categoryOptions,'tag_options'=>$tagOptions,'max_images'=>$imageLimit]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')lp(405,['ok'=>false,'error'=>'method_not_allowed']);
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['image']) && ($_POST['action']??'')==='upload_activity_image'){
 $activityId=(int)($_POST['activity_id']??0); if($activityId<1)lp(422,['ok'=>false,'error'=>'activity_required','message'=>'Activity is required.']);
 $own=$db->prepare("SELECT id FROM bh_activities WHERE id=? AND organiser_id=? LIMIT 1");$own->execute([$activityId,$oid]);if(!$own->fetch())lp(404,['ok'=>false,'error'=>'activity_not_found','message'=>'Class not found.']);
 $file=$_FILES['image'];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)lp(422,['ok'=>false,'error'=>'upload_failed','message'=>'The image could not be uploaded.']);
 if((int)$file['size']>5*1024*1024)lp(422,['ok'=>false,'error'=>'image_too_large','message'=>'Each image must be 5MB or smaller.']);
 $info=@getimagesize($file['tmp_name']);if(!$info)lp(422,['ok'=>false,'error'=>'invalid_image','message'=>'Please upload a JPG, PNG or WebP image.']);
 $mime=(string)($info['mime']??'');$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext)lp(422,['ok'=>false,'error'=>'invalid_image_type','message'=>'Please upload a JPG, PNG or WebP image.']);
 $dir=__DIR__.'/../uploads/activities';if(!is_dir($dir)&&!@mkdir($dir,0755,true))lp(500,['ok'=>false,'error'=>'upload_directory_failed','message'=>'The image upload folder could not be created.']);
 $name='activity-'.$activityId.'-'.bin2hex(random_bytes(8)).'.'.$ext;$path=$dir.'/'.$name;if(!move_uploaded_file($file['tmp_name'],$path))lp(500,['ok'=>false,'error'=>'upload_move_failed','message'=>'The image could not be saved.']);
 $url='uploads/activities/'.$name;
 $cols=[];$q=$db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME IN ('image_path','image_url','gallery_images')");$q->execute();foreach($q->fetchAll(PDO::FETCH_COLUMN) as $col)$cols[$col]=true;
 if(empty($cols['gallery_images'])){try{$db->exec("ALTER TABLE bh_activities ADD COLUMN gallery_images TEXT NULL");$cols['gallery_images']=true;}catch(Throwable $ignored){}}
 $row=$db->prepare("SELECT image_path".(isset($cols['image_url'])?",image_url":"").",gallery_images FROM bh_activities WHERE id=? LIMIT 1");$row->execute([$activityId]);$current=$row->fetch()?:[];
 $gallery=[];if(!empty($current['gallery_images'])){$decoded=json_decode((string)$current['gallery_images'],true);if(is_array($decoded))$gallery=$decoded;}$gallery[]=$url;
 if(isset($cols['image_path']) && empty($current['image_path']))$db->prepare("UPDATE bh_activities SET image_path=? WHERE id=?")->execute([$url,$activityId]);
 if(isset($cols['image_url']) && empty($current['image_url']))$db->prepare("UPDATE bh_activities SET image_url=? WHERE id=?")->execute([$url,$activityId]);
 if(isset($cols['gallery_images']))$db->prepare("UPDATE bh_activities SET gallery_images=? WHERE id=?")->execute([json_encode($gallery,JSON_UNESCAPED_SLASHES),$activityId]);
 lp(201,['ok'=>true,'url'=>$url,'main'=>count($gallery)===1]);
}
$b=json_decode(file_get_contents('php://input'),true);if(!is_array($b))lp(400,['ok'=>false,'error'=>'invalid_json']);$action=$b['action']??'';

if($action==='save_profile_public'){
 $about=trim((string)($b['about_content']??''));$logo=trim((string)($b['logo_url']??''));$facebook=trim((string)($b['facebook_url']??''));$instagram=trim((string)($b['instagram_url']??''));$tiktok=trim((string)($b['tiktok_url']??''));
 if(strlen($about)>200000)lp(422,['ok'=>false,'error'=>'about_too_long','message'=>'Your organisation description is too long.']);
 foreach(['logo'=>$logo,'facebook'=>$facebook,'instagram'=>$instagram,'tiktok'=>$tiktok] as $key=>$url){if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))lp(422,['ok'=>false,'error'=>'invalid_'.$key.'_url','message'=>'Please enter a valid URL for '.ucfirst($key).'.']);}
 $q=$db->prepare("UPDATE bh_organisers SET about_content=?,logo_url=?,facebook_url=?,instagram_url=?,tiktok_url=? WHERE id=?");$q->execute([$about,$logo,$facebook,$instagram,$tiktok,$oid]);
 lp(200,['ok'=>true,'message'=>'Public organiser profile saved.']);
}
if($action==='save_profile_public'){ $about=trim((string)($b['about_content']??''));$logo=trim((string)($b['logo_url']??''));$facebook=trim((string)($b['facebook_url']??''));$instagram=trim((string)($b['instagram_url']??''));$tiktok=trim((string)($b['tiktok_url']??'')); if(strlen($about)>200000)lp(422,['ok'=>false,'error'=>'about_too_long']); $q=$db->prepare("UPDATE bh_organisers SET about_content=?,logo_url=?,facebook_url=?,instagram_url=?,tiktok_url=? WHERE id=?");$q->execute([$about,$logo,$facebook,$instagram,$tiktok,$oid]); lp(200,['ok'=>true,'message'=>'Public organiser profile saved.']); }
if($action==='save_terms'){
 $terms=trim((string)($b['terms_content']??''));
 if(strlen($terms)>200000) lp(422,['ok'=>false,'error'=>'terms_too_long','message'=>'Your terms are too long. Please keep them under 200,000 characters.']);
 $q=$db->prepare("UPDATE bh_organisers SET terms_content=? WHERE id=?");$q->execute([$terms,$oid]);
 lp(200,['ok'=>true,'message'=>'Your Terms & Conditions have been saved.','terms_content'=>$terms]);
}
if($action==='save_account'){
 $businessName=trim((string)($b['business_name']??''));$name=trim((string)($b['name']??''));$email=strtolower(trim((string)($b['email']??'')));$phone=trim((string)($b['phone']??''));$website=trim((string)($b['website']??''));
 if($businessName==='')lp(422,['ok'=>false,'error'=>'business_name_required']);if(!filter_var($email,FILTER_VALIDATE_EMAIL))lp(422,['ok'=>false,'error'=>'invalid_email']);
 $u=$db->prepare("UPDATE bh_users SET email=? WHERE id=?");$u->execute([$email,$userId]);try{$db->prepare("UPDATE bh_organisers SET organisation_name=?,phone=?,website=? WHERE id=?")->execute([$businessName,$phone,$website,$oid]);}catch(Throwable $ignored){}
 if($name!==''){foreach(['name','display_name','contact_name'] as $col){try{$db->prepare("UPDATE bh_organisers SET $col=? WHERE id=?")->execute([$name,$oid]);break;}catch(Throwable $ignored){}}}
 lp(200,['ok'=>true,'message'=>'Account details saved.']);
}
if($action==='change_password'){
 $current=(string)($b['current_password']??'');$new=(string)($b['new_password']??'');$confirm=(string)($b['confirm_password']??'');
 if(strlen($new)<8)lp(422,['ok'=>false,'error'=>'password_too_short','message'=>'Choose a password with at least 8 characters.']);if($new!==$confirm)lp(422,['ok'=>false,'error'=>'password_mismatch','message'=>'The passwords do not match.']);
 $q=$db->prepare("SELECT password_hash FROM bh_users WHERE id=? LIMIT 1");$q->execute([$userId]);$u=$q->fetch();if(!$u||!password_verify($current,(string)$u['password_hash']))lp(403,['ok'=>false,'error'=>'current_password_invalid','message'=>'Your current password is not correct.']);
 $db->prepare("UPDATE bh_users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$userId]);session_regenerate_id(true);$_SESSION['bh_user_id']=$userId;$_SESSION['bh_csrf']=bin2hex(random_bytes(24));lp(200,['ok'=>true,'csrf'=>$_SESSION['bh_csrf']??'','message'=>'Password changed successfully.']);
}
if($action==='save_expertise'){
 $keys=is_array($b['topics']??null)?$b['topics']:[];$keys=array_values(array_filter(array_map('strval',$keys),fn($k)=>isset($expertiseTopics[$k])));
 $db->beginTransaction();try{$db->prepare("DELETE FROM bh_leader_expertise WHERE organiser_id=?")->execute([$oid]);$q=$db->prepare("INSERT INTO bh_leader_expertise (organiser_id,topic_key,enabled) VALUES (?,?,1)");foreach($keys as $k)$q->execute([$oid,$k]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();lp(500,['ok'=>false,'error'=>'expertise_save_failed','message'=>$e->getMessage()]);}
 lp(200,['ok'=>true,'expertise'=>$keys,'message'=>'Your support expertise has been saved.']);
}
if($action==='save_faq'){
 $id=(int)($b['id']??0);$question=trim((string)($b['question']??''));$answer=trim((string)($b['answer']??''));$status=in_array(($b['status']??'draft'),['draft','published'],true)?$b['status']:'draft';$activityId=($b['activity_id']??'')===''?null:(int)$b['activity_id'];$sort=(int)($b['sort_order']??0);
 if($question===''||$answer==='')lp(422,['ok'=>false,'error'=>'faq_content_required','message'=>'Add both a question and an answer.']);
 if($id){$q=$db->prepare("UPDATE bh_leader_faqs SET question=?,answer=?,status=?,activity_id=?,sort_order=? WHERE id=? AND organiser_id=?");$q->execute([$question,$answer,$status,$activityId,$sort,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'faq_not_found']);}
 else{$q=$db->prepare("INSERT INTO bh_leader_faqs (organiser_id,activity_id,question,answer,status,sort_order) VALUES (?,?,?,?,?,?)");$q->execute([$oid,$activityId,$question,$answer,$status,$sort]);}
 lp(200,['ok'=>true,'message'=>'FAQ saved.']);
}
if($action==='delete_faq'){
 $id=(int)($b['id']??0);$q=$db->prepare("UPDATE bh_leader_faqs SET status='archived' WHERE id=? AND organiser_id=?");$q->execute([$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'faq_not_found']);lp(200,['ok'=>true,'message'=>'FAQ archived.']);
}
if($action==='tag_suggestions'){
 $category=trim((string)($b['category']??''));$suggestions=[];
 if($category!==''){try{$q=$db->prepare("SELECT tags FROM bh_activities WHERE organiser_id<>? AND FIND_IN_SET(?, REPLACE(category, ', ', ',')) AND tags IS NOT NULL AND TRIM(tags)<>'' ORDER BY id DESC LIMIT 100");$q->execute([$oid,$category]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $raw){foreach(preg_split('/[,|]+/',(string)$raw) as $tag){$tag=trim($tag);if($tag!==''&&!in_array($tag,$suggestions,true))$suggestions[]=$tag;}}}catch(Throwable $ignored){}}
 sort($suggestions,SORT_NATURAL|SORT_FLAG_CASE);lp(200,['ok'=>true,'suggestions'=>array_slice($suggestions,0,12)]);
}
if($action==='create_listing'){
 $photos=is_array($b['photos']??null)?array_values(array_filter(array_map('strval',$b['photos']))):[];$title=trim((string)($b['title']??''));$description=trim((string)($b['description']??''));$category=trim((string)($b['category']??''));if($category==='__new__')$category=trim((string)($b['new_category']??''));$age=trim((string)($b['age_range']??''));$ageMin=(int)($b['age_min_months']??0);$ageMax=(int)($b['age_max_months']??0);$tags=trim((string)($b['tags']??''));$accessibility=is_array($b['accessibility']??null)?array_values(array_filter(array_map('strval',$b['accessibility']))):[];$accessibilityJson=json_encode($accessibility,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$bookingRequired=!empty($b['booking_required'])?1:0;$dropInWelcome=!empty($b['drop_in_welcome'])?1:0;$trialAvailable=!empty($b['trial_available'])?1:0;$termTimeOnly=!empty($b['term_time_only'])?1:0;$holidaySessions=!empty($b['holiday_sessions'])?1:0;$siblingsWelcome=!empty($b['siblings_welcome'])?1:0;$whatToBring=trim((string)($b['what_to_bring']??''));$goodToKnow=trim((string)($b['good_to_know']??''));$price=($b['price_from']??'')===''?null:(float)$b['price_from'];$pricePerFamily=!empty($b['price_per_family'])?1:0;$url=trim((string)($b['booking_url']??''));$venueName=trim((string)($b['venue_name']??''));$address=trim((string)($b['address']??''));$town=trim((string)($b['town']??''));$region=trim((string)($b['region']??''));$postcode=trim((string)($b['postcode']??''));$lat=trim((string)($b['latitude']??''));$lng=trim((string)($b['longitude']??''));
 if($title==='')lp(422,['ok'=>false,'error'=>'title_required']);if($category==='')lp(422,['ok'=>false,'error'=>'category_required']);if($venueName===''||$town==='')lp(422,['ok'=>false,'error'=>'venue_required']);$db->beginTransaction();
 try{$baseSlug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $title),'-'));if($baseSlug==='')$baseSlug='class';$slug=$baseSlug;$suffix=2;$slugCheck=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");while(true){$slugCheck->execute([$slug]);if(!$slugCheck->fetch())break;$slug=$baseSlug.'-'.$suffix++;}
  $countyExists=false;try{$cc=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$cc->execute();$countyExists=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
  try{$db->exec("ALTER TABLE bh_activities ADD COLUMN tags TEXT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN age_min_months INT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN age_max_months INT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN price_per_family TINYINT(1) NOT NULL DEFAULT 0");}catch(Throwable $ignored){}foreach(["booking_required"=>"TINYINT(1) NOT NULL DEFAULT 0","drop_in_welcome"=>"TINYINT(1) NOT NULL DEFAULT 0","trial_available"=>"TINYINT(1) NOT NULL DEFAULT 0","term_time_only"=>"TINYINT(1) NOT NULL DEFAULT 0","holiday_sessions"=>"TINYINT(1) NOT NULL DEFAULT 0","siblings_welcome"=>"TINYINT(1) NOT NULL DEFAULT 0","what_to_bring"=>"VARCHAR(250) NULL","good_to_know"=>"VARCHAR(250) NULL"] as $col=>$definition){try{$db->exec("ALTER TABLE bh_activities ADD COLUMN $col $definition");}catch(Throwable $ignored){}}
if($countyExists){$q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,age_min_months,age_max_months,accessibility,tags,county,price_from,price_per_family,booking_url,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome,what_to_bring,good_to_know,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$q->execute([$title,$slug,$description,$category,$age,$ageMin,$ageMax,$accessibilityJson,$tags,'',$price,$pricePerFamily,'',$bookingRequired,$dropInWelcome,$trialAvailable,$termTimeOnly,$holidaySessions,$siblingsWelcome,$whatToBring,$goodToKnow,'draft',$oid]);}else{$q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,age_min_months,age_max_months,accessibility,tags,price_from,price_per_family,booking_url,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome,what_to_bring,good_to_know,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$q->execute([$title,$slug,$description,$category,$age,$ageMin,$ageMax,$accessibilityJson,$tags,$price,$pricePerFamily,'',$bookingRequired,$dropInWelcome,$trialAvailable,$termTimeOnly,$holidaySessions,$siblingsWelcome,$whatToBring,$goodToKnow,'draft',$oid]);}
  $activityId=(int)$db->lastInsertId();$v=$db->prepare("INSERT INTO bh_venues (venue_name,address,town,region,postcode,latitude,longitude,notes,activity_id) VALUES (?,?,?,?,?,?,?,?,?)");$v->execute([$venueName,$address,$town,$region,$postcode,$lat===''?null:$lat,$lng===''?null:$lng,'',$activityId]);$db->commit();
  if($leaderEmail!==''){try{$safeTitle=htmlspecialchars($title,ENT_QUOTES,'UTF-8');$safeOrg=htmlspecialchars((string)($org['organisation_name']??''),ENT_QUOTES,'UTF-8');$html='<div style="font-family:Arial,sans-serif;line-height:1.6;color:#26352b;max-width:640px;margin:0 auto"><h1 style="color:#617261">Welcome to Bubba Hub</h1><p>Hi'.($safeOrg!==''?' '.$safeOrg:'').',</p><p>Thanks for adding <strong>'.$safeTitle.'</strong> to Bubba Hub.</p><p>Your listing has been submitted and is currently awaiting review. We will let you know when there is an update.</p><p>The Bubba Hub team</p></div>';$plain="Welcome to Bubba Hub\n\nThanks for adding {$title} to Bubba Hub.\n\nYour listing has been submitted and is currently awaiting review.\n\nThe Bubba Hub team";bh_send_onesignal_email($leaderEmail,'Welcome to Bubba Hub – your listing has been submitted',$html,$plain);}catch(Throwable $ignored){}}
  lp(201,['ok'=>true,'id'=>$activityId]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if($action==='save_class'){
 $id=(int)($b['id']??0);$title=trim((string)($b['title']??''));$description=trim((string)($b['description']??''));$category=trim((string)($b['category']??''));$age=trim((string)($b['age_range']??''));$accessibility=is_array($b['accessibility']??null)?array_values(array_filter(array_map('strval',$b['accessibility']))):[];$accessibilityJson=json_encode($accessibility,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$price=($b['price_from']??'')===''?null:(float)$b['price_from'];$url=trim((string)($b['booking_url']??''));if($title==='')lp(422,['ok'=>false,'error'=>'title_required']);$q=$db->prepare("UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,accessibility=?,price_from=?,booking_url=? WHERE id=? AND organiser_id=?");$q->execute([$title,$description,$category,$age,$accessibilityJson,$price,$url,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'class_not_found']);lp(200,['ok'=>true]);
}
if($action==='save_venue'){
 $id=(int)($b['id']??0);$fields=['venue_name','address','town','region','postcode','latitude','longitude','notes'];$vals=[];foreach($fields as $f)$vals[]=trim((string)($b[$f]??''));$q=$db->prepare("UPDATE bh_venues v JOIN bh_activities a ON a.id=v.activity_id SET v.venue_name=?,v.address=?,v.town=?,v.region=?,v.postcode=?,v.latitude=NULLIF(?,''),v.longitude=NULLIF(?,''),v.notes=? WHERE v.id=? AND a.organiser_id=?");$q->execute([...$vals,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'venue_not_found']);lp(200,['ok'=>true]);
}
if($action==='save_booking'){
 $id=(int)($b['id']??0);$status=$b['status']??'';if(!in_array($status,['reserved','confirmed','cancelled','attended'],true))lp(422,['ok'=>false,'error'=>'invalid_status']);$q=$db->prepare("UPDATE bh_booking_reservations br JOIN bh_booking_slots bs ON bs.id=br.slot_id JOIN bh_activities a ON a.id=bs.activity_id SET br.status=? WHERE br.id=? AND a.organiser_id=?");$q->execute([$status,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'booking_not_found']);lp(200,['ok'=>true]);
}
lp(400,['ok'=>false,'error'=>'unknown_action']);
} catch (Throwable $e) {http_response_code(500);echo json_encode(['ok'=>false,'error'=>'leader_portal_error','message'=>$e->getMessage()],JSON_UNESCAPED_SLASHES);exit;}
