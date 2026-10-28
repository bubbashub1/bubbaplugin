<?php
/**
 * Plugin Name: Bubba Hub Admin Menu Organiser
 * Description: Groups diagnostic and audit screens under one Bubba Hub submenu.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', static function (): void {
    if (!current_user_can('manage_options')) return;
    global $submenu;
    if (empty($submenu['bubba-hub']) || !is_array($submenu['bubba-hub'])) return;
    $audit_items = [];
    $keep = [];
    foreach ($submenu['bubba-hub'] as $item) {
        $label = wp_strip_all_tags((string)($item[0] ?? ''));
        $slug = (string)($item[2] ?? '');
        if (preg_match('/audit|preflight|readiness|migration|login bridge checks|sponsor receipts/i', $label)) {
            $audit_items[] = ['label'=>$label,'slug'=>$slug];
        } else {
            $keep[] = $item;
        }
    }
    if (!$audit_items) return;
    $submenu['bubba-hub'] = $keep;
    add_submenu_page('bubba-hub','Audits & Diagnostics','Audits & Diagnostics','manage_options','bh-audit-centre',static function() use ($audit_items): void {
        if (!current_user_can('manage_options')) wp_die('Access denied');
        echo '<div class="wrap"><h1>Audits &amp; Diagnostics</h1><p>Existing audit and migration tools are unchanged. Choose a tool below.</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;max-width:1100px">';
        foreach ($audit_items as $item) {
            echo '<a class="button button-secondary" style="height:auto;padding:18px;text-align:left;white-space:normal" href="'.esc_url(admin_url('admin.php?page='.$item['slug'])).'">'.esc_html($item['label']).'</a>';
        }
        echo '</div></div>';
    });
}, 9999);
