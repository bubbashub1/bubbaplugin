<?php
/**
 * Fluent Forms Free enquiry inbox with separate workflow status tracking.
 * Uses Fluent Forms' own submissions table; does not duplicate private answers.
 */
defined('ABSPATH') || exit;


// Workflow metadata is separate from Fluent Forms responses and stores no family message content.
function bh_support_history_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bh_support_enquiry_history';
}

function bh_support_status_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bh_support_enquiry_status';
}

add_action('admin_init', static function (): void {
    if (!current_user_can('manage_options') || get_option('bh_support_status_schema_version') === '2') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = bh_support_status_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        entry_id bigint(20) unsigned NOT NULL,
        form_id bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        updated_at datetime NOT NULL,
        updated_by bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (entry_id),
        KEY form_id (form_id)
    ) $charset;");
    $history = bh_support_history_table();
    dbDelta("CREATE TABLE $history (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        entry_id bigint(20) unsigned NOT NULL,
        form_id bigint(20) unsigned NOT NULL,
        old_status varchar(20) NOT NULL,
        new_status varchar(20) NOT NULL,
        changed_at datetime NOT NULL,
        changed_by bigint(20) unsigned NOT NULL,
        PRIMARY KEY  (id),
        KEY entry_id (entry_id),
        KEY form_id (form_id)
    ) $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table
        && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $history)) === $history) {
        update_option('bh_support_status_schema_version', '2', false);
    }
});

