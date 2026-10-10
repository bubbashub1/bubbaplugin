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
 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='categories'){
  $names=[];try{$q=$db->query("SELECT name FROM bh_categories ORDER BY name");$names=$q->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $ignored){}
  $q=$db->query("SELECT DISTINCT category FROM bh_activities WHERE category IS NOT NULL AND category<>''");
  foreach($q->fetchAll(PDO::FETCH_COLUMN) as $entry){foreach(explode(',',(string)$entry) as $name){$name=trim($name);if($name!=='')$names[]=$name;}}
  $unique=[];foreach($names as $name){$key=mb_strtolower(trim((string)$name));if($key!=='')$unique[$key]=trim((string)$name);}
  natcasesort($unique);bh_admin_response(200,['ok'=>true,'data'=>array_values($unique)]);
 }
 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='leaders'){
  $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
  $join=$hasUserId?'u.id=o.user_id':'u.email=o.email';
  $q=$db->query("SELECT o.id,o.organisation_name,u.email FROM bh_organisers o JOIN bh_users u ON ".$join." WHERE u.role='leader' AND u.status='active' AND o.status<>'suspended' ORDER BY o.organisation_name,o.id");
  bh_admin_response(200,['ok'=>true,'data'=>$q->fetchAll(),'csrf'=>$_SESSION['bh_listing_csrf']]);
 }
 $accessibilityColumn=false;
 try{$ac=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='accessibility'");$accessibilityColumn=((int)$ac->fetchColumn())>0;}catch(Throwable $ignored){}
 if($_SERVER['REQUEST_METHOD']==='GET'){
  $id=isset($_GET['id'])?(int)$_GET['id']:0;
  if($id>0){
   $countyColumn=false;
   try{$cc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$countyColumn=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
   $countySelect=$countyColumn?'a.county':'NULL AS county';
   $q=$db->prepare("SELECT a.*,$countySelect,o.organisation_name,o.email,o.phone,o.website,v.id venue_id,v.venue_name,v.address,v.town,v.region,v.postcode,v.latitude,v.longitude FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_venues v ON v.activity_id=a.id WHERE a.id=? ORDER BY v.id LIMIT 1");$q->execute([$id]);$a=$q->fetch();if(!$a)bh_admin_response(404,['ok'=>false,'error'=>'Activity not found.']);
   $q=$db->prepare("SELECT id,venue_name,address,town,region,postcode,latitude,longitude FROM bh_venues WHERE activity_id=? ORDER BY id");$q->execute([$id]);$a['venues']=$q->fetchAll();$rawAccessibility=$a['accessibility']??null;$a['accessibility']=[];if($accessibilityColumn&&$rawAccessibility!==null){$decoded=json_decode((string)$rawAccessibility,true);$a['accessibility']=is_array($decoded)?$decoded:[];}$a['sessions']=[];foreach($a['venues'] as $vi=>$venue){$sq=$db->prepare("SELECT id,venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date FROM bh_sessions WHERE venue_id=? ORDER BY day_of_week,start_time");$sq->execute([(int)$venue['id']]);foreach($sq->fetchAll() as $session){$session['venue_index']=$vi;$a['sessions'][]=$session;}}$a['id']=(int)$a['id'];$a['price_from']=$a['price_from']!==null?(float)$a['price_from']:null;$a['latitude']=$a['latitude']!==null?(float)$a['latitude']:null;$a['longitude']=$a['longitude']!==null?(float)$a['longitude']:null;
   bh_admin_response(200,['ok'=>true,'data'=>$a]);
  }
  $countyColumn=false;
  try{$cc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$countyColumn=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
  $countySelect=$countyColumn?'a.county':'NULL AS county';
  $stmt=$db->query("SELECT a.id,a.title,a.category,a.age_range,$countySelect,a.price_from,a.status,o.id organiser_id,COALESCE(o.organisation_name,'') AS organisation_name,MIN(v.town) AS town,MIN(v.region) AS region,COUNT(DISTINCT v.id) venue_count,(SELECT GROUP_CONCAT(DISTINCT CONCAT(CASE s.day_of_week WHEN 1 THEN 'Mon' WHEN 2 THEN 'Tue' WHEN 3 THEN 'Wed' WHEN 4 THEN 'Thu' WHEN 5 THEN 'Fri' WHEN 6 THEN 'Sat' WHEN 7 THEN 'Sun' END,' ',LEFT(s.start_time,5)) ORDER BY s.day_of_week,s.start_time SEPARATOR ', ') FROM bh_sessions s INNER JOIN bh_venues sv ON sv.id=s.venue_id WHERE sv.activity_id=a.id) AS session_summary FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id LEFT JOIN bh_venues v ON v.activity_id=a.id WHERE a.status<>'archived' GROUP BY a.id,a.title,a.category,a.age_range,a.price_from,a.status,o.id,o.organisation_name".($countyColumn?",a.county":"")." ORDER BY a.updated_at DESC,a.id DESC");bh_admin_response(200,['ok'=>true,'data'=>$stmt->fetchAll(),'csrf'=>$_SESSION['bh_listing_csrf']]);
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')bh_admin_response(405,['ok'=>false,'error'=>'GET or POST required.']);
 $input=json_decode((string)file_get_contents('php://input'),true);if(!is_array($input))bh_admin_response(400,['ok'=>false,'error'=>'Invalid JSON.']);
 $id=(int)($input['id']??0);
 $listingAction=$input['action']??'';
 if(in_array($listingAction,['delete','assign_leader'],true)){
  if(!hash_equals($_SESSION['bh_listing_csrf'],(string)($input['csrf']??'')))bh_admin_response(403,['ok'=>false,'error'=>'Please refresh the page and try again.']);
  if($id<1)bh_admin_response(422,['ok'=>false,'error'=>'Choose a listing.']);
  $db->beginTransaction();
  try{
   $q=$db->prepare("SELECT id FROM bh_activities WHERE id=? AND status<>'archived' FOR UPDATE");$q->execute([$id]);
   if(!$q->fetch()){$db->rollBack();bh_admin_response(404,['ok'=>false,'error'=>'Listing not found.']);}
   if($listingAction==='delete'){
    $db->prepare("UPDATE bh_activities SET status='archived' WHERE id=?")->execute([$id]);
   }else{
    $organiserId=(int)($input['organiser_id']??0);
    $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
    $join=$hasUserId?'u.id=o.user_id':'u.email=o.email';
    $q=$db->prepare("SELECT o.id FROM bh_organisers o JOIN bh_users u ON ".$join." WHERE o.id=? AND u.role='leader' AND u.status='active' AND o.status<>'suspended' LIMIT 1");$q->execute([$organiserId]);
    if(!$q->fetch()){$db->rollBack();bh_admin_response(422,['ok'=>false,'error'=>'Choose an active registered leader organisation.']);}
    $db->prepare("UPDATE bh_activities SET organiser_id=? WHERE id=?")->execute([$organiserId,$id]);
   }
   $db->commit();bh_admin_response(200,['ok'=>true]);
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }

 $activityCountyColumn=false;
 try{$cc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$activityCountyColumn=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}$title=trim((string)($input['title']??''));$description=trim((string)($input['description']??''));$category=trim((string)($input['category']??''));$ageRange=trim((string)($input['age_range']??''));$county=trim((string)($input['county']??''));if($county!==''&&!in_array($county,['Devon','Cornwall','Plymouth','Torbay'],true))$county='';$priceFrom=($input['price_from']??'')===''?null:(float)$input['price_from'];$bookingUrl=trim((string)($input['booking_url']??''));$organisationName=trim((string)($input['organisation_name']??''));$email=trim((string)($input['email']??''));$phone=trim((string)($input['phone']??''));$website=trim((string)($input['website']??''));$venueName=trim((string)($input['venue_name']??''));$address=trim((string)($input['address']??''));$town=trim((string)($input['town']??''));$region=trim((string)($input['region']??''));$postcode=trim((string)($input['postcode']??''));$latitude=($input['latitude']??'')===''?null:(float)$input['latitude'];$longitude=($input['longitude']??'')===''?null:(float)$input['longitude'];$imagePath=trim((string)($input['image_path']??''));$status=in_array(($input['status']??'published'),['published','draft'],true)?$input['status']:'published';$accessibility=json_encode(array_values(array_unique(array_filter((array)($input['accessibility']??[]),'is_string'))),JSON_UNESCAPED_SLASHES);
 if($title===''||$organisationName===''||$venueName===''||$town===''||$category==='')bh_admin_response(422,['ok'=>false,'error'=>'Title, organisation, category, venue and town are required.']);
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
 $optional=array_intersect_key($optional,array_flip($cols));
 $db->beginTransaction();
 try{
  if($id>0){$q=$db->prepare("SELECT organiser_id FROM bh_activities WHERE id=?");$q->execute([$id]);$existing=$q->fetch();if(!$existing)bh_admin_response(404,['ok'=>false,'error'=>'Activity not found.']);$organiserId=(int)$existing['organiser_id'];$q=$db->prepare("UPDATE bh_organisers SET organisation_name=?,email=?,phone=?,website=? WHERE id=?");$q->execute([$organisationName,$email,$phone,$website,$organiserId]);if($activityCountyColumn){$q=$db->prepare("UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,county=?,price_from=?,booking_url=?,image_path=?,status=?,accessibility=? WHERE id=?");$q->execute([$title,$description,$category,$ageRange,$county,$priceFrom,$bookingUrl,$imagePath,$status,$accessibility,$id]);}else{$q=$db->prepare("UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,price_from=?,booking_url=?,image_path=?,status=?,accessibility=? WHERE id=?");$q->execute([$title,$description,$category,$ageRange,$priceFrom,$bookingUrl,$imagePath,$status,$accessibility,$id]);}$q=$db->prepare("SELECT id FROM bh_venues WHERE activity_id=? ORDER BY id LIMIT 1");$q->execute([$id]);$venueId=(int)$q->fetchColumn();if(!$venueId){$q=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$q->execute([$id,$venueName,$address,$town,$region,$postcode,$latitude,$longitude,'']);$venueId=(int)$db->lastInsertId();}else{$q=$db->prepare("UPDATE bh_venues SET venue_name=?,address=?,town=?,region=?,postcode=?,latitude=?,longitude=? WHERE id=?");$q->execute([$venueName,$address,$town,$region,$postcode,$latitude,$longitude,$venueId]);}
   $additionalVenues=[];foreach((array)($input['venues']??[]) as $venue){$vn=trim((string)($venue['venue_name']??''));$vt=trim((string)($venue['town']??''));if($vn===''||$vt==='')continue;$vid=(int)($venue['id']??0);$va=trim((string)($venue['address']??''));$vr=trim((string)($venue['region']??''));$vp=trim((string)($venue['postcode']??''));$vlat=($venue['latitude']??'')===''?null:(float)$venue['latitude'];$vlon=($venue['longitude']??'')===''?null:(float)$venue['longitude'];if($vid>0){$vq=$db->prepare("UPDATE bh_venues SET venue_name=?,address=?,town=?,region=?,postcode=?,latitude=?,longitude=? WHERE id=? AND activity_id=?");$vq->execute([$vn,$va,$vt,$vr,$vp,$vlat,$vlon,$vid,$id]);}else{$vq=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$vq->execute([$id,$vn,$va,$vt,$vr,$vp,$vlat,$vlon,'']);$vid=(int)$db->lastInsertId();}$additionalVenues[]=$vid;}$venueIds=[$venueId,...$additionalVenues];foreach($venueIds as $vid)$db->prepare("DELETE FROM bh_sessions WHERE venue_id=?")->execute([$vid]);$action='updated';
  }else{
   $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));$baseSlug=$slug;$n=2;while(true){$q=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");$q->execute([$slug]);if(!$q->fetch())break;$slug=$baseSlug.'-'.$n++;}
   $q=$db->prepare("SELECT id FROM bh_organisers WHERE organisation_name=? LIMIT 1");$q->execute([$organisationName]);$organiserId=$q->fetchColumn();
   if(!$organiserId){$oslug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$organisationName),'-'));$q=$db->prepare("INSERT INTO bh_organisers (organisation_name,slug,description,email,phone,website,status) VALUES (?,?,?,?,?,?, 'published')");$q->execute([$organisationName,$oslug,'',$email,$phone,$website]);$organiserId=(int)$db->lastInsertId();}
   if($activityCountyColumn){$q=$db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,county,price_from,booking_url,image_path,status,accessibility) VALUES (?,?,?,?,?,?,?,?,?,?,'published',?)");$q->execute([$organiserId,$title,$slug,$description,$category,$ageRange,$county,$priceFrom,$bookingUrl,$imagePath,$accessibility]);}else{$q=$db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,price_from,booking_url,image_path,status,accessibility) VALUES (?,?,?,?,?,?,?,?,?,'published',?)");$q->execute([$organiserId,$title,$slug,$description,$category,$ageRange,$priceFrom,$bookingUrl,$imagePath,$accessibility]);}$id=(int)$db->lastInsertId();$additionalVenues=[];foreach((array)($input['venues']??[]) as $venue){$vn=trim((string)($venue['venue_name']??''));$vt=trim((string)($venue['town']??''));if($vn===''||$vt==='')continue;$vq=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$vq->execute([$id,$vn,trim((string)($venue['address']??'')),$vt,trim((string)($venue['region']??'')),trim((string)($venue['postcode']??'')),($venue['latitude']??'')===''?null:(float)$venue['latitude'],($venue['longitude']??'')===''?null:(float)$venue['longitude'],'']);$additionalVenues[]=(int)$db->lastInsertId();}$q=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");$q->execute([$id,$venueName,$address,$town,$region,$postcode,$latitude,$longitude,'']);$venueId=(int)$db->lastInsertId();$action='created';
  }
  if($optional){$set=implode(',',array_map(static fn($k)=>"`".$k."`=?",array_keys($optional)));$q=$db->prepare("UPDATE bh_activities SET ".$set." WHERE id=?");$q->execute([...array_values($optional),$id]);}
  $sessionVenueIds=[$venueId,...$additionalVenues];foreach((array)($input['sessions']??[]) as $s){$venueIndex=(int)($s['venue_index']??0);$sessionVenueId=$sessionVenueIds[$venueIndex]??$venueId;$day=(int)($s['day_of_week']??0);$start=trim((string)($s['start_time']??''));$end=trim((string)($s['end_time']??''));if($day<1||$day>7||$start==='')continue;$duration=null;if($end!==''){$aa=strtotime($start);$bb=strtotime($end);if($aa!==false&&$bb!==false){$duration=(int)(($bb-$aa)/60);if($duration<0)$duration+=1440;}}$price=($s['price']??'')===''?$priceFrom:(float)$s['price'];$term=!empty($s['term_time_only'])?1:0;$frequency=trim((string)($s['frequency']??'weekly'))?:'weekly';$startDate=($s['start_date']??'')!==''?$s['start_date']:null;$endDate=($s['end_date']??'')!==''?$s['end_date']:null;$q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?)");$q->execute([$sessionVenueId,$day,$start,$end!==''?$end:null,$duration,$price,$term,$frequency,$startDate,$endDate]);}
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
  $db->commit();bh_admin_response(200,['ok'=>true,'id'=>$id,'action'=>$action]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}catch(Throwable $e){bh_admin_response(500,['ok'=>false,'error_type'=>get_class($e),'error'=>$e->getMessage()]);}
?>