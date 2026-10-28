<?php
/**
 * Plugin Name: Bubba Hub Manager
 * Description: Read-only WordPress management screens for the existing Bubba Hub activities, venues and sessions.
 * Version: 0.1.0
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
    private static function connection(): ?PDO {
        if (!class_exists('PDO') || !defined('BH_MANAGER_DB_HOST') || !defined('BH_MANAGER_DB_NAME') || !defined('BH_MANAGER_DB_USER') || !defined('BH_MANAGER_DB_PASSWORD')) return null;
        try {
            return new PDO('mysql:host='.BH_MANAGER_DB_HOST.';dbname='.BH_MANAGER_DB_NAME.';charset=utf8mb4', BH_MANAGER_DB_USER, BH_MANAGER_DB_PASSWORD, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        } catch (Throwable $e) { error_log('Bubba Hub Manager DB connection failed: '.$e->getMessage()); return null; }
    }
    public static function overview(): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>Bubba Hub Manager</h1><p>Read-only preview. No existing Bubba Hub records are modified.</p><p>Activities, venues and sessions use the existing Bubba Hub database. Configure database constants to view data.</p></div>';
    }
    private static function listing(string $type, string $label): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>'.esc_html($label).'</h1><p>Read-only preview. Editing is disabled until relationships and permissions are verified.</p>';
        $db=self::connection();
        if (!$db) { echo '<div class="notice notice-warning"><p>Database connection is not configured. Set BH_MANAGER_DB_HOST, BH_MANAGER_DB_NAME, BH_MANAGER_DB_USER and BH_MANAGER_DB_PASSWORD in wp-config.php (not in this plugin).</p></div></div>'; return; }
        $tables=['activities'=>'bh_activities','venues'=>'bh_venues','sessions'=>'bh_sessions'];
        $table=$tables[$type];
        try {
            $cols=$db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
            $wanted=['id','title','venue_name','activity_id','venue_id','day_of_week','start_time','end_time','frequency','status','town','region'];
            $selected=array_values(array_intersect($wanted,$cols));
            if (!$selected) throw new RuntimeException('No supported columns found.');
            $order=in_array('id',$selected,true)?' ORDER BY `id` DESC':'';
            $sql='SELECT '.implode(',',array_map(static fn($col)=>'`'.$col.'`',$selected)).' FROM `'.$table.'`'.$order.' LIMIT 100';
            $rows=$db->query($sql)->fetchAll();
            echo '<p>Showing up to 100 records. Session and venue relationships are not yet resolved.</p><table class="widefat striped"><thead><tr>';
            foreach ($selected as $col) echo '<th>'.esc_html($col).'</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) { echo '<tr>'; foreach ($selected as $col) echo '<td>'.esc_html((string)($row[$col]??'')).'</td>'; echo '</tr>'; }
            if (!$rows) echo '<tr><td colspan="'.count($selected).'">No records found.</td></tr>';
            echo '</tbody></table>';
        } catch (Throwable $e) {
            error_log('Bubba Hub Manager read error: '.$e->getMessage());
            echo '<div class="notice notice-error"><p>Unable to read '.esc_html($label).'. Check the PHP error log and table permissions.</p></div>';
        }
        echo '</div>';
    }
}
Bubba_Hub_Manager::init();
