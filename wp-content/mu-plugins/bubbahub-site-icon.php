<?php
/**
 * Plugin Name: Bubba Hub Site Icon
 * Description: Set the Bubba Hub main logo as the WordPress site icon.
 */
if (!defined('ABSPATH')) { exit; }
add_filter('get_site_icon_url', static function ($url, $size = 512) {
    return home_url('/images/logos/mainlogo-20260924-221429-7e305d.jpg');
}, 20, 2);
add_action('wp_head', static function () {
    $url = esc_url(home_url('/images/logos/mainlogo-20260924-221429-7e305d.jpg'));
    echo '<link rel="icon" type="image/jpeg" href="' . $url . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . $url . '">' . "\n";
}, 99);
