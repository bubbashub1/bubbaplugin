<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function bh_stripe_webhook_config(): array {
    $file = __DIR__ . '/config.php';
    $config = is_file($file) ? require $file : [];
    $stripe = is_array($config['stripe'] ?? null) ? $config['stripe'] : [];

    // Read the webhook secret from wp-config.php without executing WordPress.
    $secret = '';
    $name = 'BUBBAHUB_STRIPE_WEBHOOK_SECRET';
    if (defined($name)) {
        $secret = trim((string)constant($name));
    } else {
        $candidates = [
            '/public_html/wp-config.php',
            dirname(__DIR__) . '/wp-config.php',
            dirname(__DIR__, 2) . '/wp-config.php',
        ];
        foreach ($candidates as $path) {
            if (!is_file($path)) continue;
            $contents = @file_get_contents($path);
            if ($contents === false) continue;
            $pattern = '/define\\s*\\(\\s*[\'" ]' . preg_quote($name, '/') . '[\'" ]\\s*,\\s*[\'" ](.*?)[\'" ]\\s*\\)\\s*;/s';
            if (preg_match($pattern, $contents, $m)) {
                $secret = stripcslashes($m[1]);
                break;
            }
        }
    }

    $stripe['webhook_secret'] = trim($secret ?: (string)($stripe['webhook_secret'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
    return $stripe;
}

function bh_stripe_signature_valid(string $payload, string $header, string $secret): bool {
    if ($secret === '' || $header === '') return false;
    $timestamp = 0; $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key,$value] = array_pad(explode('=', $part, 2), 2, '');
        if ($key === 't') $timestamp = (int)$value;
        if ($key === 'v1' && $value !== '') $signatures[] = $value;
    }
    if (!$timestamp || abs(time() - $timestamp) > 300 || !$signatures) return false;
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) if (hash_equals($expected, $signature)) return true;
    return false;
}

function bh_upsert_stripe_subscription(PDO $db, int $uid, string $plan, string $status, string $source, ?string $customerId, ?string $subscriptionId, ?string $checkoutId, ?string $startedAt, ?string $expiresAt): void {
    $sql = "INSERT INTO bh_user_subscriptions
        (user_id,plan,status,source,stripe_customer_id,stripe_subscription_id,stripe_checkout_session_id,started_at,expires_at)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
        plan=VALUES(plan),status=VALUES(status),source=VALUES(source),
        stripe_customer_id=VALUES(stripe_customer_id),
        stripe_subscription_id=VALUES(stripe_subscription_id),
        stripe_checkout_session_id=VALUES(stripe_checkout_session_id),
        started_at=COALESCE(VALUES(started_at),started_at),
        expires_at=VALUES(expires_at)";
    $q = $db->prepare($sql);
    $q->execute([$uid,$plan,$status,$source,$customerId,$subscriptionId,$checkoutId,$startedAt,$expiresAt]);
}

