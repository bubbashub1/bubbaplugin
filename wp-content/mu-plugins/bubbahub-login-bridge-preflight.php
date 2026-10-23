<?php
/**
 * Plugin Name: Bubba Hub WordPress Login Bridge Preflight
 * Description: Read-only verification before connecting WordPress authentication to Bubba Hub PHP APIs.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page('bubba-hub', 'Login Bridge Checks', 'Login Bridge Checks', 'manage_options', 'bh-login-bridge-checks', 'bh_login_bridge_checks_page');
}, 34);

function bh_login_bridge_checks_page() {
    if (!current_user_can('manage_options')) wp_die('Access denied');
    global $wpdb;
    echo '<div class="wrap"><h1>WordPress Login Bridge — Preflight</h1>';
    echo '<p>Read-only checks. WordPress authentication is not yet connected to Bubba Hub API sessions. Existing sign-in remains unchanged.</p>';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s','bh_users')) !== 'bh_users') {
        echo '<p>Legacy users table missing.</p></div>'; return;
    }
    $rows=$wpdb->get_results('SELECT id,email,role FROM `bh_users` ORDER BY id LIMIT 500',ARRAY_A);
    $meta=$wpdb->get_results($wpdb->prepare("SELECT user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s",'bh_legacy_user_id'),ARRAY_A);
    $legacy_links=[];$wp_links=[];
    foreach ($meta as $m) {
        if (!ctype_digit((string)$m['meta_value']) || (int)$m['meta_value']<1) continue;
        $legacy_links[(int)$m['meta_value']][]=(int)$m['user_id'];
        $wp_links[(int)$m['user_id']][]=(int)$m['meta_value'];
    }
    echo '<table class="widefat striped"><thead><tr><th>Legacy ID</th><th>Legacy role</th><th>WordPress ID</th><th>Role verified</th><th>Email verified</th><th>Ready for bridge?</th></tr></thead><tbody>';
    $ready=0;
    foreach($rows as $row) {
        $id=(int)$row['id'];$role=strtolower((string)$row['role']);
        $linked=$legacy_links[$id]??[];
        $wp=count($linked)===1?get_userdata($linked[0]):false;
        $expected=$role==='family'?'bh_family':($role==='leader'?'bh_leader':'');
        $role_ok=$wp && $expected && in_array($expected,(array)$wp->roles,true);
        $email_ok=$wp && strcasecmp(trim((string)$row['email']),trim((string)$wp->user_email))===0;
        $unique=$wp && count(array_unique($wp_links[(int)$wp->ID]??[]))===1;
        $ok=$role_ok && $email_ok && $unique && count($linked)===1;
        if($ok)$ready++;
        echo '<tr><td>'.esc_html((string)$id).'</td><td>'.esc_html($role).'</td><td>'.esc_html($wp?(string)$wp->ID:'Not linked').'</td><td>'.esc_html($role_ok?'Yes':'No').'</td><td>'.esc_html($email_ok?'Yes':'No').'</td><td>'.esc_html($ok?'Ready for next phase':'Hold').'</td></tr>';
    }
    echo '</tbody></table><p><strong>'.esc_html((string)$ready).' of '.esc_html((string)count($rows)).' accounts pass the mapping checks.</strong></p>';
    echo '<p>Next: verify WordPress password setup and cookie authentication; then add a separately reviewed, opt-in API bridge that validates the logged-in WordPress user, their unique legacy ID, role and account status on every request. Never trust a user ID supplied by the browser. Keep Stripe subscription status authoritative.</p></div>';
}
