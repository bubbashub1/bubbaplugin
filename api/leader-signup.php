<?php
declare(strict_types=1);
/**
 * Compatibility endpoint. All leader registration must use the canonical
 * auth.php flow, which creates an email-verification token and does not
 * authenticate an unverified leader.
 *
 * Do not accept legacy signup submissions here: they previously bypassed
 * email verification and created an authenticated session.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
http_response_code(410);
echo json_encode([
    'ok' => false,
    'error' => 'legacy_signup_retired',
    'message' => 'Please use the updated leader registration page to create and verify your account.',
    'signup_url' => '/leader-login'
], JSON_UNESCAPED_SLASHES);
