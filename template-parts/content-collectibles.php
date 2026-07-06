<div class="collectibles-page">
    <div class="posts-list">
    <?php
        // Custom query to fetch all posts
        $args = array(
            'post_type'      => 'post', // Adjust post type if needed
            'posts_per_page' => -1,     // Fetch all posts
            'post_status'    => 'publish', // Only published posts
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) : 
            while ($query->have_posts()) : $query->the_post();
                $categories = wp_get_post_categories(get_the_ID(), array('fields' => 'slugs'));
                $is_frame_category = in_array('frame', $categories); // Check if "frame" is one of the categories

                // Get custom fields for email subject and body
                $email_subject = get_post_meta(get_the_ID(), 'email_subject', true) ?: 'Inquiry about ' . get_the_title();
                $email_body = get_post_meta(get_the_ID(), 'email_body', true) ?: 'I am interested in the item titled "' . get_the_title() . '". Please provide more details.';
                ?>
                <div class="post-item" data-categories="<?php echo esc_attr(implode(' ', $categories)); ?>">
                    <div class="post-thumbnail">
                        <?php if ($is_frame_category): ?>
                            <!-- Generate a mailto link for posts in the "frame" category -->
                            <a href="mailto:info@lauramrksa.com?subject=<?php echo urlencode($email_subject); ?>&body=<?php echo urlencode($email_body); ?>">
                                <?php the_post_thumbnail('medium'); ?>
                            </a>
                        <?php else: ?>
                            <!-- Default permalink for other posts -->
                            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium'); ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="post-title">
                        <h2><?php the_title(); ?></h2>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else : ?>
            <p>No posts found.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>
</div>