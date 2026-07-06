<footer>
	<?php 
		wp_nav_menu(
			array(
				'menu' => 'Bottom Menu',
				'container' => '',
				'theme_location' => 'bottom',
				'items_wrap' => '<ul class="bottom-menu">%3$s</ul>'
			)
		);
	?>
</footer>
   
<?php wp_footer(); ?>

</body>
</html>