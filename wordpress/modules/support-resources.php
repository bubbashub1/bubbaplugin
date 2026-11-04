<?php
/**
 * Bubba Hub Support & Resources — WordPress foundation.
 * Include from the BubbaWP plugin bootstrap after plugins_loaded.
 * No activation migrations or changes to existing support messaging.
 */
defined('ABSPATH') || exit;

add_action('init', static function (): void {
    register_post_type('bh_resource', [
        'labels' => ['name' => __('Family Resources', 'bubbahub'), 'singular_name' => __('Family Resource', 'bubbahub')],
        'public' => true, 'show_in_rest' => true, 'has_archive' => 'family-resources',
        'rewrite' => ['slug' => 'family-resource', 'with_front' => false],
        'menu_icon' => 'dashicons-book-alt', 'menu_position' => 22,
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'],
        'capability_type' => 'post', 'map_meta_cap' => true,
    ]);
    register_taxonomy('bh_support_topic', ['bh_resource', 'post'], [
        'labels' => ['name' => __('Support Topics', 'bubbahub'), 'singular_name' => __('Support Topic', 'bubbahub')],
        'public' => true, 'show_in_rest' => true, 'hierarchical' => true,
        'rewrite' => ['slug' => 'support-topic', 'with_front' => false],
    ]);
});

add_shortcode('bh_support_resources', static function ($atts): string {
    $atts = shortcode_atts(['limit' => 12], (array) $atts, 'bh_support_resources');
    $limit = min(36, max(1, absint($atts['limit'])));
    $topic = isset($_GET['bh_topic']) && is_string($_GET['bh_topic'])
        ? sanitize_title(wp_unslash($_GET['bh_topic'])) : '';
    $search = isset($_GET['bh_search']) && is_string($_GET['bh_search'])
        ? sanitize_text_field(wp_unslash($_GET['bh_search'])) : '';
    $page = isset($_GET['bh_page']) ? max(1, absint($_GET['bh_page'])) : 1;
    $args = ['post_type' => 'bh_resource', 'post_status' => 'publish',
        'posts_per_page' => $limit, 'paged' => $page, 's' => $search,
        'ignore_sticky_posts' => true];
    if ($topic !== '') {
        $args['tax_query'] = [['taxonomy' => 'bh_support_topic', 'field' => 'slug', 'terms' => [$topic]]];
    }
    $query = new WP_Query($args);
    $topics = get_terms(['taxonomy' => 'bh_support_topic', 'hide_empty' => true]);
    ob_start();
    ?>
    <section class="bh-resource-hub" aria-label="<?php esc_attr_e('Family resources', 'bubbahub'); ?>">
      <form method="get" class="bh-resource-filters" role="search">
        <label for="bh-resource-search"><?php esc_html_e('Search resources', 'bubbahub'); ?></label>
        <input id="bh-resource-search" type="search" name="bh_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search guides and resources', 'bubbahub'); ?>">
        <label for="bh-resource-topic"><?php esc_html_e('Topic', 'bubbahub'); ?></label>
        <select id="bh-resource-topic" name="bh_topic">
          <option value=""><?php esc_html_e('All topics', 'bubbahub'); ?></option>
          <?php if (!is_wp_error($topics)) : foreach ($topics as $term) : ?>
            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($topic, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
          <?php endforeach; endif; ?>
        </select>
        <button type="submit"><?php esc_html_e('Find resources', 'bubbahub'); ?></button>
      </form>
      <div class="bh-resource-grid">
        <?php if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post(); ?>
          <article class="bh-resource-card">
            <?php if (has_post_thumbnail()) : ?>
              <a href="<?php echo esc_url(get_permalink()); ?>"><?php the_post_thumbnail('medium_large', ['loading' => 'lazy']); ?></a>
            <?php endif; ?>
            <h2><a href="<?php echo esc_url(get_permalink()); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
            <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 26)); ?></p>
            <a class="bh-resource-read" href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('Read resource', 'bubbahub'); ?></a>
          </article>
        <?php endwhile; wp_reset_postdata(); else : ?>
          <p><?php esc_html_e('No resources match your search yet.', 'bubbahub'); ?></p>
        <?php endif; ?>
      </div>
      <?php if ($query->max_num_pages > 1) :
        $base = remove_query_arg('bh_page');
        echo '<nav class="bh-resource-pagination" aria-label="' . esc_attr__('Resource pages', 'bubbahub') . '">';
        for ($i = 1; $i <= $query->max_num_pages; $i++) {
            $url = add_query_arg('bh_page', $i, $base);
            echo '<a ' . ($i === $page ? 'aria-current="page" ' : '') . 'href="' . esc_url($url) . '">' . esc_html((string) $i) . '</a> ';
        }
        echo '</nav>';
      endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_action('wp_enqueue_scripts', static function (): void {
    if (!is_singular() || !has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'bh_support_resources')) {
        return;
    }
    wp_register_style('bh-support-resources', false, [], '1.0.0');
    wp_enqueue_style('bh-support-resources');
    wp_add_inline_style('bh-support-resources', '
      .bh-resource-hub{max-width:1200px;margin:auto;color:#243b30}
      .bh-resource-filters{display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin:24px 0}
      .bh-resource-filters label{font-weight:700}
      .bh-resource-filters input,.bh-resource-filters select{min-height:44px;padding:10px;border:1px solid #cbded1;border-radius:12px;background:white}
      .bh-resource-filters button{min-height:44px;padding:10px 18px;border:0;border-radius:12px;background:#174b36;color:white;font-weight:700;cursor:pointer}
      .bh-resource-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:18px}
      .bh-resource-card{padding:20px;border:1px solid #dbe8df;border-radius:18px;background:#f7fbf8;overflow:hidden}
      .bh-resource-card img{width:100%;height:auto;border-radius:12px}
      .bh-resource-card h2{font-size:1.2rem;line-height:1.35}
      .bh-resource-card a{color:#174b36}
      .bh-resource-read{font-weight:700}
      .bh-resource-pagination{margin:24px 0;display:flex;gap:12px}
      @media(max-width:600px){.bh-resource-filters{display:grid}.bh-resource-filters input,.bh-resource-filters select,.bh-resource-filters button{width:100%}}
    ');
});
