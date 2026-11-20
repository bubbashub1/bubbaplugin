<?php
/**
 * Plugin Name: Bubba Hub Site Manager
 * Description: Safe, WordPress-native visual controls for Bubba Hub global design settings.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;

function bhsm_defaults(){ return [
 'primary'=>'#176b52','accent'=>'#3c8e96','surface'=>'#f4f8f7','text'=>'#17221c',
 'heading_font'=>'Quicksand','body_font'=>'Quicksand','radius'=>'16','card_gap'=>'16',
 'button_radius'=>'999','published'=>false
];}
function bhsm_get_settings(){ return wp_parse_args(get_option('bhsm_design_settings',[]),bhsm_defaults());}
function bhsm_clean_settings($input){
 $d=bhsm_defaults(); $out=[];
 foreach(['primary','accent','surface','text'] as $key){$v=isset($input[$key])?sanitize_hex_color($input[$key]):null;$out[$key]=$v?:$d[$key];}
 foreach(['heading_font','body_font'] as $key){$v=isset($input[$key])?sanitize_text_field($input[$key]):$d[$key];$out[$key]=in_array($v,['Quicksand','Arial','Calibri','Georgia','Verdana'],true)?$v:$d[$key];}
 foreach(['radius'=>['0','4','8','12','16','20','24'],'card_gap'=>['8','12','16','20','24','28'],'button_radius'=>['4','8','12','16','999']] as $key=>$allowed){$v=isset($input[$key])?(string)intval($input[$key]):$d[$key];$out[$key]=in_array($v,$allowed,true)?$v:$d[$key];}
 $out['published']=!empty($input['published']); return $out;
}
add_action('admin_menu',function(){
 add_submenu_page('bubba-hub','Site Manager','Site Manager','manage_options','bh-site-manager','bhsm_render');
},30);
function bhsm_render(){
 if(!current_user_can('manage_options')) return;
 if(isset($_POST['bhsm_save']) && check_admin_referer('bhsm_save_design')){
   $settings=bhsm_clean_settings($_POST['design']??[]);
   update_option('bhsm_design_settings',$settings,false);
   echo '<div class="notice notice-success is-dismissible"><p>Design settings saved. '.($settings['published']?'Published settings are active.':'Draft saved; published design remains unchanged.').'</p></div>';
 }
 $s=bhsm_get_settings(); ?>
 <div class="wrap bhsm-wrap"><h1>🌿 Bubba Hub Site Manager</h1><p>Manage a small set of global visual settings without editing CSS. Save as a draft first, then publish when ready.</p>
 <form method="post"><?php wp_nonce_field('bhsm_save_design'); ?>
 <div class="bhsm-toolbar"><button type="submit" name="bhsm_save" value="1" class="button button-primary">Save settings</button><button type="button" id="bhsm-preview" class="button">Preview current edits</button><label class="bhsm-publish"><input type="checkbox" name="design[published]" value="1" <?php checked($s['published']);?>> Publish these design settings to the site</label></div>
 <div class="bhsm-layout"><section class="bhsm-panel"><h2>Colours</h2><?php foreach(['primary'=>'Primary','accent'=>'Accent','surface'=>'Background','text'=>'Text'] as $key=>$label):?><label class="bhsm-field"><span><?php echo esc_html($label);?></span><input type="color" name="design[<?php echo esc_attr($key);?>]" value="<?php echo esc_attr($s[$key]);?>" data-var="<?php echo esc_attr($key);?>"><code><?php echo esc_html($s[$key]);?></code></label><?php endforeach;?></section>
 <section class="bhsm-panel"><h2>Fonts</h2><?php foreach(['heading_font'=>'Headings','body_font'=>'Body text'] as $key=>$label):?><label class="bhsm-field"><span><?php echo esc_html($label);?></span><select name="design[<?php echo esc_attr($key);?>]"><?php foreach(['Quicksand','Arial','Calibri','Georgia','Verdana'] as $font):?><option value="<?php echo esc_attr($font);?>" <?php selected($s[$key],$font);?>><?php echo esc_html($font);?></option><?php endforeach;?></select></label><?php endforeach;?></section>
 <section class="bhsm-panel"><h2>Shape & spacing</h2><label class="bhsm-field"><span>Card corners</span><select name="design[radius]"><?php foreach(['0','4','8','12','16','20','24'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($s['radius'],$v);?>><?php echo esc_html($v);?> px</option><?php endforeach;?></select></label><label class="bhsm-field"><span>Card gap</span><select name="design[card_gap]"><?php foreach(['8','12','16','20','24','28'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($s['card_gap'],$v);?>><?php echo esc_html($v);?> px</option><?php endforeach;?></select></label><label class="bhsm-field"><span>Button corners</span><select name="design[button_radius]"><?php foreach(['4','8','12','16','999'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($s['button_radius'],$v);?>><?php echo esc_html($v==='999'?'Pill':$v.' px');?></option><?php endforeach;?></select></label></section>
 <section class="bhsm-panel bhsm-preview-panel"><h2>Preview</h2><div id="bhsm-preview-box"><div class="bhsm-demo-card"><small>YOUR FAMILY GUIDE</small><h3>Find your family's next little adventure.</h3><p>Discover activities, classes and events.</p><a href="#" class="bhsm-demo-button">Explore activities</a></div></div><p class="description">Preview changes here. Published settings use global CSS variables and may not override every custom component yet.</p></section></div>
 <p class="bhsm-toolbar"><button type="submit" name="bhsm_save" value="1" class="button button-primary">Save settings</button></p></form></div>
 <?php
}
add_action('admin_enqueue_scripts',function($hook){
 if($hook!=='bubba-hub_page_bh-site-manager')return;
 wp_enqueue_style('bhsm-admin',plugin_dir_url(__FILE__).'bubba-hub-site-manager.css',[], '0.1.0');
 wp_enqueue_script('bhsm-admin',plugin_dir_url(__FILE__).'bubba-hub-site-manager.js',[], '0.1.0',true);
});
add_action('wp_head',function(){
 $s=bhsm_get_settings();if(empty($s['published']))return;
 echo '<style id="bhsm-global-design">:root{--bhsm-primary:'.esc_attr($s['primary']).';--bhsm-accent:'.esc_attr($s['accent']).';--bhsm-surface:'.esc_attr($s['surface']).';--bhsm-text:'.esc_attr($s['text']).';--bhsm-radius:'.intval($s['radius']).'px;--bhsm-card-gap:'.intval($s['card_gap']).'px;--bhsm-button-radius:'.intval($s['button_radius']).'px;--bhsm-heading-font:'.esc_attr($s['heading_font']).';--bhsm-body-font:'.esc_attr($s['body_font']).'}body{color:var(--bhsm-text);font-family:var(--bhsm-body-font),sans-serif}h1,h2,h3,h4{font-family:var(--bhsm-heading-font),sans-serif} .bhsm-managed-card{border-radius:var(--bhsm-radius);gap:var(--bhsm-card-gap)}.bhsm-managed-button{border-radius:var(--bhsm-button-radius)}</style>';
},90);
