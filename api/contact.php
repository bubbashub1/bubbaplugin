<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

$activityId = max(0, (int)($_POST['activity_id'] ?? 0));
$subjectKey = trim((string)($_POST['subject'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$honeypot = trim((string)($_POST['website'] ?? ''));

if ($honeypot !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

$subjects = [
    'about' => 'About this activity',
    'booking' => 'Booking enquiry',
    'availability' => 'Availability enquiry',
    'general' => 'General enquiry',
    'other' => 'Other',
];

if ($activityId < 1 || !isset($subjects[$subjectKey]) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please complete all required fields.']);
    exit;
}

try {
    $db = bh_mysql();
    $stmt = $db->prepare(
        "SELECT a.title, a.organiser_id, o.organisation_name, o.email
         FROM bh_activities a
         INNER JOIN bh_organisers o ON o.id = a.organiser_id
         WHERE a.id = ? AND a.status = 'published' AND o.status = 'published'
         LIMIT 1"
    );
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch();

    if (!$activity || !filter_var((string)$activity['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'The organiser cannot receive messages for this activity right now.']);
        exit;
    }

    $activityTitle = trim((string)$activity['title']);
    $subject = $subjects[$subjectKey];
    $mailSubject = 'Bubba Hub enquiry: ' . $subject . ' – ' . $activityTitle;

    $body = "New Bubba Hub enquiry\n\n"
        . "Activity: {$activityTitle}\n"
        . "Subject: {$subject}\n\n"
        . "From: {$name}\n"
        . "Email: {$email}\n\n"
        . "Message:\n{$message}\n";

    require_once __DIR__ . '/mailer.php';

    if (!bh_send_smtp_mail((string)$activity['email'], $mailSubject, nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')), $body, $email)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'We could not send your message right now. Please try again later.']);
        exit;
    }

    try {
        // Inbox logging is best-effort: the enquiry email has already been sent.
        $check = $db->query("SHOW COLUMNS FROM bh_leader_messages LIKE 'sender_email'");
        if ($check && $check->fetch()) {
            $q = $db->prepare("INSERT INTO bh_leader_messages(organiser_id,sender_user_id,sender_email,subject,body,category) VALUES(?,0,?,?,?,?)");
            $q->execute([(int)$activity['organiser_id'], $email, mb_substr($subject . ' – ' . $activityTitle, 0, 180), "From: " . $name . "\n\n" . $message, $subjectKey === 'booking' ? 'booking' : 'help']);
        } else {
            error_log('Could not save leader inbox copy: bh_leader_messages.sender_email is unavailable.');
        }
    } catch (Throwable $logError) {
        error_log('Could not save leader inbox copy: ' . $logError->getMessage());
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'We could not send your message right now. Please try again later.']);
}
