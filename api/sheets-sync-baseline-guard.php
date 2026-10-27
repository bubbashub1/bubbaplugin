<?php
declare(strict_types=1);
/**
 * Stage 5 baseline guard. Pure functions only.
 * Baselines must be loaded from server-controlled, previously approved snapshots.
 * Never treat an incoming spreadsheet baseline as trusted.
 */
/** Compare plain numeric prices to pennies; never coerce blank or invalid prices to zero. */
function bh_sync_field_equal(string $field, string $left, string $right): bool {
    $left = trim($left);
    $right = trim($right);
    if ($field !== 'price_from') return $left === $right;
    if (!preg_match('/^\\d+(?:\\.\\d{1,2})?$/D', $left) ||
        !preg_match('/^\\d+(?:\\.\\d{1,2})?$/D', $right)) return $left === $right;
    $toPence = static function (string $value): string {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, 2, '0');
    };
    return $toPence($left) === $toPence($right);
}

function bh_sync_baseline_field_status(array $sheet, array $website, ?array $trustedBaseline, array $fields): array {
    $out = [];
    foreach ($fields as $field) {
        if (!is_string($field) || !array_key_exists($field, $sheet)) continue;
        $incoming = $sheet[$field];
        $current = $website[$field] ?? null;
        if (!is_scalar($incoming) && $incoming !== null) {
            $out[$field] = ['status'=>'invalid_sheet_value', 'requires_review'=>true];
            continue;
        }
        if (!is_scalar($current) && $current !== null) {
            $out[$field] = ['status'=>'invalid_website_value', 'requires_review'=>true];
            continue;
        }
        $sheetValue = trim((string)($incoming ?? ''));
        $websiteValue = trim((string)($current ?? ''));
        // A blank spreadsheet value is never an instruction to erase website data,
        // even when the website value is also blank or a trusted baseline exists.
        if ($sheetValue === '') {
            $out[$field] = ['status'=>'blank_sheet_ignored', 'requires_review'=>false, 'eligible_for_write'=>false];
            continue;
        }
        if (bh_sync_field_equal($field, $sheetValue, $websiteValue)) {
            $out[$field] = ['status'=>'same', 'requires_review'=>false];
            continue;
        }
        if ($trustedBaseline === null || !array_key_exists($field, $trustedBaseline)) {
            $out[$field] = ['status'=>'baseline_required', 'requires_review'=>true];
            continue;
        }
        $base = $trustedBaseline[$field];
        if (!is_scalar($base) && $base !== null) {
            $out[$field] = ['status'=>'invalid_baseline', 'requires_review'=>true];
            continue;
        }
        $original = trim((string)($base ?? ''));
        $websiteChanged = !bh_sync_field_equal($field, $websiteValue, $original);
        $sheetChanged = !bh_sync_field_equal($field, $sheetValue, $original);
        $status = $websiteChanged && $sheetChanged ? 'conflict_review'
            : ($websiteChanged ? 'website_changed_review' : 'sheet_changed_review');
        $out[$field] = [
            'status'=>$status,
            'requires_review'=>true,
            'website_changed'=>$websiteChanged,
            'sheet_changed'=>$sheetChanged
        ];
    }
    return $out;
}
