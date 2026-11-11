<?php
/**
 * Bubba Hub Support article library.
 * Native WordPress posts only; no duplicate article data or new login system.
 */
defined('ABSPATH') || exit;

add_shortcode('bh_support_articles', static function ($atts): string {
    $atts = shortcode_atts(['limit' => 9], (array) $atts, 'bh_support_articles');
    $limit = min(30, max(1, absint($atts['limit'])));
    $topic = isset($_GET['bh_article_topic']) && is_string($_GET['bh_article_topic'])
        ? sanitize_title(wp_unslash($_GET['bh_article_topic'])) : '';
    $search = isset($_GET['bh_article_search']) && is_string($_GET['bh_article_search'])
        ? sanitize_text_field(wp_unslash($_GET['bh_article_search'])) : '';
    $page = isset($_GET['bh_article_page']) ? max(1, absint($_GET['bh_article_page'])) : 1;
    $args = ['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $limit,
        'paged' => $page, 's' => $search, 'ignore_sticky_posts' => true];
    if ($topic !== '') {
        $args['tax_query'] = [['taxonomy' => 'bh_support_topic', 'field' => 'slug', 'terms' => [$topic]]];
    } else {
        // Show only posts deliberately categorized as support articles.
        $args['tax_query'] = [['taxonomy' => 'bh_support_topic', 'operator' => 'EXISTS']];
    }
    $query = new WP_Query($args);
    $topics = get_terms(['taxonomy' => 'bh_support_topic', 'hide_empty' => true]);
    ob_start();
    ?>
    <section class="bh-resource-hub bh-article-library" aria-label="<?php esc_attr_e('Family articles', 'bubbahub'); ?>">
      <form method="get" class="bh-resource-filters" role="search">
        <label for="bh-article-search"><?php esc_html_e('Search articles', 'bubbahub'); ?></label>
        <input type="search" id="bh-article-search" name="bh_article_search" value="<?php echo esc_attr($search); ?>">
        <label for="bh-article-topic"><?php esc_html_e('Topic', 'bubbahub'); ?></label>
        <select id="bh-article-topic" name="bh_article_topic">
          <option value=""><?php esc_html_e('All topics', 'bubbahub'); ?></option>
          <?php if (!is_wp_error($topics)) : foreach ($topics as $term) : ?>
            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($topic, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
          <?php endforeach; endif; ?>
        </select>
        <button type="submit"><?php esc_html_e('Find articles', 'bubbahub'); ?></button>
      </form>
      <div class="bh-resource-grid">
        <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); ?>
          <article class="bh-resource-card">
            <?php if (has_post_thumbnail()) : ?><a href="<?php echo esc_url(get_permalink()); ?>"><?php the_post_thumbnail('medium_large', ['loading' => 'lazy']); ?></a><?php endif; ?>
            <p class="bh-article-byline"><?php echo esc_html(get_the_date()); ?></p>
            <h2><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28)); ?></p>
            <a class="bh-resource-read" href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('Read article', 'bubbahub'); ?></a>
          </article>
        <?php endwhile; wp_reset_postdata(); else : ?>
          <p><?php esc_html_e('No articles match your search yet.', 'bubbahub'); ?></p>
        <?php endif; ?>
      </div>
      <?php if ($query->max_num_pages > 1) :
        echo '<nav class="bh-resource-pagination" aria-label="' . esc_attr__('Article pages', 'bubbahub') . '">';
        $base = remove_query_arg('bh_article_page');
        for ($i = 1; $i <= $query->max_num_pages; $i++) {
            echo '<a ' . ($i === $page ? 'aria-current="page" ' : '') . 'href="' . esc_url(add_query_arg('bh_article_page', $i, $base)) . '">' . esc_html((string) $i) . '</a> ';
        }
        echo '</nav>';
      endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
});
