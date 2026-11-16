<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/admin-session-guard.php';
try {
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $hasTable=static function(string $table)use($db):bool{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);return (int)$q->fetchColumn()>0;};
 $parts=[];
 if($hasTable('bh_activity_venues'))$parts[]="SELECT av.venue_id,av.activity_id,a.title AS activity_title,a.status AS activity_status FROM bh_activity_venues av LEFT JOIN bh_activities a ON a.id=av.activity_id";
 $hasColumn=$db->query("SHOW COLUMNS FROM bh_venues LIKE 'activity_id'")->fetch();
 if($hasColumn)$parts[]="SELECT v.id AS venue_id,v.activity_id,a.title AS activity_title,a.status AS activity_status FROM bh_venues v LEFT JOIN bh_activities a ON a.id=v.activity_id WHERE v.activity_id IS NOT NULL";
 if(!$parts)$rows=[];else $rows=$db->query(implode(" UNION ",$parts)." ORDER BY venue_id,activity_id")->fetchAll(PDO::FETCH_ASSOC);
 echo json_encode(['ok'=>true,'data'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>'Unable to load linked listings.']);
}
