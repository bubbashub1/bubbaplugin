<?php
/**
 * Support article moderation tools for WordPress administrators.
 * Existing Leader Pro submission endpoint continues to submit posts as pending.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    add_submenu_page(
        'bh-support-dashboard', __('Support Article Review', 'bubbahub'),
        __('Support Article Review', 'bubbahub'),
        'edit_others_posts', 'bh-support-article-review', 'bh_support_render_article_review'
    );
});

function bh_support_render_article_review(): void {
    if (!current_user_can('edit_others_posts')) {
        wp_die(esc_html__('You do not have permission to review articles.', 'bubbahub'));
    }
    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bh_support_review_action'])) {
        check_admin_referer('bh_support_review_article');
        $id = isset($_POST['bh_support_post_id']) ? absint($_POST['bh_support_post_id']) : 0;
        $action = isset($_POST['bh_support_review_action']) ? sanitize_key(wp_unslash($_POST['bh_support_review_action'])) : '';
        $post = get_post($id);
        $is_leader = $post && $post->post_type === 'post'
            && get_post_meta($id, '_bh_leader_organiser_id', true) !== '';
        if (!$is_leader || !current_user_can('edit_post', $id)) {
            $notice = __('Article not found or permission denied.', 'bubbahub');
        } elseif ($post->post_status !== 'pending') {
            $notice = __('This article is no longer pending review.', 'bubbahub');
        } elseif (!in_array($action, ['publish', 'draft'], true)) {
            $notice = __('Unknown review action.', 'bubbahub');
        } elseif ($action === 'publish' && !current_user_can('publish_posts')) {
            $notice = __('You cannot publish posts.', 'bubbahub');
        } else {
            $result = wp_update_post(['ID' => $id, 'post_status' => $action === 'publish' ? 'publish' : 'draft'], true);
            $notice = is_wp_error($result)
                ? __('Could not update the article.', 'bubbahub')
                : ($action === 'publish' ? __('Article published.', 'bubbahub') : __('Article returned to draft.', 'bubbahub'));
        }
    }
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $query = new WP_Query([
        'post_type' => 'post', 'post_status' => 'pending', 'posts_per_page' => 20,
        'paged' => $page, 'orderby' => 'date', 'order' => 'ASC',
        'meta_query' => [['key' => '_bh_leader_organiser_id', 'compare' => 'EXISTS']],
    ]);
    echo '<div class="wrap"><h1>' . esc_html__('Support Article Review', 'bubbahub') . '</h1>';
    echo '<p>' . esc_html__('Review pending articles submitted by class leaders. Open an article to edit its content and assign a Support Topic before publication.', 'bubbahub') . '</p>';
    if ($notice !== '') echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Article', 'bubbahub') . '</th><th>' . esc_html__('Submitted', 'bubbahub') . '</th><th>' . esc_html__('Actions', 'bubbahub') . '</th></tr></thead><tbody>';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            echo '<tr><td><strong>' . esc_html(get_the_title()) . '</strong><br><small>' . esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()), 24)) . '</small></td>';
            echo '<td>' . esc_html(get_the_date()) . '</td><td>';
            echo '<a class="button" href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html__('Edit & review', 'bubbahub') . '</a> ';
            foreach (['publish' => __('Approve', 'bubbahub'), 'draft' => __('Return to draft', 'bubbahub')] as $action => $label) {
                echo '<form method="post" style="display:inline-block;margin:0 4px 4px 0">';
                wp_nonce_field('bh_support_review_article');
                echo '<input type="hidden" name="bh_support_post_id" value="' . esc_attr((string) $id) . '">';
                echo '<button class="button ' . ($action === 'publish' ? 'button-primary' : '') . '" name="bh_support_review_action" value="' . esc_attr($action) . '">' . esc_html($label) . '</button></form>';
            }
            echo '</td></tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="3">' . esc_html__('No pending leader articles.', 'bubbahub') . '</td></tr>';
    }
    echo '</tbody></table>';
    if ($query->max_num_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($i = 1; $i <= $query->max_num_pages; $i++) {
            echo '<a class="button" ' . ($i === $page ? 'aria-current="page" ' : '') . 'href="' . esc_url(add_query_arg(['page' => 'bh-support-article-review', 'paged' => $i], admin_url('admin.php'))) . '">' . esc_html((string) $i) . '</a> ';
        }
        echo '</div></div>';
    }
    echo '</div>';
}
