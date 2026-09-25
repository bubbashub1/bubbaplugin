<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
session_start();
function bh_ls_json(int $status,array $data): never { http_response_code($status); echo json_encode($data); exit; }
if (!isset($_SESSION['bh_user_id']) || !(int)$_SESSION['bh_user_id']) bh_ls_json(401,['ok'=>false,'error'=>'login_required']);
$userId=(int)$_SESSION['bh_user_id']; $db=bh_mysql();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $s=$db->prepare('SELECT g.id,g.name,g.description,COALESCE(o.enabled,0) AS enabled FROM bh_guidance_topics g LEFT JOIN bh_guidance_offers o ON o.topic_id=g.id AND o.user_id=? WHERE g.active=1 ORDER BY g.name');
 $s->execute([$userId]); bh_ls_json(200,['ok'=>true,'topics'=>$s->fetchAll()]);
}
if($_SERVER['REQUEST_METHOD']!=='POST') bh_ls_json(405,['ok'=>false,'error'=>'method_not_allowed']);
$b=json_decode(file_get_contents('php://input'),true); $topics=array_values(array_unique(array_map('intval',$b['topic_ids']??[]))); $enabled=!empty($b['enabled']);
$db->beginTransaction();
$db->prepare('DELETE FROM bh_guidance_offers WHERE user_id=?')->execute([$userId]);
if($enabled && $topics){$s=$db->prepare('INSERT INTO bh_guidance_offers (user_id,topic_id,enabled) VALUES (?,?,1)');foreach($topics as $id)$s->execute([$userId,$id]);}
$db->commit(); bh_ls_json(200,['ok'=>true,'enabled'=>$enabled,'topic_ids'=>$topics]);
?>