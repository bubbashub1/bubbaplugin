<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function inboxReply(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function inboxMail(string $to, string $subject, string $body): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $wp = dirname(__DIR__) . '/wp-load.php';
    if (!is_file($wp)) return false;
    ob_start();
    require_once $wp;
    ob_end_clean();
    return function_exists('wp_mail') ? (bool) wp_mail($to, $subject, $body) : false;
}

try {
    require_once __DIR__ . '/db.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('BUBBAHUBSESSID');
        session_start();
    }

    $uid = (int) ($_SESSION['bh_user_id'] ?? 0);
    if (!$uid) inboxReply(401, ['ok' => false, 'message' => 'Please sign in.']);

    $db = bh_mysql();
    $q = $db->prepare("SELECT id,email,role,status FROM bh_users WHERE id=? LIMIT 1");
    $q->execute([$uid]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['status'] !== 'active') {
        inboxReply(403, ['ok' => false, 'message' => 'Account unavailable.']);
    }

    // Storage is provisioned by migration 008, never created during a web request.
    $table = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_leader_messages'");
    $table->execute();
    if ((int) $table->fetchColumn() !== 1) {
        error_log('Leader messages migration 008 is required.');
        inboxReply(503, ['ok' => false, 'message' => 'Messaging is not set up yet. Please contact Bubba Hub support.']);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        inboxReply(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }

    if ($method === 'POST') {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($origin !== '') {
            $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
            if ($originHost === '' || $host === '' || !hash_equals($host, $originHost)) {
                inboxReply(403, ['ok' => false, 'message' => 'Invalid request origin.']);
            }
        }
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) inboxReply(400, ['ok' => false, 'message' => 'Invalid request.']);
        $action = (string) ($body['action'] ?? '');

        // Family enquiries are sent to an explicitly selected organiser.
        if ($action === 'send') {
            $oid = (int) ($body['organiser_id'] ?? 0);
            $slug = trim((string) ($body['organiser_slug'] ?? ''));
            if (!$oid && $slug !== '') {
                $lookup = $db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");
                $lookup->execute([$slug]);
                $oid = (int) $lookup->fetchColumn();
            }
            $subject = trim((string) ($body['subject'] ?? ''));
            $message = trim((string) ($body['message'] ?? ''));
            $category = (string) ($body['category'] ?? 'help');
            if ($oid < 1 || $subject === '' || $message === '' ||
                mb_strlen($subject) > 180 || mb_strlen($message) > 5000 ||
                !in_array($category, ['help', 'booking'], true)) {
                inboxReply(422, ['ok' => false, 'message' => 'Enter a valid recipient, subject and message.']);
            }

            $q = $db->prepare("SELECT id,email FROM bh_organisers WHERE id=? LIMIT 1");
            $q->execute([$oid]);
            $org = $q->fetch(PDO::FETCH_ASSOC);
            if (!$org) inboxReply(404, ['ok' => false, 'message' => 'Organiser not found.']);

            $q = $db->prepare("INSERT INTO bh_leader_messages(organiser_id,sender_user_id,subject,body,category) VALUES(?,?,?,?,?)");
            $q->execute([$oid, $uid, $subject, $message, $category]);
            inboxMail(
                (string) $org['email'],
                'New Bubba Hub message: ' . $subject,
                "A family has sent you a new " . ($category === 'booking' ? 'booking enquiry' : 'help enquiry') .
                ". Sign in to Bubba Hub > Leader > My Messages to view and reply.\n\n" . $message
            );
            inboxReply(201, ['ok' => true, 'message' => 'Your message has been sent.']);
        }

        if ($action !== 'reply') {
            inboxReply(400, ['ok' => false, 'message' => 'Unsupported action.']);
        }
        if ($user['role'] !== 'leader') {
            inboxReply(403, ['ok' => false, 'message' => 'Leader access required.']);
        }

        if (empty($_SESSION['bh_csrf']) || !is_string($_SESSION['bh_csrf'])) {
            $_SESSION['bh_csrf'] = bin2hex(random_bytes(32));
        }
        $providedCsrf = (string) ($_SERVER['HTTP_X_BH_CSRF'] ?? '');
        if ($providedCsrf === '' || !hash_equals($_SESSION['bh_csrf'], $providedCsrf)) {
            inboxReply(403, ['ok' => false, 'message' => 'Your session security check has expired. Refresh the page and try again.']);
        }
    } elseif ($user['role'] !== 'leader') {
        inboxReply(403, ['ok' => false, 'message' => 'Leader access required.']);
    }

    if (empty($_SESSION['bh_csrf']) || !is_string($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(32));
    }

    $hasOrgUserId = false;
    $column = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");
    $column->execute();
    $hasOrgUserId = (int) $column->fetchColumn() === 1;

    if ($hasOrgUserId) {
        $q = $db->prepare("SELECT id,email FROM bh_organisers WHERE user_id=? LIMIT 1");
        $q->execute([$uid]);
        $org = $q->fetch(PDO::FETCH_ASSOC);
    } else {
        $q = $db->prepare("SELECT id,email FROM bh_organisers WHERE email=? LIMIT 1");
        $q->execute([$user['email']]);
        $org = $q->fetch(PDO::FETCH_ASSOC);
    }
    if (!$org) inboxReply(403, ['ok' => false, 'message' => 'Organiser profile required.']);
    $oid = (int) $org['id'];

    $hasSenderEmail = false;
    $column = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_leader_messages' AND COLUMN_NAME='sender_email'");
    $column->execute();
    $hasSenderEmail = (int) $column->fetchColumn() === 1;
    $senderExpr = $hasSenderEmail ? 'COALESCE(m.sender_email,u.email)' : 'u.email';

    if ($method === 'GET') {
        $count = $db->prepare("SELECT COUNT(*) FROM bh_leader_messages WHERE organiser_id=? AND replied_at IS NULL");
        $count->execute([$oid]);
        $unread = (int) $count->fetchColumn();

        $q = $db->prepare(
            "SELECT m.id,m.subject,m.body,m.category,m.reply,m.replied_at,m.created_at," .
            $senderExpr . " AS sender_email FROM bh_leader_messages m " .
            "LEFT JOIN bh_users u ON u.id=m.sender_user_id " .
            "WHERE m.organiser_id=? ORDER BY m.created_at DESC LIMIT 100"
        );
        $q->execute([$oid]);
        inboxReply(200, [
            'ok' => true,
            'csrf' => $_SESSION['bh_csrf'],
            'messages' => $q->fetchAll(PDO::FETCH_ASSOC),
            'unanswered_count' => $unread
        ]);
    }

    $id = (int) ($body['id'] ?? 0);
    $reply = trim((string) ($body['reply'] ?? ''));
    if (!$id || $reply === '' || mb_strlen($reply) > 5000) {
        inboxReply(422, ['ok' => false, 'message' => 'Write a reply of up to 5000 characters.']);
    }

    $q = $db->prepare(
        "SELECT m.id,m.subject," . $senderExpr . " AS email " .
        "FROM bh_leader_messages m LEFT JOIN bh_users u ON u.id=m.sender_user_id " .
        "WHERE m.id=? AND m.organiser_id=?"
    );
    $q->execute([$id, $oid]);
    $msg = $q->fetch(PDO::FETCH_ASSOC);
    if (!$msg) inboxReply(404, ['ok' => false, 'message' => 'Message not found.']);

    $q = $db->prepare("UPDATE bh_leader_messages SET reply=?,replied_at=NOW() WHERE id=? AND organiser_id=?");
    $q->execute([$reply, $id, $oid]);
    $sent = inboxMail((string) $msg['email'], 'Re: ' . $msg['subject'],
        "A class leader has replied to your Bubba Hub enquiry:\n\n" . $reply .
        "\n\nYou can contact the organiser through Bubba Hub."
    );
    inboxReply(200, [
        'ok' => true,
        'email_sent' => $sent,
        'message' => $sent ? 'Reply saved and emailed to the family.' : 'Reply saved, but email delivery could not be confirmed.'
    ]);
} catch (Throwable $e) {
    error_log('Leader inbox: ' . $e->getMessage());
    inboxReply(500, ['ok' => false, 'message' => 'Messaging is temporarily unavailable.']);
}
