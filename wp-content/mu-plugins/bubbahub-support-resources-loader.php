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
        dirname(__DIR__, 2) . '/wordpress/modules/support-resources.php',
        WP_PLUGIN_DIR . '/bubbawp/wordpress/modules/support-resources.php',
        WP_PLUGIN_DIR . '/bubbahub/wordpress/modules/support-resources.php',
    ];
    foreach ($candidates as $file) {
        if (is_readable($file)) {
            require_once $file;
            $articles = dirname($file) . '/support-articles.php';
            if (is_readable($articles)) require_once $articles;
            $hub = dirname($file) . '/support-hub.php';
            if (is_readable($hub)) require_once $hub;
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
