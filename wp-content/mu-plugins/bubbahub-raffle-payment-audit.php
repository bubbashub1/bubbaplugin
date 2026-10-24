<?php
/**
 * Plugin Name: Bubba Hub Raffle Payment Audit
 * Description: Read-only Forminator raffle submission metadata audit.
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function () {
 add_submenu_page('forminator', 'Raffle Payment Audit', 'Raffle Payment Audit', 'manage_options', 'bh-raffle-payment-audit', 'bh_raffle_payment_audit_page');
});
function bh_raffle_payment_audit_page() {
 if (!current_user_can('manage_options')) wp_die('Not authorised');
 global $wpdb;
 $entries=$wpdb->prefix.'frmt_form_entry';
 $meta=$wpdb->prefix.'frmt_form_entry_meta';
 echo '<div class="wrap"><h1>Bubba &amp; Me — Forminator #76 audit</h1><p>Read-only diagnostics. Submission counts are not paid ticket totals. No records are modified.</p>';
 foreach ([$entries,$meta] as $table) {
  if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) !== $table) {
   echo '<div class="notice notice-error"><p>Forminator table unavailable: '.esc_html($table).'</p></div></div>';return;
  }
 }
 $entry_columns=$wpdb->get_col('SHOW COLUMNS FROM '.$entries,0);
 $meta_columns=$wpdb->get_col('SHOW COLUMNS FROM '.$meta,0);
 if (count(array_intersect(['form_id','entry_id'],$entry_columns))!==2 || count(array_intersect(['entry_id','meta_key'],$meta_columns))!==2) {
  echo '<p>Unexpected Forminator schema. No data read.</p></div>';return;
 }
 $total=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$entries} WHERE form_id=%d",76));
 $keys=$wpdb->get_results($wpdb->prepare("SELECT m.meta_key,COUNT(DISTINCT m.entry_id) AS entry_count FROM {$meta} m INNER JOIN {$entries} e ON e.entry_id=m.entry_id WHERE e.form_id=%d GROUP BY m.meta_key ORDER BY m.meta_key",76),ARRAY_A);
 echo '<p><strong>Form submissions:</strong> '.esc_html((string)$total).' (not verified payments)</p>';
 echo '<h2>Saved field identifiers</h2><p>Only field names and occurrence counts are shown; no customer information or transaction values.</p>';
 echo '<table class="widefat striped"><thead><tr><th>Field identifier</th><th>Entries containing field</th></tr></thead><tbody>';
 foreach($keys as $row)echo '<tr><td><code>'.esc_html($row['meta_key']).'</code></td><td>'.esc_html((string)$row['entry_count']).'</td></tr>';
 echo '</tbody></table><p>Next: identify transaction and payment status fields, cross-check with Stripe, then implement paid-entry reconciliation. Do not infer payment success from submission alone.</p></div>';
}
