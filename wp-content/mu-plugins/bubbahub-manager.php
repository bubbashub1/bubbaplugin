<?php
/**
 * Plugin Name: Bubba Hub Manager MU Loader
 * Description: Loads the Bubba Hub Manager in WordPress must-use plugins.
 * Version: 0.1.1
 */
if (!defined('ABSPATH')) exit;
// The separately installed ZIP version may still be active during staging rollout.
// MU plugins run before ordinary plugins, so defer loading until ordinary plugins
// have loaded and avoid declaring the same class twice.
add_action('plugins_loaded', static function (): void {
    if (class_exists('Bubba_Hub_Manager', false)) return;
    $path = __DIR__ . '/bubbahub-manager/bubba-hub-manager.php';
    if (is_readable($path)) require_once $path;
}, 20);
