<?php
/**
 * Plugin Name: Bubba Hub Sponsor Payment Reconciliation
 * Description: Manually verified sponsor receipts with an append-only audit trail. Does not connect to Stripe.
 * Version: 1.1.0
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', static function () {
    add_submenu_page('bubba-hub', 'Sponsor Receipts', 'Sponsor Receipts', 'manage_options',
        'bh-sponsor-receipts', 'bh_sponsor_receipts_page');
}, 31);

function bh_sponsor_receipts_page() {
    if (!current_user_can('manage_options')) wp_die('Not authorised');
    global $wpdb;
    $ledger = $wpdb->prefix . 'bh_sponsor_receipts';
    $entries = $wpdb->prefix . 'frmt_form_entry';
    $flags = $wpdb->prefix . 'bh_sponsor_audit_flags';
    $adjustments = $wpdb->prefix . 'bh_sponsor_receipt_adjustments';
    echo '<div class="wrap"><h1>Sponsor Receipts &amp; Reconciliation</h1>';
    echo '<style>.bh-sponsor-receipts-form{max-width:680px;background:#f7fcf9;border:1px solid #d7e9df;border-radius:14px;padding:20px 24px;margin:16px 0 24px}.bh-sponsor-receipts-form label{display:block;font-weight:600;color:#214d3a}.bh-sponsor-receipts-form input:not([type=checkbox]),.bh-sponsor-receipts-form select,.bh-sponsor-receipts-form textarea{display:block;width:100%;max-width:100%;margin-top:7px;border:1px solid #b9d5c6;border-radius:8px;padding:8px}.bh-sponsor-receipts-form label:has(input[type=checkbox]){font-weight:400}.bh-sponsor-receipts-form .button-primary{background:#176c4c;border-color:#176c4c;border-radius:8px}</style>';
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=bh-sponsor-management')) . '">Back to Sponsor Management</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=bh-sponsor-payment-audit')) . '">Payment Audit</a></p>';
    echo '<p>Manual verification only. A Forminator submission, amount selection or invoice is not proof of payment. Stripe is NOT connected to this screen.</p>';
    foreach ([$ledger,$entries,$flags] as $table) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table)) !== $table) {
            echo '<div class="notice notice-warning"><p>Required table missing. Apply the reviewed migration before using this screen.</p></div></div>';
            return;
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bh_receipt_entry'])) {
        check_admin_referer('bh_record_sponsor_receipt');
        $id = absint(wp_unslash($_POST['bh_receipt_entry']));
        $amount_raw = trim((string)wp_unslash($_POST['bh_receipt_amount'] ?? ''));
        $method = sanitize_key(wp_unslash($_POST['bh_receipt_method'] ?? ''));
        $reference = sanitize_text_field(wp_unslash($_POST['bh_receipt_reference'] ?? ''));
        $note = sanitize_textarea_field(wp_unslash($_POST['bh_receipt_note'] ?? ''));
        $verified = !empty($_POST['bh_receipt_verified']);
        $valid_entry = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$entries} e LEFT JOIN {$flags} f ON f.entry_id=e.entry_id
             WHERE e.entry_id=%d AND e.form_id=88 AND COALESCE(f.is_test,0)=0", $id));
        $valid_amount = preg_match('/^\\d{1,7}(?:\\.\\d{1,2})?$/', $amount_raw);
        $pence = $valid_amount ? (int)round(((float)$amount_raw)*100) : 0;
        $allowed = ['stripe','bank_transfer','cash','other'];
        if (!$valid_entry || !$valid_amount || $pence <= 0 || !in_array($method,$allowed,true)
            || !$verified || strlen($reference) < 4 || strlen($reference) > 190) {
            echo '<div class="notice notice-error"><p>Receipt not saved. Choose a genuine application, enter a positive amount, payment method, evidence reference and confirm independent verification.</p></div>';
        } else {
            $saved = $wpdb->insert($ledger, [
                'entry_id'=>$id,'amount_pence'=>$pence,'method'=>$method,
                'evidence_reference'=>$reference,'verification_note'=>$note,
                'verified_by'=>get_current_user_id(),'verified_at'=>current_time('mysql',true)
            ], ['%d','%d','%s','%s','%s','%d','%s']);
            echo $saved
                ? '<div class="notice notice-success"><p>Verified receipt recorded with administrator audit details.</p></div>'
                : '<div class="notice notice-error"><p>Could not record receipt. Check for a duplicate evidence reference or database error.</p></div>';
        }
    }
    $adjustments_ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$adjustments)) === $adjustments;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bh_refund_receipt'])) {
        check_admin_referer('bh_sponsor_refund');
        $receipt_id = absint(wp_unslash($_POST['bh_refund_receipt']));
        $amount_raw = trim((string)wp_unslash($_POST['bh_refund_amount'] ?? ''));
        $reference = sanitize_text_field(wp_unslash($_POST['bh_refund_reference'] ?? ''));
        $reason = sanitize_textarea_field(wp_unslash($_POST['bh_refund_reason'] ?? ''));
        $valid_amount = preg_match('/^\\d{1,7}(?:\\.\\d{1,2})?$/', $amount_raw);
        $pence = $valid_amount ? (int)round(((float)$amount_raw)*100) : 0;
        // Serialize adjustments for a receipt by locking its InnoDB parent row.
        $valid_input = $adjustments_ready && $valid_amount && $pence > 0
            && strlen($reference) >= 4 && strlen($reference) <= 190
            && strlen($reason) >= 5 && !empty($_POST['bh_refund_confirmed']);
        $saved = false;
        if ($valid_input && $wpdb->query('START TRANSACTION') !== false) {
            $receipt = $wpdb->get_row($wpdb->prepare(
                "SELECT r.id,r.amount_pence FROM {$ledger} r
                 JOIN {$entries} e ON e.entry_id=r.entry_id AND e.form_id=88
                 LEFT JOIN {$flags} f ON f.entry_id=e.entry_id
                 WHERE r.id=%d AND COALESCE(f.is_test,0)=0 FOR UPDATE", $receipt_id), ARRAY_A);
            $previous = $receipt ? (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount_pence),0) FROM {$adjustments} WHERE receipt_id=%d", $receipt_id)) : 0;
            if ($receipt && $pence <= (int)$receipt['amount_pence'] - $previous) {
                $saved = (bool)$wpdb->insert($adjustments, [
                    'receipt_id'=>$receipt_id,'amount_pence'=>$pence,
                    'evidence_reference'=>$reference,'reason'=>$reason,
                    'recorded_by'=>get_current_user_id(),'recorded_at'=>current_time('mysql',true)
                ], ['%d','%d','%s','%s','%d','%s']);
            }
            $wpdb->query($saved ? 'COMMIT' : 'ROLLBACK');
        }
        echo $saved
            ? '<div class="notice notice-success"><p>Adjustment recorded. Original receipt retained.</p></div>'
            : '<div class="notice notice-error"><p>Adjustment not saved. Check evidence, duplicate reference and remaining receipt balance.</p></div>';
    }
    $sum = (int)$wpdb->get_var(
        "SELECT COALESCE(SUM(r.amount_pence),0) FROM {$ledger} r
         JOIN {$entries} e ON e.entry_id=r.entry_id AND e.form_id=88
         LEFT JOIN {$flags} f ON f.entry_id=e.entry_id WHERE COALESCE(f.is_test,0)=0"
    );
    echo '<h2>Manually verified receipts: £' . esc_html(number_format($sum/100,2)) . '</h2>';
    echo '<p>Includes only recorded evidence-backed receipts for non-test applications. Does not represent automatic Stripe reconciliation.</p>';
    if ($adjustments_ready) {
        $deductions = (int)$wpdb->get_var(
            "SELECT COALESCE(SUM(a.amount_pence),0) FROM {$adjustments} a
             JOIN {$ledger} r ON r.id=a.receipt_id
             JOIN {$entries} e ON e.entry_id=r.entry_id AND e.form_id=88
             LEFT JOIN {$flags} f ON f.entry_id=e.entry_id WHERE COALESCE(f.is_test,0)=0"
        );
        echo '<h2>Recorded refunds and corrections: £' . esc_html(number_format($deductions/100,2)) . '</h2>';
        echo '<h2>Net manually reconciled receipts: £' . esc_html(number_format(($sum-$deductions)/100,2)) . '</h2>';
        echo '<p>Adjustments must reflect independently verified refunds or corrections. No money is refunded through this page.</p>';
    } else {
        echo '<div class="notice notice-warning inline"><p>Refund/correction ledger is not installed. Gross receipts only; do not rely on these as net income.</p></div>';
    }
    echo '<h2>Record independently verified payment</h2>';
    echo '<form method="post" class="bh-sponsor-receipts-form">';
    wp_nonce_field('bh_record_sponsor_receipt');
    echo '<p><label>Genuine application <select name="bh_receipt_entry" required><option value="">Choose entry</option>';
    $applications = $wpdb->get_col(
        "SELECT e.entry_id FROM {$entries} e LEFT JOIN {$flags} f ON f.entry_id=e.entry_id
         WHERE e.form_id=88 AND COALESCE(f.is_test,0)=0 ORDER BY e.entry_id DESC LIMIT 500"
    );
    foreach ($applications as $id) echo '<option value="' . esc_attr((string)$id) . '">Entry #' . esc_html((string)$id) . '</option>';
    echo '</select></label></p>';
    echo '<p><label>Amount received (£) <input type="number" name="bh_receipt_amount" min="0.01" step="0.01" required></label></p>';
    echo '<p><label>Method <select name="bh_receipt_method" required><option value="">Choose</option><option value="stripe">Stripe (verified externally)</option><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="other">Other</option></select></label></p>';
    echo '<p><label>Evidence reference <input name="bh_receipt_reference" maxlength="190" required placeholder="Transaction or receipt reference"></label></p>';
    echo '<p><label>Verification note <textarea name="bh_receipt_note" rows="2" cols="50"></textarea></label></p>';
    echo '<p><label><input type="checkbox" name="bh_receipt_verified" value="1" required> I independently checked that funds were received and this is not a test.</label></p>';
    submit_button('Record verified receipt');
    echo '</form><h2>Receipt audit trail</h2><table class="widefat striped"><thead><tr><th>Entry</th><th>Amount</th><th>Method</th><th>Evidence reference</th><th>Verified by user ID</th><th>Verified at (UTC)</th></tr></thead><tbody>';
    $rows = $wpdb->get_results("SELECT entry_id,amount_pence,method,evidence_reference,verified_by,verified_at FROM {$ledger} ORDER BY id DESC LIMIT 100",ARRAY_A);
    foreach ($rows as $r) {
        echo '<tr><td>#'.esc_html((string)$r['entry_id']).'</td><td>£'.esc_html(number_format($r['amount_pence']/100,2)).'</td><td>'.esc_html($r['method']).'</td><td>'.esc_html($r['evidence_reference']).'</td><td>'.esc_html((string)$r['verified_by']).'</td><td>'.esc_html($r['verified_at']).'</td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="6">No verified receipts recorded.</td></tr>';
    echo '</tbody></table>';
    if ($adjustments_ready) {
        echo '<h2>Record an externally verified refund or correction</h2>';
        echo '<p>This records an adjustment only; it does not initiate a refund in Stripe or a bank.</p>';
        echo '<form method="post" class="bh-sponsor-receipts-form">';
        wp_nonce_field('bh_sponsor_refund');
        echo '<p><label>Original receipt <select name="bh_refund_receipt" required><option value="">Choose receipt</option>';
        $eligible = $wpdb->get_results(
            "SELECT r.id,r.entry_id,r.amount_pence-COALESCE(SUM(a.amount_pence),0) AS remaining
             FROM {$ledger} r JOIN {$entries} e ON e.entry_id=r.entry_id AND e.form_id=88
             LEFT JOIN {$flags} f ON f.entry_id=e.entry_id
             LEFT JOIN {$adjustments} a ON a.receipt_id=r.id
             WHERE COALESCE(f.is_test,0)=0
             GROUP BY r.id,r.entry_id,r.amount_pence HAVING remaining>0 ORDER BY r.id DESC LIMIT 100", ARRAY_A);
        foreach ($eligible as $item) {
            echo '<option value="'.esc_attr((string)$item['id']).'">Receipt #'.esc_html((string)$item['id']).' / Entry #'.esc_html((string)$item['entry_id']).' (remaining £'.esc_html(number_format($item['remaining']/100,2)).')</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>Refund/correction amount (£)<input type="number" name="bh_refund_amount" min="0.01" step="0.01" required></label></p>';
        echo '<p><label>Evidence reference<input name="bh_refund_reference" maxlength="190" required></label></p>';
        echo '<p><label>Reason<textarea name="bh_refund_reason" required minlength="5"></textarea></label></p>';
        echo '<p><label><input type="checkbox" name="bh_refund_confirmed" value="1" required> I verified the external refund or correction and its evidence.</label></p>';
        submit_button('Record adjustment');
        echo '</form><h2>Adjustment audit trail</h2><table class="widefat striped"><thead><tr><th>Receipt</th><th>Deduction</th><th>Reference</th><th>Reason</th><th>Recorded by</th><th>UTC</th></tr></thead><tbody>';
        $history=$wpdb->get_results("SELECT receipt_id,amount_pence,evidence_reference,reason,recorded_by,recorded_at FROM {$adjustments} ORDER BY id DESC LIMIT 100",ARRAY_A);
        foreach ($history as $item) {
            echo '<tr><td>#'.esc_html((string)$item['receipt_id']).'</td><td>£'.esc_html(number_format($item['amount_pence']/100,2)).'</td><td>'.esc_html($item['evidence_reference']).'</td><td>'.esc_html($item['reason']).'</td><td>'.esc_html((string)$item['recorded_by']).'</td><td>'.esc_html($item['recorded_at']).'</td></tr>';
        }
        if (!$history) echo '<tr><td colspan="6">No adjustments recorded.</td></tr>';
        echo '</tbody></table>';
    }
    echo '<p>All records are append-only in this interface. Stripe webhooks and automatic refund detection are not connected.</p></div>';
}
