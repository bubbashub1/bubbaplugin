<?php
/**
 * Plugin Name: Bubba Hub Account Linking Audit
 * Description: Read-only validation for future WordPress/legacy account linking.
 */
if (!defined('ABSPATH')) exit;

/**
 * Return a legacy ID linked to a WP user, but never infer a link by email.
 * Future migration code must verify identity before writing this meta key.
 */
function bh_wp_legacy_user_id($wp_user_id) {
    $id = get_user_meta((int)$wp_user_id, 'bh_legacy_user_id', true);
    return ctype_digit((string)$id) && (int)$id > 0 ? (int)$id : null;
}
add_action('admin_menu', function () {
    add_submenu_page('bubba-hub', 'Account Link Audit', 'Account Link Audit', 'manage_options', 'bubba-hub-link-audit', 'bh_wp_render_link_audit');
}, 32);
function bh_wp_render_link_audit() {
    if (!current_user_can('manage_options')) wp_die('Access denied');
    global $wpdb;
    echo '<div class="wrap"><h1>Account Link Audit</h1><p>Read-only checks. No accounts, passwords, user metadata, or subscriptions are changed.</p>';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_users')) !== 'bh_users') {
        echo '<p>Legacy accounts table unavailable.</p></div>'; return;
    }
    $cols = $wpdb->get_col('SHOW COLUMNS FROM `bh_users`', 0);
    if (count(array_intersect(['id','email','role'], $cols)) !== 3) {
        echo '<p>Legacy account schema is incomplete.</p></div>'; return;
    }
    $users = $wpdb->get_results('SELECT id,email,role FROM `bh_users` ORDER BY id LIMIT 500', ARRAY_A);
    $subtable = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'bh_user_subscriptions')) === 'bh_user_subscriptions';
    $subcols = $subtable ? $wpdb->get_col('SHOW COLUMNS FROM `bh_user_subscriptions`', 0) : [];
    $subs = [];
    if (count(array_intersect(['user_id','plan','status'], $subcols)) === 3) {
        foreach ($wpdb->get_results('SELECT user_id,plan,status FROM `bh_user_subscriptions`', ARRAY_A) as $s) {
            $subs[(int)$s['user_id']][] = $s;
        }
    }
    $meta = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s", 'bh_legacy_user_id'
    ), ARRAY_A);
    $links = [];
    foreach ($meta as $m) {
        $id = (int)$m['meta_value'];
        if ($id > 0) $links[$id][] = (int)$m['user_id'];
    }
    echo '<table class="widefat striped"><thead><tr><th>Legacy ID</th><th>Role</th><th>Pro status</th><th>Link status</th><th>Action required</th></tr></thead><tbody>';
    foreach ($users as $u) {
        $id=(int)$u['id']; $role=strtolower((string)$u['role']);
        $records=$subs[$id]??[];
        $pro='None';
        $issues=[];
        foreach ($records as $s) {
            if (in_array($s['status'], ['active','trialing'], true) && in_array($s['plan'], ['family_pro','leader_pro'], true)) {
                $pro=$s['plan'].' ('.$s['status'].')';
                if (($role==='leader' && $s['plan']!=='leader_pro') || ($role==='family' && $s['plan']!=='family_pro')) $issues[]='Pro plan/role mismatch: investigate, do not alter';
            }
        }
        if (count($records)>1) $issues[]='Multiple subscription records: review';
        if ($role==='admin') $issues[]='Manual administrator approval required';
        $linked=$links[$id]??[];
        if (count($linked)>1) $issues[]='Duplicate legacy links';
        foreach ($linked as $wpid) {
            $wp=get_userdata($wpid);
            if (!$wp) $issues[]='Linked WordPress account missing';
            elseif (strcasecmp(trim((string)$u['email']),trim((string)$wp->user_email))!==0) $issues[]='Linked account email differs: verify ownership';
        }
        $matching=get_user_by('email',trim((string)$u['email']));
        if ($matching && !$linked) $issues[]='Matching email exists: verify identity before linking';
        if (!$linked && !$matching && $role!=='admin') $issues[]='Needs verified account creation and secure password setup';
        echo '<tr><td>'.esc_html((string)$id).'</td><td>'.esc_html($role).'</td><td>'.esc_html($pro).'</td><td>'.esc_html($linked?'Linked to WP ID(s) '.implode(', ', $linked):'Not linked').'</td><td>'.esc_html(implode('; ', $issues) ?: 'No issues detected').'</td></tr>';
    }
    echo '</tbody></table><p><strong>Next stage:</strong> Back up databases; validate ownership and unique mappings; implement a staging-only, administrator-confirmed migration with password setup and rollback. Legacy PHP sessions and Stripe remain unchanged.</p></div>';
}
