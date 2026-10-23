<?php
declare(strict_types=1);

/**
 * Expo readiness probe. GET-only and deliberately read-only.
 * No ticket creation, payment collection, emails, or public admin data.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function expoReply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    expoReply(405, ['ok'=>false,'error'=>'method_not_allowed']);
}
$bookingEnabled = defined('BUBBAHUB_EXPO_BOOKINGS_ENABLED')
    && constant('BUBBAHUB_EXPO_BOOKINGS_ENABLED') === true;
$exhibitorEnabled = defined('BUBBAHUB_EXPO_EXHIBITORS_ENABLED')
    && constant('BUBBAHUB_EXPO_EXHIBITORS_ENABLED') === true;

// Feature flags alone never activate registration: submission endpoints
// require separate implementation, security review and testing.
$tablesReady = false;
try {
    require __DIR__ . '/db.php';
    $pdo = bh_mysql();
    $required = ['bh_expo_reservations','bh_expo_tickets','bh_expo_exhibitors','bh_expo_tables'];
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (?,?,?,?)'
    );
    $stmt->execute($required);
    $tablesReady = ((int)$stmt->fetchColumn() === count($required));
} catch (Throwable $error) {
    // Fail closed, never reveal database credentials or connection details.
    $tablesReady = false;
}
expoReply(200, [
    'ok'=>true,
    'event'=>'bubba-me-2027',
    'database_ready'=>$tablesReady,
    'visitor_reservations_open'=>false,
    'exhibitor_applications_open'=>false,
    'payment_collection_open'=>false,
    'email_confirmation_ready'=>false,
    'feature_flags_requested'=>['visitor'=>$bookingEnabled,'exhibitor'=>$exhibitorEnabled],
    'message'=>$tablesReady
        ? 'Database foundation detected. Public booking and applications remain closed pending implementation and testing.'
        : 'Expo tables have not been verified. Public booking and applications remain closed.'
]);
