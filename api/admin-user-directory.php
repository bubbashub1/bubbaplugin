<?php
declare(strict_types=1);
/** Admin-only, read-only account directory. No child profiles or password hashes exposed. */
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');session_start();
function admin_users_reply(int $status,array $payload):never{http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))admin_users_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')admin_users_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=trim((string)($_GET['q']??''));$role=trim((string)($_GET['role']??''));$status=trim((string)($_GET['status']??''));
 if(mb_strlen($q)>100||!in_array($role,['','family','leader'],true)||!in_array($status,['','active','pending','suspended','inactive'],true))admin_users_reply(422,['ok'=>false,'error'=>'invalid_filters']);
 $page=min(1000,max(1,(int)($_GET['page']??1)));$size=25;$offset=($page-1)*$size;
 $where=[];$params=[];
 if($q!==''){$where[]='(email LIKE :q)';$params['q']='%'.str_replace(['%','_'],['\\%','\\_'],$q).'%';}
 if($role!==''){$where[]='role=:role';$params['role']=$role;}
 if($status!==''){$where[]='status=:status';$params['status']=$status;}
 $condition=$where?' WHERE '.implode(' AND ',$where):'';
 $count=$db->prepare('SELECT COUNT(*) FROM bh_users'.$condition);$count->execute($params);$total=(int)$count->fetchColumn();
 $stmt=$db->prepare('SELECT id,email,role,status FROM bh_users'.$condition.' ORDER BY id DESC LIMIT '.$size.' OFFSET '.$offset);$stmt->execute($params);
 admin_users_reply(200,['ok'=>true,'users'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'page_size'=>$size,'read_only'=>true]);
}catch(Throwable $e){error_log('Admin user directory: '.$e->getMessage());admin_users_reply(500,['ok'=>false,'error'=>'user_directory_unavailable']);}
