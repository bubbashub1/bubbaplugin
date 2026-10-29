<?php
/**
 * Plugin Name: Bubba Hub Google Analytics
 * Description: Adds the Bubba Hub GA4 tag to WordPress pages.
 */
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-PPG9B0GHN2"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-PPG9B0GHN2');
    </script>
    <?php
}, 20);
