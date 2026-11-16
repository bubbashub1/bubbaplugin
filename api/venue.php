<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try{
 require __DIR__.'/db.php'; $db=bh_mysql();
 $id=(int)($_GET['id']??0);
 if($id<1){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Venue not specified.']);exit;}
 $q=$db->prepare("SELECT v.id,v.venue_name AS name,v.address,v.town,v.region,v.postcode,v.latitude,v.longitude FROM bh_venues v WHERE v.id=? LIMIT 1");
 $q->execute([$id]);$venue=$q->fetch();
 if(!$venue){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Venue not found.']);exit;}
 $hasLinks=(bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activity_venues'")->fetchColumn();
 $hasDirect=(bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_venues' AND COLUMN_NAME='activity_id'")->fetchColumn();
 $linkParts=[];
 if($hasLinks)$linkParts[]="SELECT av.activity_id FROM bh_activity_venues av WHERE av.venue_id=?";
 if($hasDirect)$linkParts[]="SELECT v.activity_id FROM bh_venues v WHERE v.id=? AND v.activity_id IS NOT NULL";
 if($linkParts){
  $sql="SELECT DISTINCT a.id,a.title,a.slug,a.category,a.age_range,a.price_from,a.image_path,o.organisation_name
  FROM (".implode(" UNION ",$linkParts).") venue_activity_links
  INNER JOIN bh_activities a ON a.id=venue_activity_links.activity_id AND a.status='published'
  INNER JOIN bh_organisers o ON o.id=a.organiser_id AND o.status='published'
  ORDER BY a.title ASC";
  $q=$db->prepare($sql);$q->execute(array_fill(0,count($linkParts),$id));
 }else{$activities=[];$q=null;}
 $activities=$q?$q->fetchAll():[];
 foreach($activities as &$a){$a['id']=(int)$a['id'];$a['price_from']=$a['price_from']!==null?(float)$a['price_from']:null;}
 echo json_encode(['ok'=>true,'venue'=>$venue,'activities'=>$activities],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Unable to load venue.','message'=>$e->getMessage()],JSON_UNESCAPED_SLASHES);}
?>