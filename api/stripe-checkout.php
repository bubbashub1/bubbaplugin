<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_stripe_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function bh_stripe_config(): array {
    $file = __DIR__ . '/config.php';
    $config = is_file($file) ? require $file : [];
    $stripe = is_array($config['stripe'] ?? null) ? $config['stripe'] : [];
    $stripe['secret_key'] = trim((string)($stripe['secret_key'] ?? getenv('STRIPE_SECRET_KEY') ?: ''));
    $stripe['success_url'] = trim((string)($stripe['success_url'] ?? getenv('BUBBAHUB_STRIPE_SUCCESS_URL') ?: 'https://bubbahub.co.uk/account/subscription.html?stripe=success'));
    $stripe['cancel_url'] = trim((string)($stripe['cancel_url'] ?? getenv('BUBBAHUB_STRIPE_CANCEL_URL') ?: 'https://bubbahub.co.uk/account/subscription.html?stripe=cancelled'));
    return $stripe;
}

function bh_stripe_post(string $endpoint, array $params, string $secret): array {
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret],
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $error !== '') throw new RuntimeException('Stripe connection failed.');
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Stripe returned invalid JSON.');
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException((string)($data['error']['message'] ?? 'Stripe request failed.'));
    }
    return $data;
}

try {
    require __DIR__ . '/db.php';
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $uid = (int)($_SESSION['bh_user_id'] ?? 0);
    if (!$uid) bh_stripe_response(401, ['ok'=>false,'error'=>'login_required']);

    $stripe = bh_stripe_config();
    if ($stripe['secret_key'] === '') bh_stripe_response(503, ['ok'=>false,'error'=>'stripe_not_configured','message'=>'Stripe is not configured yet.']);

    $db = bh_mysql();
    $q = $db->prepare("SELECT id,email FROM bh_users WHERE id=? AND status='active' LIMIT 1");
    $q->execute([$uid]);
    $user = $q->fetch();
    if (!$user) bh_stripe_response(401, ['ok'=>false,'error'=>'account_not_found']);

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
        PRIMARY KEY(id),
        UNIQUE KEY uq_bh_user_subscription(user_id),
        UNIQUE KEY uq_bh_stripe_subscription(stripe_subscription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $session = bh_stripe_post('checkout/sessions', [
        'mode' => 'subscription',
        'customer_email' => (string)$user['email'],
        'client_reference_id' => (string)$uid,
        'line_items[0][price_data][currency]' => 'gbp',
        'line_items[0][price_data][product_data][name]' => 'Bubba Hub Family Pro',
        'line_items[0][price_data][product_data][description]' => 'Family Pro membership · £20 per year',
        'line_items[0][price_data][unit_amount]' => '2000',
        'line_items[0][price_data][recurring][interval]' => 'year',
        'line_items[0][quantity]' => '1',
        'allow_promotion_codes' => 'true',
        'billing_address_collection' => 'auto',
        'success_url' => $stripe['success_url'],
        'cancel_url' => $stripe['cancel_url'],
        'metadata[user_id]' => (string)$uid,
        'metadata[plan]' => 'family_pro',
    ], $stripe['secret_key']);

    echo json_encode(['ok'=>true,'url'=>$session['url'] ?? null,'session_id'=>$session['id'] ?? null], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    bh_stripe_response(500, ['ok'=>false,'error'=>'stripe_checkout_error','message'=>$e->getMessage()]);
}
?>