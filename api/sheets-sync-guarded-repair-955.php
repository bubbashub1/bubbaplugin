<?php
declare(strict_types=1);

/**
 * One-record guarded repair for live listing 955.
 * Disabled until BH_SHEETS_955_WRITE_ENABLED=1 is configured server-side.
 * Never enable from a request parameter.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh955Reply(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bh955Reply(405, ['ok'=>false, 'error'=>'post_required']);
}
if (!in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['www.bubbahub.co.uk','bubbahub.co.uk'], true)) {
    bh955Reply(403, ['ok'=>false, 'error'=>'live_only']);
}
$secretPath = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretPath)) {
    bh955Reply(503, ['ok'=>false, 'error'=>'sync_not_configured']);
}
require_once $secretPath;
$token = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (strlen($token) < 32 || !preg_match('/^Bearer\s+(.+)$/i', $auth, $m) || !hash_equals($token, trim($m[1]))) {
    bh955Reply(401, ['ok'=>false, 'error'=>'unauthorized']);
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw) > 4096) {
    bh955Reply(413, ['ok'=>false, 'error'=>'payload_too_large']);
}
$req = json_decode($raw, true);
if (!is_array($req) || ($req['action'] ?? null) !== 'repair_955' ||
    ($req['id'] ?? null) !== 955 ||
    ($req['expected_price_from'] ?? null) !== '' ||
    ($req['expected_age_range'] ?? null) !== '' ||
    ($req['proposed_price_from'] ?? null) !== '2.00' ||
    ($req['proposed_age_range'] ?? null) !== '0 - 5 years' ||
    !in_array($req['dry_run'] ?? null, [true, false], true)) {
    bh955Reply(400, ['ok'=>false, 'error'=>'invalid_request']);
}
$dryRun = $req['dry_run'];
if (!$dryRun && getenv('BH_SHEETS_955_WRITE_ENABLED') !== '1') {
    bh955Reply(403, ['ok'=>false, 'error'=>'writes_disabled']);
}
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('Expected PDO connection');
    }
    if ($dryRun) {
        $stmt = $pdo->prepare('SELECT price_from, age_range FROM bh_activities WHERE id = 955 LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) bh955Reply(404, ['ok'=>false, 'error'=>'listing_not_found']);
        $matches = (string)($row['price_from'] ?? '') === '' && (string)($row['age_range'] ?? '') === '';
        bh955Reply(200, [
            'ok'=>true, 'mode'=>'dry_run', 'id'=>955,
            'current'=>['price_from'=>(string)($row['price_from'] ?? ''), 'age_range'=>(string)($row['age_range'] ?? '')],
            'proposed'=>['price_from'=>'2.00', 'age_range'=>'0 - 5 years'],
            'safe_to_proceed'=>$matches, 'writes_performed'=>false
        ]);
    }
    $pdo->beginTransaction();
    // Locks the row and performs compare-and-swap inside one transaction.
    $stmt = $pdo->prepare('SELECT price_from, age_range FROM bh_activities WHERE id = 955 FOR UPDATE');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        bh955Reply(404, ['ok'=>false, 'error'=>'listing_not_found']);
    }
    $before = ['price_from'=>(string)($row['price_from'] ?? ''), 'age_range'=>(string)($row['age_range'] ?? '')];
    if ($before['price_from'] !== '' || $before['age_range'] !== '') {
        $pdo->rollBack();
        bh955Reply(409, ['ok'=>false, 'error'=>'conflict', 'current'=>$before, 'writes_performed'=>false]);
    }
    // An audit table is mandatory: if logging fails, the update rolls back.
    // Audit table must be provisioned by a separate migration before writes are enabled.
    // Never run DDL inside this transaction (MySQL DDL implicitly commits).
    $upd = $pdo->prepare("UPDATE bh_activities
        SET price_from = '2.00', age_range = '0 - 5 years'
        WHERE id = 955 AND (price_from IS NULL OR price_from = '')
          AND (age_range IS NULL OR age_range = '')");
    $upd->execute();
    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        bh955Reply(409, ['ok'=>false, 'error'=>'conflict', 'writes_performed'=>false]);
    }
    $after = ['price_from'=>'2.00','age_range'=>'0 - 5 years'];
    $audit = $pdo->prepare('INSERT INTO bh_sheets_sync_audit (listing_id, operation, before_json, after_json)
        VALUES (955, :operation, :before_json, :after_json)');
    $audit->execute([
        ':operation'=>'manual_guarded_repair_955',
        ':before_json'=>json_encode($before, JSON_THROW_ON_ERROR),
        ':after_json'=>json_encode($after, JSON_THROW_ON_ERROR)
    ]);
    $pdo->commit();
    bh955Reply(200, ['ok'=>true, 'mode'=>'guarded_repair', 'id'=>955, 'before'=>$before, 'after'=>$after, 'writes_performed'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('BH sheets guarded 955 repair failed: '.$e->getMessage());
    bh955Reply(500, ['ok'=>false, 'error'=>'repair_failed', 'writes_performed'=>false]);
}
