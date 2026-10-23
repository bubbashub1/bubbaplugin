<?php
/**
 * Plugin Name: Bubba Hub User Migration Pilot
 * Description: Administrator-confirmed, one-account-at-a-time WordPress migration pilot.
 */
if (!defined('ABSPATH')) exit;

function bh_migration_is_allowed_host() {
    $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    return in_array($host, ['bubbahub.co.uk','staging.bubbahub.co.uk'], true);
}

add_action('admin_menu', function () {
    if (!bh_migration_is_allowed_host()) return;
    add_submenu_page('bubba-hub', 'User Migration Pilot', 'User Migration Pilot', 'manage_options', 'bh-user-migration-pilot', 'bh_render_staging_migration_pilot');
}, 33);

function bh_render_staging_migration_pilot() {
    if (!current_user_can('manage_options')) wp_die('Access denied');
    if (!bh_migration_is_allowed_host()) wp_die('Unapproved host');
    global $wpdb;
    echo '<div class="wrap"><h1>User Migration Pilot</h1><p>Create and link ONE verified legacy family or leader account at a time. Legacy logins, passwords, sessions and subscriptions are not modified.</p>';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s','bh_users')) !== 'bh_users') {
        echo '<p>Legacy users table not found.</p></div>'; return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bh_migrate_one'])) {
        check_admin_referer('bh_migrate_one');
        $id = absint($_POST['legacy_id'] ?? 0);
        $confirmation = sanitize_text_field(wp_unslash($_POST['confirmation'] ?? ''));
        if ($confirmation !== 'MIGRATE ONE LIVE USER' || !$id || empty($_POST['backup_confirmed'])) {
            echo '<div class="notice notice-error"><p>Exact confirmation phrase and valid ID required.</p></div>';
        } else {
            $user = $wpdb->get_row($wpdb->prepare('SELECT id,email,role FROM `bh_users` WHERE id=%d', $id), ARRAY_A);
            $role = $user ? strtolower((string)$user['role']) : '';
            $target = $role === 'family' ? 'bh_family' : ($role === 'leader' ? 'bh_leader' : '');
            $email = $user ? sanitize_email($user['email']) : '';
            $error = '';
            if (!$user || !$target || !get_role($target)) $error='Unsupported role or missing legacy account.';
            elseif (!is_email($email) || strcasecmp($email,trim((string)$user['email']))!==0) $error='Invalid email; manual review required.';
            elseif (email_exists($email)) $error='Email already exists in WordPress; manual identity verification required.';
            elseif ($wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s LIMIT 1",'bh_legacy_user_id',(string)$id))) $error='Legacy ID already linked.';
            elseif ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s','bh_user_subscriptions')) === 'bh_user_subscriptions') {
                $subscription = $wpdb->get_row($wpdb->prepare('SELECT plan,status FROM `bh_user_subscriptions` WHERE user_id=%d LIMIT 1',$id),ARRAY_A);
                if ($subscription && in_array($subscription['status'],['active','trialing'],true) && (($role==='leader' && $subscription['plan']!=='leader_pro') || ($role==='family' && $subscription['plan']!=='family_pro'))) $error='Pro subscription mismatch: manual review required.';
            }
            if ($error) {
                echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
            } else {
                $login = 'bh_legacy_'.$id;
                if (username_exists($login)) {
                    echo '<div class="notice notice-error"><p>Proposed login already exists; no changes made.</p></div>';
                } else {
                    $new_id = wp_insert_user([
                        'user_login'=>$login,
                        'user_email'=>$email,
                        'user_pass'=>wp_generate_password(40,true,true),
                        'role'=>$target,
                        'display_name'=>$login
                    ]);
                    if (is_wp_error($new_id)) {
                        echo '<div class="notice notice-error"><p>'.esc_html($new_id->get_error_message()).'</p></div>';
                    } else {
                        $saved = update_user_meta($new_id,'bh_legacy_user_id',(string)$id);
                        if (!$saved) {
                            require_once ABSPATH . 'wp-admin/includes/user.php';
                            wp_delete_user($new_id);
                            echo '<div class="notice notice-error"><p>Link failed; new WordPress user rolled back.</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>WordPress user '.esc_html((string)$new_id).' linked to legacy ID '.esc_html((string)$id).'. No password email sent. Review mapping and arrange verified password setup before login cutover.</p></div>';
                        }
                    }
                }
            }
        }
    }
    echo '<form method="post">';
    wp_nonce_field('bh_migrate_one');
    echo '<p><label>Legacy ID <input name="legacy_id" type="number" min="1" required></label></p>';
    echo '<p><label><input type="checkbox" name="backup_confirmed" value="1" required> I have backed up the current WordPress and Bubba Hub databases</label></p>';
    echo '<p><label>Type MIGRATE ONE LIVE USER <input name="confirmation" type="text" autocomplete="off" required></label></p>';
    submit_button('Create one WordPress user','secondary','bh_migrate_one');
    echo '</form><p><strong>Safety:</strong> Only approved Bubba Hub domains allow this page. Legacy admin accounts, existing WP emails and mismatched active Pro subscriptions are blocked. Back up the live databases first. Keep legacy login active until login bridging and rollback are tested.</p></div>';
}
