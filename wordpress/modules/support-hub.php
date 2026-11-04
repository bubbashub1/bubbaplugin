<?php
/**
 * Combined Bubba Hub Support & Guidance landing page.
 * Uses the existing resource and article shortcodes and does not touch private messaging.
 */
defined('ABSPATH') || exit;

add_shortcode('bh_support_hub', static function (): string {
    ob_start();
    ?>
    <div class="bh-support-landing">
      <header class="bh-support-landing__hero">
        <span class="bh-support-landing__eyebrow"><?php esc_html_e('Bubba Hub · Devon & Cornwall', 'bubbahub'); ?></span>
        <h1><?php esc_html_e('Support & Guidance for family life', 'bubbahub'); ?></h1>
        <p><?php esc_html_e('Explore practical resources, trusted information and articles from our community.', 'bubbahub'); ?></p>
      </header>
      <nav class="bh-support-landing__links" aria-label="<?php esc_attr_e('Support sections', 'bubbahub'); ?>">
        <a href="#bh-family-resources"><strong><?php esc_html_e('Family resources', 'bubbahub'); ?></strong><span><?php esc_html_e('Guides, useful links and downloads', 'bubbahub'); ?></span></a>
        <a href="#bh-family-articles"><strong><?php esc_html_e('Blogs & articles', 'bubbahub'); ?></strong><span><?php esc_html_e('Ideas, experiences and advice', 'bubbahub'); ?></span></a>
        <a href="#bh-website-help"><strong><?php esc_html_e('Website help', 'bubbahub'); ?></strong><span><?php esc_html_e('Help using your Bubba Hub account', 'bubbahub'); ?></span></a>
      </nav>
      <section id="bh-family-resources" class="bh-support-landing__section">
        <h2><?php esc_html_e('Family resources', 'bubbahub'); ?></h2>
        <?php echo do_shortcode('[bh_support_resources limit="6"]'); ?>
      </section>
      <section id="bh-family-articles" class="bh-support-landing__section">
        <h2><?php esc_html_e('Blogs & articles', 'bubbahub'); ?></h2>
        <?php echo do_shortcode('[bh_support_articles limit="6"]'); ?>
      </section>
      <section id="bh-website-help" class="bh-support-landing__section">
        <h2><?php esc_html_e('Help using Bubba Hub', 'bubbahub'); ?></h2>
        <p><?php esc_html_e('Need help with your account, activities, planner or a class listing?', 'bubbahub'); ?></p>
        <a class="bh-support-landing__button" href="<?php echo esc_url(home_url('/support/website/')); ?>"><?php esc_html_e('Website help & FAQs', 'bubbahub'); ?></a>
      </section>
      <aside class="bh-support-landing__notice">
        <strong><?php esc_html_e('Important information', 'bubbahub'); ?></strong>
        <p><?php esc_html_e('Community articles and resources are for general information, not a replacement for professional health or emergency advice. For urgent help, contact an appropriate professional or emergency service.', 'bubbahub'); ?></p>
      </aside>
    </div>
    <?php
    return (string) ob_get_clean();
});

add_action('wp_enqueue_scripts', static function (): void {
    if (!is_singular() || !has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'bh_support_hub')) return;
    wp_register_style('bh-support-landing', false, [], '1.0.0');
    wp_enqueue_style('bh-support-landing');
    wp_add_inline_style('bh-support-landing', '
      .bh-support-landing{max-width:1240px;margin:0 auto;padding:12px 18px;color:#213d30}
      .bh-support-landing__hero{background:linear-gradient(135deg,#eaf5ec,#f8f7ee);border-radius:24px;padding:clamp(28px,5vw,64px);margin-bottom:24px}
      .bh-support-landing__hero h1{font-size:clamp(2rem,4vw,3.2rem);line-height:1.12;max-width:780px;margin:12px 0}
      .bh-support-landing__hero p{font-size:1.1rem;max-width:680px}
      .bh-support-landing__eyebrow{font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#276345}
      .bh-support-landing__links{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:32px}
      .bh-support-landing__links a{display:flex;flex-direction:column;gap:8px;padding:22px;background:#f6faf7;border:1px solid #d7e7dc;border-radius:18px;color:#174b36;text-decoration:none}
      .bh-support-landing__links strong{font-size:1.2rem}
      .bh-support-landing__links span{color:#52665b}
      .bh-support-landing__section{padding:24px 0;scroll-margin-top:90px}
      .bh-support-landing__section>h2{font-size:clamp(1.5rem,3vw,2rem)}
      .bh-support-landing__button{display:inline-block;background:#174b36;color:#fff;padding:12px 20px;border-radius:12px;text-decoration:none}
      .bh-support-landing__notice{border-left:4px solid #356c4b;background:#f4f8f4;padding:18px 22px;margin:24px 0;border-radius:8px}
      @media(max-width:700px){.bh-support-landing__links{grid-template-columns:1fr}.bh-support-landing{padding:8px}}
    ');
});
