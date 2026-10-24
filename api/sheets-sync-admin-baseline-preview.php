<?php
declare(strict_types=1);
/**
 * Read-only staging baseline review preview.
 * Uses a server-stored review queue entry, never client-supplied sheet values.
 * No approval or activity writes.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhBaselinePreviewReply(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk')
    bhBaselinePreviewReply(403, ['ok'=>false,'error'=>'staging_only']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET')
    bhBaselinePreviewReply(405, ['ok'=>false,'error'=>'get_required']);
require_once __DIR__ . '/admin-session-guard.php';
$reviewId = filter_input(INPUT_GET, 'review_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$reviewId) bhBaselinePreviewReply(422, ['ok'=>false,'error'=>'review_id_required']);
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    $reviewStmt = $pdo->prepare("SELECT id,activity_id,field_name,website_value,sheet_value,
        website_fingerprint,sheet_fingerprint,status
        FROM bh_sheets_sync_review_queue
        WHERE id=:id AND environment='staging' LIMIT 1");
    $reviewStmt->execute([':id'=>$reviewId]);
    $review = $reviewStmt->fetch(PDO::FETCH_ASSOC);
    if (!$review) bhBaselinePreviewReply(404, ['ok'=>false,'error'=>'review_not_found']);
    $field = (string)$review['field_name'];
    if (!in_array($field, ['title','description','age_range','price_from','booking_url'], true))
        bhBaselinePreviewReply(409, ['ok'=>false,'error'=>'field_not_allowed']);
    $stmt = $pdo->prepare('SELECT status, `'.$field.'` AS field_value FROM bh_activities WHERE id=:id LIMIT 1');
    $stmt->execute([':id'=>$review['activity_id']]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$listing) bhBaselinePreviewReply(404, ['ok'=>false,'error'=>'listing_not_found']);
    $current = (string)($listing['field_value'] ?? '');
    $snapshot = (string)($review['website_value'] ?? '');
    $sheet = (string)($review['sheet_value'] ?? '');
    $websiteUnchanged = hash_equals((string)$review['website_fingerprint'], hash('sha256', $current))
        && hash_equals((string)$review['website_fingerprint'], hash('sha256', $snapshot));
    $sheetVerified = hash_equals((string)$review['sheet_fingerprint'], hash('sha256', $sheet));
    $pending = $review['status'] === 'pending';
    $published = $listing['status'] === 'published';
    $blank = trim($sheet) === '';
    $eligibleForFurtherReview = $pending && $published && $websiteUnchanged && $sheetVerified && !$blank;
    bhBaselinePreviewReply(200, [
        'ok'=>true, 'mode'=>'read_only_baseline_candidate',
        'review_id'=>(int)$review['id'], 'activity_id'=>(int)$review['activity_id'],
        'field'=>$field, 'website_current'=>$current, 'website_snapshot'=>$snapshot,
        'sheet_snapshot'=>$sheet, 'listing_status'=>$listing['status'],
        'review_status'=>$review['status'], 'website_unchanged'=>$websiteUnchanged,
        'sheet_fingerprint_valid'=>$sheetVerified, 'sheet_value_blank'=>$blank,
        'eligible_for_further_review'=>$eligibleForFurtherReview,
        'approved'=>false, 'activity_writes_performed'=>0, 'baseline_writes_performed'=>0
    ]);
} catch (Throwable $e) {
    error_log('Baseline preview error: '.$e->getMessage());
    bhBaselinePreviewReply(503, ['ok'=>false,'error'=>'baseline_preview_unavailable']);
}
