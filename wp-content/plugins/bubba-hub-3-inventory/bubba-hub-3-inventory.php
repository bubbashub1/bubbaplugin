<?php
/**
 * Plugin Name: Bubba Hub 3 — Read-only Inventory
 * Description: Staging audit of WordPress configuration and existing database schema. Does not alter data.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', static function () {
    add_management_page('Bubba Hub 3 Inventory', 'BH3 Inventory', 'manage_options', 'bh3-inventory', 'bh3_inventory_render');
});

function bh3_inventory_collect() {
    global $wpdb, $wp_registered_sidebars, $wp_registered_widgets, $shortcode_tags;
    $post_types = get_post_types([], 'objects');
    $types = [];
    foreach ($post_types as $name => $type) {
        $counts = wp_count_posts($name);
        $types[$name] = [
            'label' => $type->label,
            'public' => (bool) $type->public,
            'counts' => $counts ? (array) $counts : [],
        ];
    }
    $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish','draft','private','pending','future'], 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids']);
    $page_details = [];
    foreach ($pages as $id) {
        $page_details[] = [
            'id' => (int) $id,
            'slug' => get_post_field('post_name', $id),
            'status' => get_post_status($id),
            'template' => get_page_template_slug($id) ?: 'default',
            'elementor' => (bool) get_post_meta($id, '_elementor_edit_mode', true),
        ];
    }
    $plugins = get_plugins();
    $active = (array) get_option('active_plugins', []);
    $plugin_details = [];
    foreach ($plugins as $path => $plugin) {
        $plugin_details[] = ['file' => $path, 'name' => $plugin['Name'], 'version' => $plugin['Version'], 'active' => in_array($path, $active, true)];
    }
    $tables = [];
    // SHOW TABLES is read-only. Do not query table rows or expose personal records.
    $names = $wpdb->get_col('SHOW TABLES');
    foreach ((array) $names as $name) {
        if (!preg_match('/^(bh_|' . preg_quote($wpdb->prefix, '/') . ')/', $name)) { continue; }
        $columns = $wpdb->get_results('SHOW COLUMNS FROM `' . str_replace('`', '``', $name) . '`', ARRAY_A);
        $tables[] = ['name' => $name, 'columns' => array_map(static function ($c) {
            return ['name' => $c['Field'], 'type' => $c['Type'], 'null' => $c['Null'], 'key' => $c['Key']];
        }, (array) $columns)];
    }
    $menus = [];
    foreach (wp_get_nav_menus() as $menu) {
        $menus[] = ['term_id' => (int) $menu->term_id, 'slug' => $menu->slug, 'count' => count(wp_get_nav_menu_items($menu->term_id) ?: [])];
    }
    $cron = _get_cron_array();
    $hooks = [];
    foreach ((array) $cron as $timestamp => $events) {
        foreach ($events as $hook => $instances) {
            $hooks[$hook] = ($hooks[$hook] ?? 0) + count($instances);
        }
    }
    ksort($hooks);
    return [
        'generated_utc' => gmdate('c'),
        'site_url' => home_url('/'),
        'wp_version' => get_bloginfo('version'),
        'theme' => wp_get_theme()->get('Name'),
        'post_types' => $types,
        'pages' => $page_details,
        'plugins' => $plugin_details,
        'mu_plugins' => array_keys(get_mu_plugins()),
        'sidebars' => array_keys((array) $wp_registered_sidebars),
        'widgets' => array_keys((array) $wp_registered_widgets),
        'shortcodes' => array_keys((array) $shortcode_tags),
        'menus' => $menus,
        'cron_hooks' => $hooks,
        'database_tables' => $tables,
        'database_note' => 'Only tables accessible through the current WordPress database connection are listed. External bh_ databases require a separate approved read-only audit.',
    ];
}

function bh3_inventory_render() {
    if (!current_user_can('manage_options')) { wp_die('Access denied'); }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $data = bh3_inventory_collect();
    echo '<div class="wrap"><h1>Bubba Hub 3 — WordPress Inventory</h1>';
    echo '<p>Read-only discovery. No database writes, migrations or plugin activation side effects.</p>';
    echo '<p><strong>Post types:</strong> ' . esc_html(count($data['post_types'])) . ' | <strong>Pages:</strong> ' . esc_html(count($data['pages'])) . ' | <strong>Plugins:</strong> ' . esc_html(count($data['plugins'])) . ' | <strong>Tables visible:</strong> ' . esc_html(count($data['database_tables'])) . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="bh3_inventory_export">';
    wp_nonce_field('bh3_inventory_export');
    submit_button('Download inventory JSON', 'primary', 'submit', false);
    echo '</form><p>Export contains site configuration and database column names, but no table records or personal details. Store it privately.</p></div>';
}

add_action('admin_post_bh3_inventory_export', static function () {
    if (!current_user_can('manage_options')) { wp_die('Access denied'); }
    check_admin_referer('bh3_inventory_export');
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $json = wp_json_encode(bh3_inventory_collect(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="bh3-wordpress-inventory.json"');
    echo $json;
    exit;
});
