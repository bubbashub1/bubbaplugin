<?php
/**
 * Plugin Name: Bubba Hub Manager
 * Description: WordPress management for existing Bubba Hub records.
 * Version: 0.2.0
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;
if (class_exists('Bubba_Hub_Manager', false)) return;
final class Bubba_Hub_Manager {
    private const CAP = 'manage_options';
    private const TABLES = ['activities'=>'bh_activities','venues'=>'bh_venues','sessions'=>'bh_sessions'];
    public static function init(): void { add_action('admin_menu', [__CLASS__, 'menu'], 40); }
    public static function menu(): void {
        $parent = function_exists('bh_admin_sections') ? 'bubba-hub' : 'bh-manager';
        if ($parent === 'bh-manager') add_menu_page('Bubba Hub Manager','Bubba Hub',self::CAP,$parent,[__CLASS__,'overview'],'dashicons-location-alt',26);
        foreach (['activities'=>'Activities','venues'=>'Venues','sessions'=>'Sessions'] as $slug=>$label)
            add_submenu_page($parent,$label,$label,self::CAP,'bh-manager-'.$slug,static function()use($slug,$label){self::listing($slug,$label);});
    }
    private static function db(): wpdb { global $wpdb; return $wpdb; }
    private static function columns(string $table): array {
        return array_map('strval', self::db()->get_col('SHOW COLUMNS FROM `'.$table.'`',0) ?: []);
    }
    private static function url(string $type, array $args=[]): string {
        return add_query_arg(array_merge(['page'=>'bh-manager-'.$type],$args),admin_url('admin.php'));
    }
    public static function overview(): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>Bubba Hub Manager</h1><p>Manage existing activities. Venues and sessions remain read-only while relationships are verified.</p></div>';
    }
    private static function edit_activity(array $cols): void {
        $db=self::db(); $id=isset($_GET['activity_id'])?absint($_GET['activity_id']):0;
        if (!$id) { echo '<div class="notice notice-error"><p>Invalid activity ID.</p></div>'; return; }
        $table=self::TABLES['activities'];
        $record=$db->get_row($db->prepare('SELECT * FROM `bh_activities` WHERE id=%d LIMIT 1',$id),ARRAY_A);
        if (!$record) { echo '<div class="notice notice-error"><p>Activity not found.</p></div>'; return; }
        $editable=array_values(array_intersect(['title','description','good_to_know','what_to_bring'], $cols));
        if (isset($_POST['bh_manager_save']) && is_array($_POST)) {
            check_admin_referer('bh_manager_edit_'.$id);
            if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
            $data=[];
            foreach ($editable as $field) {
                if (!isset($_POST[$field]) || !is_string($_POST[$field])) continue;
                $value=wp_unslash($_POST[$field]);
                $data[$field]=$field==='title'?sanitize_text_field($value):wp_kses_post($value);
            }
            if (isset($data['title']) && trim($data['title'])==='') {
                echo '<div class="notice notice-error"><p>Title cannot be empty.</p></div>';
            } elseif ($data) {
                $ok=$db->update($table,$data,['id'=>$id]);
                if ($ok===false) echo '<div class="notice notice-error"><p>Save failed. No timetable or venue changes were made.</p></div>';
                else {
                    echo '<div class="notice notice-success"><p>Activity details saved.</p></div>';
                    $record=$db->get_row($db->prepare('SELECT * FROM `bh_activities` WHERE id=%d LIMIT 1',$id),ARRAY_A);
                }
            }
        }
        echo '<p><a href="'.esc_url(self::url('activities')).'">&larr; Back to activities</a></p>';
        echo '<h2>Edit activity #'.esc_html((string)$id).'</h2>';
        echo '<div class="notice notice-info inline"><p>Only basic content fields are editable here. Images, organisers, venues, sessions and schedules are not changed by this form.</p></div>';
        echo '<form method="post">';
        wp_nonce_field('bh_manager_edit_'.$id);
        echo '<table class="form-table"><tbody>';
        foreach ($editable as $field) {
            echo '<tr><th scope="row"><label for="bh-'.esc_attr($field).'">'.esc_html(ucwords(str_replace('_',' ',$field))).'</label></th><td>';
            if ($field==='title') echo '<input class="regular-text" required id="bh-title" name="title" value="'.esc_attr((string)($record[$field]??'')).'"/>';
            else echo '<textarea class="large-text" rows="8" id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'">'.esc_textarea((string)($record[$field]??'')).'</textarea>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button('Save activity details','primary','bh_manager_save');
        echo '</form>';
    }
    private static function listing(string $type,string $label): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        $db=self::db(); $table=self::TABLES[$type]; $cols=self::columns($table);
        echo '<div class="wrap"><h1>'.esc_html($label).'</h1>';
        if (!$cols) { echo '<div class="notice notice-error"><p>Required table '.esc_html($table).' not found.</p></div></div>'; return; }
        if ($type==='activities' && isset($_GET['activity_id'])) { self::edit_activity($cols); echo '</div>'; return; }
        if ($type!=='activities') echo '<div class="notice notice-info inline"><p>Read-only: venue and session relationships are being audited before editing is enabled.</p></div>';
        $wanted=['id','title','venue_name','name','activity_id','venue_id','day_of_week','start_time','end_time','frequency','status','city','region','postcode'];
        $selected=array_values(array_intersect($wanted,$cols));
        if (!$selected) $selected=array_slice($cols,0,6);
        $search=isset($_GET['s']) && is_string($_GET['s'])?sanitize_text_field(wp_unslash($_GET['s'])):'';
        $searchable=array_values(array_intersect(['title','venue_name','name','city','region','postcode'],$cols));
        $where=''; $params=[];
        if ($search!=='' && $searchable) {
            $where=' WHERE ('.implode(' OR ',array_map(static fn($c)=>'`'.$c.'` LIKE %s',$searchable)).')';
            $params=array_fill(0,count($searchable),'%'.$db->esc_like($search).'%');
        }
        $page=max(1,isset($_GET['paged'])?absint($_GET['paged']):1);
        $limit=30;
        $countSql='SELECT COUNT(*) FROM `'.$table.'`'.$where;
        $count=(int)$db->get_var($params?$db->prepare($countSql,$params):$countSql);
        $sql='SELECT '.implode(',',array_map(static fn($c)=>'`'.$c.'`',$selected)).' FROM `'.$table.'`'.$where.(in_array('id',$cols,true)?' ORDER BY `id` DESC':'').' LIMIT %d OFFSET %d';
        $rows=$db->get_results($db->prepare($sql,array_merge($params,[$limit,($page-1)*$limit])),ARRAY_A) ?: [];
        echo '<form method="get"><input type="hidden" name="page" value="'.esc_attr('bh-manager-'.$type).'"/><p class="search-box"><input aria-label="Search '.esc_attr($label).'" type="search" name="s" value="'.esc_attr($search).'"/> <input type="submit" class="button" value="Search"/></p></form>';
        echo '<p>'.esc_html(number_format_i18n($count)).' matching records; '.esc_html((string)count($rows)).' shown on this page.</p>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach ($selected as $col) echo '<th scope="col">'.esc_html(ucwords(str_replace('_',' ',$col))).'</th>';
        if ($type==='activities') echo '<th scope="col">Action</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($selected as $col) echo '<td>'.esc_html(wp_html_excerpt((string)($row[$col]??''),180,'…')).'</td>';
            if ($type==='activities') echo '<td><a href="'.esc_url(self::url('activities',['activity_id'=>(int)$row['id']])).'">Edit details</a></td>';
            echo '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="'.(count($selected)+($type==='activities'?1:0)).'">No records found.</td></tr>';
        echo '</tbody></table>';
        $pages=max(1,(int)ceil($count/$limit));
        if ($pages>1) echo '<div class="tablenav"><div class="tablenav-pages">'.wp_kses_post(paginate_links(['base'=>self::url($type,['s'=>$search,'paged'=>'%#%']),'format'=>'','current'=>$page,'total'=>$pages])).'</div></div>';
        echo '</div>';
    }
}
Bubba_Hub_Manager::init();
