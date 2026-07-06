<?php get_template_part('template-parts/content', 'head'); ?>
<?php get_header('page'); ?>
<?php
	if( have_posts() ){
		while(have_posts()){
			the_post();
			get_template_part('template-parts/content', 'page');
		}
	}
?>
<?php get_footer(); ?>