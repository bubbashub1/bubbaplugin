<?php
/**
 * Read-only Fluent Forms Free enquiry inbox.
 * Uses Fluent Forms' own submissions table; does not duplicate private answers.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    add_submenu_page('tools.php', __('Bubba Hub Enquiries', 'bubbahub'), __('Bubba Hub Enquiries', 'bubbahub'),
        'manage_options', 'bh-support-enquiries', 'bh_support_enquiries_page');
});

function bh_support_enquiries_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view enquiries.', 'bubbahub'));
    }
    if (isset($_POST['bh_support_save_form_ids'])) {
        check_admin_referer('bh_support_form_settings');
        $raw = isset($_POST['bh_support_form_ids']) ? sanitize_text_field(wp_unslash($_POST['bh_support_form_ids'])) : '';
        $ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,]+/', $raw)))));
        update_option('bh_support_fluent_form_ids', $ids, false);
        echo '<div class="notice notice-success"><p>' . esc_html__('Support form settings saved.', 'bubbahub') . '</p></div>';
    }
    $ids = array_values(array_filter(array_map('absint', (array) get_option('bh_support_fluent_form_ids', []))));
    echo '<div class="wrap"><h1>' . esc_html__('Bubba Hub · Support Enquiries', 'bubbahub') . '</h1>';
    echo '<p>' . esc_html__('Select the Fluent Forms form IDs used for support enquiries. Entries remain stored in Fluent Forms; this page shows limited metadata only. Open Fluent Forms to view private responses.', 'bubbahub') . '</p>';
    echo '<form method="post">';
    wp_nonce_field('bh_support_form_settings');
    echo '<label for="bh_support_form_ids"><strong>' . esc_html__('Support form IDs (comma-separated)', 'bubbahub') . '</strong></label><br>';
    echo '<input id="bh_support_form_ids" name="bh_support_form_ids" type="text" class="regular-text" value="' . esc_attr(implode(', ', $ids)) . '" placeholder="12, 15">';
    echo ' <button class="button button-primary" name="bh_support_save_form_ids" value="1">' . esc_html__('Save', 'bubbahub') . '</button></form>';
    if (!$ids) {
        echo '<p>' . esc_html__('No support forms configured yet.', 'bubbahub') . '</p></div>';
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'fluentform_submissions';
    // This module is optional: do not query tables unless Fluent Forms is installed.
    if (!defined('FLUENTFORM') || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Fluent Forms submissions are not available on this installation.', 'bubbahub') . '</p></div></div>';
        return;
    }
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($page - 1) * 20;
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $where = "form_id IN ($placeholders)";
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table` WHERE $where", ...$ids));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, form_id, status, created_at FROM `$table` WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d",
        ...array_merge($ids, [20, $offset])
    ));
    echo '<p><strong>' . esc_html(sprintf(_n('%d enquiry', '%d enquiries', $count, 'bubbahub'), $count)) . '</strong></p>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Entry', 'bubbahub') . '</th><th>' . esc_html__('Form', 'bubbahub') . '</th><th>' . esc_html__('Submitted', 'bubbahub') . '</th><th>' . esc_html__('Fluent Forms status', 'bubbahub') . '</th></tr></thead><tbody>';
    foreach ((array) $rows as $entry) {
        echo '<tr><td>#' . esc_html((string) $entry->id) . '</td><td>#' . esc_html((string) $entry->form_id) . '</td><td>' . esc_html((string) $entry->created_at) . '</td><td>' . esc_html((string) $entry->status) . '</td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="4">' . esc_html__('No entries found.', 'bubbahub') . '</td></tr>';
    echo '</tbody></table>';
    $pages = (int) ceil($count / 20);
    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= min($pages, 100); $i++) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'bh-support-enquiries', 'paged' => $i], admin_url('tools.php'))) . '">' . esc_html((string) $i) . '</a> ';
        }
        echo '</p>';
    }
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=fluent_forms')) . '">' . esc_html__('Open Fluent Forms', 'bubbahub') . '</a></p></div>';
}
