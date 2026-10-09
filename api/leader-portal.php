<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
require_once __DIR__.'/db.php';
require_once __DIR__.'/mailer.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
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
$accessibilityOptions=[
 'step_free'=>'Step-free access','accessible_toilet'=>'Accessible toilet','baby_changing'=>'Baby changing','pram_access'=>'Pram / pushchair friendly','parking'=>'Parking available','quiet_space'=>'Quiet / low-sensory space','hearing_loop'=>'Hearing loop / assistive listening','visual_supports'=>'Visual supports','sensory_friendly'=>'Sensory-friendly','send_support'=>'SEND / additional-needs support','outdoor_access'=>'Outdoor access','toilets'=>'Toilets available'
];
$ageRangeOptions=['0-3'=>'0–3 years','1-3'=>'1–3 years','2-4'=>'2–4 years','3-5'=>'3–5 years','3-6'=>'3–6 years','5-plus'=>'5+ years','0-5'=>'0–5 years','all'=>'All ages'];
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
$oid=(int)$org['id'];$isPro=false;$imageLimit=3;foreach(['plan','membership_plan','membership_tier','subscription_plan','tier'] as $pcn){if(array_key_exists($pcn,$org)){ $pv=strtolower(trim((string)$org[$pcn]));$isPro=in_array($pv,['pro','premium','ultimate'],true);break;}}try{$t=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions'");if((int)$t->fetchColumn()){$p=$db->prepare("SELECT COUNT(*) FROM bh_user_subscriptions WHERE user_id=? AND plan='leader_pro' AND status IN ('active','trialing') AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())");$p->execute([$userId]);if((int)$p->fetchColumn()>0)$isPro=true;}}catch(Throwable $ignored){error_log('Leader portal subscription lookup: '.$ignored->getMessage());}
$imageLimit=$isPro?12:3;$faqLimit=$isPro?null:5;
function lpEnsureActivityAccessibility(PDO $db): void{
 try{
  $db->exec("ALTER TABLE bh_activities ADD COLUMN accessibility TEXT NULL");
 }catch(Throwable $ignored){}
}
lpEnsureActivityAccessibility($db);
function lpTableOptions(PDO $db,array $tables,array $keyCols,array $labelCols): array{
 foreach($tables as $table){
  try{
   $tq=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
   $tq->execute([$table]);if(!(int)$tq->fetchColumn())continue;
   $cq=$db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
   $cq->execute([$table]);$cols=$cq->fetchAll(PDO::FETCH_COLUMN);
   $key=array_values(array_intersect($keyCols,$cols))[0]??null;$label=array_values(array_intersect($labelCols,$cols))[0]??null;
   if(!$label)continue;$key=$key?:$label;
   $q=$db->query("SELECT DISTINCT ".$key." AS option_key, ".$label." AS option_label FROM ".$table." WHERE ".$label." IS NOT NULL AND TRIM(".$label.")<>'' ORDER BY ".$label);
   $out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$k=trim((string)$row['option_key']);$l=trim((string)$row['option_label']);if($k!==''&&$l!=='')$out[$k]=$l;}if($out)return $out;
  }catch(Throwable $ignored){}
 }
 return [];
}
$dynamicAccessibility=lpTableOptions($db,['bh_accessibility','bh_accessibility_options','bh_family_facilities'],['key','slug','id'],['label','name','title']);
if($dynamicAccessibility)$accessibilityOptions=$dynamicAccessibility;

