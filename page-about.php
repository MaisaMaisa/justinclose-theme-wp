<?php get_template_part('template-parts/content', 'head'); ?>
<?php
/* Template Name: About Me Page */
get_header('page'); ?>
<div class="abt-content-container">
    <?php the_content();?>
    <div class="copyright">
      <span><?php echo wp_kses_post(get_theme_mod('footer_copyright_text', '&copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.')); ?></span>
    </div>
</div>

<?php get_footer(); ?>