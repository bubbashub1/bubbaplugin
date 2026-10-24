<?php
/**
 * Plugin Name: Bubba Hub Sponsor Stripe Readiness
 * Description: Read-only sponsor Stripe reconciliation readiness. Never modifies payments.
 * Version: 1.1.0
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', static function () {
    add_submenu_page('bubba-hub','Sponsor Stripe Readiness','Sponsor Stripe Readiness',
        'manage_options','bh-sponsor-stripe-readiness','bh_sponsor_stripe_readiness_page');
}, 32);
function bh_sponsor_stripe_readiness_page() {
    if (!current_user_can('manage_options')) wp_die('Not authorised');
    global $wpdb;
    $entries = $wpdb->prefix . 'frmt_form_entry';
    $meta = $wpdb->prefix . 'frmt_form_entry_meta';
    $flags = $wpdb->prefix . 'bh_sponsor_audit_flags';
    $receipts = $wpdb->prefix . 'bh_sponsor_receipts';
    $has = static function ($table) use ($wpdb) {
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) === $table;
    };
    echo '<div class="wrap"><h1>Sponsor Stripe Reconciliation — Readiness</h1>';
    echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=bh-sponsor-management')).'">Sponsor Management</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=bh-sponsor-receipts')).'">Sponsor Receipts</a></p>';
    echo '<div class="notice notice-warning inline"><p><strong>Automatic reconciliation is disabled.</strong> This screen does not contact Stripe, create payments, change records or grant Pro access.</p></div>';
    foreach ([$entries,$meta,$flags,$receipts] as $table) {
        if (!$has($table)) {
            echo '<p>Required table unavailable: '.esc_html($table).'. No reconciliation is possible.</p></div>';
            return;
        }
    }
    $counts = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) total, SUM(CASE WHEN COALESCE(f.is_test,0)=1 THEN 1 ELSE 0 END) tests
         FROM {$entries} e LEFT JOIN {$flags} f ON f.entry_id=e.entry_id WHERE e.form_id=%d",88),ARRAY_A);
    $total=(int)($counts['total']??0); $tests=(int)($counts['tests']??0);
    $receipt_count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$receipts}");
    echo '<h2>Current records</h2><table class="widefat striped" style="max-width:700px"><tbody>';
    foreach (['Forminator sponsor applications'=>$total,'Marked test'=>$tests,
        'Non-test applications'=>max(0,$total-$tests),'Existing manual receipt records'=>$receipt_count] as $label=>$count) {
        echo '<tr><th>'.esc_html($label).'</th><td>'.esc_html((string)$count).'</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h2>Forminator payment metadata diagnostics</h2>';
    echo '<p>Read-only: lists saved field identifiers and counts, never transaction values, emails, card details or Stripe secrets. Empty results do not prove Stripe is disabled.</p>';
    $keys = $wpdb->get_results($wpdb->prepare(
        "SELECT m.meta_key, COUNT(DISTINCT m.entry_id) AS entry_count
         FROM {$meta} m INNER JOIN {$entries} e ON e.entry_id=m.entry_id
         WHERE e.form_id=%d GROUP BY m.meta_key ORDER BY m.meta_key",88), ARRAY_A);
    echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th>Saved field identifier</th><th>Entries</th></tr></thead><tbody>';
    $payment_keys = 0;
    foreach ($keys as $key) {
        $name = (string)$key['meta_key'];
        if (!preg_match('/stripe|payment|transaction|charge|intent|checkout|status/i',$name)) continue;
        $payment_keys++;
        echo '<tr><td><code>'.esc_html($name).'</code></td><td>'.esc_html((string)$key['entry_count']).'</td></tr>';
    }
    if (!$payment_keys) echo '<tr><td colspan="2">No payment-related field identifiers found in saved submissions. Form settings and Stripe Dashboard must be checked separately.</td></tr>';
    echo '</tbody></table>';
    echo '<p><strong>Next verification:</strong> inspect the Stripe field in Forminator #88, confirm its live/test setting, and identify the actual Stripe PaymentIntent or Checkout Session reference before any automated matching.</p>';
    echo '<h2>Required before automatic matching</h2><ol>';
    echo '<li>Confirm Forminator #88 Stripe field and actual payment workflow (one-time, invoice, cash).</li>';
    echo '<li>Capture a trusted sponsor-specific checkout/session or PaymentIntent identifier, not just email or amount.</li>';
    echo '<li>Verify Stripe webhook signatures and paid status, and store Stripe event IDs for replay protection.</li>';
    echo '<li>Reconcile refunds and disputes without double-counting manual receipts.</li>';
    echo '<li>Run end-to-end tests in Stripe test mode before enabling any live write.</li></ol>';
    echo '<p><strong>Status:</strong> Awaiting verified Stripe-to-Forminator identifier mapping. No automatic income total is shown.</p></div>';
}
