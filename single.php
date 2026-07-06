<?php get_template_part('template-parts/content', 'head'); ?>
<?php get_header('collectible'); ?>

<?php
	if( have_posts() ){
		while(have_posts()){
			the_post();
			get_template_part('template-parts/content', 'article');
		}
	}
?>