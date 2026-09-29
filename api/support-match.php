<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
session_start();
function bh_sm_json(int $status,array $data): never{http_response_code($status);echo json_encode($data);exit;}
if(!isset($_SESSION['bh_user_id'])||!(int)$_SESSION['bh_user_id'])bh_sm_json(401,['ok'=>false,'error'=>'login_required']);
$db=bh_mysql();$topic=trim((string)($_GET['topic']??''));$limit=min(10,max(1,(int)($_GET['limit']??5)));
try{
 $sql="SELECT u.id,o.organisation_name,o.description
       FROM bh_users u
       INNER JOIN bh_organisers o ON o.user_id=u.id AND o.status='published'
       LEFT JOIN bh_guidance_offers go ON go.user_id=u.id AND go.enabled=1
       LEFT JOIN bh_guidance_topics gt ON gt.id=go.topic_id AND gt.active=1
       LEFT JOIN bh_leader_expertise le ON le.organiser_id=o.id AND le.enabled=1
       WHERE u.status='active'";
 $params=[];
 if($topic!==''){$sql.=" AND (gt.name=? OR le.topic_key=?)";$params=[$topic,$topic];}
 $sql.=" GROUP BY u.id,o.id ORDER BY u.id LIMIT ".$limit;
 $stmt=$db->prepare($sql);$stmt->execute($params);
 bh_sm_json(200,['ok'=>true,'responders'=>$stmt->fetchAll()]);
}catch(Throwable $e){
 bh_sm_json(200,['ok'=>true,'responders'=>[],'note'=>'Responder matching is not available until leader expertise is enabled.']);
}
?>