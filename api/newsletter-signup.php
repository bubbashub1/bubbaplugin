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

    $db->exec("CREATE TABLE IF NOT EXISTS bh_newsletter_subscribers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
        source VARCHAR(80) NOT NULL DEFAULT 'website',
        consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_newsletter_status (status),
        INDEX idx_newsletter_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_newsletter_signup_json(405, ['ok'=>false,'error'=>'method_not_allowed']);
    }

    $body = json_decode((string)file_get_contents('php://input'), true);
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
    $stmt->execute([$email, trim((string)($body['source'] ?? 'website')) ?: 'website']);

    bh_newsletter_signup_json(200, [
        'ok'=>true,
        'message'=>"You're subscribed to Bubba Hub updates."
    ]);
} catch (Throwable $e) {
    bh_newsletter_signup_json(500, [
        'ok'=>false,
        'error'=>'newsletter_signup_error'
    ]);
}
?>