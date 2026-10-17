<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/admin-session-guard.php';
function reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id||$id<1)reply(422,['ok'=>false,'error'=>'invalid_leader_id']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $stmt=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? AND role='leader' LIMIT 1");$stmt->execute([$id]);$leader=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$leader)reply(404,['ok'=>false,'error'=>'leader_not_found']);
 $cols=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);
 $hasUserId=in_array('user_id',$cols,true);
 $nameCol=null;foreach(['name','organisation_name','business_name','title'] as $c){if(in_array($c,$cols,true)){$nameCol=$c;break;}}
 $organiserSql="SELECT id".($nameCol?", `$nameCol` AS name":", CAST(id AS CHAR) AS name")." FROM bh_organisers WHERE ";
 if($hasUserId){$organiserSql.="user_id=:uid";if(in_array('email',$cols,true))$organiserSql.=" OR (user_id IS NULL AND LOWER(email)=LOWER(:email))";}
 elseif(in_array('email',$cols,true))$organiserSql.="LOWER(email)=LOWER(:email)";
 else reply(200,['ok'=>true,'leader'=>$leader,'organisers'=>[],'notice'=>'No organiser ownership field available']);
 $orgStmt=$db->prepare($organiserSql.' ORDER BY id LIMIT 50');
 $orgStmt->execute($hasUserId? (in_array('email',$cols,true)?['uid'=>$id,'email'=>$leader['email']]:['uid'=>$id]):['email'=>$leader['email']]);
 $organisers=$orgStmt->fetchAll(PDO::FETCH_ASSOC);
 $act=$db->prepare("SELECT id,title,status FROM bh_activities WHERE organiser_id=? ORDER BY title LIMIT 200");
 foreach($organisers as &$org){$act->execute([(int)$org['id']]);$org['activities']=$act->fetchAll(PDO::FETCH_ASSOC);}unset($org);
 reply(200,['ok'=>true,'leader'=>$leader,'organisers'=>$organisers,'read_only'=>true]);
}catch(Throwable $e){error_log('Admin leader detail: '.$e->getMessage());reply(500,['ok'=>false,'error'=>'leader_details_unavailable']);}
