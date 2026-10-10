<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/db.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function bh_support_user(): int { return isset($_SESSION['bh_user_id']) ? (int)$_SESSION['bh_user_id'] : 0; }
function bh_json(int $status,array $data): never { http_response_code($status); echo json_encode($data); exit; }
set_exception_handler(function (Throwable $error): void {
  error_log('Bubba Hub support: '.$error->getMessage());
  bh_json(500,['ok'=>false,'error'=>'support_unavailable']);
});
function bh_support_db(): PDO {
  $db=bh_mysql();
  try {
    $db->query('SELECT id FROM bh_support_questions LIMIT 0');
    $db->query('SELECT id FROM bh_support_answers LIMIT 0');
    $db->query('SELECT id FROM bh_guidance_topics LIMIT 0');
  } catch (PDOException $error) {
    if ($error->getCode() !== '42S02') throw $error;
    foreach (["CREATE TABLE IF NOT EXISTS bh_guidance_topics (\n id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n name VARCHAR(120) NOT NULL UNIQUE,\n description VARCHAR(500) NULL,\n active TINYINT(1) NOT NULL DEFAULT 1,\n created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;","CREATE TABLE IF NOT EXISTS bh_support_questions (\n id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n user_id BIGINT UNSIGNED NOT NULL,\n topic_id BIGINT UNSIGNED NULL,\n subject VARCHAR(190) NULL,\n question TEXT NOT NULL,\n status ENUM('submitted','assigned','awaiting_user','answered','closed') NOT NULL DEFAULT 'submitted',\n assigned_user_id BIGINT UNSIGNED NULL,\n created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n INDEX idx_support_user (user_id),\n INDEX idx_support_assigned (assigned_user_id),\n INDEX idx_support_status (status)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;","CREATE TABLE IF NOT EXISTS bh_support_answers (\n id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n question_id BIGINT UNSIGNED NOT NULL,\n responder_user_id BIGINT UNSIGNED NOT NULL,\n answer TEXT NOT NULL,\n created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n INDEX idx_support_answer_question (question_id)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"] as $sql) $db->exec($sql);
  }
  return $db;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!bh_support_user()) bh_json(401,['ok'=>false,'error'=>'login_required']);
  $stmt=bh_support_db()->prepare('SELECT q.id,q.subject,q.question,q.status,q.created_at,q.updated_at,a.answer,a.created_at AS answer_created_at FROM bh_support_questions q LEFT JOIN bh_support_answers a ON a.id=(SELECT MAX(a2.id) FROM bh_support_answers a2 WHERE a2.question_id=q.id) WHERE q.user_id=? ORDER BY q.updated_at DESC');
  $stmt->execute([bh_support_user()]);
  bh_json(200,['ok'=>true,'questions'=>$stmt->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bh_json(405,['ok'=>false,'error'=>'method_not_allowed']);
if (!bh_support_user()) bh_json(401,['ok'=>false,'error'=>'login_required']);
$body=json_decode(file_get_contents('php://input'),true);
$question=trim((string)($body['question']??'')); $topic=trim((string)($body['topic']??'')); $subject=trim((string)($body['subject']??''));
if ($question===''||mb_strlen($question)>5000) bh_json(422,['ok'=>false,'error'=>'question_required']);
$db=bh_support_db();
$topicId=null;
if($topic!==''){ $s=$db->prepare('SELECT id FROM bh_guidance_topics WHERE name=? AND active=1 LIMIT 1'); $s->execute([$topic]); $topicId=$s->fetchColumn()?:null; }
$stmt=$db->prepare('INSERT INTO bh_support_questions (user_id,topic_id,subject,question,status) VALUES (?,?,?,?,\'submitted\')');
$stmt->execute([bh_support_user(),$topicId,$subject?:null,$question]);
$questionId=(int)$db->lastInsertId();
$topicKeys=['Baby & child health'=>'baby-child-health','Feeding & weaning'=>'feeding-weaning','Sleep'=>'sleep','Pregnancy & new parents'=>'pregnancy-new-parents','Family wellbeing'=>'family-wellbeing','Activities & classes'=>'activities-classes','Something else'=>''];
$key=$topicKeys[$topic]??'';
$routed=false;
if($key!==''){
 try{
  $match=$db->prepare("SELECT o.id,o.email FROM bh_leader_expertise le INNER JOIN bh_organisers o ON o.id=le.organiser_id INNER JOIN bh_users u ON u.id=o.user_id WHERE le.topic_key=? AND le.enabled=1 AND u.role='leader' AND u.status='active' ORDER BY o.id LIMIT 1");
  $match->execute([$key]);$org=$match->fetch(PDO::FETCH_ASSOC);
  if($org){
   $db->exec("CREATE TABLE IF NOT EXISTS bh_leader_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,organiser_id BIGINT UNSIGNED NOT NULL,sender_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,subject VARCHAR(180) NOT NULL,body TEXT NOT NULL,category VARCHAR(30) NOT NULL DEFAULT 'help',reply TEXT NULL,replied_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_leader_messages_organiser(organiser_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
   try{$db->exec("ALTER TABLE bh_leader_messages ADD COLUMN sender_email VARCHAR(190) NULL");}catch(Throwable $ignored){}
   $insert=$db->prepare("INSERT INTO bh_leader_messages (organiser_id,sender_user_id,subject,body,category) VALUES (?,?,?,?,'help')");
   $insert->execute([(int)$org['id'],bh_support_user(),mb_substr('Private support: '.$topic,0,180),$question]);
   $routed=true;
   try{$wp=dirname(__DIR__).'/wp-load.php';if(is_file($wp)){ob_start();require_once $wp;ob_end_clean();if(function_exists('wp_mail'))wp_mail((string)$org['email'],'New private Bubba Hub support request','A new private support request matches your specialisms. Sign in to My Messages to read it.');}}catch(Throwable $mailError){error_log('Support notification: '.$mailError->getMessage());}
  }
 }catch(Throwable $routingError){error_log('Support routing: '.$routingError->getMessage());}
}
bh_json(201,['ok'=>true,'question_id'=>$questionId,'status'=>'submitted','routed'=>$routed]);
?>