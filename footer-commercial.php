<footer id="site-footer" class="site-footer site-footer--commercial">
    <?php if (is_active_sidebar('footer-commercial-widgets')) : ?>
        <?php dynamic_sidebar('footer-commercial-widgets'); ?>
    <?php else : ?>
        <?php // Fallback so the page is never a dead end if no widgets are set ?>
        <div class="footer-widget">
            <span class="footer-eyes-link-wrap">
                <a class="footer-eyes-link footer-eyes-link--video-home" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr__( 'Back to homepage', 'justin' ); ?>">👀</a>
            </span>
        </div>
    <?php endif; ?>
</footer>

<?php wp_footer(); ?>

</body>
</html>