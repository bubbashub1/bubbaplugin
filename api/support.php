<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
session_start();
function bh_support_user(): int { return isset($_SESSION['bh_user_id']) ? (int)$_SESSION['bh_user_id'] : 0; }
function bh_json(int $status,array $data): never { http_response_code($status); echo json_encode($data); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!bh_support_user()) bh_json(401,['ok'=>false,'error'=>'login_required']);
  $stmt=bh_mysql()->prepare('SELECT q.id,q.subject,q.question,q.status,q.created_at,q.updated_at,a.answer,a.created_at AS answer_created_at FROM bh_support_questions q LEFT JOIN bh_support_answers a ON a.id=(SELECT MAX(a2.id) FROM bh_support_answers a2 WHERE a2.question_id=q.id) WHERE q.user_id=? ORDER BY q.updated_at DESC');
  $stmt->execute([bh_support_user()]);
  bh_json(200,['ok'=>true,'questions'=>$stmt->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bh_json(405,['ok'=>false,'error'=>'method_not_allowed']);
if (!bh_support_user()) bh_json(401,['ok'=>false,'error'=>'login_required']);
$body=json_decode(file_get_contents('php://input'),true);
$question=trim((string)($body['question']??'')); $topic=trim((string)($body['topic']??'')); $subject=trim((string)($body['subject']??''));
if ($question==='') bh_json(422,['ok'=>false,'error'=>'question_required']);
$db=bh_mysql();
$topicId=null;
if($topic!==''){ $s=$db->prepare('SELECT id FROM bh_guidance_topics WHERE name=? AND active=1 LIMIT 1'); $s->execute([$topic]); $topicId=$s->fetchColumn()?:null; }
$stmt=$db->prepare('INSERT INTO bh_support_questions (user_id,topic_id,subject,question,status) VALUES (?,?,?,?,\'submitted\')');
$stmt->execute([bh_support_user(),$topicId,$subject?:null,$question]);
bh_json(201,['ok'=>true,'question_id'=>(int)$db->lastInsertId(),'status'=>'submitted']);
?>