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
  ['User migration audit',admin_url('admin.php?page=bubba-hub-user-audit')],['WordPress users',admin_url('users.php')],['Family accounts','/account'],['Family hub','/my-hub']]],
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

/**
 * Read-only user migration preflight. No user data, password hashes, or
 * email addresses are output. WordPress DB may differ from custom bh DB.
 */
add_action('admin_menu',function(){
 add_submenu_page('bubba-hub','User Migration Audit','User Migration Audit','manage_options','bubba-hub-user-audit','bh_user_migration_audit_render');
},30);
function bh_user_migration_audit_render(){
 if(!current_user_can('manage_options'))wp_die('Access denied');
 global $wpdb;
 echo '<div class="wrap bh-admin"><h1>WordPress User Migration Audit</h1><p class="bh-sub">Read-only checks. No accounts will be created, changed, merged or deleted.</p>';
 $table='bh_users';
 $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));
 if($exists!==$table){
  echo '<div class="bh-admin-banner">The custom bh_users table is not accessible through the WordPress database connection. It may live in a separate database. A secure, read-only connection will be required before migration.</div>';
 } else {
  $columns=$wpdb->get_col("SHOW COLUMNS FROM `bh_users`",0);
  if(in_array('id',$columns,true)&&in_array('email',$columns,true)){
   $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM `bh_users`');
   $matching=(int)$wpdb->get_var("SELECT COUNT(*) FROM `bh_users` b INNER JOIN {$wpdb->users} w ON LOWER(TRIM(b.email))=LOWER(TRIM(w.user_email))");
   echo '<div class="bh-admin-grid"><div class="bh-admin-stat"><span>Legacy Bubba Hub accounts</span><strong>'.esc_html((string)$total).'</strong></div><div class="bh-admin-stat"><span>WordPress email matches</span><strong>'.esc_html((string)$matching).'</strong></div></div>';
   if(in_array('role',$columns,true)){
    $roles=$wpdb->get_results('SELECT role,COUNT(*) AS total FROM `bh_users` GROUP BY role ORDER BY role',ARRAY_A);
    echo '<h2>Legacy roles</h2><table class="widefat striped"><thead><tr><th>Role</th><th>Accounts</th></tr></thead><tbody>';
    foreach($roles as $r)echo '<tr><td>'.esc_html((string)$r['role']).'</td><td>'.esc_html((string)$r['total']).'</td></tr>';
    echo '</tbody></table>';
   }
  }else echo '<div class="bh-admin-banner">Legacy table exists but required id/email columns were not found. No migration attempted.</div>';
 }
 echo '<div class="bh-admin-banner">Migration gate: back up both databases, validate email collisions and linked records, add a stable WordPress-to-Bubba user mapping, then test sign-in and password reset on staging. Do not delete bh_users or copy password hashes directly.</div></div>';
}

/* Account migration phase 2: role registration and read-only preview. */
add_action('init',function(){
 foreach(['bh_family'=>'Bubba Hub Family','bh_leader'=>'Bubba Hub Class Leader','bh_volunteer'=>'Bubba Hub Volunteer','bh_region_coordinator'=>'Bubba Hub Regional Coordinator'] as $key=>$label){
  if(!get_role($key))add_role($key,$label,['read'=>true]);
 }
});
add_action('admin_menu',function(){
 add_submenu_page('bubba-hub','Account Migration Preview','Account Migration Preview','manage_options','bubba-hub-user-preview','bh_wp_user_preview_render');
},31);
function bh_wp_user_preview_render(){
 if(!current_user_can('manage_options'))wp_die('Access denied');
 global $wpdb;
 echo '<div class="wrap bh-admin"><h1>Account Migration Preview</h1><p class="bh-sub">Read-only account, role and Pro membership review. No WordPress users are created.</p>';
 $table=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s','bh_users'));
 if($table!=='bh_users'){echo '<div class="bh-admin-banner">Legacy users table is not accessible.</div></div>';return;}
 $columns=$wpdb->get_col('SHOW COLUMNS FROM `bh_users`',0);
 if(!in_array('id',$columns,true)||!in_array('email',$columns,true)||!in_array('role',$columns,true)){echo '<div class="bh-admin-banner">Unexpected legacy schema. Preview unavailable.</div></div>';return;}
 $users=$wpdb->get_results('SELECT id,email,role FROM `bh_users` ORDER BY id LIMIT 500',ARRAY_A);
 $subs_exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s','bh_user_subscriptions'))==='bh_user_subscriptions';
 $subcols=$subs_exists?$wpdb->get_col('SHOW COLUMNS FROM `bh_user_subscriptions`',0):[];
 $subscriptions=[];
 if($subs_exists && count(array_intersect(['user_id','plan','status'], $subcols))===3){
  $rows=$wpdb->get_results('SELECT user_id,plan,status FROM `bh_user_subscriptions`',ARRAY_A);
  foreach($rows as $row)$subscriptions[(int)$row['user_id']]=$row;
 }
 $counts=['new'=>0,'match'=>0,'family'=>0,'leader'=>0,'admin'=>0,'active_pro'=>0,'other_subscription'=>0];
 echo '<table class="widefat striped"><thead><tr><th>Legacy ID</th><th>Role</th><th>WordPress role proposed</th><th>WP account</th><th>Subscription plan</th><th>Subscription status</th></tr></thead><tbody>';
 foreach($users as $u){
  $role=strtolower((string)$u['role']);$counts[$role]=($counts[$role]??0)+1;
  $target=$role==='family'?'bh_family':($role==='leader'?'bh_leader':'Manual review required');
  $wp=get_user_by('email',trim((string)$u['email']));
  $counts[$wp?'match':'new']++;
  $sub=$subscriptions[(int)$u['id']]??null;
  $plan=$sub?(string)$sub['plan']:'No subscription record';
  $status=$sub?(string)$sub['status']:'—';
  if($sub && in_array($status,['active','trialing'],true) && in_array($plan,['family_pro','leader_pro'],true))$counts['active_pro']++;
  if($sub && !in_array($status,['active','trialing'],true))$counts['other_subscription']++;
  echo '<tr><td>'.esc_html((string)$u['id']).'</td><td>'.esc_html($role).'</td><td>'.esc_html($target).'</td><td>'.esc_html($wp?'Existing match — verify':'Would create').'</td><td>'.esc_html($plan).'</td><td>'.esc_html($status).'</td></tr>';
 }
 echo '</tbody></table><div class="bh-admin-grid">';
 foreach([['Legacy accounts',count($users)],['New WP accounts proposed',$counts['new']],['Existing WP email matches',$counts['match']],['Active or trialing Pro subscriptions',$counts['active_pro']]] as $stat)echo '<div class="bh-admin-stat"><span>'.esc_html($stat[0]).'</span><strong>'.esc_html((string)$stat[1]).'</strong></div>';
 echo '</div>';
 if(!$subs_exists)echo '<div class="bh-admin-banner">bh_user_subscriptions not found: Pro status cannot yet be verified. Do not migrate membership entitlements until the subscription source is confirmed.</div>';
 echo '<div class="bh-admin-banner">Pro is an entitlement, not a WordPress role. The existing Stripe webhook and bh_user_subscriptions remain authoritative. Legacy admins require manual approval; no automatic WordPress administrator promotion. Back up databases and test on staging before any migration write.</div></div>';
}
