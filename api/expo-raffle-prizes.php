<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $stmt=$db->prepare("SELECT title,description,image_url,donor_name,donor_url,prize_value,entry_type FROM bh_expo_raffle_prizes WHERE event_key=? AND status='published' ORDER BY sort_order,id LIMIT 100");
 $stmt->execute(['bubba-me-2027']);
 echo json_encode(['ok'=>true,'prizes'=>$stmt->fetchAll(PDO::FETCH_ASSOC)],JSON_INVALID_UTF8_SUBSTITUTE);
} catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false,'error'=>'prizes_unavailable']);}
