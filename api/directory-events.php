<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $exists=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_directory_events'");
 if(!(int)$exists->fetchColumn()){http_response_code(200);echo json_encode(['ok'=>true,'schema_ready'=>false,'events'=>[]]);exit;}
 $town=trim((string)($_GET['town']??''));$category=trim((string)($_GET['category']??''));$limit=max(1,min(100,(int)($_GET['limit']??60)));
 $where=["e.status='published'","x.occurrence_status='scheduled'","x.starts_at>=?"];$params=[(new DateTimeImmutable('now',new DateTimeZone('Europe/London')))->format('Y-m-d H:i:s')];
 if($town!==''){$where[]="e.town LIKE ?";$params[]='%'.$town.'%';}
 if($category!==''){$where[]="e.category=?";$params[]=$category;}
 $sql="SELECT e.id,e.title,e.slug,e.summary,e.description,e.category,e.age_min_months,e.age_max_months,e.booking_url,e.image_path,e.venue_name,e.address,e.town,e.region,e.postcode,x.starts_at,x.ends_at,x.timezone FROM bh_directory_events e INNER JOIN bh_directory_event_occurrences x ON x.event_id=e.id WHERE ".implode(' AND ',$where)." ORDER BY x.starts_at ASC,e.id DESC LIMIT ".$limit;
 $q=$db->prepare($sql);$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as &$row){$row['id']=(int)$row['id'];$row['age_min_months']=$row['age_min_months']===null?null:(int)$row['age_min_months'];$row['age_max_months']=$row['age_max_months']===null?null:(int)$row['age_max_months'];}unset($row);
 echo json_encode(['ok'=>true,'schema_ready'=>true,'events'=>$rows],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){error_log('Directory events API: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'events_unavailable']);}
