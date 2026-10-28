<?php
/**
 * Plugin Name: Bubba Hub Manager
 * Description: Read-only WordPress management screens for the existing Bubba Hub activities, venues and sessions.
 * Version: 0.1.3
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
    private static function listing(string $type, string $label): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>'.esc_html($label).'</h1><p>Read-only preview. Editing is disabled until relationships and permissions are verified.</p>';
        $db=self::connection();
        $tables=['activities'=>'bh_activities','venues'=>'bh_venues','sessions'=>'bh_sessions'];
        $table=$tables[$type];
        try {
            $cols=$db->get_col('SHOW COLUMNS FROM `'.$table.'`', 0);
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
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) { echo '<tr>'; foreach ($selected as $col) echo '<td>'.esc_html((string)($row[$col]??'')).'</td>'; echo '</tr>'; }
            if (!$rows) echo '<tr><td colspan="'.count($selected).'">No records found.</td></tr>';
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