add_action('admin_menu', static function (): void {
    add_submenu_page('bh-support-dashboard', __('Bubba Hub Enquiries', 'bubbahub'), __('Bubba Hub Enquiries', 'bubbahub'),
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
    if (isset($_POST['bh_support_change_status'])) {
        check_admin_referer('bh_support_change_status');
        global $wpdb;
        $entry_id = isset($_POST['bh_support_entry_id']) ? absint($_POST['bh_support_entry_id']) : 0;
        $status = isset($_POST['bh_support_status']) ? sanitize_key(wp_unslash($_POST['bh_support_status'])) : '';
        $allowed = ['new', 'in_progress', 'resolved'];
        $configured = array_values(array_filter(array_map('absint', (array) get_option('bh_support_fluent_form_ids', []))));
        $submissions_table = $wpdb->prefix . 'fluentform_submissions';
        if ($entry_id && in_array($status, $allowed, true) && $configured
            && get_option('bh_support_status_schema_version') === '2'
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', bh_support_status_table())) === bh_support_status_table()
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $submissions_table)) === $submissions_table) {
            $form_id = (int) $wpdb->get_var($wpdb->prepare("SELECT form_id FROM `$submissions_table` WHERE id = %d", $entry_id));
            if (in_array($form_id, $configured, true)) {
                $previous = $wpdb->get_var($wpdb->prepare(
                    'SELECT status FROM `' . bh_support_status_table() . '` WHERE entry_id = %d AND form_id = %d',
                    $entry_id, $form_id
                ));
                $previous = $previous ?: 'new';
                if ($previous !== $status) {
                    $saved = $wpdb->replace(bh_support_status_table(), [
                        'entry_id' => $entry_id, 'form_id' => $form_id, 'status' => $status,
                        'updated_at' => current_time('mysql', true), 'updated_by' => get_current_user_id(),
                    ], ['%d', '%d', '%s', '%s', '%d']);
                    if ($saved !== false) {
                        $wpdb->insert(bh_support_history_table(), [
                            'entry_id' => $entry_id, 'form_id' => $form_id,
                            'old_status' => $previous, 'new_status' => $status,
                            'changed_at' => current_time('mysql', true), 'changed_by' => get_current_user_id(),
                        ], ['%d', '%d', '%s', '%s', '%d']);
                        echo '<div class="notice notice-success"><p>' . esc_html__('Enquiry status updated.', 'bubbahub') . '</p></div>';
                    }
                }
            }
        }
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
    $filter = isset($_GET['bh_workflow']) && is_string($_GET['bh_workflow']) ? sanitize_key(wp_unslash($_GET['bh_workflow'])) : '';
    if (!in_array($filter, ['', 'new', 'in_progress', 'resolved'], true)) $filter = '';
    $offset = ($page - 1) * 20;
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $where = "form_id IN ($placeholders)";
    $status_table = bh_support_status_table();
    $has_workflow = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $status_table)) === $status_table;
    if (!$has_workflow) $filter = '';
    $join = $has_workflow ? " LEFT JOIN `$status_table` w ON w.entry_id = s.id AND w.form_id = s.form_id" : '';
    $where = 's.' . $where;
    if ($filter !== '' && $has_workflow) {
        $where .= $filter === 'new' ? " AND (w.status IS NULL OR w.status = 'new')" : " AND w.status = '" . esc_sql($filter) . "'";
    }
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table` s $join WHERE $where", ...$ids));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.form_id, s.status, s.created_at FROM `$table` s $join WHERE $where ORDER BY s.id DESC LIMIT %d OFFSET %d",
        ...array_merge($ids, [20, $offset])
    ));
    echo '<form method="get" style="margin:18px 0">';
    echo '<input type="hidden" name="page" value="bh-support-enquiries">';
    echo '<label for="bh-workflow-filter">' . esc_html__('Show', 'bubbahub') . '</label> ';
    echo '<select id="bh-workflow-filter" name="bh_workflow">';
    foreach (['' => __('All enquiries', 'bubbahub'), 'new' => __('New', 'bubbahub'), 'in_progress' => __('In Progress', 'bubbahub'), 'resolved' => __('Resolved', 'bubbahub')] as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($filter, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select> <button class="button">' . esc_html__('Filter', 'bubbahub') . '</button></form>';
    echo '<p><strong>' . esc_html(sprintf(_n('%d enquiry', '%d enquiries', $count, 'bubbahub'), $count)) . '</strong></p>';
    $status_table = bh_support_status_table();
    $status_map = [];
    $status_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $status_table)) === $status_table;
    if ($rows && $status_table_exists) {
        $entry_ids = array_map(static function ($row) { return (int) $row->id; }, $rows);
        $keys = implode(',', array_fill(0, count($entry_ids), '%d'));
        $states = $wpdb->get_results($wpdb->prepare("SELECT entry_id, status FROM `$status_table` WHERE entry_id IN ($keys)", ...$entry_ids));
        foreach ((array) $states as $state) $status_map[(int) $state->entry_id] = $state->status;
    }
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Entry', 'bubbahub') . '</th><th>' . esc_html__('Form', 'bubbahub') . '</th><th>' . esc_html__('Submitted', 'bubbahub') . '</th><th>' . esc_html__('Fluent Forms status', 'bubbahub') . '</th><th>' . esc_html__('Support workflow', 'bubbahub') . '</th></tr></thead><tbody>';
    foreach ((array) $rows as $entry) {
        echo '<tr><td><a href="' . esc_url(add_query_arg(['page' => 'bh-support-enquiries', 'bh_history_entry' => (int) $entry->id], admin_url('admin.php'))) . '">#' . esc_html((string) $entry->id) . '</a></td><td>#' . esc_html((string) $entry->form_id) . '</td><td>' . esc_html((string) $entry->created_at) . '</td><td>' . esc_html((string) $entry->status) . '</td><td>';
        if (!$status_table_exists) {
            echo esc_html__('Workflow table unavailable', 'bubbahub') . '</td></tr>';
            continue;
        }
        echo '<form method="post">';
        wp_nonce_field('bh_support_change_status');
        echo '<input type="hidden" name="bh_support_entry_id" value="' . esc_attr((string) $entry->id) . '">';
        echo '<select name="bh_support_status" aria-label="' . esc_attr__('Support workflow status', 'bubbahub') . '">';
        foreach (['new' => __('New', 'bubbahub'), 'in_progress' => __('In Progress', 'bubbahub'), 'resolved' => __('Resolved', 'bubbahub')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status_map[(int) $entry->id] ?? 'new', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> <button class="button" name="bh_support_change_status" value="1">' . esc_html__('Update', 'bubbahub') . '</button></form></td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="5">' . esc_html__('No entries found.', 'bubbahub') . '</td></tr>';
    echo '</tbody></table>';
    $pages = (int) ceil($count / 20);
    if ($pages > 1) {
        echo '<p>';
        for ($i = 1; $i <= min($pages, 100); $i++) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'bh-support-enquiries', 'paged' => $i, 'bh_workflow' => $filter], admin_url('admin.php'))) . '">' . esc_html((string) $i) . '</a> ';
        }
        echo '</p>';
    }
    $history_entry = isset($_GET['bh_history_entry']) ? absint($_GET['bh_history_entry']) : 0;
    if ($history_entry && get_option('bh_support_status_schema_version') === '2') {
        $entry_form = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT form_id FROM `$table` WHERE id = %d", $history_entry
        ));
        if (in_array($entry_form, $ids, true)) {
            $history_table = bh_support_history_table();
            $events = $wpdb->get_results($wpdb->prepare(
                "SELECT old_status, new_status, changed_at, changed_by FROM `$history_table`
                 WHERE entry_id = %d AND form_id = %d ORDER BY id DESC LIMIT 50",
                $history_entry, $entry_form
            ));
            echo '<h2>' . esc_html(sprintf(__('Activity history for enquiry #%d', 'bubbahub'), $history_entry)) . '</h2>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('When (UTC)', 'bubbahub') . '</th><th>' . esc_html__('Changed by', 'bubbahub') . '</th><th>' . esc_html__('Change', 'bubbahub') . '</th></tr></thead><tbody>';
            foreach ((array) $events as $event) {
                $staff = get_userdata((int) $event->changed_by);
                echo '<tr><td>' . esc_html($event->changed_at) . '</td><td>' . esc_html($staff ? $staff->display_name : __('Former user', 'bubbahub')) . '</td><td>' . esc_html($event->old_status . ' → ' . $event->new_status) . '</td></tr>';
            }
            if (!$events) echo '<tr><td colspan="3">' . esc_html__('No status changes recorded yet.', 'bubbahub') . '</td></tr>';
            echo '</tbody></table>';
        }
    }
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=fluent_forms')) . '">' . esc_html__('Open Fluent Forms', 'bubbahub') . '</a></p></div>';
}
