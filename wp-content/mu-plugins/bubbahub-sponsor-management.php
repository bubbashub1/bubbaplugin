<?php
/**
 * Plugin Name: Bubba Hub Sponsor Management
 * Description: Administrator-only sponsor application review for Forminator form 88.
 * Version: 1.2.0
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
    // Manual workflow statuses are administrative notes, not payment confirmations.
    if (isset($_POST['bh_sponsor_status_entry'])) {
        check_admin_referer('bh_sponsor_status');
        $entry_id = absint(wp_unslash($_POST['bh_sponsor_status_entry']));
        $status = sanitize_key(wp_unslash($_POST['bh_sponsor_status'] ?? 'pending'));
        $allowed = ['pending', 'invoice_requested', 'invoice_sent', 'cash_expected', 'needs_review'];
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$entries} WHERE entry_id=%d AND form_id=%d", $entry_id, 88
        ));
        $is_test = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT is_test FROM {$flags} WHERE entry_id=%d", $entry_id
        ));
        if ($exists && !$is_test && in_array($status, $allowed, true)) {
            update_option('bh_sponsor_workflow_' . $entry_id, $status, false);
            echo '<div class="notice notice-success"><p>Workflow status saved. No payment has been confirmed.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Could not update status for this entry.</p></div>';
        }
    }
    echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0">';
    foreach (['Applications'=>$total,'Marked test'=>$test,'Non-test (unverified)'=>max(0,$total-$test)] as $label=>$count) {
        echo '<div style="background:#eaf5ee;border-left:4px solid #21825c;padding:16px 24px;min-width:170px;border-radius:8px"><span>' .
            esc_html($label) . '</span><div style="font-size:28px;font-weight:700">' . esc_html((string)$count) . '</div></div>';
    }
    echo '</div><h2>Recent applications</h2>';
    echo '<p><strong>Form mapping preview:</strong> The values in select-1 is the support type and select-2 is the selected donation amount, based on the verified test entry. Check them against Forminator form #88 before enabling income calculations.</p>';
    echo '<p>Contact information is visible only to WordPress administrators. Amount and payment method are shown only when their saved field values can be interpreted reliably; otherwise review the original Forminator entry.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Entry</th><th>Submitted</th><th>Contact</th><th>Email</th><th>Support type</th><th>Selected amount</th><th>Classification</th><th>Workflow status</th><th>Payment</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="9">No sponsor applications yet.</td></tr>';
    foreach ($rows as $row) {
        $id = (int)$row['entry_id'];
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$meta} WHERE entry_id=%d AND meta_key IN ('name-1','email-1','select-1','select-2')", $id
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
            '</td><td>' . esc_html($values['select-1'] ?? 'Not saved') .
            '</td><td>' . esc_html($values['select-2'] ?? 'Not saved') .
            '</td><td>' . ($row['is_test'] ? 'Test' : 'Unverified application') .
            '</td><td>';
        if ($row['is_test']) {
            echo 'Test (excluded)';
        } else {
            $current = get_option('bh_sponsor_workflow_' . $id, 'pending');
            $statuses = ['pending'=>'Pending','invoice_requested'=>'Invoice requested',
                'invoice_sent'=>'Invoice sent','cash_expected'=>'Cash expected','needs_review'=>'Needs review'];
            echo '<form method="post">';
            wp_nonce_field('bh_sponsor_status');
            echo '<input type="hidden" name="bh_sponsor_status_entry" value="' . esc_attr((string)$id) . '">';
            echo '<select name="bh_sponsor_status" aria-label="Workflow status for entry ' . esc_attr((string)$id) . '">';
            foreach ($statuses as $key=>$label) {
                echo '<option value="' . esc_attr($key) . '"' . selected($current,$key,false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select> <button class="button" type="submit">Save</button></form>';
        }
        echo '</td><td>Not verified</td></tr>';
    }
    echo '</tbody></table>';
    if ($total > count($rows)) echo '<p>Showing the most recent 100 entries.</p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=bh-sponsor-payment-audit')) . '">Review test flags and field diagnostics</a></p>';
    echo '<div class="notice notice-info inline"><p><strong>Payment safety:</strong> Neither dropdown values nor a submitted form establish payment. Cash, invoices and Stripe must be reconciled separately. Test submissions stay excluded from non-test counts.</p></div>';
    echo '<div class="notice notice-info inline"><p>Next: independently reconcile Stripe and cash/invoice receipts before adding a paid status or confirmed income totals. No paid total is calculated until verification is available.</p></div></div>';
}
