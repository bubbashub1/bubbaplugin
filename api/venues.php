<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try{
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $stmt=$db->query("SELECT v.id,v.venue_name AS name,v.address,v.town,v.region,v.postcode,v.latitude,v.longitude,COUNT(DISTINCT v.activity_id) AS activity_count
 FROM bh_venues v
 WHERE TRIM(COALESCE(v.venue_name,'')) <> ''
 GROUP BY v.id,v.venue_name,v.address,v.town,v.region,v.postcode,v.latitude,v.longitude
 ORDER BY v.venue_name ASC,v.id ASC");
 $rows=$stmt->fetchAll();
 echo json_encode(['ok'=>true,'data'=>$rows],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>'Unable to load venues.','message'=>$e->getMessage()],JSON_UNESCAPED_SLASHES);
}
?>