$leaderEmail='';
try{$eq=$db->prepare("SELECT email FROM bh_users WHERE id=? LIMIT 1");$eq->execute([$userId]);$leaderEmail=strtolower(trim((string)$eq->fetchColumn()));}catch(Throwable $ignored){$leaderEmail=strtolower(trim((string)($org['email']??'')));}
lpEnsureExpertise($db);
if($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['csv']??'')==='template'){
 header('Content-Type: text/csv; charset=utf-8');
 header('Content-Disposition: attachment; filename="bubba-hub-class-import-template.csv"');
 $headers=['title','description','category','age_range','age_min_months','age_max_months','tags','accessibility','price_from','price_per_family','price_per_session','price_free','booking_url','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know','schedule','venue_name','address','town','region','postcode','latitude','longitude','image_1','image_2','image_3'];
 $example=['Baby & Toddler Group','A friendly weekly group for babies and toddlers.','Baby & toddler','0-3','0','36','baby,play,groups','baby_changing|pram_access|toilets','5','0','1','0','https://example.com/book','1','1','1','1','0','1','Bring a play mat','Parking available nearby.','Monday 10:00-11:00|Wednesday 10:00-11:00','Community Centre','High Street','Torquay','Torbay','TQ1 1AA','50.4619','-3.5253','https://example.com/image1.jpg','',''];
 $out=fopen('php://output','w');fputcsv($out,$headers);fputcsv($out,$example);fclose($out);exit;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
 $classes=[];try{$a=$db->prepare("SELECT * FROM bh_activities WHERE organiser_id=? AND status<>'archived' ORDER BY title");$a->execute([$oid]);$classes=$a->fetchAll();}catch(Throwable $e){lp(500,['ok'=>false,'error'=>'classes_query_failed','message'=>$e->getMessage()]);}
 foreach($classes as &$c){$c['venues']=[];try{$v=$db->prepare("SELECT * FROM bh_venues WHERE activity_id=? ORDER BY id");$v->execute([(int)$c['id']]);$c['venues']=$v->fetchAll();}catch(Throwable $ignored){}
  foreach($c['venues'] as &$venue){$venue['sessions']=[];try{$s=$db->prepare("SELECT * FROM bh_sessions WHERE venue_id=? ORDER BY day_of_week,start_time");$s->execute([(int)$venue['id']]);$venue['sessions']=$s->fetchAll();}catch(Throwable $ignored){}}
 }unset($c,$venue);
 $bookings=[];try{$bookingCheck=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_booking_reservations','bh_booking_slots','bh_users')");if((int)$bookingCheck->fetchColumn()===3){$r=$db->prepare("SELECT br.id,br.status,br.quantity,br.created_at,bs.starts_at,bs.ends_at,bs.activity_id,bs.venue_id,a.title,v.venue_name,u.email FROM bh_booking_reservations br JOIN bh_booking_slots bs ON bs.id=br.slot_id JOIN bh_activities a ON a.id=bs.activity_id JOIN bh_venues v ON v.id=bs.venue_id JOIN bh_users u ON u.id=br.user_id WHERE a.organiser_id=? ORDER BY bs.starts_at DESC,br.id DESC");$r->execute([$oid]);$bookings=$r->fetchAll();}}catch(Throwable $ignored){}
 $faqs=[];try{$f=$db->prepare("SELECT * FROM bh_leader_faqs WHERE organiser_id=? AND status<>'archived' ORDER BY sort_order,id");$f->execute([$oid]);$faqs=$f->fetchAll();}catch(Throwable $ignored){}
 $expertise=[];try{$e=$db->prepare("SELECT topic_key FROM bh_leader_expertise WHERE organiser_id=? AND enabled=1 ORDER BY topic_key");$e->execute([$oid]);$expertise=$e->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $ignored){}
 $categoryOptions=lpTableOptions($db,['bh_categories','bh_activity_categories','bh_activity_categories_options'],['key','slug','id'],['name','label','title','category']);
if(!$categoryOptions){try{$cq=$db->query("SELECT DISTINCT TRIM(category) AS category FROM bh_activities WHERE category IS NOT NULL AND TRIM(category)<>'' ORDER BY category ASC");$categoryOptions=$cq->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $ignored){}}
if(!$categoryOptions)$categoryOptions=['Baby & toddler','Classes & groups','Music & singing','Sport & movement','Arts & crafts','Messy play','Dance','Outdoor activities','Family wellbeing','SEND & additional needs','Pregnancy & new parents','Other'];
$venues=[];try{$vq=$db->query("SELECT id,venue_name,address,town,region,postcode,latitude,longitude FROM bh_venues WHERE venue_name IS NOT NULL AND TRIM(venue_name)<>'' ORDER BY town,venue_name");$venues=$vq->fetchAll();}catch(Throwable $ignored){}
$tagMap=lpTableOptions($db,['bh_tags','bh_activity_tags'],['key','slug','id'],['name','label','title','tag']);
$tagOptions=$tagMap?array_values($tagMap):[];
if(!$tagOptions){
 try{
  $tc=$db->query("SELECT DISTINCT TRIM(tags) AS tags FROM bh_activities WHERE tags IS NOT NULL AND TRIM(tags)<>'' ORDER BY tags ASC");
  foreach($tc->fetchAll(PDO::FETCH_COLUMN) as $raw){
   foreach(preg_split('/[,|]+/',(string)$raw) as $tag){$tag=trim($tag);if($tag!==''&&!in_array($tag,$tagOptions,true))$tagOptions[]=$tag;}
  }
 }catch(Throwable $ignored){}
}
sort($tagOptions,SORT_NATURAL|SORT_FLAG_CASE);
lp(200,['ok'=>true,'organisation'=>$org,'classes'=>$classes,'bookings'=>$bookings,'faqs'=>$faqs,'expertise_topics'=>$expertiseTopics,'expertise'=>$expertise,'accessibility_options'=>$accessibilityOptions,'age_range_options'=>$ageRangeOptions,'category_options'=>$categoryOptions,'tag_options'=>$tagOptions,'venues'=>$venues,'max_images'=>$imageLimit,'is_pro'=>$isPro,'faq_limit'=>$faqLimit]);
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
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='import_csv'){
 $file=$_FILES['csv']??null;
 if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) lp(422,['ok'=>false,'error'=>'csv_upload_failed','message'=>'Please choose a CSV file to upload.']);
 if((int)$file['size']>5*1024*1024) lp(422,['ok'=>false,'error'=>'csv_too_large','message'=>'The CSV file must be 5MB or smaller.']);
 $fh=@fopen($file['tmp_name'],'r'); if(!$fh) lp(422,['ok'=>false,'error'=>'csv_unreadable','message'=>'The CSV file could not be read.']);
 $headers=fgetcsv($fh); if(!$headers) lp(422,['ok'=>422,'error'=>'csv_empty','message'=>'The CSV file is empty.']);
 $headers=array_map(fn($v)=>strtolower(trim((string)$v)), $headers);
 $required=['title','description','category','age_range','venue_name','town'];
 foreach($required as $requiredCol) if(!in_array($requiredCol,$headers,true)) lp(422,['ok'=>false,'error'=>'csv_columns_missing','message'=>'The CSV is missing the required column: '.$requiredCol]);
 $allowed=array_flip(['title','description','category','age_range','age_min_months','age_max_months','tags','accessibility','price_from','price_per_family','price_per_session','price_free','booking_url','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know','schedule','venue_name','address','town','region','postcode','latitude','longitude','image_1','image_2','image_3']);
 $unknown=array_values(array_diff($headers,array_keys($allowed))); if($unknown) lp(422,['ok'=>false,'error'=>'csv_unknown_columns','message'=>'The CSV contains unsupported columns: '.implode(', ',$unknown)]);
 $rows=[];$line=1;while(($row=fgetcsv($fh))!==false){$line++;if(count($row)===1 && trim((string)$row[0])==='')continue;$item=[];foreach($headers as $i=>$key)$item[$key]=trim((string)($row[$i]??''));$rows[]=['line'=>$line,'data'=>$item];}
 fclose($fh); if(!$rows) lp(422,['ok'=>false,'error'=>'csv_empty_rows','message'=>'The CSV contains no data rows.']);
 if(count($rows)>500) lp(422,['ok'=>false,'error'=>'csv_too_many_rows','message'=>'You can import up to 500 classes at a time.']);
 $errors=[];$created=0;$createdIds=[];$db->beginTransaction();
 try{
  foreach($rows as $entry){
   $line=$entry['line'];$r=$entry['data'];$title=$r['title'];$description=$r['description'];$category=$r['category'];$age=$r['age_range'];$venueName=$r['venue_name'];$town=$r['town'];
   if($title===''||$description===''||$category===''||$age===''||$venueName===''||$town===''){ $errors[]='Row '.$line.': title, description, category, age_range, venue_name and town are required.';continue; }
   $ageMin=(int)($r['age_min_months']!==''?$r['age_min_months']:0);$ageMax=(int)($r['age_max_months']!==''?$r['age_max_months']:0);
   if($ageMin<0||$ageMax<0||$ageMax<$ageMin||$ageMax>216){$errors[]='Row '.$line.': invalid age_min_months/age_max_months.';continue;}
   $priceFree=in_array(strtolower($r['price_free']),['1','yes','true','y'],true)?1:0;$price=$r['price_from']===''?null:(float)$r['price_from'];
   $perFamily=in_array(strtolower($r['price_per_family']),['1','yes','true','y'],true)?1:0;$perSession=in_array(strtolower($r['price_per_session']),['1','yes','true','y'],true)?1:0;
   if($priceFree){$price=null;$perFamily=0;$perSession=0;}elseif($price===null||$price<0||($perFamily+$perSession)!==1){$errors[]='Row '.$line.': choose a price and exactly one of price_per_family or price_per_session, or set price_free to 1.';continue;}
   $bool=function($v){return in_array(strtolower(trim((string)$v)),['1','yes','true','y'],true)?1:0;};
   $access=$r['accessibility']===''?[]:array_values(array_filter(array_map('trim',preg_split('/[|,]+/',$r['accessibility']))));$accessJson=json_encode($access,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   $photos=array_values(array_filter([$r['image_1'],$r['image_2'],$r['image_3']],fn($v)=>$v!==''));if(count($photos)>$imageLimit){$errors[]='Row '.$line.': Basic plan allows up to '.$imageLimit.' images.';continue;}foreach($photos as $photo)if(!filter_var($photo,FILTER_VALIDATE_URL)){$errors[]='Row '.$line.': image URLs must be valid URLs.';continue 2;}
   $scheduleRaw=$r['schedule'];$schedule=[];foreach(array_filter(array_map('trim',explode('|',$scheduleRaw))) as $part){if(!preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\\s+([01]\\d|2[0-3]):[0-5]\\d-([01]\\d|2[0-3]):[0-5]\\d$/i',$part,$m)){$errors[]='Row '.$line.': schedule must use e.g. Monday 10:00-11:00.';continue 2;}$times=preg_split('/\\s+/',$part);[$start,$end]=explode('-',$times[1]);if($end<=$start){$errors[]='Row '.$line.': schedule end time must be after start time.';continue 2;}$schedule[]=['day'=>ucfirst(strtolower($times[0])),'start'=>$start,'end'=>$end];}
   if(!$schedule){$errors[]='Row '.$line.': schedule is required.';continue;}
   $slugBase=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));if($slugBase==='')$slugBase='class';$slug=$slugBase;$suffix=2;$sq=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");while(true){$sq->execute([$slug]);if(!$sq->fetch())break;$slug=$slugBase.'-'.$suffix++;}
   $colsCheck=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities'")->fetchAll(PDO::FETCH_COLUMN);$hasCounty=in_array('county',$colsCheck,true);
   $values=[$title,$slug,$description,$category,$age,$ageMin,$ageMax,$accessJson,$r['tags'],$price,$perFamily,$perSession,$r['booking_url'],$r['schedule'],$bool($r['booking_required']),$bool($r['drop_in_welcome']),$bool($r['trial_available']),$bool($r['term_time_only']),$bool($r['holiday_sessions']),$bool($r['siblings_welcome']),$r['what_to_bring'],$r['good_to_know'],'draft',$oid];
   if($hasCounty){$q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,age_min_months,age_max_months,accessibility,tags,county,price_from,price_per_family,price_per_session,booking_url,weekly_schedule,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome,what_to_bring,good_to_know,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$q->execute(array_merge([$title,$slug,$description,$category,$age,$ageMin,$ageMax,$accessJson,$r['tags'],''],$values[9:]));}
   else{$q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,age_min_months,age_max_months,accessibility,tags,price_from,price_per_family,price_per_session,booking_url,weekly_schedule,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome,what_to_bring,good_to_know,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$q->execute($values);}
   $activityId=(int)$db->lastInsertId();if($photos)$db->prepare("UPDATE bh_activities SET image_path=? WHERE id=?")->execute([$photos[0],$activityId]);
   $vq=$db->prepare("INSERT INTO bh_venues (venue_name,address,town,region,postcode,latitude,longitude,notes,activity_id) VALUES (?,?,?,?,?,?,?,?,?)");$vq->execute([$venueName,$r['address'],$town,$r['region'],$r['postcode'],$r['latitude']===''?null:$r['latitude'],$r['longitude']===''?null:$r['longitude'],'',$activityId]);
   $created++;$createdIds[]=$activityId;
  }
  if($errors){$db->rollBack();lp(422,['ok'=>false,'error'=>'csv_validation_failed','message'=>'No classes were imported because one or more rows contain errors.','errors'=>$errors]);}
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();lp(500,['ok'=>false,'error'=>'csv_import_failed','message'=>'The CSV could not be imported: '.$e->getMessage()]);}
 lp(201,['ok'=>true,'message'=>$created.' class'.($created===1?'':'es').' imported successfully.','created'=>$created,'ids'=>$createdIds]);
}

$b=json_decode(file_get_contents('php://input'),true);if(!is_array($b))lp(400,['ok'=>false,'error'=>'invalid_json']);$action=$b['action']??'';
if($action==='save_listing_photos'){$activityId=(int)($b['activity_id']??0);$photos=is_array($b['photos']??null)?array_values(array_unique(array_filter(array_map('trim',$b['photos'])))):[];if(count($photos)>$imageLimit)lp(422,['ok'=>false,'error'=>'too_many_photos','message'=>'Your plan allows up to '.$imageLimit.' photos.']);$own=$db->prepare("SELECT id FROM bh_activities WHERE id=? AND organiser_id=? LIMIT 1");$own->execute([$activityId,$oid]);if(!$own->fetch())lp(404,['ok'=>false,'error'=>'activity_not_found']);$q=$db->prepare("UPDATE bh_activities SET gallery_images=? WHERE id=?");$q->execute([json_encode($photos,JSON_UNESCAPED_SLASHES),$activityId]);if($photos)$db->prepare("UPDATE bh_activities SET image_path=? WHERE id=?")->execute([$photos[0],$activityId]);lp(200,['ok'=>true,'photos'=>$photos]);}

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
 else{if(!$isPro){$count=$db->prepare("SELECT COUNT(*) FROM bh_leader_faqs WHERE organiser_id=? AND status<>'archived'");$count->execute([$oid]);if((int)$count->fetchColumn()>=5)lp(403,['ok'=>false,'error'=>'faq_limit_reached','message'=>'Free leaders can add up to 5 FAQs. Upgrade to Pro for unlimited FAQs.']);}$q=$db->prepare("INSERT INTO bh_leader_faqs (organiser_id,activity_id,question,answer,status,sort_order) VALUES (?,?,?,?,?,?)");$q->execute([$oid,$activityId,$question,$answer,$status,$sort]);}
 lp(200,['ok'=>true,'message'=>'FAQ saved.']);
}
if($action==='delete_faq'){
 $id=(int)($b['id']??0);$q=$db->prepare("UPDATE bh_leader_faqs SET status='archived' WHERE id=? AND organiser_id=?");$q->execute([$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'faq_not_found']);lp(200,['ok'=>true,'message'=>'FAQ archived.']);
}
function lpFindOptionTable(PDO $db,string $type): ?array{
 $tables=$type==='category'?['bh_categories','bh_activity_categories','bh_activity_categories_options']:['bh_tags','bh_activity_tags'];
 $labels=['name','label','title',$type==='category'?'category':'tag'];
 foreach($tables as $table){
  try{
   $cq=$db->prepare("SELECT COLUMN_NAME,EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
   $cq->execute([$table]);$cols=$cq->fetchAll(PDO::FETCH_ASSOC);if(!$cols)continue;
   $names=array_column($cols,'COLUMN_NAME');$label=array_values(array_intersect($labels,$names))[0]??null;if(!$label)continue;
   $key=array_values(array_intersect(['key','slug','id'],$names))[0]??$label;
   return ['table'=>$table,'label'=>$label,'key'=>$key,'columns'=>$cols];
  }catch(Throwable $ignored){}
 }
 return null;
}
if($action==='add_option'){
 $type=($b['type']??'')==='tag'?'tag':'category';$label=trim((string)($b['value']??''));if($label==='')lp(422,['ok'=>false,'error'=>'option_required','message'=>'Enter a name.']);
 $meta=lpFindOptionTable($db,$type);if(!$meta)lp(422,['ok'=>false,'error'=>'option_table_missing','message'=>'The dynamic options table is not available.']);
 $table=$meta['table'];$labelCol=$meta['label'];$keyCol=$meta['key'];
 try{
  $q=$db->prepare("SELECT ".$labelCol." FROM ".$table." WHERE LOWER(TRIM(".$labelCol."))=LOWER(TRIM(?)) LIMIT 1");$q->execute([$label]);$existing=$q->fetchColumn();
  if($existing!==false)lp(200,['ok'=>true,'value'=>(string)$existing,'existing'=>true,'type'=>$type]);
  if($keyCol!==$labelCol&&$keyCol!=='id'){
   $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $label),'-'));if($slug==='')$slug='option';
   $base=$slug;$n=2;$q=$db->prepare("SELECT ".$keyCol." FROM ".$table." WHERE ".$keyCol."=? LIMIT 1");while(true){$q->execute([$slug]);if(!$q->fetch())break;$slug=$base.'-'.$n++;}
   $q=$db->prepare("INSERT INTO ".$table." (".$keyCol.",".$labelCol.") VALUES (?,?)");$q->execute([$slug,$label]);
  }else{$q=$db->prepare("INSERT INTO ".$table." (".$labelCol.") VALUES (?)");$q->execute([$label]);}
  lp(201,['ok'=>true,'value'=>$label,'existing'=>false,'type'=>$type]);
 }catch(Throwable $e){lp(500,['ok'=>false,'error'=>'option_save_failed','message'=>'Could not add this option to the dynamic options table.']);}
}
if($action==='tag_suggestions'){
 $category=trim((string)($b['category']??''));$suggestions=[];
 if($category!==''){try{$q=$db->prepare("SELECT tags FROM bh_activities WHERE organiser_id<>? AND FIND_IN_SET(?, REPLACE(category, ', ', ',')) AND tags IS NOT NULL AND TRIM(tags)<>'' ORDER BY id DESC LIMIT 100");$q->execute([$oid,$category]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $raw){foreach(preg_split('/[,|]+/',(string)$raw) as $tag){$tag=trim($tag);if($tag!==''&&!in_array($tag,$suggestions,true))$suggestions[]=$tag;}}}catch(Throwable $ignored){}}
 sort($suggestions,SORT_NATURAL|SORT_FLAG_CASE);lp(200,['ok'=>true,'suggestions'=>array_slice($suggestions,0,12)]);
}
if($action==='create_listing'){
 $photos=is_array($b['photos']??null)?array_values(array_filter(array_map('strval',$b['photos']))):[];$title=trim((string)($b['title']??''));$description=trim((string)($b['description']??''));$category=trim((string)($b['category']??''));if($category==='__new__')$category=trim((string)($b['new_category']??''));$age=trim((string)($b['age_range']??''));$ageMin=(int)($b['age_min_months']??0);$ageMax=(int)($b['age_max_months']??0);$tags=trim((string)($b['tags']??''));$accessibility=is_array($b['accessibility']??null)?array_values(array_filter(array_map('strval',$b['accessibility']))):[];$accessibilityJson=json_encode($accessibility,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$bookingRequired=!empty($b['booking_required'])?1:0;$dropInWelcome=!empty($b['drop_in_welcome'])?1:0;$trialAvailable=!empty($b['trial_available'])?1:0;$termTimeOnly=!empty($b['term_time_only'])?1:0;$holidaySessions=!empty($b['holiday_sessions'])?1:0;$siblingsWelcome=!empty($b['siblings_welcome'])?1:0;$schedule=is_array($b['schedule']??null)?array_values(array_filter($b['schedule'],fn($r)=>is_array($r)&&!empty($r['day'])&&!empty($r['start'])&&!empty($r['end']))):[];$validDays=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];$seenSessions=[];foreach($schedule as $sr){$day=(string)$sr['day'];$start=(string)$sr['start'];$end=(string)$sr['end'];if(!in_array($day,$validDays,true)||!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/',$start)||!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/',$end)||$end<=$start)lp(422,['ok'=>false,'error'=>'invalid_schedule','message'=>'Please check each session day and time.']);$key=$day.'|'.$start.'|'.$end;if(isset($seenSessions[$key]))lp(422,['ok'=>false,'error'=>'duplicate_schedule','message'=>'The same session has been added more than once.']);$seenSessions[$key]=true;}if(!$schedule)lp(422,['ok'=>false,'error'=>'schedule_required','message'=>'Add at least one weekly session.']);$scheduleJson=json_encode($schedule,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$whatToBring=trim((string)($b['what_to_bring']??''));$goodToKnow=trim((string)($b['good_to_know']??''));$price=($b['price_from']??'')===''?null:(float)$b['price_from'];$pricePerFamily=!empty($b['price_per_family'])?1:0;$pricePerSession=!empty($b['price_per_session'])?1:0;$priceFree=!empty($b['price_free'])?1:0;$url=trim((string)($b['booking_url']??''));$existingVenueId=(int)($b['existing_venue_id']??0);$venueName=trim((string)($b['venue_name']??''));$address=trim((string)($b['address']??''));$town=trim((string)($b['town']??''));$region=trim((string)($b['region']??''));$postcode=trim((string)($b['postcode']??''));$lat=trim((string)($b['latitude']??''));$lng=trim((string)($b['longitude']??''));
 if($title==='')lp(422,['ok'=>false,'error'=>'title_required']);if($description==='')lp(422,['ok'=>false,'error'=>'description_required']);if($category==='')lp(422,['ok'=>false,'error'=>'category_required']);if($tags==='')lp(422,['ok'=>false,'error'=>'tags_required']);if($ageMin<0||$ageMax<0||$ageMax<$ageMin||$ageMax>216)lp(422,['ok'=>false,'error'=>'invalid_age_range','message'=>'Please enter a valid age range.']);if($priceFree){$price=null;$pricePerFamily=0;$pricePerSession=0;}elseif($price===null||$price<0||($pricePerFamily+$pricePerSession)!==1)lp(422,['ok'=>false,'error'=>'invalid_pricing','message'=>'Choose a price and either Per Session or Per Family, or select Free.']);if(count($photos)>$imageLimit)lp(422,['ok'=>false,'error'=>'too_many_photos','message'=>'Your plan allows up to '.$imageLimit.' photos.']);foreach($photos as $photo){if(!filter_var($photo,FILTER_VALIDATE_URL))lp(422,['ok'=>false,'error'=>'invalid_photo_url','message'=>'Each image must be a valid URL.']);}if($existingVenueId<1 && ($venueName===''||$town===''))lp(422,['ok'=>false,'error'=>'venue_required']);$db->beginTransaction();
 try{$baseSlug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $title),'-'));if($baseSlug==='')$baseSlug='class';$slug=$baseSlug;$suffix=2;$slugCheck=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");while(true){$slugCheck->execute([$slug]);if(!$slugCheck->fetch())break;$slug=$baseSlug.'-'.$suffix++;}
  $countyExists=false;try{$cc=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$cc->execute();$countyExists=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
  try{$db->exec("ALTER TABLE bh_activities ADD COLUMN tags TEXT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN weekly_schedule TEXT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN age_min_months INT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN age_max_months INT NULL");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN price_per_family TINYINT(1) NOT NULL DEFAULT 0");}catch(Throwable $ignored){}try{$db->exec("ALTER TABLE bh_activities ADD COLUMN price_per_session TINYINT(1) NOT NULL DEFAULT 0");}catch(Throwable $ignored){}foreach(["booking_required"=>"TINYINT(1) NOT NULL DEFAULT 0","drop_in_welcome"=>"TINYINT(1) NOT NULL DEFAULT 0","trial_available"=>"TINYINT(1) NOT NULL DEFAULT 0","term_time_only"=>"TINYINT(1) NOT NULL DEFAULT 0","holiday_sessions"=>"TINYINT(1) NOT NULL DEFAULT 0","siblings_welcome"=>"TINYINT(1) NOT NULL DEFAULT 0","what_to_bring"=>"VARCHAR(250) NULL","good_to_know"=>"VARCHAR(250) NULL"] as $col=>$definition){try{$db->exec("ALTER TABLE bh_activities ADD COLUMN $col $definition");}catch(Throwable $ignored){}}
if($countyExists){$q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,age_min_months,age_max_months,accessibility,tags,county,price_from,price_per_family,price_per_session,booking_url,weekly_schedule,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome,what_to_bring,good_to_know,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$q->execute([$title,$slug,$description,$category,$age,$ageMin,$ageMax,$accessibilityJson,$tags,'',$price,$pricePerFamily,$pricePerSession,'',$scheduleJson,$bookingRequired,$dropInWelcome,$trialAvailable,$termTimeOnly,$holidaySessions,$siblingsWelcome,$whatToBring,$goodToKnow,'draft',$oid]);}else{$q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,age_min_months,age_max_months,accessibility,tags,price_from,price_per_family,price_per_session,booking_url,weekly_schedule,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome,what_to_bring,good_to_know,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");$q->execute([$title,$slug,$description,$category,$age,$ageMin,$ageMax,$accessibilityJson,$tags,$price,$pricePerFamily,$pricePerSession,'',$scheduleJson,$bookingRequired,$dropInWelcome,$trialAvailable,$termTimeOnly,$holidaySessions,$siblingsWelcome,$whatToBring,$goodToKnow,'draft',$oid]);}
  $activityId=(int)$db->lastInsertId();if($photos){try{$db->prepare("UPDATE bh_activities SET image_path=? WHERE id=?")->execute([$photos[0],$activityId]);}catch(Throwable $ignored){}}if($existingVenueId>0){$v=$db->prepare("SELECT id FROM bh_venues WHERE id=? LIMIT 1");$v->execute([$existingVenueId]);if(!$v->fetch())throw new RuntimeException('Selected venue could not be found.');$linked=$db->prepare("SELECT id FROM bh_venues WHERE id=? AND activity_id=?");$linked->execute([$existingVenueId,$activityId]);if(!$linked->fetch()){$src=$db->prepare("SELECT venue_name,address,town,region,postcode,latitude,longitude,notes FROM bh_venues WHERE id=?");$src->execute([$existingVenueId]);$sv=$src->fetch();$db->prepare("INSERT INTO bh_venues (venue_name,address,town,region,postcode,latitude,longitude,notes,activity_id) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$sv['venue_name'],$sv['address'],$sv['town'],$sv['region'],$sv['postcode'],$sv['latitude'],$sv['longitude'],$sv['notes'],$activityId]);}}else{$v=$db->prepare("INSERT INTO bh_venues (venue_name,address,town,region,postcode,latitude,longitude,notes,activity_id) VALUES (?,?,?,?,?,?,?,?,?)");$v->execute([$venueName,$address,$town,$region,$postcode,$lat===''?null:$lat,$lng===''?null:$lng,'',$activityId]);}$db->commit();
  if($leaderEmail!==''){try{$safeTitle=htmlspecialchars($title,ENT_QUOTES,'UTF-8');$safeOrg=htmlspecialchars((string)($org['organisation_name']??''),ENT_QUOTES,'UTF-8');$html='<div style="font-family:Arial,sans-serif;line-height:1.6;color:#26352b;max-width:640px;margin:0 auto"><h1 style="color:#617261">Welcome to Bubba Hub</h1><p>Hi'.($safeOrg!==''?' '.$safeOrg:'').',</p><p>Thanks for adding <strong>'.$safeTitle.'</strong> to Bubba Hub.</p><p>Your listing has been submitted and is currently awaiting review. We will let you know when there is an update.</p><p>The Bubba Hub team</p></div>';$plain="Welcome to Bubba Hub\n\nThanks for adding {$title} to Bubba Hub.\n\nYour listing has been submitted and is currently awaiting review.\n\nThe Bubba Hub team";bh_send_smtp_mail($leaderEmail,'Welcome to Bubba Hub – your listing has been submitted',$html,$plain);}catch(Throwable $ignored){}}
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
