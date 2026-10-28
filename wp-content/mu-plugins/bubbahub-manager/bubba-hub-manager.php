<?php
/**
 * Plugin Name: Bubba Hub Manager
 * Description: Read-only WordPress management screens for the existing Bubba Hub activities, venues and sessions.
 * Version: 0.2.0
 * Requires PHP: 8.0
 * Text Domain: bubba-hub-manager
 */
if (!defined('ABSPATH')) exit;
final class Bubba_Hub_Manager {
    private const CAP = 'manage_options';
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 40);
    }
    public static function menu(): void {
        $parent = function_exists('bh_admin_sections') ? 'bubba-hub' : 'bh-manager';
        if ($parent === 'bh-manager') add_menu_page('Bubba Hub Manager', 'Bubba Hub', self::CAP, 'bh-manager', [__CLASS__, 'overview'], 'dashicons-location-alt', 26);
        foreach (['activities'=>'Activities','venues'=>'Venues','sessions'=>'Sessions'] as $slug=>$label) {
            add_submenu_page($parent, $label, $label, self::CAP, 'bh-manager-'.$slug, static function () use ($slug, $label) { self::listing($slug, $label); });
        }
    }
    private static function connection(): wpdb {
        global $wpdb;
        return $wpdb;
    }
    public static function overview(): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>Bubba Hub Manager</h1><p>Read-only preview. No existing Bubba Hub records are modified.</p><p>Activities, venues and sessions use the existing Bubba Hub database. Uses the existing WordPress database connection.</p></div>';
    }
    private static function edit_activity(array $columns): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        $db=self::connection();
        $id=isset($_GET['activity_id'])?absint($_GET['activity_id']):0;
        if (!$id) { echo '<div class="notice notice-error"><p>Invalid activity ID.</p></div>'; return; }
        $record=$db->get_row($db->prepare('SELECT * FROM `bh_activities` WHERE id = %d LIMIT 1',$id),ARRAY_A);
        if (!$record) { echo '<div class="notice notice-error"><p>Activity not found.</p></div>'; return; }
        $fields=array_values(array_intersect(['title','description','good_to_know','what_to_bring'], $columns));
        if (isset($_POST['bh_manager_save'])) {
            check_admin_referer('bh_manager_activity_'.$id);
            $changes=[];
            foreach ($fields as $field) {
                if (!isset($_POST[$field]) || !is_string($_POST[$field])) continue;
                $raw=wp_unslash($_POST[$field]);
                $changes[$field]=$field==='title'?sanitize_text_field($raw):wp_kses_post($raw);
            }
            if (isset($changes['title']) && trim($changes['title'])==='') {
                echo '<div class="notice notice-error"><p>Title cannot be blank.</p></div>';
            } elseif ($changes) {
                $result=$db->update('bh_activities',$changes,['id'=>$id]);
                if ($result===false) echo '<div class="notice notice-error"><p>Unable to save activity details.</p></div>';
                else {
                    echo '<div class="notice notice-success"><p>Activity details saved.</p></div>';
                    $record=$db->get_row($db->prepare('SELECT * FROM `bh_activities` WHERE id = %d LIMIT 1',$id),ARRAY_A);
                }
            }
        }
        echo '<p><a href="'.esc_url(admin_url('admin.php?page=bh-manager-activities')).'">&larr; Back to Activities</a></p>';
        echo '<h2>Edit activity #'.esc_html((string)$id).'</h2>';
        echo '<div class="notice notice-info inline"><p>This first editing stage changes only basic activity text. Venues, schedules, sessions, images, organisers and publication status are untouched.</p></div>';
        echo '<form method="post">';
        wp_nonce_field('bh_manager_activity_'.$id);
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $field) {
            $label=ucwords(str_replace('_',' ',$field));
            echo '<tr><th scope="row"><label for="bh-'.esc_attr($field).'">'.esc_html($label).'</label></th><td>';
            if ($field==='title') echo '<input required class="regular-text" id="bh-title" name="title" value="'.esc_attr((string)($record[$field]??'')).'"/>';
            else echo '<textarea class="large-text" rows="7" id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'">'.esc_textarea((string)($record[$field]??'')).'</textarea>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button('Save activity details','primary','bh_manager_save');
        echo '</form>';
    }
    private static function listing(string $type, string $label): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>'.esc_html($label).'</h1><p>Read-only preview. Editing is disabled until relationships and permissions are verified.</p>';
        $db=self::connection();
        $tables=['activities'=>'bh_activities','venues'=>'bh_venues','sessions'=>'bh_sessions'];
        $table=$tables[$type];
        try {
            $cols=$db->get_col('SHOW COLUMNS FROM `'.$table.'`', 0);
            if ($type==='activities' && isset($_GET['activity_id'])) { self::edit_activity($cols); echo '</div>'; return; }
            $wanted=['id','title','venue_name','activity_id','venue_id','day_of_week','start_time','end_time','frequency','status','town','region'];
            $selected=array_values(array_intersect($wanted,$cols));
            if (!$selected) throw new RuntimeException('No supported columns found.');
            $page=max(1,isset($_GET['paged'])?absint($_GET['paged']):1);
            $search=isset($_GET['s']) && is_string($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            $search_cols=array_values(array_intersect(['title','venue_name','name','city','region','postcode'],$cols));
            $where=''; $params=[];
            if ($search!=='' && $search_cols) {
                $where=' WHERE ('.implode(' OR ',array_map(static fn($c)=>'`'.$c.'` LIKE %s',$search_cols)).')';
                $params=array_fill(0,count($search_cols),'%'.$db->esc_like($search).'%');
            }
            $count_sql='SELECT COUNT(*) FROM `'.$table.'`'.$where;
            $total=(int)$db->get_var($params?$db->prepare($count_sql,$params):$count_sql);
            $order=in_array('id',$selected,true)?' ORDER BY `id` DESC':'';
            $sql='SELECT '.implode(',',array_map(static fn($col)=>'`'.$col.'`',$selected)).' FROM `'.$table.'`'.$where.$order.' LIMIT %d OFFSET %d';
            $rows=$db->get_results($db->prepare($sql,array_merge($params,[30,($page-1)*30])), ARRAY_A);
            echo '<form method="get"><input type="hidden" name="page" value="'.esc_attr('bh-manager-'.$type).'"/><p class="search-box"><input type="search" name="s" aria-label="Search '.esc_attr($label).'" value="'.esc_attr($search).'"/> <input type="submit" class="button" value="Search"/></p></form>';
            echo '<p>'.esc_html(number_format_i18n($total)).' records found. Showing up to 30 per page.</p><table class="widefat striped"><thead><tr>';
            foreach ($selected as $col) echo '<th>'.esc_html($col).'</th>';
            if ($type==='activities') echo '<th>Actions</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) { echo '<tr>'; foreach ($selected as $col) echo '<td>'.esc_html((string)($row[$col]??'')).'</td>'; if ($type==='activities') echo '<td><a href="'.esc_url(add_query_arg(['page'=>'bh-manager-activities','activity_id'=>(int)$row['id']],admin_url('admin.php'))).'">Edit details</a></td>'; echo '</tr>'; }
            if (!$rows) echo '<tr><td colspan="'.(count($selected)+($type==='activities'?1:0)).'">No records found.</td></tr>';
            echo '</tbody></table>';
            $pages=(int)ceil($total/30);
            if ($pages>1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post(paginate_links(['base'=>add_query_arg(['page'=>'bh-manager-'.$type,'s'=>$search,'paged'=>'%#%'],admin_url('admin.php')),'current'=>$page,'total'=>$pages]));
                echo '</div></div>';
            }
        } catch (Throwable $e) {
            error_log('Bubba Hub Manager read error: '.$e->getMessage());
            echo '<div class="notice notice-error"><p>Unable to read '.esc_html($label).'. Check the PHP error log and table permissions.</p></div>';
        }
        echo '</div>';
    }
}
Bubba_Hub_Manager::init();
