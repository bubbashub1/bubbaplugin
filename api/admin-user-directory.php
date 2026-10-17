<?php
declare(strict_types=1);
/** Admin-only, read-only account directory. No child profiles or password hashes exposed. */
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/admin-session-guard.php';
function admin_users_reply(int $status,array $payload):never{http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')admin_users_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(empty($_SESSION['bh_admin_csrf']))$_SESSION['bh_admin_csrf']=bin2hex(random_bytes(32));
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=trim((string)($_GET['q']??''));$role=trim((string)($_GET['role']??''));$status=trim((string)($_GET['status']??''));$verification=trim((string)($_GET['verification']??''));
 if(mb_strlen($q)>100||!in_array($role,['','family','leader'],true)||!in_array($status,['','active','pending','suspended','inactive'],true)||!in_array($verification,['','verified','unverified'],true))admin_users_reply(422,['ok'=>false,'error'=>'invalid_filters']);
 $page=min(1000,max(1,(int)($_GET['page']??1)));$size=25;$offset=($page-1)*$size;
 $where=[];$params=[];
 if($q!==''){$where[]='(email LIKE :q)';$params['q']='%'.str_replace(['%','_','\\'],['\\%','\\_','\\\\'],$q).'%';}
 if($role!==''){$where[]='role=:role';$params['role']=$role;}
 if($status!==''){$where[]='status=:status';$params['status']=$status;}
 if($verification!==''){$where[]=$verification==='verified'?'email_verified_at IS NOT NULL':'email_verified_at IS NULL';}
 $condition=$where?' WHERE '.implode(' AND ',$where):'';
 $count=$db->prepare('SELECT COUNT(*) FROM bh_users'.$condition);$count->execute($params);$total=(int)$count->fetchColumn();
 $stmt=$db->prepare('SELECT id,email,role,status,CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END AS email_verified FROM bh_users'.$condition.' ORDER BY id DESC LIMIT '.$size.' OFFSET '.$offset);$stmt->execute($params);
 $users=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $ids=array_map(static fn($u)=>(int)$u['id'],$users);
 $promos=[];$subs=[];
 if($ids){
  $placeholders=implode(',',array_fill(0,count($ids),'?'));
  try{$p=$db->prepare('SELECT leader_user_id FROM bh_admin_leader_promotions WHERE active=1 AND leader_user_id IN ('.$placeholders.')');$p->execute($ids);$promos=array_fill_keys(array_map('intval',$p->fetchAll(PDO::FETCH_COLUMN)),true);}catch(Throwable $ignored){}
  try{$p=$db->prepare("SELECT DISTINCT user_id FROM bh_user_subscriptions WHERE plan='leader_pro' AND status IN ('active','trialing') AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) AND user_id IN (".$placeholders.")");$p->execute($ids);$subs=array_fill_keys(array_map('intval',$p->fetchAll(PDO::FETCH_COLUMN)),true);}catch(Throwable $ignored){}
 }
 foreach($users as &$u){$uid=(int)$u['id'];$u['account_type']=isset($promos[$uid])?'Pro (complimentary)':(isset($subs[$uid])?'Pro':'Free');}unset($u);
 admin_users_reply(200,['ok'=>true,'users'=>$users,'total'=>$total,'page'=>$page,'page_size'=>$size,'read_only'=>true,'csrf'=>$_SESSION['bh_admin_csrf']]);
}catch(Throwable $e){error_log('Admin user directory: '.$e->getMessage());admin_users_reply(500,['ok'=>false,'error'=>'user_directory_unavailable']);}
