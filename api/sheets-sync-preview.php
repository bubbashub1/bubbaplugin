<?php
declare(strict_types=1);
/**
 * Bubba Hub Google Sheets sync preview.
 * Read-only endpoint. Does not modify the database.
 * Configure BUBBAHUB_SHEETS_SYNC_TOKEN outside the web root.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh_preview_reply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    bh_preview_reply(405, ['ok'=>false,'error'=>'method_not_allowed']);
}
// Staging-only: reuse the existing BH_SHEETS_SYNC_TOKEN name.
// Accept the newer BUBBAHUB_SHEETS_SYNC_TOKEN name for compatibility.
// The secret file is outside staging_html and never returned to callers.
$expected = getenv('BH_SHEETS_SYNC_TOKEN');
if (!is_string($expected) || $expected === '') {
    $expected = getenv('BUBBAHUB_SHEETS_SYNC_TOKEN');
}
if (!is_string($expected) || $expected === '') {
    $privateConfig = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
    if (is_file($privateConfig) && is_readable($privateConfig)) {
        require_once $privateConfig;
    }
    $expected = getenv('BH_SHEETS_SYNC_TOKEN');
    if (!is_string($expected) || $expected === '') {
        $expected = getenv('BUBBAHUB_SHEETS_SYNC_TOKEN');
    }
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!is_string($expected) || strlen($expected) < 32 ||
    !preg_match('/^Bearer ([^\x00-\x20\x7F]+)$/D', $authorization, $matches) ||
    !hash_equals($expected, $matches[1])) {
    bh_preview_reply(401, ['ok'=>false,'error'=>'unauthorized']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) {
    bh_preview_reply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
$raw = file_get_contents('php://input', false, null, 0, 262145);
if ($raw === false || strlen($raw) > 262144) {
    bh_preview_reply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
catch (JsonException $e) { bh_preview_reply(400, ['ok'=>false,'error'=>'invalid_json']); }
if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows']) || !array_is_list($data['rows'])) {
    bh_preview_reply(400, ['ok'=>false,'error'=>'rows_array_required']);
}
if (count($data['rows']) > 100) {
    bh_preview_reply(422, ['ok'=>false,'error'=>'too_many_rows']);
}
foreach ($data['rows'] as $row) {
    if (!is_array($row) || array_is_list($row)) {
        bh_preview_reply(422, ['ok'=>false,'error'=>'invalid_row']);
    }
}
require_once __DIR__ . '/sheets-sync-preflight.php';
$preflight = bh_sync_preflight($data['rows']);
// Read-only database ID lookup, deliberately conservative:
// URL-only rows and missing explicit IDs remain manual-review candidates.
// No inserts, updates, deletes, schema migrations or database-side logging.
$ids = [];
foreach ($data['rows'] as $index => $row) {
    if (($preflight['rows'][$index]['status'] ?? '') !== 'pending_database_match') continue;
    $id = bh_sync_normalize_id($row['activity_id'] ?? $row['id'] ?? null);
    if ($id !== null) $ids[$id] = true;
}
if ($ids) {
    try {
        require_once __DIR__ . '/db.php';
        $db = bh_mysql();
        // Cast to integers only after validated decimal ID; never interpolate user input.
        // Prepared placeholders preserve the existing database connection and permissions.
        $idList = array_keys($ids);
        $found = [];
        foreach (array_chunk($idList, 50) as $chunk) {
            $sql = 'SELECT id FROM bh_activities WHERE id IN (' .
                implode(',', array_fill(0, count($chunk), '?')) . ')';
            $stmt = $db->prepare($sql);
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existingId) {
                $found[(string)$existingId] = true;
            }
        }
        foreach ($data['rows'] as $index => $row) {
            if (($preflight['rows'][$index]['status'] ?? '') !== 'pending_database_match') continue;
            $id = bh_sync_normalize_id($row['activity_id'] ?? $row['id'] ?? null);
            if ($id === null) {
                $preflight['rows'][$index]['status'] = 'review_url_only';
            } elseif (isset($found[$id])) {
                $preflight['rows'][$index]['status'] = 'matched_id';
                $preflight['rows'][$index]['matched_id'] = $id;
            } else {
                $preflight['rows'][$index]['status'] = 'review_unknown_id';
            }
        }
        $preflight['database_matching_enabled'] = true;
        $preflight['matching_scope'] = 'explicit_id_only';
    } catch (Throwable $e) {
        // Fail closed without disclosing database credentials or query details.
        bh_preview_reply(503, ['ok'=>false,'error'=>'database_preview_unavailable',
            'writes_performed'=>0]);
    }
}
$preflight['counts'] = [];
foreach ($preflight['rows'] as $result) {
    $status = $result['status'];
    $preflight['counts'][$status] = ($preflight['counts'][$status] ?? 0) + 1;
}
bh_preview_reply(200, [
    'ok'=>true,
    'mode'=>'preview_only',
    'writes_performed'=>0,
    'rows_received'=>count($data['rows']),
    'preflight'=>$preflight,
    'message'=>'Read-only ID matching where available. URL-only rows, unknown IDs and field conflicts require later review; no writes performed.'
]);
