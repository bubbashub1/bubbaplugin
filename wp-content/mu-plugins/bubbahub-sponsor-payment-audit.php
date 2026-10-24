<?php
/**
 * Plugin Name: Bubba Hub Sponsor Payment Audit
 * Description: Read-only Forminator sponsorship form submission and payment-field audit.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'bubba-hub',
        'Sponsor Payment Audit',
        'Sponsor Payment Audit',
        'manage_options',
        'bh-sponsor-payment-audit',
        'bh_sponsor_payment_audit_page'
    );
}, 30);

function bh_sponsor_payment_audit_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Not authorised');
    }

    global $wpdb;
    $entries = $wpdb->prefix . 'frmt_form_entry';
    $meta = $wpdb->prefix . 'frmt_form_entry_meta';
    $form_id = 88;

    echo '<div class="wrap">';
    echo '<h1>Bubba &amp; Me — Sponsor Payment Audit</h1>';
    echo '<p>Read-only diagnostics for Forminator sponsor form #88. Submission counts are not confirmed payments. No records are modified and no donor details, field values, or transaction references are displayed.</p>';

    foreach ([$entries, $meta] as $table) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            echo '<div class="notice notice-error"><p>Forminator table unavailable: ' . esc_html($table) . '</p></div></div>';
            return;
        }
    }

    $entry_columns = $wpdb->get_col('SHOW COLUMNS FROM ' . esc_sql($entries), 0);
    $meta_columns = $wpdb->get_col('SHOW COLUMNS FROM ' . esc_sql($meta), 0);
    if (count(array_intersect(['form_id', 'entry_id'], $entry_columns)) !== 2
        || count(array_intersect(['entry_id', 'meta_key'], $meta_columns)) !== 2) {
        echo '<div class="notice notice-error"><p>Unexpected Forminator schema. No audit data was read.</p></div></div>';
        return;
    }

    // Test flags are stored separately, never alter Forminator submissions.
    $flags_table = $wpdb->prefix . 'bh_sponsor_audit_flags';
    $flags_ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $flags_table)) === $flags_table;

    if (isset($_POST['bh_sponsor_flag_entry']) && check_admin_referer('bh_sponsor_flag_action')) {
        $entry_id = absint(wp_unslash($_POST['bh_sponsor_flag_entry']));
        $is_test = !empty($_POST['bh_sponsor_is_test']) ? 1 : 0;
        $belongs = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$entries} WHERE entry_id = %d AND form_id = %d", $entry_id, $form_id
        ));
        if ($flags_ready && $belongs) {
            $wpdb->replace($flags_table, ['entry_id' => $entry_id, 'is_test' => $is_test], ['%d','%d']);
            echo '<div class="notice notice-success"><p>Submission classification saved.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Could not save classification. Verify the entry and audit table.</p></div>';
        }
    }

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$entries} WHERE form_id = %d",
        $form_id
    ));

    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT entry_id FROM {$entries} WHERE form_id = %d ORDER BY entry_id DESC LIMIT 100", $form_id
    ), ARRAY_A);
    $test_count = $flags_ready ? (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$flags_table} f INNER JOIN {$entries} e ON e.entry_id = f.entry_id WHERE e.form_id = %d AND f.is_test = 1", $form_id
    )) : 0;

    $keys = $wpdb->get_results($wpdb->prepare(
        "SELECT m.meta_key, COUNT(DISTINCT m.entry_id) AS entry_count
         FROM {$meta} m
         INNER JOIN {$entries} e ON e.entry_id = m.entry_id
         WHERE e.form_id = %d
         GROUP BY m.meta_key
         ORDER BY m.meta_key",
        $form_id
    ), ARRAY_A);

    echo '<div style="margin:18px 0;padding:18px 22px;background:#e8f5ec;border-left:4px solid #238d69;border-radius:8px;max-width:420px">';
    echo '<span style="display:block;color:#456b58">Sponsor form submissions</span>';
    echo '<strong style="font-size:30px;color:#17513c">' . esc_html((string) $total) . '</strong>';
    echo '<p style="margin-bottom:0">This is a submission count only, not a paid-sponsorship total.</p></div>';

    echo '<h2>Submission classification</h2>';
    echo '<p><strong>Test submissions:</strong> ' . esc_html((string)$test_count) .
         ' &nbsp; <strong>Non-test submissions:</strong> ' . esc_html((string)max(0,$total-$test_count)) . '</p>';
    echo '<p>Classify the known test submission here. This does not delete Forminator data or verify payment.</p>';
    if (!$flags_ready) {
        echo '<div class="notice notice-warning inline"><p>Test classification table is not installed. Run the SQL migration in the PR before using these controls.</p></div>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Forminator entry ID</th><th>Classification</th><th>Action</th></tr></thead><tbody>';
        foreach ($submissions as $submission) {
            $id = (int)$submission['entry_id'];
            $flag = (int)$wpdb->get_var($wpdb->prepare("SELECT is_test FROM {$flags_table} WHERE entry_id = %d", $id));
            echo '<tr><td>' . esc_html((string)$id) . '</td><td>' . ($flag ? 'Test' : 'Unclassified / real (unverified)') . '</td><td>';
            echo '<form method="post">';
            wp_nonce_field('bh_sponsor_flag_action');
            echo '<input type="hidden" name="bh_sponsor_flag_entry" value="' . esc_attr((string)$id) . '">';
            echo '<input type="hidden" name="bh_sponsor_is_test" value="' . ($flag ? '0' : '1') . '">';
            echo '<button type="submit" class="button">' . ($flag ? 'Remove test flag' : 'Mark as test') . '</button></form></td></tr>';
        }
        if (!$submissions) echo '<tr><td colspan="3">No submissions yet.</td></tr>';
        echo '</tbody></table>';
    }
    echo '<h2>Saved field identifiers</h2>';
    echo '<p>Use these field names to confirm the sponsor form’s amount, Stripe payment status, and transaction-reference fields before building reconciliation. Only field identifiers and occurrence counts are shown.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Field identifier</th><th>Entries containing field</th></tr></thead><tbody>';

    if (!$keys) {
        echo '<tr><td colspan="2">No field metadata was found for sponsor form #88.</td></tr>';
    } else {
        foreach ($keys as $row) {
            echo '<tr><td><code>' . esc_html((string) $row['meta_key']) . '</code></td><td>' . esc_html((string) $row['entry_count']) . '</td></tr>';
        }
    }

    echo '</tbody></table>';
    echo '<div class="notice notice-info inline"><p><strong>Next step:</strong> identify the actual saved payment-status and Stripe transaction fields, then reconcile them against Stripe. A submitted form or selected donation amount must never be treated as proof of payment.</p></div>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=bubba-hub-payments')) . '">Back to Payments</a></p>';
    echo '</div>';
}
