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
 $db->exec("CREATE TABLE IF NOT EXISTS bh_user_subscriptions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,plan VARCHAR(40) NOT NULL DEFAULT 'family_pro',status VARCHAR(30) NOT NULL DEFAULT 'active',source VARCHAR(40) NOT NULL DEFAULT 'event',stripe_customer_id VARCHAR(255) NULL,stripe_subscription_id VARCHAR(255) NULL,stripe_checkout_session_id VARCHAR(255) NULL,started_at DATETIME NULL,expires_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_bh_user_subscription(user_id),UNIQUE KEY uq_bh_stripe_subscription(stripe_subscription_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $columns=['stripe_customer_id'=>'VARCHAR(255) NULL','stripe_subscription_id'=>'VARCHAR(255) NULL','stripe_checkout_session_id'=>'VARCHAR(255) NULL'];
 foreach($columns as $name=>$definition){try{$q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions' AND COLUMN_NAME=?");$q->execute([$name]);if(!(int)$q->fetchColumn())$db->exec("ALTER TABLE bh_user_subscriptions ADD COLUMN $name $definition");}catch(Throwable $ignored){}}
 $q=$db->prepare("SELECT plan,status,source,stripe_customer_id,stripe_subscription_id,stripe_checkout_session_id,started_at,expires_at FROM bh_user_subscriptions WHERE user_id=? LIMIT 1");$q->execute([$uid]);$s=$q->fetch();
 $active=!!$s && in_array(($s['status']??''),['active','trialing'],true) && (empty($s['expires_at']) || strtotime((string)$s['expires_at'])>=time());
 echo json_encode(['ok'=>true,'pro'=>$active,'subscription'=>$s?:['plan'=>'free','status'=>'active','source'=>'default','started_at'=>null,'expires_at'=>null]],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'subscription_error']);}
?>