<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/sheets-sync-matching.php';
require_once __DIR__ . '/../api/sheets-sync-field-preview.php';
$r = bh_sync_field_preview(
    ['title'=>'Changed', 'description'=>'Same', 'price_from'=>'2.50',
     'age_range'=>'0-12 months', 'booking_url'=>'https://example.org/book',
     'unknown_column'=>'ignored'],
    ['title'=>'Original', 'description'=>'Same', 'price_from'=>'2.5',
     'age_range'=>'0-6 months', 'booking_url'=>'https://example.org/book']
);
if ($r['title']['status'] !== 'different_review' ||
    $r['description']['status'] !== 'same' ||
    $r['price_from']['status'] !== 'same' ||
    $r['age_range']['status'] !== 'different_review' ||
    isset($r['unknown_column']) ||
    $r['title']['conflict_confirmed'] !== false) exit(1);
$bad = bh_sync_field_preview(['booking_url'=>'javascript:alert(1)','price_from'=>'-10'], []);
if ($bad['booking_url']['status'] !== 'invalid_sheet_value' ||
    $bad['price_from']['status'] !== 'invalid_sheet_value') exit(1);
echo "PASS: read-only field preview validation and comparisons\n";
