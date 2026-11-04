<?php
/**
 * Bubba Hub Support overview: private admin-only navigation and aggregate counts.
 * Does not render sensitive submission answers.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    add_menu_page(
        __('Bubba Hub Support', 'bubbahub'),
        __('BH Support', 'bubbahub'),
        'manage_options',
        'bh-support-dashboard',
        'bh_support_dashboard_render',
        'dashicons-sos',
        58
    );
});

function bh_support_dashboard_render(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Access denied.', 'bubbahub'));
    }

    $resources = wp_count_posts('bh_resource');
    $posts = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'pending',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
        'meta_query' => [[
            'key' => '_bh_leader_organiser_id',
            'compare' => 'EXISTS',
        ]],
    ]);
    $pending = (int) $posts->found_posts;
    $forms = array_values(array_unique(array_filter(array_map('absint', (array) get_option('bh_support_fluent_form_ids', [])))));
    $counts = ['new' => 0, 'in_progress' => 0, 'resolved' => 0];
    $total = 0;
    $fluent_available = false;

    global $wpdb;
    $submission_table = $wpdb->prefix . 'fluentform_submissions';
    $workflow_table = $wpdb->prefix . 'bh_support_enquiry_status';
    if ($forms && defined('FLUENTFORM') &&
        $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $submission_table)) === $submission_table) {
        $fluent_available = true;
        $placeholders = implode(',', array_fill(0, count($forms), '%d'));
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$submission_table` WHERE form_id IN ($placeholders)", ...$forms
        ));
        $counts['new'] = $total;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $workflow_table)) === $workflow_table) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT w.status, COUNT(*) AS qty
                 FROM `$workflow_table` w
                 INNER JOIN `$submission_table` s ON s.id = w.entry_id AND s.form_id = w.form_id
                 WHERE s.form_id IN ($placeholders) GROUP BY w.status", ...$forms
            ));
            foreach ((array) $rows as $row) {
                if (isset($counts[$row->status])) {
                    $qty = (int) $row->qty;
                    if ($row->status !== 'new') {
                        $counts[$row->status] += $qty;
                        $counts['new'] -= $qty;
                    }
                }
            }
        }
    }
    $cards = [
        [
            'label' => __('Published resources', 'bubbahub'),
            'number' => (int) ($resources->publish ?? 0),
            'url' => admin_url('edit.php?post_type=bh_resource'),
            'action' => __('Manage resources', 'bubbahub'),
        ],
        [
            'label' => __('Pending leader articles', 'bubbahub'),
            'number' => $pending,
            'url' => admin_url('admin.php?page=bh-support-article-review'),
            'action' => __('Review articles', 'bubbahub'),
        ],
        [
            'label' => __('Support enquiries', 'bubbahub'),
            'number' => $total,
            'url' => admin_url('admin.php?page=bh-support-enquiries'),
            'action' => __('Open enquiry inbox', 'bubbahub'),
        ],
    ];

    echo '<div class="wrap bh-support-admin"><h1>' . esc_html__('Bubba Hub · Help & Support', 'bubbahub') . '</h1>';
    echo '<p>' . esc_html__('Manage family resources, community articles and support enquiries in one place.', 'bubbahub') . '</p>';
    echo '<div class="bh-support-admin__cards">';
    foreach ($cards as $card) {
        echo '<section class="bh-support-admin__card"><h2>' . esc_html($card['label']) . '</h2>';
        echo '<div class="bh-support-admin__number">' . esc_html(number_format_i18n($card['number'])) . '</div>';
        echo '<a class="button button-primary" href="' . esc_url($card['url']) . '">' . esc_html($card['action']) . '</a></section>';
    }
    echo '</div><section class="bh-support-admin__section"><h2>' . esc_html__('Enquiry workflow', 'bubbahub') . '</h2>';
    if (!$forms) {
        echo '<p>' . esc_html__('Select your support form IDs in the enquiry inbox to activate these figures.', 'bubbahub') . '</p>';
    } elseif (!$fluent_available) {
        echo '<p>' . esc_html__('Fluent Forms submissions are not currently available.', 'bubbahub') . '</p>';
    } else {
        echo '<div class="bh-support-admin__statuses">';
        foreach (['new' => __('New', 'bubbahub'), 'in_progress' => __('In Progress', 'bubbahub'), 'resolved' => __('Resolved', 'bubbahub')] as $key => $label) {
            echo '<div><strong>' . esc_html($label) . '</strong><span>' . esc_html(number_format_i18n(max(0, $counts[$key]))) . '</span></div>';
        }
        echo '</div>';
    }
    echo '</section><section class="bh-support-admin__section"><h2>' . esc_html__('Quick links', 'bubbahub') . '</h2>';
    echo '<p><a href="' . esc_url(admin_url('edit-tags.php?taxonomy=bh_support_topic&post_type=bh_resource')) . '">' . esc_html__('Manage support topics', 'bubbahub') . '</a> · ';
    echo '<a href="' . esc_url(admin_url('post-new.php?post_type=bh_resource')) . '">' . esc_html__('Add a resource', 'bubbahub') . '</a> · ';
    echo '<a href="' . esc_url(admin_url('admin.php?page=fluent_forms')) . '">' . esc_html__('Fluent Forms', 'bubbahub') . '</a></p>';
    echo '<p>' . esc_html__('Private enquiries remain inside Fluent Forms. This dashboard only shows aggregate totals.', 'bubbahub') . '</p></section></div>';
}

add_action('admin_enqueue_scripts', static function ($hook): void {
    if ($hook !== 'toplevel_page_bh-support-dashboard') return;
    wp_register_style('bh-support-admin-dashboard', false, [], '1.0.0');
    wp_enqueue_style('bh-support-admin-dashboard');
    wp_add_inline_style('bh-support-admin-dashboard', '
        .bh-support-admin{max-width:1200px;color:#1e382c}
        .bh-support-admin>h1{font-size:30px;font-weight:750;margin:24px 0 8px}
        .bh-support-admin>p{font-size:15px;color:#53645b}
        .bh-support-admin__cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:24px 0}
        .bh-support-admin__card,.bh-support-admin__section{background:#fff;border:1px solid #dce8df;border-radius:16px;padding:24px;box-shadow:0 3px 16px rgba(17,61,37,.04)}
        .bh-support-admin__card h2{font-size:16px;margin:0 0 14px}
        .bh-support-admin__number{font-size:40px;font-weight:800;line-height:1.2;margin-bottom:20px;color:#174b36}
        .bh-support-admin .button-primary{background:#174b36;border-color:#174b36}
        .bh-support-admin__section{margin:16px 0}
        .bh-support-admin__statuses{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .bh-support-admin__statuses>div{padding:16px;border-radius:12px;background:#eff6f0;display:flex;align-items:center;justify-content:space-between}
        .bh-support-admin__statuses span{font-size:24px;font-weight:800;color:#174b36}
        @media(max-width:800px){.bh-support-admin__cards,.bh-support-admin__statuses{grid-template-columns:1fr}}
    ');
});
