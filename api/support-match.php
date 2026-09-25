<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
session_start();
function bh_sm_json(int $s,array $d): never { http_response_code($s); echo json_encode($d); exit; }
if(!isset($_SESSION['bh_user_id']) || !(int)$_SESSION['bh_user_id'])bh_sm_json(401,['ok'=>false,'error'=>'login_required']);
$db=bh_mysql();$topic=trim((string)($_GET['topic']??''));$limit=min(10,max(1,(int)($_GET['limit']??5)));
$sql='SELECT u.id,u.email,o.organisation_name,o.description FROM bh_users u INNER JOIN bh_guidance_offers go ON go.user_id=u.id AND go.enabled=1 INNER JOIN bh_organisers o ON o.user_id=u.id AND o.status='published' INNER JOIN bh_guidance_topics gt ON gt.id=go.topic_id WHERE u.status='active'';
$params=[];if($topic!==''){$sql.=' AND gt.name=?';$params[]=$topic;}$sql.=' GROUP BY u.id,o.id ORDER BY u.id LIMIT '.$limit;
$s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll();array_walk($rows,function(&$r){unset($r['email']);});bh_sm_json(200,['ok'=>true,'responders'=>$rows]);
?>