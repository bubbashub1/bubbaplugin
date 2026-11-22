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
 foreach([['q',"(b.title LIKE ? OR b.summary LIKE ? OR b.description LIKE ? OR b.category LIKE ? OR b.town LIKE ? OR b.postcode LIKE ? OR b.address LIKE ? OR o.organisation_name LIKE ?)"],['category','b.category = ?'],['town','(b.town LIKE ? OR b.postcode LIKE ?)'],['region','b.region = ?']] as [$key,$condition]){
  $value=$$key;if($value==='')continue;$where[]=$condition;
  if($key==='q'){for($i=0;$i<8;$i++)$args[]='%'.$value.'%';}elseif($key==='town'){$args[]='%'.$value.'%';$args[]='%'.$value.'%';}else $args[]=$value;
 }
 $sql="SELECT b.id,COALESCE(NULLIF(TRIM(o.organisation_name),''),b.title) AS title,b.category,b.summary,b.description,b.town,b.region,b.address,b.postcode,
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
 WHERE ".implode(' AND ',$where)." ORDER BY COALESCE(NULLIF(TRIM(o.organisation_name),''),b.title) ASC LIMIT 300";
 $s=$db->prepare($sql);$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);

 echo json_encode(['ok'=>true,'businesses'=>$rows,'count'=>count($rows)],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){error_log('Public Businesses directory: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'directory_unavailable']);}
