<?php
/**
 * Plugin Name: Bubba Hub Admin Dashboard
 * Description: Phase 1: WordPress-native navigation hub for Bubba Hub and Expo management.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function () {
    add_menu_page('Bubba Hub', 'Bubba Hub', 'manage_options', 'bubba-hub', 'bh_admin_dashboard_render', 'dashicons-heart', 3);
}, 20);
function bh_admin_dashboard_render() {
    if (!current_user_can('manage_options')) wp_die(esc_html__('You do not have permission to access this page.', 'bubba-hub'));
    $items = [
      ['Exhibitor applications', 'Review exhibitor applications', home_url('/expo-exhibitors.html'), 'dashicons-store'],
      ['Forminator forms', 'Edit Expo forms and view submissions', admin_url('admin.php?page=forminator'), 'dashicons-feedback'],
      ['Raffle prize manager', 'Manage prize approvals and the public carousel', home_url('/admin/expo-raffle-prizes.html'), 'dashicons-awards'],
      ['Expo exhibitor approvals', 'Existing approval workspace (separate admin sign-in may be required)', home_url('/admin/expo-exhibitor-approvals.html'), 'dashicons-yes-alt'],
      ['Expo page', 'View the public Expo page', home_url('/expo.html'), 'dashicons-calendar-alt'],
      ['Stripe dashboard', 'Payments and donation reporting', 'https://dashboard.stripe.com/', 'dashicons-money-alt'],
    ];
    echo '<div class="wrap bh-wp-dashboard"><h1>Bubba Hub <span class="bh-wp-muted">Admin Dashboard</span></h1><p>Manage Expo forms and existing Bubba Hub tools from one place. Existing PHP admin tools retain their separate authentication.</p>';
    echo '<style>.bh-wp-dashboard{font-family:system-ui,sans-serif;color:#18503d}.bh-wp-dashboard h1{font-size:30px;font-weight:800;margin:26px 0 12px}.bh-wp-muted{color:#69877a;font-size:17px}.bh-wp-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;max-width:1100px;margin:24px 0}.bh-wp-card{background:#fff;border:1px solid #d4e8db;border-radius:18px;padding:23px;text-decoration:none;color:#18503d;box-shadow:0 4px 14px #173e2a0a}.bh-wp-card:hover{border-color:#55b58b;box-shadow:0 7px 18px #173e2a17}.bh-wp-card .dashicons{color:#238c70;font-size:28px;width:30px;height:30px}.bh-wp-card h2{font-size:18px;color:#18503d;margin:14px 0 8px}.bh-wp-card p{color:#526c5e;margin:0;line-height:1.5}.bh-wp-note{background:#eaf6ee;border-left:4px solid #278b64;padding:13px 18px;max-width:1040px;border-radius:6px}</style>';
    echo '<div class="bh-wp-cards">';
    foreach ($items as $item) {
       echo '<a class="bh-wp-card" href="'.esc_url($item[2]).'"'.(str_starts_with($item[2], 'https://dashboard.stripe.com/')?' target="_blank" rel="noopener noreferrer"':'').'><span class="dashicons '.esc_attr($item[3]).'" aria-hidden="true"></span><h2>'.esc_html($item[0]).'</h2><p>'.esc_html($item[1]).'</p></a>';
    }
    echo '</div><p class="bh-wp-note">Phase 1 is a navigation dashboard only. It does not change payments, application approval states, or permissions in existing Bubba Hub PHP tools.</p></div>';
}
