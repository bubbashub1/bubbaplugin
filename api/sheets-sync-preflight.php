<?php
declare(strict_types=1);
/**
 * Pure read-only preflight for a Google Sheets batch.
 * Existing DB records must be fetched by a trusted server-side adapter later.
 */
require_once __DIR__ . '/sheets-sync-matching.php';
require_once __DIR__ . '/sheets-sync-diff.php';

function bh_sync_preflight(array $rows): array {
    $seenIds = []; $seenUrls = []; $results = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row) || array_is_list($row)) {
            $results[] = ['row' => $index + 1, 'status' => 'invalid_row'];
            continue;
        }
        $rawId = $row['activity_id'] ?? $row['id'] ?? null;
        $rawUrl = $row['booking_url'] ?? null;
        $id = bh_sync_normalize_id($rawId);
        $url = bh_sync_normalize_url($rawUrl);
        if (($rawId !== null && $rawId !== '' && $id === null) ||
            ($rawUrl !== null && $rawUrl !== '' && $url === null)) {
            $results[] = ['row' => $index + 1, 'status' => 'invalid_identifier'];
            continue;
        }
        if ($id === null && $url === null) {
            $results[] = ['row' => $index + 1, 'status' => 'missing_identifier'];
            continue;
        }
        if (($id !== null && isset($seenIds[$id])) ||
            ($url !== null && isset($seenUrls[$url]))) {
            $results[] = ['row' => $index + 1, 'status' => 'duplicate_input'];
            continue;
        }
        if ($id !== null) $seenIds[$id] = true;
        if ($url !== null) $seenUrls[$url] = true;
        $results[] = ['row' => $index + 1, 'status' => 'pending_database_match'];
    }
    $counts = [];
    foreach ($results as $result) {
        $status = $result['status'];
        $counts[$status] = ($counts[$status] ?? 0) + 1;
    }
    return ['rows' => $results, 'counts' => $counts, 'writes_performed' => 0,
            'database_matching_enabled' => false];
}
