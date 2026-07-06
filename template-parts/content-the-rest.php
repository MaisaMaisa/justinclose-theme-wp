<div class="the-rest-n-copyright">
<div class="page-container the-rest-container">
    <?php get_template_part('template-parts/content', 'breakbtn'); ?>
    <?php
    $args = array(
        'post_type' => 'the_rest',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    );

    $the_rest_query = new WP_Query($args);

    if ($the_rest_query->have_posts()):
        while ($the_rest_query->have_posts()):
            $the_rest_query->the_post();
            // Retrieve the slideshow images
            $image_ids = get_post_meta(get_the_ID(), '_therest_slideshow_images', true);
            $image_ids = is_array($image_ids) ? $image_ids : array();
            ?>

            <div class="rest-item">
                <div class="rest-summary">
                    <h3>
                        <?php the_title(); ?>
                    </h3>
                </div>
                <div class="rest-content" style="display: none;">
                    <div class="short-long">
                        <!-- Display the meta fields -- REMOVE THIS WHEN I GET BACK TO LA AND CHANGE THE LAYOUT IN CSS -->
                        <div class="the-rest-meta"></div>
                        <div class="rest-details">
                            <?php the_content(); ?>
                        </div>
                    </div>

                    <!-- Horizontal Slideshow -->
                    <?php if (!empty($image_ids)): ?>
                        <div class="horizontal-slideshow">
                            <div class="slides-container">
                                <?php foreach ($image_ids as $image_id): ?>
                                    <div class="slide">
                                        <?php echo wp_get_attachment_image($image_id, 'large'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="slideshow-arrow mobile-off">swipe <span>←</span></div>

                    <?php endif; ?>

                </div>
            </div>

        <?php endwhile;
        wp_reset_postdata();
    else: ?>
        <p>No items found.</p>
    <?php endif; ?>
</div>
</div>