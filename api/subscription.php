<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try{
 require __DIR__.'/db.php';
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 $uid=(int)($_SESSION['bh_user_id']??0);
 if(!$uid){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'login_required']);exit;}
 $db=bh_mysql();
 $tableCheck=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions'");
 $tableCheck->execute();
 if(!(int)$tableCheck->fetchColumn()){
  http_response_code(503);echo json_encode(['ok'=>false,'error'=>'subscription_storage_unavailable']);exit;
 }
 foreach(['stripe_customer_id','stripe_subscription_id','stripe_checkout_session_id'] as $column){
  $columnCheck=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions' AND COLUMN_NAME=?");
  $columnCheck->execute([$column]);
  if(!(int)$columnCheck->fetchColumn()){
   http_response_code(503);echo json_encode(['ok'=>false,'error'=>'subscription_storage_unavailable']);exit;
  }
 }
 $q=$db->prepare("SELECT plan,status,source,stripe_customer_id,stripe_subscription_id,stripe_checkout_session_id,started_at,expires_at FROM bh_user_subscriptions WHERE user_id=? LIMIT 1");$q->execute([$uid]);$s=$q->fetch();
 $active=!!$s && in_array(($s['status']??''),['active','trialing'],true) && (empty($s['expires_at']) || strtotime((string)$s['expires_at'])>=time());
 echo json_encode(['ok'=>true,'pro'=>$active,'subscription'=>$s?:['plan'=>'free','status'=>'active','source'=>'default','started_at'=>null,'expires_at'=>null]],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'subscription_error']);}
?>