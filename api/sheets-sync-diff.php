<?php
declare(strict_types=1);
/**
 * Read-only field comparison for the Sheets preview. No database access.
 * Input records must be fetched by an authenticated caller.
 */
function bh_sync_compare_fields(array $incoming, array $existing, array $allowedFields): array {
    $changes = [];
    foreach ($allowedFields as $field) {
        if (!is_string($field) || !array_key_exists($field, $incoming)) continue;
        $before = $existing[$field] ?? null;
        $after = $incoming[$field];
        if (!is_scalar($after) && $after !== null) continue;
        if (!is_scalar($before) && $before !== null) continue;
        $old = $before === null ? '' : trim((string)$before);
        $new = $after === null ? '' : trim((string)$after);
        if ($old !== $new) {
            $changes[$field] = ['before' => $before, 'after' => $after];
        }
    }
    return $changes;
}
/**
 * Three-way comparison: base is the last confirmed sync snapshot.
 * A conflict means both the website and sheet changed the same field differently.
 */
function bh_sync_three_way(array $sheet, array $website, array $base, array $allowedFields): array {
    $changes = []; $conflicts = [];
    foreach ($allowedFields as $field) {
        if (!is_string($field) || !array_key_exists($field, $sheet)) continue;
        $incoming = $sheet[$field];
        $current = $website[$field] ?? null;
        if ((!is_scalar($incoming) && $incoming !== null) ||
            (!is_scalar($current) && $current !== null)) continue;
        $new = $incoming === null ? '' : trim((string)$incoming);
        $now = $current === null ? '' : trim((string)$current);
        if ($new === $now) continue;
        if (!array_key_exists($field, $base)) {
            $conflicts[$field] = ['reason'=>'missing_baseline']; continue;
        }
        $original = $base[$field];
        if (!is_scalar($original) && $original !== null) {
            $conflicts[$field] = ['reason'=>'invalid_baseline']; continue;
        }
        $old = $original === null ? '' : trim((string)$original);
        if ($now !== $old) {
            $conflicts[$field] = ['reason'=>'website_changed','base'=>$original,'website'=>$current,'sheet'=>$incoming];
        } else {
            $changes[$field] = ['before'=>$current,'after'=>$incoming];
        }
    }
    return ['status'=>count($conflicts)?'conflict':(count($changes)?'changed':'unchanged'),
            'changes'=>$changes,'conflicts'=>$conflicts,'writes_performed'=>0];
}
