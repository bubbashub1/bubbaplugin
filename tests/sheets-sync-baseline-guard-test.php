<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/sheets-sync-baseline-guard.php';
function check_status(array $sheet, array $website, ?array $base, string $expected): void {
    $result = bh_sync_baseline_field_status($sheet, $website, $base, ['title']);
    if (($result['title']['status'] ?? '') !== $expected) {
        fwrite(STDERR, "Expected $expected, got ".json_encode($result)."\n");
        exit(1);
    }
}
check_status(['title'=>'Same'], ['title'=>'Same'], null, 'same');
check_status(['title'=>'New'], ['title'=>'Old'], null, 'baseline_required');
check_status(['title'=>'New'], ['title'=>'Old'], ['title'=>'Old'], 'sheet_changed_review');
check_status(['title'=>'Old'], ['title'=>'New'], ['title'=>'Old'], 'website_changed_review');
check_status(['title'=>'Sheet'], ['title'=>'Website'], ['title'=>'Old'], 'conflict_review');
check_status(['title'=>'New'], ['title'=>'Old'], ['title'=>[]], 'invalid_baseline');
check_status(['title'=>[]], ['title'=>'Old'], null, 'invalid_sheet_value');
echo "PASS: missing, same, sheet-only, website-only and dual-change guards\n";
