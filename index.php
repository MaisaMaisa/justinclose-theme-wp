<?php get_template_part('template-parts/content', 'head'); ?>
<?php
$bio_copy = get_theme_mod( 'justin_bio_copy', '' );   // <-- replaced $site_description block
?>
<div id="bg-hover-img" aria-hidden="true"></div>

<div id="justin-label"><span>Justin</span></div>
<div id="bio-label"><span>Bio</span></div>
<a id="god-mode-btn" href="#" aria-label="Toggle God Mode">(GOD MODE)</a>

<div id="god-mode-overlay" aria-hidden="true">
	<video id="god-mode-video" autoplay loop muted playsinline></video>
</div>

<div id="page">
	<div id="nav-line" aria-label="Project categories"></div>
	<ul id="list"></ul>
</div>

<section id="bio-section" aria-label="Bio section">
	<button id="bio-toggle" type="button" aria-expanded="false">+</button>
	<div class="bio-panel">
        <p class="bio-copy"><?php echo wp_kses_post( $bio_copy ); ?></p>  <!-- <-- replaced esc_html() line -->
	</div>
</section>

<div class="lightbox-overlay" id="lightbox-overlay" aria-hidden="true">
	<button class="lightbox-close" type="button" aria-label="Close lightbox">×</button>
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

<?php wp_footer(); ?>
</body>
</html>