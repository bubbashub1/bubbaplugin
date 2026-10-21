<?php
declare(strict_types=1);
/**
 * Validates metadata for a future server-side approved snapshot.
 * Pure function; NEVER creates or approves snapshots.
 */
function bh_sync_validate_snapshot_metadata(array $record): array {
    $required = ['environment', 'activity_id', 'approved_by', 'approved_at', 'version', 'fields'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $record)) return ['ok'=>false,'error'=>'missing_'.$key];
    }
    if ($record['environment'] !== 'staging') return ['ok'=>false,'error'=>'staging_only'];
    if (!is_int($record['activity_id']) || $record['activity_id'] < 1) return ['ok'=>false,'error'=>'invalid_activity_id'];
    if (!is_int($record['approved_by']) || $record['approved_by'] < 1) return ['ok'=>false,'error'=>'invalid_approver'];
    if (!is_int($record['version']) || $record['version'] < 1) return ['ok'=>false,'error'=>'invalid_version'];
    if (!is_string($record['approved_at']) ||
        !preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/D', $record['approved_at']) ||
        strtotime($record['approved_at']) === false) return ['ok'=>false,'error'=>'invalid_approval_time'];
    if (!is_array($record['fields']) || !$record['fields'] || array_is_list($record['fields'])) return ['ok'=>false,'error'=>'invalid_fields'];
    $allowed = ['title'=>500,'description'=>20000,'age_range'=>250,'booking_url'=>2048,'price_from'=>30];
    foreach ($record['fields'] as $field=>$value) {
        if (!isset($allowed[$field]) || !is_string($value) || strlen($value) > $allowed[$field]) {
            return ['ok'=>false,'error'=>'invalid_snapshot_field'];
        }
    }
    return ['ok'=>true,'error'=>null];
}
