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

    $constant = static function(string $name): string {
        if (defined($name)) return trim((string)constant($name));
        $candidates = [
            '/public_html/wp-config.php',
            dirname(__DIR__) . '/wp-config.php',
            dirname(__DIR__, 2) . '/wp-config.php',
        ];
        foreach ($candidates as $file) {
            if (!is_file($file)) continue;
            $contents = @file_get_contents($file);
            if ($contents === false) continue;
            $pattern = '/define\s*\(\s*[\'" ]' . preg_quote($name, '/') . '[\'" ]\s*,\s*[\'" ](.*?)[\'" ]\s*\)\s*;/s';
            if (preg_match($pattern, $contents, $m)) return stripcslashes($m[1]);
        }
        return '';
    };

    $stripe['secret_key'] = $constant('BUBBAHUB_STRIPE_SECRET_KEY')
        ?: trim((string)($stripe['secret_key'] ?? getenv('STRIPE_SECRET_KEY') ?: ''));
    $stripe['monthly_price_id'] = $constant('BUBBAHUB_STRIPE_PRICE_MONTHLY')
        ?: trim((string)($stripe['monthly_price_id'] ?? getenv('BUBBAHUB_STRIPE_PRICE_MONTHLY') ?: 'price_1UNyo9BCqGaB2UiPpuJ64CqM'));
    $stripe['annual_price_id'] = $constant('BUBBAHUB_STRIPE_PRICE_ANNUAL')
        ?: trim((string)($stripe['annual_price_id'] ?? getenv('BUBBAHUB_STRIPE_PRICE_ANNUAL') ?: 'price_1UNytgBCqGaB2UiPeqTNsLh9'));
    $stripe['leader_monthly_price_id'] = $constant('BUBBAHUB_LEADER_STRIPE_PRICE_MONTHLY')
        ?: trim((string)($stripe['leader_monthly_price_id'] ?? getenv('BUBBAHUB_LEADER_STRIPE_PRICE_MONTHLY') ?: ''));
    $stripe['leader_annual_price_id'] = $constant('BUBBAHUB_LEADER_STRIPE_PRICE_ANNUAL')
        ?: trim((string)($stripe['leader_annual_price_id'] ?? getenv('BUBBAHUB_LEADER_STRIPE_PRICE_ANNUAL') ?: ''));
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
    $stripe = bh_stripe_config();

    if ($stripe['secret_key'] === '') {
        bh_stripe_response(503, ['ok'=>false,'error'=>'stripe_not_configured','message'=>'Stripe is not configured yet.']);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $plan = strtolower(trim((string)($input['plan'] ?? 'family_pro')));
    if (!in_array($plan, ['family_pro','leader_pro'], true)) $plan='family_pro';

    $billing = strtolower(trim((string)($input['billing'] ?? 'annual')));
    if (!in_array($billing, ['monthly', 'annual'], true)) $billing = 'annual';

    $priceId = $plan === 'leader_pro'
        ? ($billing === 'monthly' ? $stripe['leader_monthly_price_id'] : $stripe['leader_annual_price_id'])
        : ($billing === 'monthly' ? $stripe['monthly_price_id'] : $stripe['annual_price_id']);

    if ($priceId === '') {
        $label = $plan === 'leader_pro' ? 'Leader Pro' : 'Family Pro';
        bh_stripe_response(503, ['ok'=>false,'error'=>'stripe_price_not_configured','message'=>"The selected {$label} price is not configured yet."]);
    }

    $db = bh_mysql();

    $user = null;
    if ($uid > 0) {
        $q = $db->prepare("SELECT id,email,role FROM bh_users WHERE id=? AND status='active' LIMIT 1");
        $q->execute([$uid]);
        $user = $q->fetch();
        if (!$user) $uid = 0;
    }

    if ($plan === 'leader_pro' && (!$user || ($user['role'] ?? '') !== 'leader')) {
        bh_stripe_response(403, ['ok'=>false,'error'=>'leader_account_required','message'=>'Please sign in to your class leader account before upgrading to Leader Pro.']);
    }

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

    /*
     * Stripe subscription Checkout creates the Customer automatically.
     * customer_creation is intentionally NOT sent here because Stripe only
     * permits customer_creation in payment mode.
     */
    $params = [
        'mode' => 'subscription',
        'line_items[0][price]' => $priceId,
        'line_items[0][quantity]' => '1',
        'allow_promotion_codes' => 'true',
        'billing_address_collection' => 'auto',
        'success_url' => $plan === 'leader_pro'
            ? 'https://bubbahub.co.uk/account/leader-subscription.html?stripe=success'
            : $stripe['success_url'],
        'cancel_url' => $plan === 'leader_pro'
            ? 'https://bubbahub.co.uk/account/leader-subscription.html?stripe=cancelled'
            : $stripe['cancel_url'],
        'metadata[user_id]' => (string)$uid,
        'metadata[plan]' => $plan,
        'metadata[billing]' => $billing,
        'subscription_data[metadata][user_id]' => (string)$uid,
        'subscription_data[metadata][plan]' => $plan,
        'subscription_data[metadata][billing]' => $billing,
    ];

    if ($user) {
        $params['customer_email'] = (string)$user['email'];
        $params['client_reference_id'] = (string)$uid;
    }

    $session = bh_stripe_post('checkout/sessions', $params, $stripe['secret_key']);

    echo json_encode([
        'ok'=>true,
        'url'=>$session['url'] ?? null,
        'session_id'=>$session['id'] ?? null
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    bh_stripe_response(500, ['ok'=>false,'error'=>'stripe_checkout_error','message'=>$e->getMessage()]);
}
?>