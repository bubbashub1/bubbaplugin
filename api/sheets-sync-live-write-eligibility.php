<?php
declare(strict_types=1);
/**
 * Pure eligibility gate for a future, separately approved live writer.
 * No I/O and no SQL. Review statuses NEVER authorise a write.
 */
function bh_sync_live_write_eligibility(array $context):array {
 $required=['activity_id','field','live_enabled','admin_authorized','csrf_valid','protected','baseline_version','snapshot_received_utc','sheet_value','website_value','baseline_value','review_approved'];
 foreach($required as $key) if(!array_key_exists($key,$context)) return ['allowed'=>false,'reason'=>'missing_context'];
 if($context['activity_id']!==955||$context['field']!=='price_from') return ['allowed'=>false,'reason'=>'outside_pilot_scope'];
 if($context['live_enabled']!==true) return ['allowed'=>false,'reason'=>'live_disabled'];
 if($context['admin_authorized']!==true||$context['csrf_valid']!==true) return ['allowed'=>false,'reason'=>'authorization_required'];
 if($context['protected']!==false) return ['allowed'=>false,'reason'=>'protected_listing'];
 if(!is_int($context['baseline_version'])||$context['baseline_version']<1) return ['allowed'=>false,'reason'=>'baseline_required'];
 if(!is_string($context['snapshot_received_utc'])||!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D',$context['snapshot_received_utc'])) return ['allowed'=>false,'reason'=>'invalid_snapshot_time'];
 $timestamp=strtotime($context['snapshot_received_utc'].' UTC');
 if($timestamp===false||$timestamp>time()+300||$timestamp<time()-86400) return ['allowed'=>false,'reason'=>'snapshot_expired'];
 foreach(['sheet_value','website_value','baseline_value'] as $key) {
   if(!is_string($context[$key])||!preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/D',trim($context[$key]))) return ['allowed'=>false,'reason'=>'invalid_price'];
 }
 require_once __DIR__.'/sheets-sync-baseline-guard.php';
 $comparison=bh_sync_baseline_field_status(['price_from'=>$context['sheet_value']],['price_from'=>$context['website_value']],['price_from'=>$context['baseline_value']],['price_from']);
 $status=$comparison['price_from']['status']??'unknown';
 if($status!=='sheet_changed_review') return ['allowed'=>false,'reason'=>$status];
 if($context['review_approved']!==true) return ['allowed'=>false,'reason'=>'explicit_review_required'];
 return ['allowed'=>true,'reason'=>'eligible_for_separate_transactional_recheck'];
}
