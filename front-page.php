<?php get_template_part('template-parts/content', 'head'); ?>
<div id="bg-hover-img" aria-hidden="true"></div>

<div id="justin-label"><span>Justin</span></div>
<!-- <div id="bio-label"><span>Bio</span></div> -->
<!-- <a id="god-mode-btn" href="#" aria-label="Toggle God Mode">(GOD MODE)</a> -->

<a id="god-mode-btn" href="#" aria-label="Toggle God Mode">
    <?php echo justin_render_circular_text_button('GOD MODE', ['id' => 'god-mode-circ']); ?>
</a>

<div id="god-mode-overlay" aria-hidden="true">
	 <div id="god-mode-frame"></div>
</div>

<div id="page">
	<div id="nav-line" aria-label="Project categories"></div>
	<ul id="list"></ul>
</div>

<div class="lightbox-overlay" id="lightbox-overlay" aria-hidden="true">
	<button class="lightbox-close" type="button" aria-label="Close lightbox">
		<?php echo justin_render_circular_text_button('CLOSE', ['id' => 'lightbox-close-circ', 'size' => 70]); ?>
	</button>
	<div class="lightbox-main">
		<button class="lightbox-arrow prev" type="button" aria-label="Previous image">‹</button>
		<div id="lightbox-stage"></div>
		<button class="lightbox-arrow next" type="button" aria-label="Next image">›</button>
	</div>
	<div class="lightbox-infobar">
		<button class="lightbox-info-toggle" type="button" aria-label="Toggle info">ⓘ</button>
	</div>
	<div class="lightbox-info-panel" id="lightbox-info-panel"></div>
	<div class="lightbox-thumbs" id="lightbox-thumbs"></div>
</div>

<footer id="site-footer" class="site-footer">
    <?php if (is_active_sidebar('footer-widgets')) : ?>
        <?php dynamic_sidebar('footer-widgets'); ?>
    <?php endif; ?>
</footer>
   
<?php wp_footer(); ?>
</body>
</html>