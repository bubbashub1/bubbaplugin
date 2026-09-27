<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_push_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

function bh_push_config(): array {
    $configFile = __DIR__.'/config.php';
    $config = is_file($configFile) ? (require $configFile) : [];
    return [
        'public' => trim((string)($config['push']['vapid_public'] ?? getenv('BUBBAHUB_VAPID_PUBLIC') ?: '')),
        'private' => trim((string)($config['push']['vapid_private'] ?? getenv('BUBBAHUB_VAPID_PRIVATE') ?: '')),
        'subject' => trim((string)($config['push']['vapid_subject'] ?? getenv('BUBBAHUB_VAPID_SUBJECT') ?: 'mailto:noreply@bubbahub.co.uk')),
        'secret' => trim((string)($config['push']['send_secret'] ?? getenv('BUBBAHUB_PUSH_SECRET') ?: ''))
    ];
}

function bh_push_session(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
}

function bh_push_csrf(): string {
    if (!isset($_SESSION['bh_csrf'])) $_SESSION['bh_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['bh_csrf'];
}

try {
    require __DIR__.'/db.php';
    $db = bh_mysql();
    bh_push_session();
    $config = bh_push_config();
    $userId = (int)($_SESSION['bh_user_id'] ?? 0);

    $db->exec("CREATE TABLE IF NOT EXISTS bh_push_subscriptions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      endpoint TEXT NOT NULL,
      endpoint_hash CHAR(64) NOT NULL,
      p256dh VARCHAR(255) NOT NULL,
      auth VARCHAR(255) NOT NULL,
      content_encoding VARCHAR(30) NOT NULL DEFAULT 'aes128gcm',
      user_agent VARCHAR(500) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
      INDEX idx_push_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $action = trim((string)($_GET['action'] ?? ''));

    if ($action === 'config' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($config['public'] === '') bh_push_json(503, ['ok'=>false,'error'=>'push_not_configured','message'=>'Push notifications are not configured yet.']);
        bh_push_json(200, ['ok'=>true,'publicKey'=>$config['public']]);
    }

    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $secret = trim((string)($_SERVER['HTTP_X_BUBBAHUB_PUSH_SECRET'] ?? $_GET['token'] ?? ''));
        if ($config['private'] === '' || $config['public'] === '' || $config['secret'] === '' || !hash_equals($config['secret'], $secret)) {
            bh_push_json(403, ['ok'=>false,'error'=>'push_forbidden']);
        }
        if (!class_exists('Minishlink\\WebPush\\WebPush')) {
            $autoload = dirname(__DIR__).'/vendor/autoload.php';
            if (is_file($autoload)) require_once $autoload;
        }
        if (!class_exists('Minishlink\\WebPush\\WebPush')) {
            bh_push_json(503, ['ok'=>false,'error'=>'webpush_library_missing','message'=>'Run composer install on the server.']);
        }

        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) bh_push_json(400, ['ok'=>false,'error'=>'invalid_json']);
        $payload = [
            'title'=>(string)($body['title'] ?? 'Bubba Hub'),
            'body'=>(string)($body['message'] ?? $body['body'] ?? ''),
            'url'=>(string)($body['url'] ?? '/my-hub.html'),
            'tag'=>(string)($body['tag'] ?? 'bubbahub')
        ];
        $targetUser = isset($body['user_id']) ? (int)$body['user_id'] : 0;
        $sql = $targetUser > 0
            ? "SELECT * FROM bh_push_subscriptions WHERE user_id=?"
            : "SELECT * FROM bh_push_subscriptions";
        $stmt = $targetUser > 0 ? $db->prepare($sql) : $db->query($sql);
        if ($targetUser > 0) $stmt->execute([$targetUser]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $auth = [
            'VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['public'],
                'privateKey' => $config['private']
            ]
        ];
        $webPush = new Minishlink\\WebPush\\WebPush($auth);
        $sent = 0; $failed = 0; $expired = 0;
        foreach ($subscriptions as $sub) {
            try {
                $subscription = Minishlink\\WebPush\\Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys' => ['p256dh'=>$sub['p256dh'], 'auth'=>$sub['auth']],
                    'contentEncoding' => $sub['content_encoding'] ?: 'aes128gcm'
                ]);
                $report = $webPush->sendOneNotification($subscription, json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    $failed++;
                    $reason = (string)$report->getReason();
                    if (str_contains($reason, '404') || str_contains($reason, '410')) {
                        $db->prepare("DELETE FROM bh_push_subscriptions WHERE id=?")->execute([(int)$sub['id']]);
                        $expired++;
                    }
                }
            } catch (Throwable $e) {
                $failed++;
            }
        }
        bh_push_json(200, ['ok'=>true,'sent'=>$sent,'failed'=>$failed,'expired'=>$expired]);
    }

    if ($userId < 1) bh_push_json(401, ['ok'=>false,'error'=>'login_required']);

    $user = $db->prepare("SELECT id,status FROM bh_users WHERE id=? LIMIT 1");
    $user->execute([$userId]);
    $account = $user->fetch();
    if (!$account || $account['status'] !== 'active') bh_push_json(401, ['ok'=>false,'error'=>'login_required']);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("SELECT id FROM bh_push_subscriptions WHERE user_id=? LIMIT 1");
        $stmt->execute([$userId]);
        bh_push_json(200, ['ok'=>true,'subscribed'=>(bool)$stmt->fetch(),'csrf'=>bh_push_csrf()]);
    }

    if (!hash_equals(bh_push_csrf(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        bh_push_json(403, ['ok'=>false,'error'=>'csrf_invalid']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $endpoint = trim((string)($body['endpoint'] ?? ''));
        if ($endpoint !== '') {
            $db->prepare("DELETE FROM bh_push_subscriptions WHERE user_id=? AND endpoint_hash=?")
               ->execute([$userId, hash('sha256',$endpoint)]);
        } else {
            $db->prepare("DELETE FROM bh_push_subscriptions WHERE user_id=?")->execute([$userId]);
        }
        bh_push_json(200, ['ok'=>true,'subscribed'=>false,'csrf'=>bh_push_csrf()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') bh_push_json(405, ['ok'=>false,'error'=>'method_not_allowed']);

    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) bh_push_json(400, ['ok'=>false,'error'=>'invalid_json']);

    $endpoint = trim((string)($body['endpoint'] ?? ''));
    $p256dh = trim((string)($body['p256dh'] ?? ''));
    $auth = trim((string)($body['auth'] ?? ''));
    $encoding = trim((string)($body['contentEncoding'] ?? 'aes128gcm'));
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        bh_push_json(422, ['ok'=>false,'error'=>'invalid_subscription']);
    }

    $hash = hash('sha256', $endpoint);
    $stmt = $db->prepare("INSERT INTO bh_push_subscriptions
      (user_id,endpoint,endpoint_hash,p256dh,auth,content_encoding,user_agent)
      VALUES (?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),p256dh=VALUES(p256dh),auth=VALUES(auth),content_encoding=VALUES(content_encoding),user_agent=VALUES(user_agent)");
    $stmt->execute([$userId,$endpoint,$hash,$p256dh,$auth,$encoding,substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500)]);

    $db->prepare("UPDATE bh_user_preferences SET push_enabled=1 WHERE user_id=?")->execute([$userId]);
    bh_push_json(200, ['ok'=>true,'subscribed'=>true,'csrf'=>bh_push_csrf()]);
} catch (Throwable $e) {
    bh_push_json(500, ['ok'=>false,'error'=>'push_error','message'=>$e->getMessage()]);
}
?>
