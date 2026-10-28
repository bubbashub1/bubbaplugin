<?php
/**
 * Plugin Name: Bubba Hub Manager
 * Description: Read-only WordPress management screens for the existing Bubba Hub activities, venues and sessions.
 * Version: 0.19.0
 * Requires PHP: 8.0
 * Text Domain: bubba-hub-manager
 */
if (!defined('ABSPATH')) exit;
final class Bubba_Hub_Manager {
    private const CAP = 'manage_options';
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 40);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_media']);
    }
    public static function enqueue_media(string $hook): void {
        if (!current_user_can(self::CAP)) return;
        if (!isset($_GET['page'],$_GET['activity_id']) || $_GET['page']!=='bh-manager-activities' || !absint($_GET['activity_id'])) return;
        wp_enqueue_media();
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
        $fields=array_values(array_intersect(['title','description','good_to_know','what_to_bring','category','age_range','age_min_months','age_max_months','price_from','price_free','price_per_session','price_per_family','booking_url','website','facebook_url','instagram_url','email','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','image_path','image_1','image_2','image_3'], $columns));
        if (isset($_POST['bh_manager_save'])) {
            check_admin_referer('bh_manager_activity_'.$id);
            $changes=[]; $invalid_url=false; $invalid_email=false;
            foreach ($fields as $field) {
                if (!isset($_POST[$field]) || !is_string($_POST[$field])) continue;
                $raw=wp_unslash($_POST[$field]);
                if (in_array($field,['age_min_months','age_max_months'],true)) {
                    if ($raw!=='' && (!ctype_digit($raw) || (int)$raw>216)) continue;
                    $changes[$field]=$raw===''?null:(int)$raw;
                } elseif (in_array($field,['price_from','price_per_session','price_per_family'],true)) {
                    if ($raw!=='' && (!is_numeric($raw) || (float)$raw<0 || (float)$raw>99999)) continue;
                    $changes[$field]=$raw===''?null:round((float)$raw,2);
                } elseif (in_array($field,['price_free','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome'],true)) {
                    $changes[$field]=in_array($raw,['0','1'],true)?(int)$raw:0;
                } elseif (in_array($field,['booking_url','website','facebook_url','instagram_url','image_path','image_1','image_2','image_3'],true)) {
                    $url=trim($raw);
                    if ($url!=='' && (!wp_http_validate_url($url) || !in_array(strtolower((string)wp_parse_url($url,PHP_URL_SCHEME)),['http','https'],true))) {
                        $invalid_url=true;
                        continue;
                    }
                    $changes[$field]=esc_url_raw($url);
                } elseif ($field==='email') {
                    if ($raw!=='' && !is_email($raw)) { $invalid_email=true; continue; }
                    $changes[$field]=sanitize_email($raw);
                } else {
                    $changes[$field]=in_array($field,['title','category','age_range'],true)?sanitize_text_field($raw):wp_kses_post($raw);
                }
            }
            if ($invalid_url || $invalid_email) {
                echo '<div class="notice notice-error"><p>One or more website links or email addresses are invalid. Nothing was saved.</p></div>';
            } elseif (isset($changes['age_min_months'],$changes['age_max_months']) && $changes['age_min_months']>$changes['age_max_months']) {
                echo '<div class="notice notice-error"><p>Minimum age cannot exceed maximum age.</p></div>';
            } elseif (isset($changes['title']) && trim($changes['title'])==='') {
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
        echo '<div class="bh-manager-head"><div><a class="bh-manager-back" href="'.esc_url(admin_url('admin.php?page=bh-manager-activities')).'">&larr; All activities</a><h1>'.esc_html((string)($record['title']??'Edit activity')).'</h1><p>Activity #'.esc_html((string)$id).' <span class="bh-manager-dot">•</span> '.esc_html(ucfirst((string)($record['status']??'Listing'))).'</p></div><span class="bh-manager-tag">Bubba Hub Directory</span></div>';
        
        echo '<div class="notice notice-info inline"><p>Edit activity details, ages, pricing, booking and contact information below. Venues, schedules, sessions, organiser assignments and publication status are untouched. Image fields accept web URLs; local file paths remain unchanged unless edited.</p></div>';
        echo '<form method="post" id="bh-manager-activity-form">';
        echo '<p class="description">Ages are stored in months. Examples: 12 = 1 year; 60 = 5 years. Only change pricing fields that apply to this activity.</p>';
        echo '<nav class="bh-editor-tabs" role="tablist" aria-label="Activity editor sections"><a href="#bh-basic-details">Details</a><a href="#bh-age-suitability">Ages</a><a href="#bh-pricing">Pricing</a><a href="#bh-helpful-information">Helpful info</a><a href="#bh-booking-attendance">Booking</a><a href="#bh-contact-social-links">Contact</a><a href="#bh-listing-images">Images</a><a href="#bh-venue-information">Venues</a><a href="#bh-session-information">Sessions</a></nav>';
        echo '<div class="bh-editor-actions"><button type="submit" class="button button-primary" name="bh_manager_save" value="1">Save activity details</button> <span>Changes are saved to this activity only.</span></div>';
        wp_nonce_field('bh_manager_activity_'.$id);
        echo '<style>
        .bh-editor-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 8px}.bh-editor-tabs a{display:inline-block;text-decoration:none;color:#245e42;background:#fff;border:1px solid #d1e2d6;border-radius:9px;padding:9px 15px;font-weight:650}.bh-editor-tabs a:hover,.bh-editor-tabs a:focus,.bh-editor-tabs a.is-active{background:#216647;color:#fff;border-color:#216647}.bh-editor-section[hidden]{display:none!important}
        .bh-editor-section{scroll-margin-top:120px}.bh-image-row td{padding-top:18px;padding-bottom:20px}.bh-image-row .bh-media-select{margin-left:8px;border-radius:8px}.bh-image-row .bh-image-preview{display:block;background:#f8faf8;padding:5px}
        #bh-manager-activity-form>.submit .button-primary{background:#216647;border-color:#216647;border-radius:7px;padding:4px 18px}
        .bh-manager-head{background:linear-gradient(115deg,#e6f4e9,#f7fbf8);border:1px solid #c9e3d1;border-radius:16px;padding:24px 28px;margin:18px 0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .bh-manager-head h1{font-size:25px;font-weight:750;margin:10px 0 8px;color:#174f39;line-height:1.25}
        .bh-manager-head p{margin:0;color:#53675d}.bh-manager-back{font-weight:650;text-decoration:none;color:#226746}
        .bh-manager-dot{padding:0 6px}.bh-manager-tag{display:inline-block;border-radius:24px;background:#d0ead8;color:#18553b;font-weight:700;padding:8px 14px}
        .bh-editor-section{box-shadow:0 3px 12px rgba(24,67,45,.05);border-color:#dce7df!important;border-radius:14px!important;padding:22px 26px!important}
        .bh-editor-section h3{color:#1c5d40;font-size:19px!important;border-bottom:1px solid #edf1ed;padding-bottom:14px;margin-bottom:15px!important}
        .bh-editor-section input,.bh-editor-section textarea,.bh-editor-section select{border-radius:8px;min-height:39px;border-color:#cbd7cf}
        .bh-editor-section input:focus,.bh-editor-section textarea:focus,.bh-editor-section select:focus{border-color:#28754e;box-shadow:0 0 0 1px #28754e}
        .bh-editor-actions{box-shadow:0 4px 16px rgba(15,48,29,.09);border-color:#d7e5dc!important;border-radius:10px!important;background:#fff!important}
        .bh-editor-actions .button-primary{background:#216647;border-color:#216647;border-radius:7px;padding:3px 18px}
        @media(max-width:782px){.bh-manager-head{padding:18px}.bh-editor-section{padding:14px!important}.bh-editor-section .form-table th{padding-bottom:4px}.bh-editor-section .form-table td{padding-top:0}}
        </style><style>.bh-editor-actions{position:sticky;top:32px;z-index:20;background:#f6f7f7;border:1px solid #dcdcde;padding:10px 14px;margin:12px 0;border-radius:8px;display:flex;align-items:center;gap:12px}.bh-editor-actions span{color:#50575e}.bh-editor-section{max-width:1050px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 22px;margin:18px 0}.bh-editor-section h3{margin:4px 0 12px;font-size:17px}.bh-editor-section .form-table th{width:190px}.bh-editor-section .form-table td input.regular-text{max-width:100%;width:460px}.bh-editor-section .form-table textarea{max-width:100%}</style>';
        $groups=['Basic details'=>['title','description','category'],'Age suitability'=>['age_range','age_min_months','age_max_months'],'Pricing'=>['price_free','price_from','price_per_session','price_per_family'],'Helpful information'=>['good_to_know','what_to_bring'],'Booking & attendance'=>['booking_required','booking_url','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome'],'Contact & social links'=>['website','facebook_url','instagram_url','email'],'Listing images'=>['image_path','image_1','image_2','image_3']];
        $category_options=[];
        if (in_array('category',$columns,true)) {
            $category_options=$db->get_col("SELECT DISTINCT `category` FROM `bh_activities` WHERE `category` IS NOT NULL AND `category` <> '' ORDER BY `category` ASC LIMIT 100");
        }
        foreach ($groups as $group=>$group_fields) {
            $visible=array_values(array_intersect($group_fields,$fields));
            if (!$visible) continue;
            echo '<section id="bh-'.esc_attr(sanitize_title($group)).'" class="bh-editor-section" role="tabpanel"><h3>'.esc_html($group).'</h3>'.($group==='Age suitability'?'<p class="description">Choose an age preset or enter exact minimum and maximum months below.</p><p><select id="bh-age-preset"><option value="">Select age preset (optional)</option><option value="0:6">0–6 months</option><option value="6:12">6–12 months</option><option value="0:12">0–12 months</option><option value="12:48">1–4 years</option><option value="48:216">4+ years</option></select></p>':'').'<table class="form-table"><tbody>';
            foreach ($visible as $field) {
                    $label=ucwords(str_replace('_',' ',$field));
            echo '<tr'.(in_array($field,['image_path','image_1','image_2','image_3'],true)?' class="bh-image-row"':'').'><th scope="row"><label for="bh-'.esc_attr($field).'">'.esc_html($field==='image_path'?'Featured image':str_replace('_',' ',$label)).'</label></th><td>';
            if (in_array($field,['price_free','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome'],true)) echo '<select id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'"><option value="0" '.selected((string)($record[$field]??'0'),'0',false).'>No</option><option value="1" '.selected((string)($record[$field]??'0'),'1',false).'>Yes</option></select>';
            elseif (in_array($field,['age_min_months','age_max_months','price_from','price_per_session','price_per_family'],true)) echo '<input type="number" min="0" '.(in_array($field,['price_from','price_per_session','price_per_family'],true)?'step="0.01"':'step="1" max="216"').' id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'" value="'.esc_attr((string)($record[$field]??'')).'"/>';
            elseif ($field==='category') {
                echo '<select id="bh-category" name="category"><option value="">Select category</option>';
                $current=(string)($record[$field]??'');
                if ($current!=='' && !in_array($current,$category_options,true)) echo '<option selected value="'.esc_attr($current).'">'.esc_html($current).' (current)</option>';
                foreach ($category_options as $option) echo '<option value="'.esc_attr((string)$option).'" '.selected($current,(string)$option,false).'>'.esc_html((string)$option).'</option>';
                echo '</select>';
            }
            elseif (in_array($field,['booking_url','website','facebook_url','instagram_url','email','image_path','image_1','image_2','image_3'],true)) echo '<input type="'.($field==='email'?'email':'url').'" class="regular-text" id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'" value="'.esc_attr((string)($record[$field]??'')).'"/>';
            elseif ($field==='age_range') echo '<input class="regular-text" id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'" value="'.esc_attr((string)($record[$field]??'')).'"/>';
            elseif ($field==='title') echo '<input required class="regular-text" id="bh-title" name="title" value="'.esc_attr((string)($record[$field]??'')).'"/>';
            else echo '<textarea class="large-text" rows="7" id="bh-'.esc_attr($field).'" name="'.esc_attr($field).'">'.esc_textarea(html_entity_decode((string)($record[$field]??''), ENT_QUOTES | ENT_HTML5, 'UTF-8')).'</textarea>';
            if (in_array($field,['image_path','image_1','image_2','image_3'],true)) echo ' <button type="button" class="button bh-media-select" data-target="bh-'.esc_attr($field).'">Choose / replace image</button><p class="description">Use the WordPress Media Library or paste a direct image URL, then save this activity.</p>';
            if (in_array($field,['image_path','image_1','image_2','image_3'],true) && !empty($record[$field]) && filter_var($record[$field],FILTER_VALIDATE_URL)) echo '<p><img loading="lazy" alt="Current listing image" src="'.esc_url((string)$record[$field]).'" class="bh-image-preview" style="max-width:240px;max-height:160px;object-fit:contain;border:1px solid #dce7df;border-radius:8px;margin-top:8px"/></p>';
            echo '</td></tr>';
        }
        echo '</tbody></table></section>'; }
        echo '<section id="bh-venue-information" class="bh-editor-section" role="tabpanel"><h3>Linked venues</h3><p class="description">Read-only venue details for this activity. Venue editing is managed separately.</p>';
        $venue_columns=$db->get_col('SHOW COLUMNS FROM `bh_venues`',0);
        $venue_conditions=[];
        if (is_array($venue_columns) && in_array('activity_id',$venue_columns,true)) $venue_conditions[]='v.activity_id=%d';
        $venue_junction=$db->get_var($db->prepare('SHOW TABLES LIKE %s','bh_activity_venues'));
        if ($venue_junction==='bh_activity_venues') {
            $venue_junction_columns=$db->get_col('SHOW COLUMNS FROM `bh_activity_venues`',0);
            if (in_array('activity_id',$venue_junction_columns,true) && in_array('venue_id',$venue_junction_columns,true))
                $venue_conditions[]='EXISTS (SELECT 1 FROM `bh_activity_venues` av WHERE av.venue_id=v.id AND av.activity_id=%d)';
        }
        if ($venue_conditions && in_array('id',$venue_columns,true)) {
            $venue_display=array_values(array_intersect(['id','name','address','address_line_1','city','postcode','latitude','longitude'],$venue_columns));
            $venue_sql='SELECT DISTINCT '.implode(',',array_map(static fn($c)=>'v.`'.$c.'`',$venue_display)).' FROM `bh_venues` v WHERE '.implode(' OR ',$venue_conditions).' ORDER BY v.id ASC LIMIT 100';
            $venue_rows=$db->get_results($db->prepare($venue_sql,...array_fill(0,count($venue_conditions),$id)),ARRAY_A);
            if ($venue_rows) {
                echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
                foreach ($venue_display as $col) echo '<th scope="col">'.esc_html(ucwords(str_replace('_',' ',$col))).'</th>';
                echo '</tr></thead><tbody>';
                foreach ($venue_rows as $venue_row) {
                    echo '<tr>';
                    foreach ($venue_display as $col) echo '<td>'.esc_html((string)($venue_row[$col]??'')).'</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            } else echo '<p>No directly linked venues found for this activity.</p>';
        } else echo '<p>Venue relationships are unavailable in the current database schema.</p>';
        echo '</section>';
        echo '<section id="bh-session-information" class="bh-editor-section" role="tabpanel"><h3>Session information</h3><p class="description">Read-only timetable preview. Session editing is managed separately.</p>';
        $session_cols=$db->get_col('SHOW COLUMNS FROM `bh_sessions`',0);
        $venue_cols=$db->get_col('SHOW COLUMNS FROM `bh_venues`',0);
        $linked=[];
        $has_session_activity=is_array($session_cols) && in_array('activity_id',$session_cols,true);
        $has_junction=false;
        if (is_array($session_cols) && in_array('venue_id',$session_cols,true)) {
            if (is_array($venue_cols) && in_array('activity_id',$venue_cols,true)) {
                $linked[]='EXISTS (SELECT 1 FROM `bh_venues` v WHERE v.id=s.venue_id AND v.activity_id=%d)';
            }
            $junction=$db->get_var($db->prepare('SHOW TABLES LIKE %s','bh_activity_venues'));
            if ($junction==='bh_activity_venues') {
                $junction_cols=$db->get_col('SHOW COLUMNS FROM `bh_activity_venues`',0);
                if (in_array('activity_id',$junction_cols,true) && in_array('venue_id',$junction_cols,true)) {
                    $has_junction=true;
                    // A venue shared by several activities is ambiguous unless
                    // the session itself identifies its activity.
                    $linked[]='EXISTS (SELECT 1 FROM `bh_activity_venues` av WHERE av.venue_id=s.venue_id AND av.activity_id=%d AND (SELECT COUNT(DISTINCT av2.activity_id) FROM `bh_activity_venues` av2 WHERE av2.venue_id=s.venue_id)=1)';
                }
            }
        }
        if ($has_session_activity) {
            $linked[]='s.activity_id=%d';
        }
        if ($linked && in_array('id',$session_cols,true)) {
            $display_cols=array_values(array_intersect(['id','day_of_week','start_time','end_time','frequency','start_date','end_date','venue_id','status'],$session_cols));
            if ($has_session_activity) {
                $linked=array_map(static fn($condition)=>$condition==='s.activity_id=%d'?$condition:'(s.activity_id IS NULL AND '.$condition.')',$linked);
            }
            $has_venue_name=in_array('venue_id',$session_cols,true) && is_array($venue_cols) && in_array('id',$venue_cols,true) && in_array('name',$venue_cols,true);
            $venue_select=$has_venue_name?', v.`name` AS venue_name':'';
            $venue_join=$has_venue_name?' LEFT JOIN `bh_venues` v ON v.id=s.venue_id':'';
            $sql='SELECT DISTINCT '.implode(',',array_map(static fn($c)=>'s.`'.$c.'`',$display_cols)).$venue_select.' FROM `bh_sessions` s'.$venue_join.' WHERE ('.implode(' OR ',$linked).') ORDER BY s.id DESC LIMIT 100';
            $rows=$db->get_results($db->prepare($sql,...array_fill(0,count($linked),$id)),ARRAY_A);
            if ($rows) {
                echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
                foreach ($display_cols as $col) echo '<th scope="col">'.esc_html(ucwords(str_replace('_',' ',$col))).'</th>';
                if ($has_venue_name) echo '<th scope="col">Venue name</th>';
                echo '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($display_cols as $col) echo '<td>'.esc_html((string)($row[$col]??'')).'</td>';
                    if ($has_venue_name) echo '<td>'.esc_html((string)($row['venue_name']?:'Venue name unavailable')).'</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            } else echo '<p>No sessions found through the available activity or venue relationships.</p>';
        } else echo '<p>No supported session relationship was found in the current database schema.</p>';
        if ($has_junction) echo '<p class="description">Sessions from venues shared by multiple activities are omitted unless a direct activity assignment confirms ownership.</p>';
        echo '</section>';
        if (!in_array('facebook_url',$columns,true) || !in_array('instagram_url',$columns,true) || !in_array('email',$columns,true)) {
            echo '<div class="notice notice-info inline"><p>Some social or email fields are not stored on this activity. Organiser-level contact details are not edited here.</p></div>';
        }
        echo '<script>document.addEventListener("DOMContentLoaded",function(){
            document.querySelectorAll(".bh-image-row input[type=url]").forEach(function(input){
                input.addEventListener("change",function(){
                    var row=input.closest(".bh-image-row"),preview=row&&row.querySelector(".bh-image-preview");
                    if(preview){if(/^https?:\\/\\//i.test(input.value)){preview.src=input.value;preview.style.display="";}else{preview.style.display="none";}}
                });
            });
            document.querySelectorAll(".bh-media-select").forEach(function(btn){
                btn.addEventListener("click",function(){
                    if(!window.wp||!wp.media){alert("WordPress Media Library is unavailable.");return;}
                    var target=document.getElementById(btn.getAttribute("data-target"));
                    var frame=wp.media({title:"Select listing image",button:{text:"Use this image"},library:{type:"image"},multiple:false});
                    frame.on("select",function(){var item=frame.state().get("selection").first();if(item&&target){target.value=item.toJSON().url;target.dispatchEvent(new Event("change",{bubbles:true}));}});
                    frame.open();
                });
            });
            var nav=document.querySelector(".bh-editor-tabs"),panels=Array.from(document.querySelectorAll(".bh-editor-section"));
            if(nav&&panels.length){
                var links=Array.from(nav.querySelectorAll("a")).filter(function(a){return document.querySelector(a.getAttribute("href"));});
                function activate(hash){
                    var target=links.some(function(a){return a.getAttribute("href")===hash;})?hash:links[0].getAttribute("href");
                    links.forEach(function(a){var active=a.getAttribute("href")===target;a.setAttribute("role","tab");a.setAttribute("aria-selected",active?"true":"false");a.setAttribute("tabindex",active?"0":"-1");a.classList.toggle("is-active",active);});
                    panels.forEach(function(p){p.hidden="#"+p.id!==target;});
                }
                links.forEach(function(a){a.addEventListener("click",function(e){e.preventDefault();history.replaceState(null,"",a.getAttribute("href"));activate(a.getAttribute("href"));});});
                activate(location.hash);
                window.addEventListener("hashchange",function(){activate(location.hash);});
            }
            var preset=document.getElementById("bh-age-preset"),min=document.getElementById("bh-age_min_months"),max=document.getElementById("bh-age_max_months");if(preset&&min&&max){preset.addEventListener("change",function(){if(!this.value)return;var parts=this.value.split(":");min.value=parts[0];max.value=parts[1];});}});</script>';
        submit_button('Save activity details','primary','bh_manager_save');
        echo '</form>';
    }
    private static function listing(string $type, string $label): void {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.');
        echo '<div class="wrap"><h1>'.esc_html($label).'</h1><p>'.($type==='activities'?'Manage activity details. Venues and sessions are edited separately.':'Read-only preview. Editing is disabled until relationships and permissions are verified.').'</p>';
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
