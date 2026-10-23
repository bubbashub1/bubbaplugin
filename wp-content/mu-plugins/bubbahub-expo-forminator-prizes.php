<?php
/**
 * Plugin Name: Bubba Hub Expo Forminator Prize Bridge
 * Description: Copies successful Forminator form 85 entries into the existing Expo prize approval queue.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;
add_action('forminator_custom_form_after_save_entry', function($form_id, $response) {
    if ((int)$form_id !== 85) return;
    // Do not guess an entry ID: duplicate/race-safe handling requires the actual saved submission.
    $entry_id = 0;
    if (is_array($response)) $entry_id = (int)($response['entry_id'] ?? $response['id'] ?? 0);
    elseif (is_object($response)) $entry_id = (int)($response->entry_id ?? $response->id ?? 0);
    // Forminator normally supplies only form ID and response. Its documented
    // model accessor provides the saved entry for both AJAX and reload modes.
    if (!$entry_id && class_exists('Forminator_Form_Entry_Model')) {
        $latest = Forminator_Form_Entry_Model::get_latest_entry_by_form_id($form_id);
        if (is_object($latest)) $entry_id = (int)($latest->entry_id ?? 0);
    }
    if (!$entry_id) { error_log('Bubba Hub prize bridge: missing Forminator entry ID'); return; }
    global $wpdb;
    $entries = $wpdb->prefix.'frmt_form_entry_meta';
    $meta = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$entries} WHERE entry_id=%d", $entry_id), ARRAY_A);
    if (!$meta) return;
    $values = [];
    foreach ($meta as $row) {
        $v = maybe_unserialize($row['meta_value']);
        $values[$row['meta_key']] = $v;
    }
    $str = static function($key) use ($values): string {
        $v = $values[$key] ?? '';
        if (is_array($v)) return sanitize_text_field(implode(' ', array_filter(array_map('strval', $v), 'is_scalar')));
        return is_scalar($v) ? sanitize_text_field((string)$v) : '';
    };
    $title = $str('text-1'); $email = sanitize_email($str('email-1'));
    if (!$title || !is_email($email)) return;
    $donor = $str('text-3');
    $url = esc_url_raw($str('url-1'));
    if ($url && !in_array(wp_parse_url($url, PHP_URL_SCHEME), ['http','https'], true)) $url='';
    $credit = !empty($values['checkbox-1']) ? 1 : 0;
    if (empty($values['checkbox-2'])) return;
    // Existing live schema stores event key and review status; no automatic publication.
    $table = 'bh_expo_public_prize_donations';
    // Unique submission marker is stored in collection details, preventing replays.
    $marker = '[Forminator #85 entry '.$entry_id.']';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE collection_details LIKE %s LIMIT 1", '%'.$wpdb->esc_like($marker).'%'));
    if ($existing) return;
    $collection = trim($str('textarea-2').' '.$marker);
    $ok = $wpdb->insert($table, [
        'event_key'=>'bubba-me-2027',
        'donor_name'=>$donor ?: $str('name-1'),
        'email'=>$email,
        'title'=>$title,
        'description'=>$str('textarea-1'),
        'prize_value'=>$str('text-2'),
        'donor_url'=>$url,
        'collection_details'=>$collection,
        'public_credit'=>$credit,
        'status'=>'pending',
    ], ['%s','%s','%s','%s','%s','%s','%s','%s','%d','%s']);
    if (!$ok) error_log('Bubba Hub prize bridge: insert failed: '.$wpdb->last_error);
}, 10, 2);
// Forminator uses different post-submission hooks for AJAX and page reload.
// Both call the same handler; the entry marker prevents duplicate imports.
add_action('forminator_custom_form_after_handle_submit', function($form_id, $response) {
    do_action('forminator_custom_form_after_save_entry', $form_id, $response);
}, 10, 2);
