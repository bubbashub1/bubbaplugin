<?php
declare(strict_types=1);
/** Pure check: a future approval must match the exact reviewed pilot change. No I/O. */
function bh_pilot_approval_binding(array $approved, array $current): bool {
  $required = ['activity_id','field','baseline_version','website_value','sheet_value','snapshot_run_id','snapshot_received_utc'];
  foreach ($required as $key) {
    if (!array_key_exists($key,$approved) || !array_key_exists($key,$current)) return false;
  }
  if ($approved['activity_id'] !== 955 || $current['activity_id'] !== 955) return false;
  if ($approved['field'] !== 'price_from' || $current['field'] !== 'price_from') return false;
  if (!is_int($approved['baseline_version']) || $approved['baseline_version'] < 1) return false;
  if ($approved['baseline_version'] !== $current['baseline_version']) return false;
  foreach (['website_value','sheet_value'] as $key) {
    if (!is_string($approved[$key]) || !is_string($current[$key])) return false;
    if (!preg_match('/^[0-9]{1,8}([.][0-9]{1,2})?$/D',$approved[$key])) return false;
    if (!preg_match('/^[0-9]{1,8}([.][0-9]{1,2})?$/D',$current[$key])) return false;
    if (number_format((float)$approved[$key],2,'.','') !== number_format((float)$current[$key],2,'.','')) return false;
  }
  foreach (['snapshot_run_id','snapshot_received_utc'] as $key) {
    if (!is_string($approved[$key]) || $approved[$key] === '') return false;
    if (!is_string($current[$key]) || $approved[$key] !== $current[$key]) return false;
  }
  return true;
}
