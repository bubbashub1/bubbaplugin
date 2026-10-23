<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function result(int $status,array $data): never {http_response_code($status);echo json_encode($data);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET')result(405,['ok'=>false,'error'=>'method_not_allowed']);
if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax','path'=>'/']);session_start();}
$userId=(int)($_SESSION['bh_user_id']??0);
if($userId<1)result(401,['ok'=>false,'error'=>'login_required']);
try{require __DIR__.'/db.php';$db=bh_mysql();$u=$db->prepare("SELECT id,email FROM bh_users WHERE id=? AND status='active' LIMIT 1");$u->execute([$userId]);if(!$u->fetch())result(403,['ok'=>false,'error'=>'inactive_account']);
$regions=$db->prepare("SELECT region FROM bh_volunteer_assignments WHERE user_id=? AND is_active=1 ORDER BY region");$regions->execute([$userId]);$areas=$regions->fetchAll(PDO::FETCH_COLUMN);
if(!$areas)result(403,['ok'=>false,'error'=>'volunteer_assignment_required']);
$placeholders=implode(',',array_fill(0,count($areas),'?'));
$stmt=$db->prepare("SELECT DISTINCT a.id,a.title,a.status,v.town,v.region FROM bh_activities a JOIN bh_venues v ON v.activity_id=a.id WHERE v.region IN ($placeholders) ORDER BY a.title LIMIT 200");
$stmt->execute($areas);result(200,['ok'=>true,'regions'=>$areas,'listings'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'editing_enabled'=>false]);
}catch(Throwable $e){error_log('Volunteer dashboard failed: '.$e->getMessage());result(500,['ok'=>false,'error'=>'temporarily_unavailable']);}
