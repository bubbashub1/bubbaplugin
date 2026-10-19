<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
$event=(string)($_GET['event']??'');
if($event!=='bubba-me-2027'){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'invalid_event']);exit;}
try{
 require_once __DIR__.'/sponsor-records.php';
 $db=bh_sponsor_db();
 $q=$db->prepare("SELECT stripe_session_id,business_name,tier,website,logo_url FROM bh_event_sponsor_approvals WHERE event_key=? AND approval_status='approved' ORDER BY tier DESC, reviewed_at ASC LIMIT 100");
 $q->execute([$event]);$sponsors=[];
 foreach($q->fetchAll() as $row){
  // Re-check Stripe before publication, including if a payment is later refunded.
  $session=bh_sponsor_session((string)$row['stripe_session_id']);
  if(!$session||!bh_sponsor_verified($session))continue;
  $sponsors[]=['name'=>$row['business_name'],'tier'=>(int)$row['tier'],'website'=>$row['website'],'logo_url'=>$row['logo_url']];
 }
 echo json_encode(['ok'=>true,'sponsors'=>$sponsors],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){error_log('Sponsor public listing unavailable: '.get_class($e));http_response_code(503);echo json_encode(['ok'=>false,'error'=>'temporarily_unavailable']);}
