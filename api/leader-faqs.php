<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
session_start();
function bh_faq_json(int $s,array $d): never { http_response_code($s); echo json_encode($d); exit; }
if(!isset($_SESSION['bh_user_id']) || !(int)$_SESSION['bh_user_id']) bh_faq_json(401,['ok'=>false,'error'=>'login_required']);
$userId=(int)$_SESSION['bh_user_id']; $db=bh_mysql();
$s=$db->prepare('SELECT id FROM bh_organisers WHERE user_id=? LIMIT 1');$s->execute([$userId]);$organiserId=(int)($s->fetchColumn()?:0);if(!$organiserId)bh_faq_json(403,['ok'=>false,'error'=>'organiser_required']);
if($_SERVER['REQUEST_METHOD']==='GET'){ $s=$db->prepare('SELECT id,activity_id,question,answer,status,sort_order FROM bh_leader_faqs WHERE organiser_id=? AND status<>'archived' ORDER BY sort_order,id');$s->execute([$organiserId]);bh_faq_json(200,['ok'=>true,'faqs'=>$s->fetchAll()]);}
if($_SERVER['REQUEST_METHOD']!=='POST')bh_faq_json(405,['ok'=>false,'error'=>'method_not_allowed']);
$b=json_decode(file_get_contents('php://input'),true);$q=trim((string)($b['question']??''));$a=trim((string)($b['answer']??''));$activity=(int)($b['activity_id']??0);
if($q===''||$a==='')bh_faq_json(422,['ok'=>false,'error'=>'question_and_answer_required']);
$s=$db->prepare('INSERT INTO bh_leader_faqs (organiser_id,activity_id,question,answer,status) VALUES (?,?,?,?,'draft')');$s->execute([$organiserId,$activity?:null,$q,$a]);bh_faq_json(201,['ok'=>true,'faq_id'=>(int)$db->lastInsertId()]);
?>