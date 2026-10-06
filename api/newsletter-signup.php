<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_newsletter_signup_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require __DIR__.'/db.php';
    $db = bh_mysql();

    bh_ensure_newsletter_subscribers($db);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_newsletter_signup_json(405, ['ok'=>false,'error'=>'method_not_allowed']);
    }

    $raw = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    // Accept both the site's JSON request and a normal form POST.
    if (!is_array($body)) {
        $body = $_POST;
    }
    if (!is_array($body)) {
        bh_newsletter_signup_json(400, ['ok'=>false,'error'=>'invalid_request']);
    }

    // Quiet honeypot for simple bot protection.
    if (trim((string)($body['website'] ?? '')) !== '') {
        bh_newsletter_signup_json(200, ['ok'=>true,'message'=>'Thanks — you’re subscribed.']);
    }

    $email = strtolower(trim((string)($body['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        bh_newsletter_signup_json(422, ['ok'=>false,'error'=>'invalid_email','message'=>'Please enter a valid email address.']);
    }

    $stmt = $db->prepare("INSERT INTO bh_newsletter_subscribers
        (email,status,source,consented_at,unsubscribed_at)
        VALUES (?, 'subscribed', ?, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
          status='subscribed',
          source=VALUES(source),
          consented_at=NOW(),
          unsubscribed_at=NULL");
    $stmt->execute([$email, substr(trim((string)($body['source'] ?? 'website')) ?: 'website', 0, 80)]);

    // Welcome email is best-effort and must never turn a successful database
    // signup into a 500 response.
    try {
        require_once __DIR__.'/newsletter-welcome.php';
        bh_send_newsletter_welcome($email);
    } catch (Throwable $mailError) {
        error_log('[Bubba Hub newsletter welcome] '.$mailError->getMessage());
    }

    bh_newsletter_signup_json(200, [
        'ok'=>true,
        'message'=>"You're subscribed to Bubba Hub updates."
    ]);
} catch (Throwable $e) {
    error_log('[Bubba Hub newsletter] '.$e->getMessage());
    bh_newsletter_signup_json(500, [
        'ok'=>false,
        'error'=>'newsletter_signup_error',
        'message'=>'Newsletter signup is temporarily unavailable. Please try again shortly.'
    ]);
}
?>