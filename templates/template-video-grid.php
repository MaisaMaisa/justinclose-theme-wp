<?php
/**
 * Template Name: Video Grid
 *
 * Assign this template to a page from the "Page Attributes" box in the
 * block/classic editor. Grid items themselves are managed under the
 * "Video Grid Items" menu added by the VIDEO GRID block in functions.php.
 */
get_template_part('template-parts/content', 'head');
get_header();

$items = get_posts(
	array(
		'post_type'      => 'grid_item',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	)
);
 
$all_terms = get_terms(
	array(
		'taxonomy'   => 'grid_tag',
		'hide_empty' => true,
	)
);
if ( is_wp_error( $all_terms ) ) {
	$all_terms = array();
}
?>
 
<div class="pg-wrap" id="pg-wrap">
 
	<div class="pg-tagbar" id="pg-tagbar">
		<?php foreach ( $all_terms as $term ) : ?>
			<button type="button" class="pg-tag-btn" data-tag="<?php echo esc_attr( $term->slug ); ?>">
				<?php echo esc_html( $term->name ); ?>
			</button>
		<?php endforeach; ?>
		<button type="button" class="pg-reset" id="pg-reset">Reset</button>
	</div>
 
	<div class="pg-grid" id="pg-grid">
		<?php if ( ! $items ) : ?>
			<div class="pg-empty">No grid items yet. Add some under "Video Grid Items" in the admin menu.</div>
		<?php endif; ?>
 
		<?php foreach ( $items as $item ) :
			$video_url    = get_post_meta( $item->ID, '_vgi_video_url', true );
			$external_url = get_post_meta( $item->ID, '_vgi_external_link', true );
			$description  = get_post_meta( $item->ID, '_vgi_description', true );
			$platform     = vgi_detect_platform( $video_url );
 
			$terms     = get_the_terms( $item->ID, 'grid_tag' );
			$tag_slugs = array();
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$tag_slugs[] = $t->slug;
				}
			}
 
			$thumbnail_id      = absint( get_post_meta( $item->ID, '_vgi_thumbnail_id', true ) );
			$custom_thumbnail  = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'large' ) : '';
			$featured_fallback = has_post_thumbnail( $item->ID ) ? get_the_post_thumbnail_url( $item->ID, 'large' ) : '';
			$picked_thumbnail  = $custom_thumbnail ? $custom_thumbnail : $featured_fallback;
 
			$thumbnail  = '';
			$embed_html = '';
			// Clicking prefers the external link if one was set, otherwise
			// falls back to the video URL itself (for "other" platforms this
			// just opens the video/post URL directly in a new tab).
			$click_href = $external_url ? $external_url : $video_url;
 
			if ( 'youtube' === $platform ) {
				// Auto thumbnail, but let a manually-picked one override it.
				$thumbnail  = $picked_thumbnail ? $picked_thumbnail : vgi_youtube_thumbnail( $video_url );
				$embed_html = vgi_youtube_embed_html( $video_url );
			} elseif ( 'instagram' === $platform ) {
				$embed_html = vgi_instagram_embed_html( $video_url );
				$thumbnail  = $picked_thumbnail;
			} else {
				// "other" or "none": plain link tile, no auto embed.
				$thumbnail = $picked_thumbnail;
			}
 
			$tile_classes = array( 'pg-tile', 'pg-tile-' . $platform );
			if ( ! $thumbnail ) {
				$tile_classes[] = 'pg-tile-empty';
			}
 
			$youtube_id = ( 'youtube' === $platform ) ? vgi_youtube_id( $video_url ) : '';
			?>
			<div
				class="<?php echo esc_attr( implode( ' ', $tile_classes ) ); ?>"
				data-tags="<?php echo esc_attr( implode( ' ', $tag_slugs ) ); ?>"
				data-platform="<?php echo esc_attr( $platform ); ?>"
				<?php if ( $youtube_id ) : ?>data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>"<?php endif; ?>
				<?php if ( $embed_html ) : ?>data-embed="<?php echo esc_attr( $embed_html ); ?>"<?php endif; ?>
				<?php if ( $click_href && ! $embed_html ) : ?>data-link="<?php echo esc_url( $click_href ); ?>"<?php endif; ?>
				tabindex="0" role="button"
				aria-label="<?php echo esc_attr( get_the_title( $item ) ); ?>"
			>
				<?php if ( $thumbnail ) : ?>
					<img class="pg-media" src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $item ) ); ?>" loading="lazy">
					<?php if ( $embed_html ) : ?>
						<span class="pg-play-icon" aria-hidden="true">&#9658;</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="pg-empty-label"><?php echo esc_html( get_the_title( $item ) ); ?></span>
				<?php endif; ?>
 
				<?php if ( $description ) : ?>
					<div class="pg-tile-caption"><?php echo esc_html( $description ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
 
<!-- Lightbox used for YouTube / Instagram playback -->
<div class="vgi-lightbox" id="vgi-lightbox" aria-hidden="true">
	<button type="button" class="vgi-lightbox-close" id="vgi-lightbox-close" aria-label="Close">&times;</button>
	<div class="vgi-lightbox-inner" id="vgi-lightbox-inner"></div>
</div>
 
<?php get_footer('videopage'); ?>