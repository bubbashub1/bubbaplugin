<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/admin-session-guard.php';
try {
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $rows=$db->query("SELECT v.id AS venue_id, v.activity_id, a.title AS activity_title, a.status AS activity_status FROM bh_venues v LEFT JOIN bh_activities a ON a.id=v.activity_id ORDER BY v.id")->fetchAll(PDO::FETCH_ASSOC);
 echo json_encode(['ok'=>true,'data'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>'Unable to load linked listings.']);
}
