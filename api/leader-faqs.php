<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__.'/db.php';
session_start();

function bh_faq_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['bh_user_id']) || !(int)$_SESSION['bh_user_id']) {
    bh_faq_json(401, ['ok' => false, 'error' => 'login_required']);
}

$userId = (int)$_SESSION['bh_user_id'];
$db = bh_mysql();

$stmt = $db->prepare('SELECT id FROM bh_organisers WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$organiserId = (int)($stmt->fetchColumn() ?: 0);

if (!$organiserId) {
    bh_faq_json(403, ['ok' => false, 'error' => 'organiser_required']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        "SELECT id, activity_id, question, answer, status, sort_order
         FROM bh_leader_faqs
         WHERE organiser_id = ? AND status <> 'archived'
         ORDER BY sort_order, id"
    );
    $stmt->execute([$organiserId]);

    bh_faq_json(200, [
        'ok' => true,
        'faqs' => $stmt->fetchAll(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bh_faq_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = [];
}

$question = trim((string)($body['question'] ?? ''));
$answer = trim((string)($body['answer'] ?? ''));
$activityId = (int)($body['activity_id'] ?? 0);

if ($question === '' || $answer === '') {
    bh_faq_json(422, ['ok' => false, 'error' => 'question_and_answer_required']);
}

$stmt = $db->prepare(
    "INSERT INTO bh_leader_faqs
        (organiser_id, activity_id, question, answer, status)
     VALUES (?, ?, ?, ?, 'draft')"
);
$stmt->execute([
    $organiserId,
    $activityId ?: null,
    $question,
    $answer,
]);

bh_faq_json(201, [
    'ok' => true,
    'faq_id' => (int)$db->lastInsertId(),
]);
?>