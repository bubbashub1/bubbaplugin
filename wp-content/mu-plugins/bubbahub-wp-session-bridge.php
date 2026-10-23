<?php
/**
 * Plugin Name: Bubba Hub WordPress Session Bridge
 * Description: Opt-in, nonce-protected session bridge for verified WordPress users.
 */
if (!defined('ABSPATH')) exit;

/**
 * Bridge is explicitly requested by a logged-in WordPress user.
 * It does not authenticate from a browser-supplied legacy ID.
 */
add_action('wp_ajax_bh_start_legacy_session', function () {
    if (!check_ajax_referer('bh_start_legacy_session', '_ajax_nonce', false)) {
        wp_send_json_error(['error'=>'invalid_nonce'],403);
    }
    if (!function_exists('bh_wp_resolve_legacy_identity')) {
        wp_send_json_error(['error'=>'resolver_unavailable'],503);
    }
    $identity=bh_wp_resolve_legacy_identity();
    if (is_wp_error($identity)) {
        wp_send_json_error(['error'=>$identity->get_error_code()],403);
    }
    if (!in_array($identity['role'],['family','leader'],true)) {
        wp_send_json_error(['error'=>'unsupported_role'],403);
    }
    $secure=is_ssl();
    if (session_status()!==PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode','1');
        ini_set('session.use_only_cookies','1');
        session_name('BUBBAHUBSESSID');
        session_set_cookie_params([
            'lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'
        ]);
        if (!session_start()) wp_send_json_error(['error'=>'session_unavailable'],503);
    }
    session_regenerate_id(true);
    $_SESSION['bh_user_id']=(int)$identity['legacy_id'];
    unset($_SESSION['bh_family_authenticated'],$_SESSION['bh_leader_authenticated'],$_SESSION['bh_admin_authenticated']);
    if ($identity['role']==='leader') $_SESSION['bh_leader_authenticated']=true;
    else $_SESSION['bh_family_authenticated']=true;
    $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
    session_write_close();
    nocache_headers();
    wp_send_json_success(['linked'=>true,'role'=>$identity['role']]);
});
/**
 * Shortcode for testing the bridge only after WordPress password setup.
 * Does not expose user identifiers or session tokens to JavaScript.
 */
add_shortcode('bubbahub_wp_session_bridge', function () {
    if (!is_user_logged_in()) return '<p>Please sign in to WordPress first.</p>';
    $nonce=wp_create_nonce('bh_start_legacy_session');
    $url=admin_url('admin-ajax.php');
    $id='bh-bridge-'.wp_rand(10000,99999);
    return '<button type="button" id="'.esc_attr($id).'">Connect my Bubba Hub account</button><span id="'.esc_attr($id.'-result').'" role="status"></span>'.
        '<script>(function(){const b=document.getElementById('.wp_json_encode($id).');b.addEventListener("click",async function(){b.disabled=true;const output=document.getElementById('.wp_json_encode($id.'-result').');try{const r=await fetch('.wp_json_encode($url).',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"bh_start_legacy_session",_ajax_nonce:'.wp_json_encode($nonce).'})});const j=await r.json();output.textContent=j.success?" Account connected. You can now open My Hub or your Class Leader portal.":" Could not connect this account."; }catch(e){output.textContent=" Connection failed.";}finally{b.disabled=false;}})})()</script>';
});

/**
 * Clear the separate Bubba Hub session when a WordPress user signs out.
 * WordPress logout does not normally invalidate a PHP application session.
 */
add_action('wp_logout', function () {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        session_name('BUBBAHUBSESSID');
        if (!session_start()) return;
    }
    unset($_SESSION['bh_user_id'], $_SESSION['bh_family_authenticated'],
          $_SESSION['bh_leader_authenticated'], $_SESSION['bh_admin_authenticated'],
          $_SESSION['bh_csrf']);
    session_regenerate_id(true);
    session_write_close();
}, 10);
