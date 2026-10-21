<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/sheets-sync-preflight.php';

function check(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
$rows = [
    ['activity_id' => 955, 'booking_url' => 'https://example.org/book/'],
    ['activity_id' => '955', 'booking_url' => 'https://example.org/other'],
    ['booking_url' => 'https://example.org/book'],
    ['activity_id' => 'bad'],
    ['name' => 'No identifier'],
    ['activity_id' => 956, 'booking_url' => 'https://example.org/new'],
];
$r = bh_sync_preflight($rows);
$statuses = array_column($r['rows'], 'status');
check($statuses === [
    'pending_database_match', 'duplicate_input', 'duplicate_input',
    'invalid_identifier', 'missing_identifier', 'pending_database_match'
], 'preflight handles duplicates, invalid identifiers and valid rows');
check($r['writes_performed'] === 0 && $r['database_matching_enabled'] === false,
    'preflight never claims database matching or writes');
$match = bh_sync_preview_matches(
    [['activity_id' => 10, 'booking_url' => 'https://example.org/a']],
    [['id' => 10, 'booking_url' => 'https://example.org/a']]
);
check($match[0]['status'] === 'matched', 'pure matching helper resolves known ID');
$diff = bh_sync_three_way(
    ['title' => 'Sheet'], ['title' => 'Website'], ['title' => 'Original'], ['title']
);
check($diff['status'] === 'conflict' && $diff['writes_performed'] === 0,
    'three-way conflict detection fails closed');
