<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');
try {
    $wp=dirname(__DIR__).'/wp-load.php';
    if (!is_file($wp)) throw new RuntimeException('Publishing service unavailable.');
    ob_start(); require_once $wp; ob_end_clean();
    if (!function_exists('get_posts')) throw new RuntimeException('Publishing service unavailable.');
    $posts=get_posts(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>12,'meta_key'=>'_bh_leader_organiser_id','meta_compare'=>'EXISTS','orderby'=>'date','order'=>'DESC','suppress_filters'=>false]);
    $articles=[];
    foreach ($posts as $post) {
        $url=get_permalink($post);
        if (!$url) continue;
        $articles[]=['title'=>get_the_title($post),'excerpt'=>wp_trim_words(wp_strip_all_tags($post->post_excerpt?:$post->post_content),26),'url'=>esc_url_raw($url),'date'=>get_the_date('j M Y',$post)];
    }
    echo json_encode(['ok'=>true,'articles'=>$articles],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Public leader articles: '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['ok'=>false,'message'=>'Articles are temporarily unavailable.']);
}
