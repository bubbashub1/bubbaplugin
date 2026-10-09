<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function entReply(int $code,array $value):never{http_response_code($code);echo json_encode($value);exit;}
try{
require_once __DIR__.'/db.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
$uid=(int)($_SESSION['bh_user_id']??0);if(!$uid)entReply(401,['ok'=>false,'is_pro'=>false]);
$db=bh_mysql();$q=$db->prepare("SELECT role,status,email FROM bh_users WHERE id=?");$q->execute([$uid]);$u=$q->fetch();
if(!$u||$u['role']!=='leader'||$u['status']!=='active')entReply(403,['ok'=>false,'is_pro'=>false]);
$pro=false;$t=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_user_subscriptions'");
if((int)$t->fetchColumn()){$q=$db->prepare("SELECT COUNT(*) FROM bh_user_subscriptions WHERE user_id=? AND plan='leader_pro' AND status IN ('active','trialing') AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())");$q->execute([$uid]);$pro=(int)$q->fetchColumn()>0;}
if(!$pro){$cols=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers'")->fetchAll(PDO::FETCH_COLUMN);$q=in_array('user_id',$cols,true)?$db->prepare("SELECT * FROM bh_organisers WHERE user_id=? LIMIT 1"):$db->prepare("SELECT * FROM bh_organisers WHERE email=? LIMIT 1");$q->execute([in_array('user_id',$cols,true)?$uid:$u['email']]);$org=$q->fetch();if($org){foreach(['plan','membership_plan','membership_tier','subscription_plan','tier'] as $c){if(isset($org[$c])){$pro=in_array(strtolower(trim((string)$org[$c])),['pro','premium','ultimate'],true);break;}}}}
entReply(200,['ok'=>true,'is_pro'=>$pro]);
}catch(Throwable $e){error_log('Leader entitlement: '.$e->getMessage());entReply(500,['ok'=>false,'is_pro'=>false]);}
