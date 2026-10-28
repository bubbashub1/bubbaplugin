<?php
/**
 * Plugin Name: Bubba Hub Team Profiles
 * Description: Admin-managed, consent-controlled public team profiles.
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    register_post_type('bh_team_profile', [
        'labels'=>['name'=>'Team Profiles','singular_name'=>'Team Profile','add_new_item'=>'Add Team Profile','edit_item'=>'Edit Team Profile'],
        'public'=>false,'show_ui'=>true,'show_in_menu'=>'bubba-hub',
        'supports'=>['title','editor','thumbnail','page-attributes'],
        'capability_type'=>'post','map_meta_cap'=>true,
        'show_in_rest'=>false,
    ]);
    register_post_meta('bh_team_profile','bh_team_role',['type'=>'string','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'sanitize_text_field']);
    register_post_meta('bh_team_profile','bh_team_area',['type'=>'string','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'sanitize_text_field']);
    register_post_meta('bh_team_profile','bh_team_consent',['type'=>'boolean','single'=>true,'show_in_rest'=>false]);
});
add_action('add_meta_boxes_bh_team_profile',function(){
    add_meta_box('bh_team_details','Team profile details',function($post){
        wp_nonce_field('bh_team_save','bh_team_nonce');
        $role=get_post_meta($post->ID,'bh_team_role',true);
        $area=get_post_meta($post->ID,'bh_team_area',true);
        $consent=get_post_meta($post->ID,'bh_team_consent',true);
        echo '<p><label>Role<br><input class="widefat" name="bh_team_role" value="'.esc_attr($role).'"></label></p>';
        echo '<p><label>Area<br><input class="widefat" name="bh_team_area" value="'.esc_attr($area).'"></label></p>';
        echo '<p><label><input type="checkbox" name="bh_team_consent" value="1" '.checked($consent,'1',false).'> Permission obtained to publish this name, biography and photograph</label></p>';
        echo '<p>Use the title for the name, editor for the biography, Featured Image for the photo and Order under Page Attributes for display order. Publish only with permission.</p>';
    },'bh_team_profile','normal','high');
});
add_action('save_post_bh_team_profile',function($id){
    if(!isset($_POST['bh_team_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_team_nonce'])),'bh_team_save') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post',$id)) return;
    update_post_meta($id,'bh_team_role',sanitize_text_field(wp_unslash($_POST['bh_team_role']??'')));
    update_post_meta($id,'bh_team_area',sanitize_text_field(wp_unslash($_POST['bh_team_area']??'')));
    update_post_meta($id,'bh_team_consent',isset($_POST['bh_team_consent'])?'1':'0');
});
add_action('rest_api_init',function(){
    register_rest_route('bubbahub/v1','/team',[
        'methods'=>'GET','permission_callback'=>'__return_true',
        'callback'=>function(){
            $posts=get_posts(['post_type'=>'bh_team_profile','post_status'=>'publish','numberposts'=>100,'orderby'=>['menu_order'=>'ASC','date'=>'ASC']]);
            $out=[];
            foreach($posts as $post){
                if(get_post_meta($post->ID,'bh_team_consent',true)!=='1')continue;
                $out[]=[
                    'name'=>get_the_title($post),'role'=>get_post_meta($post->ID,'bh_team_role',true),
                    'area'=>get_post_meta($post->ID,'bh_team_area',true),
                    'bio'=>wp_strip_all_tags($post->post_content),
                    'image'=>get_the_post_thumbnail_url($post,'medium')?:'',
                ];
            }
            return rest_ensure_response($out);
        }
    ]);
});