try {
    require __DIR__ . '/db.php';
    $cfg = bh_stripe_webhook_config();
    $payload = (string)file_get_contents('php://input');
    $signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    if (!bh_stripe_signature_valid($payload, $signature, $cfg['webhook_secret'])) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_signature']); exit;
    }

    $event = json_decode($payload, true);
    if (!is_array($event)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_payload']); exit; }

    $db = bh_mysql();
    $db->exec("CREATE TABLE IF NOT EXISTS bh_user_subscriptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        plan VARCHAR(40) NOT NULL DEFAULT 'family_pro',
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        source VARCHAR(40) NOT NULL DEFAULT 'event',
        stripe_customer_id VARCHAR(255) NULL,
        stripe_subscription_id VARCHAR(255) NULL,
        stripe_checkout_session_id VARCHAR(255) NULL,
        started_at DATETIME NULL,
        expires_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id), UNIQUE KEY uq_bh_user_subscription(user_id),
        UNIQUE KEY uq_bh_stripe_subscription(stripe_subscription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $type = (string)($event['type'] ?? '');
    $obj = $event['data']['object'] ?? [];

    if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
        // Never grant paid access for an incomplete or unpaid checkout.
        if (($obj['mode'] ?? '') !== 'subscription') {
            echo json_encode(['received'=>true]); exit;
        }
        if (($obj['payment_status'] ?? '') !== 'paid' && ($obj['payment_status'] ?? '') !== 'no_payment_required') {
            echo json_encode(['received'=>true]); exit;
        }
        $uid = (int)($obj['metadata']['user_id'] ?? $obj['client_reference_id'] ?? 0);
        $subscriptionId = is_string($obj['subscription'] ?? null) ? $obj['subscription'] : null;
        $customerId = is_string($obj['customer'] ?? null) ? $obj['customer'] : null;

        // Family Pro can be purchased without first creating a normal account.
        // Stripe supplies the verified checkout email; create/link the family
        // account here and then attach the subscription to that user.
        if ($uid <= 0 && ($obj['metadata']['plan'] ?? 'family_pro') === 'family_pro') {
            $email = strtolower(trim((string)($obj['customer_details']['email'] ?? $obj['customer_email'] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $q = $db->prepare("SELECT id,status FROM bh_users WHERE LOWER(email)=? LIMIT 1");
                $q->execute([$email]);
                $existing = $q->fetch();

                if ($existing) {
                    if (($existing['status'] ?? '') === 'active') {
                        $uid = (int)$existing['id'];
                    }
                } else {
                    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                    $q = $db->prepare("INSERT INTO bh_users (email,password_hash,role,status) VALUES (?,?,'family','active')");
                    $q->execute([$email,$randomPassword]);
                    $uid = (int)$db->lastInsertId();

                    // Send the normal welcome email; the customer can use the
                    // password-reset flow later if they want to sign in.
                    try {
                        require_once __DIR__ . '/mailer.php';
                        bh_send_smtp_mail(
                            $email,
                            'Welcome to Bubba Hub 💚',
                            '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#26352b;max-width:640px;margin:0 auto"><h1 style="color:#416651">Welcome to Bubba Hub 💚</h1><p>Your Family Pro account is ready.</p><p>Your payment has been received and your Family Pro access is now being activated.</p><p>You can sign in at <a href="https://bubbahub.co.uk/auth.html">Bubba Hub</a> and use the password reset option if you would like to set a password.</p><p>The Bubba Hub team</p></div>',
                            "Welcome to Bubba Hub 💚\n\nYour Family Pro account is ready. Your payment has been received and your Family Pro access is now being activated.\n\nSign in: https://bubbahub.co.uk/auth.html\nUse the password reset option if you would like to set a password.\n\nThe Bubba Hub team"
                        );
                    } catch (Throwable $ignored) {}
                }
            }
        }

        $plan = (string)($obj['metadata']['plan'] ?? 'family_pro');
        if (!in_array($plan,['family_pro','leader_pro'],true)) $plan='family_pro';
        if ($plan==='leader_pro' && $uid>0) {
            $roleQ=$db->prepare("SELECT role FROM bh_users WHERE id=? LIMIT 1"); $roleQ->execute([$uid]);
            if (($roleQ->fetchColumn() ?? '') !== 'leader') $uid=0;
        }
        if ($uid > 0) {
            bh_upsert_stripe_subscription($db,$uid,$plan,'active','stripe',$customerId,$subscriptionId,(string)($obj['id'] ?? ''),date('Y-m-d H:i:s'),null);
        }
    } elseif (in_array($type,['customer.subscription.updated','customer.subscription.created','customer.subscription.deleted'],true)) {
        $subscriptionId = (string)($obj['id'] ?? '');
        $status = (string)($obj['status'] ?? 'inactive');
        $activeStatus = in_array($status,['active','trialing'],true) ? 'active' : $status;
        $uid = (int)($obj['metadata']['user_id'] ?? 0);
        $plan = (string)($obj['metadata']['plan'] ?? 'family_pro');
        if (!in_array($plan,['family_pro','leader_pro'],true)) $plan='family_pro';
        if (!$uid && $subscriptionId !== '') {
            $q=$db->prepare("SELECT user_id FROM bh_user_subscriptions WHERE stripe_subscription_id=? LIMIT 1");
            $q->execute([$subscriptionId]); $uid=(int)$q->fetchColumn();
        }
        if ($uid && $plan === 'leader_pro') {
            $roleQ=$db->prepare("SELECT role FROM bh_users WHERE id=? LIMIT 1");
            $roleQ->execute([$uid]);
            if ($roleQ->fetchColumn() !== 'leader') $uid=0;
        }
        if ($uid) {
            $expires = !empty($obj['current_period_end']) ? date('Y-m-d H:i:s',(int)$obj['current_period_end']) : null;
            bh_upsert_stripe_subscription($db,$uid,$plan,$activeStatus,'stripe',(string)($obj['customer'] ?? ''),$subscriptionId,null,null,$expires);
        }
    }

    echo json_encode(['received'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'webhook_error']);
}
?>