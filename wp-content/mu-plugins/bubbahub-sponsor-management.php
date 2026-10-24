<?php
/**
 * Plugin Name: Bubba Hub Sponsor Management
 * Description: Administrator-only sponsor application review for Forminator form 88.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', static function () {
    add_submenu_page('bubba-hub', 'Sponsor Management', 'Sponsor Management',
        'manage_options', 'bh-sponsor-management', 'bh_sponsor_management_page');
}, 29);

function bh_sponsor_management_page() {
    if (!current_user_can('manage_options')) wp_die('Not authorised');
    global $wpdb;
    $entries = $wpdb->prefix . 'frmt_form_entry';
    $meta = $wpdb->prefix . 'frmt_form_entry_meta';
    $flags = $wpdb->prefix . 'bh_sponsor_audit_flags';
    $has_table = static function ($table) use ($wpdb) {
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    };
    echo '<div class="wrap"><h1>Bubba &amp; Me — Sponsor Management</h1>';
    echo '<p>Applications from Forminator #88. Submission data is not proof of payment. Stripe reconciliation and confirmed income reporting are not enabled.</p>';
    if (!$has_table($entries) || !$has_table($meta) || !$has_table($flags)) {
        echo '<div class="notice notice-error"><p>Required Forminator or sponsor test-flag table missing. No data loaded.</p></div></div>';
        return;
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT e.entry_id, e.date_created, COALESCE(f.is_test,0) AS is_test
         FROM {$entries} e LEFT JOIN {$flags} f ON f.entry_id = e.entry_id
         WHERE e.form_id = %d ORDER BY e.entry_id DESC LIMIT 100", 88
    ), ARRAY_A);
    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$entries} WHERE form_id=%d",88));
    $test = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$entries} e JOIN {$flags} f ON f.entry_id=e.entry_id
         WHERE e.form_id=%d AND f.is_test=1",88
    ));
    echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0">';
    foreach (['Applications'=>$total,'Marked test'=>$test,'Non-test (unverified)'=>max(0,$total-$test)] as $label=>$count) {
        echo '<div style="background:#eaf5ee;border-left:4px solid #21825c;padding:16px 24px;min-width:170px;border-radius:8px"><span>' .
            esc_html($label) . '</span><div style="font-size:28px;font-weight:700">' . esc_html((string)$count) . '</div></div>';
    }
    echo '</div><h2>Recent applications</h2>';
    echo '<p>Contact information is visible only to WordPress administrators. Amount and payment method are shown only when their saved field values can be interpreted reliably; otherwise review the original Forminator entry.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Entry</th><th>Submitted</th><th>Contact</th><th>Email</th><th>Classification</th><th>Payment</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="6">No sponsor applications yet.</td></tr>';
    foreach ($rows as $row) {
        $id = (int)$row['entry_id'];
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$meta} WHERE entry_id=%d AND meta_key IN ('name-1','email-1')", $id
        ), ARRAY_A);
        $values = [];
        foreach ($fields as $field) {
            $value = maybe_unserialize($field['meta_value']);
            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(static function($v) {
                    return is_scalar($v) ? (string)$v : '';
                }, $value)));
            }
            $values[$field['meta_key']] = is_scalar($value) ? (string)$value : '';
        }
        echo '<tr><td>#' . esc_html((string)$id) . '</td><td>' . esc_html((string)$row['date_created']) .
            '</td><td>' . esc_html($values['name-1'] ?? 'Not supplied') .
            '</td><td>' . esc_html($values['email-1'] ?? 'Not supplied') .
            '</td><td>' . ($row['is_test'] ? 'Test' : 'Unverified application') .
            '</td><td>Not verified</td></tr>';
    }
    echo '</tbody></table>';
    if ($total > count($rows)) echo '<p>Showing the most recent 100 entries.</p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=bh-sponsor-payment-audit')) . '">Review test flags and field diagnostics</a></p>';
    echo '<div class="notice notice-info inline"><p>Next: map sponsor amount and payment method fields from Forminator, then verify paid status against Stripe or recorded cash/invoice receipts. No paid total is calculated until verification is available.</p></div></div>';
}
