<?php
/**
 * Plugin Name: Bubba Hub Login Bridge Foundation
 * Description: Secure, opt-in WordPress identity resolution for future Bubba Hub API integration.
 */
if (!defined('ABSPATH')) exit;

/**
 * Resolve a WP session to an existing legacy account without changing sessions.
 * This helper is intentionally NOT wired into legacy API endpoints yet.
 */
function bh_wp_resolve_legacy_identity() {
    if (!is_user_logged_in()) return new WP_Error('not_authenticated', 'WordPress login required.');
    global $wpdb;
    $wp = wp_get_current_user();
    if (!$wp || !$wp->ID) return new WP_Error('not_authenticated', 'WordPress login required.');
    $linked = get_user_meta($wp->ID, 'bh_legacy_user_id', false);
    if (count($linked)!==1 || !ctype_digit((string)$linked[0]) || (int)$linked[0]<1)
        return new WP_Error('invalid_link','Exactly one valid legacy account link is required.');
    $id=(int)$linked[0];
    $links=$wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s",'bh_legacy_user_id',(string)$id));
    if (count($links)!==1 || (int)$links[0] !== (int)$wp->ID)
        return new WP_Error('duplicate_link','Legacy account is linked more than once.');
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s','bh_users'))!=='bh_users')
        return new WP_Error('legacy_unavailable','Legacy account data unavailable.');
    $cols=$wpdb->get_col('SHOW COLUMNS FROM `bh_users`',0);
    if (count(array_intersect(['id','email','role','status'], $cols))!==4)
        return new WP_Error('legacy_schema','Legacy account schema unavailable.');
    $legacy=$wpdb->get_row($wpdb->prepare('SELECT id,email,role,status FROM `bh_users` WHERE id=%d',$id),ARRAY_A);
    if (!$legacy || strtolower((string)$legacy['status'])!=='active')
        return new WP_Error('legacy_inactive','Legacy account is missing or inactive.');
    if (strcasecmp(trim((string)$legacy['email']),trim((string)$wp->user_email))!==0)
        return new WP_Error('email_mismatch','Account emails do not match.');
    $role=strtolower((string)$legacy['role']);
    $expected=$role==='family'?'bh_family':($role==='leader'?'bh_leader':'');
    if (!$expected || !in_array($expected,(array)$wp->roles,true))
        return new WP_Error('role_mismatch','Account role does not match.');
    return ['legacy_id'=>$id,'wordpress_id'=>(int)$wp->ID,'role'=>$role];
}
