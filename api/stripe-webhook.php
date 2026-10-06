<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function bh_stripe_webhook_config(): array {
    $file = __DIR__ . '/config.php';
    $config = is_file($file) ? require $file : [];
    $stripe = is_array($config['stripe'] ?? null) ? $config['stripe'] : [];
    $stripe['webhook_secret'] = trim((string)($stripe['webhook_secret'] ?? getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
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

function bh_upsert_stripe_subscription(PDO $db, int $uid, string $status, string $source, ?string $customerId, ?string $subscriptionId, ?string $checkoutId, ?string $startedAt, ?string $expiresAt): void {
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
    $q->execute([$uid,'family_pro',$status,$source,$customerId,$subscriptionId,$checkoutId,$startedAt,$expiresAt]);
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

    if ($type === 'checkout.session.completed') {
        $uid = (int)($obj['metadata']['user_id'] ?? $obj['client_reference_id'] ?? 0);
        if ($uid > 0) {
            $subscriptionId = is_string($obj['subscription'] ?? null) ? $obj['subscription'] : null;
            $customerId = is_string($obj['customer'] ?? null) ? $obj['customer'] : null;
            bh_upsert_stripe_subscription($db,$uid,'active','stripe',$customerId,$subscriptionId,(string)($obj['id'] ?? ''),date('Y-m-d H:i:s'),null);
        }
    } elseif (in_array($type,['customer.subscription.updated','customer.subscription.created','customer.subscription.deleted'],true)) {
        $subscriptionId = (string)($obj['id'] ?? '');
        $status = (string)($obj['status'] ?? 'inactive');
        $activeStatus = in_array($status,['active','trialing'],true) ? 'active' : $status;
        $uid = (int)($obj['metadata']['user_id'] ?? 0);
        if (!$uid && $subscriptionId !== '') {
            $q=$db->prepare("SELECT user_id FROM bh_user_subscriptions WHERE stripe_subscription_id=? LIMIT 1");
            $q->execute([$subscriptionId]); $uid=(int)$q->fetchColumn();
        }
        if ($uid) {
            $expires = !empty($obj['current_period_end']) ? date('Y-m-d H:i:s',(int)$obj['current_period_end']) : null;
            bh_upsert_stripe_subscription($db,$uid,$activeStatus,'stripe',(string)($obj['customer'] ?? ''),$subscriptionId,null,null,$expires);
        }
    }

    echo json_encode(['received'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'webhook_error']);
}
?>