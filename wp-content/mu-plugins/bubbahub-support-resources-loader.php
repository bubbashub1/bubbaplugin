<?php
/**
 * Plugin Name: Bubba Hub Support Resources Loader
 * Description: Loads the WordPress Support & Guidance resource module when its source is deployed.
 * Version: 0.1.0
 */
defined('ABSPATH') || exit;

// Keep this separate from the existing manager, auth and support-message systems.
// Deployments may place the repository at the WP root or inside a plugin folder.
add_action('plugins_loaded', static function (): void {
    $candidates = [
        WP_CONTENT_DIR . '/plugins/bubbaplugin/wordpress/modules/support-resources.php',
        WP_CONTENT_DIR . '/plugins/bubbawp/wordpress/modules/support-resources.php',
        dirname(__DIR__, 2) . '/wordpress/modules/support-resources.php',
        WP_PLUGIN_DIR . '/bubbawp/wordpress/modules/support-resources.php',
        WP_PLUGIN_DIR . '/bubbahub/wordpress/modules/support-resources.php',
    ];
    // Allow staging to supply its actual deployed module path without editing this loader.
    $custom = apply_filters('bh_support_resources_module_paths', []);
    if (is_array($custom)) $candidates = array_merge($custom, $candidates);
    foreach (array_unique($candidates) as $file) {
        if (is_readable($file)) {
            require_once $file;
            $articles = dirname($file) . '/support-articles.php';
            if (is_readable($articles)) require_once $articles;
            $hub = dirname($file) . '/support-hub.php';
            if (is_readable($hub)) require_once $hub;
            $moderation = dirname($file) . '/support-article-moderation.php';
            if (is_readable($moderation)) require_once $moderation;
            $inbox = dirname($file) . '/support-fluentforms-inbox.php';
            if (is_readable($inbox)) require_once $inbox;
            $dashboard = dirname($file) . '/support-admin-dashboard.php';
            if (is_readable($dashboard)) require_once $dashboard;
            return;
        }
    }
    if (is_admin()) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('manage_options')) return;
            echo '<div class="notice notice-warning"><p>' .
                esc_html__('Bubba Hub Resources: module not found. Deploy wordpress/modules/support-resources.php alongside the loader.', 'bubbahub') .
                '</p></div>';
        });
    }
});
