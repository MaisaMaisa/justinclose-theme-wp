<footer id="site-footer" class="site-footer">
    <?php if (is_active_sidebar('footer-widgets')) : ?>
        <?php dynamic_sidebar('footer-widgets'); ?>
    <?php endif; ?>
</footer>
   
<?php wp_footer(); ?>

</body>
</html>