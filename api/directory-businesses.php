<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $check=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_directory_businesses','bh_directory_business_listings','bh_organisers','bh_users','bh_activities')");$check->execute();
 if((int)$check->fetchColumn()!==5){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'business_directory_not_ready']);exit;}
 $q=trim((string)($_GET['q']??''));$category=trim((string)($_GET['category']??''));$town=trim((string)($_GET['town']??''));$region=trim((string)($_GET['region']??''));
 $orgColumns=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers'")->fetchAll(PDO::FETCH_COLUMN);
 $hasOwnerId=in_array('user_id',$orgColumns,true);$hasOrgEmail=in_array('email',$orgColumns,true);
 $leaderJoin=$hasOwnerId ? "u.id=o.user_id".($hasOrgEmail?" OR (o.user_id IS NULL AND LOWER(u.email)=LOWER(o.email))":"") : ($hasOrgEmail?"LOWER(u.email)=LOWER(o.email)":"1=0");
 $where=["b.status='published'"];$args=[];
 foreach([['q',"(b.title LIKE ? OR b.summary LIKE ? OR b.description LIKE ? OR b.category LIKE ? OR b.town LIKE ? OR o.organisation_name LIKE ?)"],['category','b.category = ?'],['town','b.town = ?'],['region','b.region = ?']] as [$key,$condition]){
  $value=$$key;if($value==='')continue;$where[]=$condition;
  if($key==='q'){for($i=0;$i<6;$i++)$args[]='%'.$value.'%';}else $args[]=$value;
 }
 $sql="SELECT b.id,b.title,b.category,b.summary,b.description,b.town,b.region,b.address,b.postcode,
 COALESCE(NULLIF(b.website_url,''),o.website) AS website,
 COALESCE(NULLIF(b.contact_email,''),o.email) AS contact_email,
 COALESCE(NULLIF(b.contact_phone,''),o.phone) AS contact_phone,
 o.organisation_name,o.logo_url,
 CASE WHEN u.id IS NOT NULL THEN
  (SELECT COUNT(*) FROM bh_activities la WHERE la.organiser_id=o.id AND LOWER(TRIM(COALESCE(la.status,''))) IN ('published','publish'))
 ELSE
  (SELECT COUNT(*) FROM bh_directory_business_listings bl INNER JOIN bh_activities a ON a.id=bl.activity_id AND LOWER(TRIM(COALESCE(a.status,''))) IN ('published','publish') WHERE bl.business_id=b.id)
 END AS linked_listing_count,
 CASE WHEN u.id IS NOT NULL THEN 'class_leader' ELSE 'business' END AS storefront_type
 FROM bh_directory_businesses b LEFT JOIN bh_organisers o ON o.id=b.organiser_id
 LEFT JOIN bh_users u ON (".$leaderJoin.") AND u.role='leader' AND u.status='active'
 WHERE ".implode(' AND ',$where)." ORDER BY b.title ASC LIMIT 300";
 $s=$db->prepare($sql);$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);

 // Every active leader account with an organiser profile gets a public storefront.
 // These are virtual directory entries: no duplicate business row or migration is needed.
 $hasVenueActivity=(bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_venues' AND COLUMN_NAME='activity_id'")->fetchColumn();
 $locationJoin=$hasVenueActivity?"LEFT JOIN (SELECT a.organiser_id,MAX(v.town) AS town,MAX(v.region) AS region FROM bh_activities a LEFT JOIN bh_venues v ON v.activity_id=a.id WHERE LOWER(TRIM(COALESCE(a.status,''))) IN ('published','publish') GROUP BY a.organiser_id) loc ON loc.organiser_id=o.id":"";
 $locationSelect=$hasVenueActivity?"loc.town,loc.region":"NULL AS town,NULL AS region";
 $orgSql="SELECT o.*,u.id AS leader_user_id,".$locationSelect."
 FROM bh_organisers o INNER JOIN bh_users u ON (".$leaderJoin.") AND u.role='leader' AND u.status='active'
 ".$locationJoin."
 WHERE NOT EXISTS (SELECT 1 FROM bh_directory_businesses existing WHERE existing.organiser_id=o.id AND existing.status='published')
 ORDER BY o.id";
 $leaders=$db->query($orgSql)->fetchAll(PDO::FETCH_ASSOC);
 $seenUsers=[];
 foreach($leaders as $leader){
  $uid=(int)$leader['leader_user_id'];if($uid<1||isset($seenUsers[$uid]))continue;$seenUsers[$uid]=true;
  $title=trim((string)($leader['organisation_name']??$leader['name']??$leader['business_name']??''));if($title==='')$title='Class leader';
  $description=trim((string)($leader['about_content']??$leader['description']??''));
  $listingCountQ=$db->prepare("SELECT COUNT(*) FROM bh_activities WHERE organiser_id=? AND LOWER(TRIM(COALESCE(status,''))) IN ('published','publish')");$listingCountQ->execute([(int)$leader['id']]);
  $slug=trim((string)($leader['slug']??''));if($slug==='')$slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-', $title),'-'));
  $rows[]=[
   'id'=>'leader-'.$uid,'organiser_id'=>(int)$leader['id'],'title'=>$title,'category'=>'Class leader',
   'summary'=>$description!==''?mb_substr(trim(strip_tags($description)),0,500):'Discover this class leader and their activities on Bubba Hub.',
   'description'=>$description,'town'=>(string)($leader['town']??''),'region'=>(string)($leader['region']??''),
   'address'=>'','postcode'=>'','website'=>(string)($leader['website']??''),'contact_email'=>(string)($leader['email']??''),
   'contact_phone'=>(string)($leader['phone']??''),'organisation_name'=>$title,'logo_url'=>(string)($leader['logo_url']??''),
   'linked_listing_count'=>(int)$listingCountQ->fetchColumn(),'storefront_type'=>'class_leader','organiser_slug'=>$slug
  ];
 }
 // Apply the same public filters to automatically generated leader storefronts.
 $rows=array_values(array_filter($rows,static function(array $row)use($q,$category,$town,$region):bool{
  if($category!==''&&($row['category']??'')!==$category)return false;
  if($town!==''&&($row['town']??'')!==$town)return false;
  if($region!==''&&($row['region']??'')!==$region)return false;
  if($q!==''){$hay=implode(' ',array_map(static fn($v)=>(string)($v??''),[$row['title']??'',$row['summary']??'',$row['description']??'',$row['category']??'',$row['town']??'',$row['organisation_name']??'']));if(mb_stripos($hay,$q)===false)return false;}
  return true;
 }));
 usort($rows,static fn($a,$b)=>strcasecmp((string)$a['title'],(string)$b['title']));
 $rows=array_slice($rows,0,300);
 echo json_encode(['ok'=>true,'businesses'=>$rows,'count'=>count($rows)],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){error_log('Public Businesses directory: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'directory_unavailable']);}
