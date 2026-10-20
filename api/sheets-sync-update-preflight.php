<?php
declare(strict_types=1);

/**
 * Bubba Hub live Sheets sync — ID 955 dry-run preflight only.
 * This endpoint has no SQL write statements.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function bhSyncPreflightReply(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bhSyncPreflightReply(405, ['ok' => false, 'error' => 'post_required']);
}
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if (!in_array($host, ['www.bubbahub.co.uk', 'bubbahub.co.uk'], true)) {
    bhSyncPreflightReply(403, ['ok' => false, 'error' => 'live_only']);
}
$secretFile = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) {
    bhSyncPreflightReply(503, ['ok' => false, 'error' => 'sync_not_configured']);
}
require_once $secretFile;
$secret = (string) (getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($secret) < 32) {
    bhSyncPreflightReply(503, ['ok' => false, 'error' => 'sync_not_configured']);
}
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $match) ||
    !hash_equals($secret, trim($match[1]))) {
    bhSyncPreflightReply(401, ['ok' => false, 'error' => 'unauthorized']);
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw) > 4096) {
    bhSyncPreflightReply(413, ['ok' => false, 'error' => 'payload_too_large']);
}
$request = json_decode($raw, true);
if (!is_array($request) ||
    ($request['action'] ?? null) !== 'preflight' ||
    ($request['dry_run'] ?? null) !== true ||
    ($request['id'] ?? null) !== 955 ||
    !array_key_exists('expected_price_from', $request) ||
    !array_key_exists('expected_age_range', $request) ||
    ($request['proposed_price_from'] ?? null) !== '2.00' ||
    ($request['proposed_age_range'] ?? null) !== '0 - 5 years' ||
    !is_string($request['expected_price_from']) ||
    !is_string($request['expected_age_range'])) {
    bhSyncPreflightReply(400, ['ok' => false, 'error' => 'invalid_preflight_request']);
}
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    $stmt = $pdo->prepare('SELECT id, price_from, age_range FROM bh_activities WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', 955, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        bhSyncPreflightReply(404, ['ok' => false, 'error' => 'listing_not_found']);
    }
    $actualPrice = (string) ($row['price_from'] ?? '');
    $actualAge = (string) ($row['age_range'] ?? '');
    $expectedPrice = $request['expected_price_from'];
    $expectedAge = $request['expected_age_range'];
    $unchanged = hash_equals($expectedPrice, $actualPrice) &&
        hash_equals($expectedAge, $actualAge);
    bhSyncPreflightReply(200, [
        'ok' => true,
        'mode' => 'read_only_preflight',
        'id' => 955,
        'current' => ['price_from' => $actualPrice, 'age_range' => $actualAge],
        'proposed' => ['price_from' => '2.00', 'age_range' => '0 - 5 years'],
        'matches_expected' => $unchanged,
        'safe_to_proceed' => $unchanged,
        'writes_performed' => false
    ]);
} catch (Throwable $error) {
    error_log('Bubba Hub Sheets preflight error: ' . $error->getMessage());
    bhSyncPreflightReply(500, ['ok' => false, 'error' => 'preflight_failed']);
}
