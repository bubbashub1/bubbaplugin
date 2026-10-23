<?php
/**
 * Plugin Name: Bubba Hub Admin Dashboard
 * Description: WordPress-native control centre for Bubba Hub.
 * Version: 0.2.0
 */
if (!defined('ABSPATH')) exit;
function bh_admin_sections() {
 return [
 'overview'=>['Overview','dashicons-dashboard',[]],
 'directory'=>['Directory','dashicons-location-alt',[
  ['Find activities','/directory'],['Admin listings','/admin'],['Data imports','/admin']]],
 'leaders'=>['Class Leaders','dashicons-groups',[
  ['Leader portal','/leader'],['Organiser listings','/directory'],['Admin management','/admin']]],
 'families'=>['Families','dashicons-admin-users',[
  ['WordPress users',admin_url('users.php')],['Family accounts','/account'],['Family hub','/my-hub']]],
 'events'=>['Events & Expo','dashicons-calendar-alt',[
  ['Expo overview','/expo.html'],['Exhibitor applications','/expo-exhibitors.html'],['Exhibitor approvals','/admin/expo-exhibitor-approvals.html'],['Raffle prizes','/admin/expo-raffle-prizes.html'],['Ticketing','/events/raffle-entry/']]],
 'payments'=>['Payments','dashicons-money-alt',[
  ['Stripe dashboard','https://dashboard.stripe.com/'],['Forminator payments',admin_url('admin.php?page=forminator')],['Sponsorship form','/events/sponsor-bubba-hub/']]],
 'marketing'=>['Marketing','dashicons-megaphone',[
  ['Forminator submissions',admin_url('admin.php?page=forminator')],['WordPress posts',admin_url('edit.php')],['Sponsor form','/events/sponsor-bubba-hub/']]],
 'volunteers'=>['Volunteers','dashicons-heart',[
  ['WordPress users',admin_url('users.php')],['Regional activity directory','/directory']]],
 'website'=>['Website','dashicons-admin-site-alt3',[
  ['WordPress pages',admin_url('edit.php?post_type=page')],['Forminator forms',admin_url('admin.php?page=forminator')],['Media library',admin_url('upload.php')],['Appearance',admin_url('themes.php')]]],
 'settings'=>['Settings','dashicons-admin-settings',[
  ['WordPress settings',admin_url('options-general.php')],['Plugins',admin_url('plugins.php')],['Users and permissions',admin_url('users.php')]]]
 ];
}
add_action('admin_menu',function(){
 add_menu_page('Bubba Hub','Bubba Hub','manage_options','bubba-hub','bh_admin_dashboard_render','dashicons-heart',3);
 foreach(bh_admin_sections() as $slug=>$section){
  if($slug==='overview')continue;
  add_submenu_page('bubba-hub',$section[0],$section[0],'manage_options','bubba-hub-'.$slug,static function()use($slug){bh_admin_dashboard_render($slug);});
 }
},20);
add_action('admin_head',function(){
 if(!is_admin() || !isset($_GET['page']) || !is_string($_GET['page']) || !str_starts_with(sanitize_key($_GET['page']),'bubba-hub'))return;
 echo '<style>.notice:not(.bh-dashboard-notice),.update-nag{display:none!important}.bh-admin{max-width:1250px;color:#17513c}.bh-admin h1{font-size:32px;font-weight:800;margin:22px 0 8px}.bh-admin .bh-sub{font-size:15px;color:#567362}.bh-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:25px 0}.bh-admin-card{display:block;background:#fff;border:1px solid #cfe5d7;border-radius:18px;padding:22px;text-decoration:none;color:#17513c;box-shadow:0 3px 12px #12452c0b}.bh-admin-card:hover{border-color:#238d69;box-shadow:0 5px 16px #12452c1c}.bh-admin-card h2{color:#17513c;font-size:18px;margin:10px 0}.bh-admin-card p{color:#597266;line-height:1.5}.bh-admin-card .dashicons{color:#238d69;font-size:26px;width:28px;height:28px}.bh-admin-stat{background:#e8f5ec;border-radius:14px;padding:18px}.bh-admin-stat strong{font-size:28px;display:block}.bh-admin-banner{background:#e7f4e9;border-left:4px solid #238d69;padding:14px 18px;border-radius:8px;margin:18px 0}</style>';
});
function bh_admin_dashboard_render($selected='overview'){
 if(!current_user_can('manage_options'))wp_die('Access denied');
 $sections=bh_admin_sections();
 if(!isset($sections[$selected]))$selected='overview';
 echo '<div class="wrap bh-admin"><h1>🌿 Bubba Hub '.esc_html($sections[$selected][0]).'</h1><p class="bh-sub">Your WordPress control centre for Bubba Hub South West</p>';
 if($selected==='overview'){
   $users=count_users();
   $pages=wp_count_posts('page');
   $posts=wp_count_posts('post');
   $form_count='—';
   echo '<div class="bh-admin-grid">';
   foreach([['WordPress users',(string)($users['total_users']??0)],['Published pages',(string)($pages->publish??0)],['Published posts',(string)($posts->publish??0)],['Expo date','9 Jan 2027']] as $stat){
    echo '<div class="bh-admin-stat"><span>'.esc_html($stat[0]).'</span><strong>'.esc_html($stat[1]).'</strong></div>';
   }
   echo '</div><div class="bh-admin-grid">';
   foreach($sections as $slug=>$section){if($slug==='overview')continue;
    echo '<a class="bh-admin-card" href="'.esc_url(admin_url('admin.php?page=bubba-hub-'.$slug)).'"><span class="dashicons '.esc_attr($section[1]).'"></span><h2>'.esc_html($section[0]).'</h2><p>Open '.esc_html(strtolower($section[0])).' management</p></a>';
   }
   echo '</div>';
 } else {
   echo '<div class="bh-admin-grid">';
   foreach($sections[$selected][2] as $item){
    $url=preg_match('~^https?://~',$item[1])?$item[1]:(str_starts_with($item[1],'/')?home_url($item[1]):$item[1]);
    echo '<a class="bh-admin-card" href="'.esc_url($url).'"><span class="dashicons '.esc_attr($sections[$selected][1]).'"></span><h2>'.esc_html($item[0]).'</h2><p>Open this management tool →</p></a>';
   }
   echo '</div>';
 }
 echo '<div class="bh-admin-banner"><strong>Hybrid integration in progress:</strong> WordPress counts above are live WordPress data. Custom Bubba Hub PHP tools may still require their separate admin login. Directory, ticket, payment and exhibitor totals will be connected through secured read-only integrations before being displayed here.</div></div>';
}
