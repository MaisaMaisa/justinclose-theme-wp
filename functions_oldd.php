<?php

if (!function_exists('justin_normalize_image_url')) {
    function justin_normalize_image_url($image) {
        if (is_array($image) && !empty($image['url'])) {
            return $image['url'];
        }

        if (is_numeric($image)) {
            return wp_get_attachment_url((int) $image) ?: '';
        }

        if (is_string($image)) {
            return $image;
        }

        return '';
    }
}

if (!function_exists('justin_extract_image_urls')) {
    function justin_extract_image_urls($images) {
        $urls = [];

        if (!is_array($images)) {
            return $urls;
        }

        foreach ($images as $image) {
            $url = justin_normalize_image_url($image);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}

if (!function_exists('justin_get_meta')) {
    function justin_get_meta($post_id, $key, $default = '') {
        $value = get_post_meta($post_id, $key, true);
        return $value === '' ? $default : $value;
    }
}

if (!function_exists('justin_get_attachment_ids')) {
    function justin_get_attachment_ids($raw_value) {
        if (is_array($raw_value)) {
            $raw_value = implode(',', $raw_value);
        }

        $ids = preg_split('/\s*,\s*/', trim((string) $raw_value), -1, PREG_SPLIT_NO_EMPTY);

        if (!$ids) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $ids)));
    }
}

if (!function_exists('justin_primary_category_name')) {
    function justin_primary_category_name($post_id) {
        $categories = get_the_category($post_id);

        if (empty($categories) || is_wp_error($categories)) {
            return '';
        }

        foreach ($categories as $category) {
            if ($category->slug !== 'uncategorized') {
                return $category->name;
            }
        }

        return $categories[0]->name;
    }
}

if (!function_exists('justin_parse_bool')) {
    function justin_parse_bool($value) {
        return in_array($value, ['1', 1, true, 'true', 'on', 'yes'], true);
    }
}

if (!function_exists('justin_render_media_preview')) {
    function justin_render_media_preview($label, $field_name, $value, $multiple = false) {
        $input_value = $multiple ? (string) $value : (string) absint($value);
        $preview_html = '';

        if ($multiple) {
            $ids = justin_get_attachment_ids($value);
            foreach ($ids as $id) {
                $thumb = wp_get_attachment_image_url($id, 'thumbnail');
                if ($thumb) {
                    $preview_html .= '<img src="' . esc_url($thumb) . '" alt="" />';
                }
            }
        } else {
            $thumb = $value ? wp_get_attachment_image_url(absint($value), 'medium') : '';
            if ($thumb) {
                $preview_html = '<img src="' . esc_url($thumb) . '" alt="" />';
            }
        }

        ?>
        <p><strong><?php echo esc_html($label); ?></strong></p>
        <div class="justin-media-field" data-multiple="<?php echo $multiple ? '1' : '0'; ?>">
            <input type="hidden" class="justin-media-value" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($input_value); ?>" />
            <div class="justin-media-preview"><?php echo $preview_html; ?></div>
            <div class="justin-media-actions">
                <button type="button" class="button justin-media-select"><?php echo $multiple ? 'Choose images' : 'Choose image'; ?></button>
                <button type="button" class="button justin-media-clear">Clear</button>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('justin_register_meta_boxes')) {
    function justin_register_meta_boxes() {
        add_meta_box('justin-project-common', 'Project Common', 'justin_render_project_common_box', 'post', 'normal', 'high');
        add_meta_box('justin-project-gallery', 'Gallery / Visuals', 'justin_render_project_gallery_box', 'post', 'normal', 'default');
        add_meta_box('justin-project-film', 'Film', 'justin_render_project_film_box', 'post', 'normal', 'default');
        add_meta_box('justin-project-books', 'Books', 'justin_render_project_books_box', 'post', 'normal', 'default');
        add_meta_box('justin-project-text', 'Text-only Body', 'justin_render_project_text_box', 'post', 'normal', 'default');
    }
}

if (!function_exists('justin_photo_grid_tags')) {
    function justin_photo_grid_tags() {
        return [
            'Roads', 'Balcony', 'Clouds', 'Street People', 'Nature', 'Food',
            'Rejections', 'Cats', 'Dogs', 'Rocks', 'Architecture', 'Myself',
            'Beds', 'Signs', 'StudioGirls', 'Guys', 'Friends', 'Home',
            'Drawings', 'Other',
        ];
    }
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});

add_action('admin_menu', function () {
    add_theme_page('Justin Settings', 'Justin Settings', 'edit_theme_options', 'justin-settings', 'justin_render_settings_page');
});

add_action('admin_init', function () {
    register_setting('justin_settings_group', 'justin_god_mode_video_url', [
        'type' => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default' => 'https://assets.mixkit.co/videos/1164/1164-720.mp4',
    ]);
});

add_action('add_meta_boxes', 'justin_register_meta_boxes');

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_script('justin-admin-metaboxes', get_template_directory_uri() . '/assets/js/admin-metaboxes.js', ['jquery'], '1.0', true);
    }
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('justin-style', get_stylesheet_uri(), [], '1.1');
    wp_enqueue_style('justin-font', 'https://fonts.googleapis.com/css2?family=Nothing+You+Could+Do&display=swap', [], null);

    wp_enqueue_script('justin-main', get_template_directory_uri() . '/assets/js/main.js', [], '1.1', true);

    $data = [
        'siteTitle' => get_bloginfo('name'),
        'siteDescription' => wp_kses_post( get_theme_mod( 'justin_bio_copy', get_bloginfo('description') ) ),
        'cats' => [],
        'catBios' => [],
        'entries' => [],
        'godModeVideo' => get_option('justin_god_mode_video_url', 'https://assets.mixkit.co/videos/1164/1164-720.mp4'),
        'photoGridTags' => justin_photo_grid_tags(), // NEW
    ];

    $terms = get_categories([
        'orderby' => 'name',
        'hide_empty' => false,
    ]);

    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            if ($term->slug === 'uncategorized') {
                continue;
            }

            $color = get_term_meta($term->term_id, 'justin_cat_color', true);

            $data['cats'][] = [
                'name' => $term->name,
                'slug' => $term->slug,
                'color' => $color ?: '#000000',
            ];

            $data['catBios'][$term->name] = wp_kses_post($term->description);
        }
    }

    $posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    foreach ($posts as $post) {
        $cat_name = justin_primary_category_name($post->ID);
        $images = justin_extract_image_urls(justin_get_attachment_ids(justin_get_meta($post->ID, 'gallery')));
        $film_images = justin_extract_image_urls(justin_get_attachment_ids(justin_get_meta($post->ID, 'film_grabs')));
        $hover_only = justin_parse_bool(justin_get_meta($post->ID, 'use_as_hover_only'));
        $hover_bg_image = absint(justin_get_meta($post->ID, 'hover_bg_image'));
        // NEW: photo grid images with their tags
        $gallery_tags_raw = justin_get_meta($post->ID, 'gallery_image_tags', '{}');
        $gallery_tags_map = json_decode($gallery_tags_raw, true);
        if (!is_array($gallery_tags_map)) {
            $gallery_tags_map = [];
        }

        $photo_grid_images = [];
        foreach (justin_get_attachment_ids(justin_get_meta($post->ID, 'gallery')) as $attachment_id) {
            $url = justin_normalize_image_url($attachment_id);
            if (!$url) {
                continue;
            }
            $photo_grid_images[] = [
                'url' => $url,
                'tags' => isset($gallery_tags_map[$attachment_id]) ? array_values($gallery_tags_map[$attachment_id]) : [],
            ];
        }

        $entry = [
            'id' => $post->ID,
            'text' => get_the_title($post),
            'cat' => $cat_name,
            'info' => wp_kses_post(justin_get_meta($post->ID, 'info_text')),
            'images' => $images,
            'body' => wp_kses_post(justin_get_meta($post->ID, 'body_text')),
            'bgImage' => $hover_only && $hover_bg_image ? justin_normalize_image_url($hover_bg_image) : '',
            'hoverOnly' => $hover_only,
            'layoutStyle' => justin_get_meta($post->ID, 'layout_style', 'standard'),
            'photoGrid' => $photo_grid_images, // NEW
            'videoUrl' => trim((string) justin_get_meta($post->ID, 'film_video_url')), // NEW
            'book' => null,
            'film' => null,
        ];

        if ($cat_name === 'Film') {
            $entry['film'] = [
                'images' => !empty($film_images) ? $film_images : $images,
                'videoUrl' => trim((string) justin_get_meta($post->ID, 'film_video_url')),
            ];
            $entry['images'] = $entry['film']['images'];
        }

        if ($cat_name === 'Books') {
            $book_images = justin_extract_image_urls(justin_get_attachment_ids(justin_get_meta($post->ID, 'book_images')));
            $teaser_images = justin_extract_image_urls(justin_get_attachment_ids(justin_get_meta($post->ID, 'teaser_images')));
            $has_teaser = justin_parse_bool(justin_get_meta($post->ID, 'has_teaser'));

            $entry['book'] = [
                'images' => $book_images,
                'text' => wp_kses_post(justin_get_meta($post->ID, 'book_text')),
                'buyUrl' => esc_url_raw(justin_get_meta($post->ID, 'buy_url')),
                'teasers' => $has_teaser ? $teaser_images : [],
                'teaserText' => $has_teaser ? wp_kses_post(justin_get_meta($post->ID, 'teaser_text')) : '',
            ];
        }

        $data['entries'][] = $entry;
    }

    wp_localize_script('justin-main', 'JUSTIN_DATA', $data);
});

add_action('save_post_post', function ($post_id) {
    if (!isset($_POST['justin_project_meta_nonce']) || !wp_verify_nonce($_POST['justin_project_meta_nonce'], 'justin_save_project_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $text_fields = ['info_text', 'film_video_url', 'body_text', 'book_text', 'buy_url', 'teaser_text'];
    foreach ($text_fields as $field_name) {
        if (isset($_POST[$field_name])) {
            $value = wp_unslash($_POST[$field_name]);
            $value = ($field_name === 'buy_url') ? esc_url_raw($value) : sanitize_text_field($value);
            if ($field_name === 'info_text' || $field_name === 'body_text' || $field_name === 'book_text' || $field_name === 'teaser_text') {
                $value = wp_kses_post(wp_unslash($_POST[$field_name]));
            }
            if ($field_name === 'film_video_url') {
                $value = esc_url_raw($value);
            }
            update_post_meta($post_id, $field_name, $value);
        }
    }

    $image_fields = ['hover_bg_image', 'gallery', 'film_grabs', 'book_images', 'teaser_images'];
    foreach ($image_fields as $field_name) {
        if (isset($_POST[$field_name])) {
            $value = sanitize_text_field(wp_unslash($_POST[$field_name]));
            update_post_meta($post_id, $field_name, $value);
        }
    }

    update_post_meta($post_id, 'use_as_hover_only', isset($_POST['use_as_hover_only']) ? '1' : '0');
    update_post_meta($post_id, 'has_teaser', isset($_POST['has_teaser']) ? '1' : '0');

    // NEW
    if (isset($_POST['layout_style'])) {
        $layout_style = sanitize_text_field(wp_unslash($_POST['layout_style']));
        if (!in_array($layout_style, ['standard', 'grid_hover', 'photo_grid', 'video_direct'], true)) {
            $layout_style = 'standard';
        }
        update_post_meta($post_id, 'layout_style', $layout_style);
    }

    // NEW: per-image tags for photo grid
    if (isset($_POST['gallery_image_tags'])) {
        $raw_json = wp_unslash($_POST['gallery_image_tags']);
        $decoded = json_decode($raw_json, true);
        $clean = [];

        if (is_array($decoded)) {
            $valid_tags = justin_photo_grid_tags();
            foreach ($decoded as $image_id => $tags) {
                $image_id = absint($image_id);
                if (!$image_id || !is_array($tags)) {
                    continue;
                }
                $filtered = array_values(array_intersect($valid_tags, $tags));
                if ($filtered) {
                    $clean[$image_id] = $filtered;
                }
            }
        }

        update_post_meta($post_id, 'gallery_image_tags', wp_json_encode($clean));
    }
});

add_action('created_category', 'justin_save_category_color');
add_action('edited_category', 'justin_save_category_color');

function justin_save_category_color($term_id) {
    if (!isset($_POST['justin_cat_color_nonce']) || !wp_verify_nonce($_POST['justin_cat_color_nonce'], 'justin_save_cat_color')) {
        return;
    }

    if (!current_user_can('manage_categories')) {
        return;
    }

    $color = isset($_POST['justin_cat_color']) ? sanitize_hex_color(wp_unslash($_POST['justin_cat_color'])) : '';

    if ($color) {
        update_term_meta($term_id, 'justin_cat_color', $color);
    } else {
        delete_term_meta($term_id, 'justin_cat_color');
    }
}

add_action('category_add_form_fields', function () {
    ?>
    <div class="form-field term-group">
        <label for="justin_cat_color">Category Color</label>
        <input type="color" id="justin_cat_color" name="justin_cat_color" value="#000000" />
        <p class="description">Color used in the project navigation.</p>
    </div>
    <?php wp_nonce_field('justin_save_cat_color', 'justin_cat_color_nonce'); ?>
    <?php
});

add_action('category_edit_form_fields', function ($term) {
    $color = get_term_meta($term->term_id, 'justin_cat_color', true) ?: '#000000';
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="justin_cat_color">Category Color</label></th>
        <td>
            <input type="color" id="justin_cat_color" name="justin_cat_color" value="<?php echo esc_attr($color); ?>" />
            <p class="description">Color used in the project navigation.</p>
            <?php wp_nonce_field('justin_save_cat_color', 'justin_cat_color_nonce'); ?>
        </td>
    </tr>
    <?php
});

function justin_render_project_common_box($post) {
    wp_nonce_field('justin_save_project_meta', 'justin_project_meta_nonce');
    $info_text = justin_get_meta($post->ID, 'info_text');
    $hover_bg_image = justin_get_meta($post->ID, 'hover_bg_image');
    $use_as_hover_only = justin_parse_bool(justin_get_meta($post->ID, 'use_as_hover_only'));
    $layout_style = justin_get_meta($post->ID, 'layout_style', 'standard'); // NEW
    ?>
    <p>Use this for all project types.</p>
    <p>
        <label for="info_text"><strong>Info text</strong></label><br />
        <textarea name="info_text" id="info_text" rows="4" style="width:100%;"><?php echo esc_textarea($info_text); ?></textarea>
    </p>
    <?php justin_render_media_preview('Hover background image', 'hover_bg_image', $hover_bg_image, false); ?>
    <p>
        <label>
            <input type="checkbox" name="use_as_hover_only" value="1" <?php checked($use_as_hover_only); ?> />
            Use as hover-only item
        </label>
    </p>
    <p>
    <label for="layout_style"><strong>Lightbox Layout</strong></label><br />
        <select name="layout_style" id="layout_style">
            <option value="standard" <?php selected($layout_style, 'standard'); ?>>Standard (image slideshow)</option>
            <option value="grid_hover" <?php selected($layout_style, 'grid_hover'); ?>>Grid + Hover Preview (Photography)</option>
            <option value="photo_grid" <?php selected($layout_style, 'photo_grid'); ?>>Photo Grid (Misc)</option>
            <option value="video_direct" <?php selected($layout_style, 'video_direct'); ?>>Video (Film)</option>
        </select>
    </p>
    <?php
}

function justin_render_project_gallery_box($post) {
    $gallery = justin_get_meta($post->ID, 'gallery');
    $gallery_tags_json = justin_get_meta($post->ID, 'gallery_image_tags', '{}');
    $all_tags = justin_photo_grid_tags();
    ?>
    <p>Used for Photography, Painting, and Collage posts.</p>
    <?php justin_render_media_preview('Gallery images', 'gallery', $gallery, true); ?>

    <div id="justin-gallery-tag-assign" data-tags='<?php echo esc_attr(wp_json_encode($all_tags)); ?>'>
        <p><strong>Photo Grid tags</strong> <span style="color:#777;">(only used when Lightbox Layout = Photo Grid)</span></p>
        <input type="hidden" id="gallery_image_tags" name="gallery_image_tags" value='<?php echo esc_attr($gallery_tags_json); ?>' />
        <div class="justin-gallery-tag-list"></div>
    </div>
    <?php
}

function justin_render_project_film_box($post) {
    $film_grabs = justin_get_meta($post->ID, 'film_grabs');
    $film_video_url = justin_get_meta($post->ID, 'film_video_url');
    ?>
    <p>
        <label for="film_video_url"><strong>Vimeo or YouTube URL</strong></label><br />
        <input type="url" name="film_video_url" id="film_video_url" value="<?php echo esc_attr($film_video_url); ?>" style="width:100%;" placeholder="https://vimeo.com/... or https://youtube.com/watch?v=..." />
    </p>
    <?php justin_render_media_preview('Film grabs', 'film_grabs', $film_grabs, true); ?>
    <?php
}

function justin_render_project_books_box($post) {
    $book_images = justin_get_meta($post->ID, 'book_images');
    $book_text = justin_get_meta($post->ID, 'book_text');
    $buy_url = justin_get_meta($post->ID, 'buy_url');
    $has_teaser = justin_parse_bool(justin_get_meta($post->ID, 'has_teaser'));
    $teaser_images = justin_get_meta($post->ID, 'teaser_images');
    $teaser_text = justin_get_meta($post->ID, 'teaser_text');
    ?>
    <p>Use this for Books posts.</p>
    <?php justin_render_media_preview('Book images', 'book_images', $book_images, true); ?>
    <p>
        <label for="book_text"><strong>Book text</strong></label><br />
        <textarea name="book_text" id="book_text" rows="4" style="width:100%;"><?php echo esc_textarea($book_text); ?></textarea>
    </p>
    <p>
        <label for="buy_url"><strong>Buy URL</strong></label><br />
        <input type="url" name="buy_url" id="buy_url" value="<?php echo esc_attr($buy_url); ?>" style="width:100%;" />
    </p>
    <p>
        <label>
            <input type="checkbox" name="has_teaser" value="1" <?php checked($has_teaser); ?> />
            Enable teaser block
        </label>
    </p>
    <?php justin_render_media_preview('Teaser images', 'teaser_images', $teaser_images, true); ?>
    <p>
        <label for="teaser_text"><strong>Teaser text</strong></label><br />
        <textarea name="teaser_text" id="teaser_text" rows="4" style="width:100%;"><?php echo esc_textarea($teaser_text); ?></textarea>
    </p>
    <?php
}

function justin_render_project_text_box($post) {
    $body_text = justin_get_meta($post->ID, 'body_text');
    ?>
    <p>Use this for Text posts.</p>
    <p>
        <label for="body_text"><strong>Body text</strong></label><br />
        <textarea name="body_text" id="body_text" rows="8" style="width:100%;"><?php echo esc_textarea($body_text); ?></textarea>
    </p>
    <?php
}

function justin_render_site_settings_box() {
    ?>
    <p>Use the Justin Settings page in Appearance for site-wide options.</p>
    <?php
}

function justin_render_settings_page() {
    $value = get_option('justin_god_mode_video_url', 'https://assets.mixkit.co/videos/1164/1164-720.mp4');
    ?>
    <div class="wrap">
        <h1>Justin Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('justin_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="justin_god_mode_video_url">God Mode video URL</label></th>
                    <td>
                        <input type="url" id="justin_god_mode_video_url" name="justin_god_mode_video_url" value="<?php echo esc_attr($value); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function justin_bio_customizer( $wp_customize ) {
    $wp_customize->add_section( 'justin_bio_section', array(
        'title'    => 'Bio Copy',
        'priority' => 30,
    ) );

    $wp_customize->add_setting( 'justin_bio_copy', array(
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post', // allows <a>, <em>, <strong>, etc. but strips dangerous tags/scripts
        'transport'         => 'refresh',
    ) );

    $wp_customize->add_control( new WP_Customize_Control(
        $wp_customize,
        'justin_bio_copy_control',
        array(
            'label'    => 'Bio text (HTML links allowed, e.g. <a href="https://example.com">text</a>)',
            'section'  => 'justin_bio_section',
            'settings' => 'justin_bio_copy',
            'type'     => 'textarea',
        )
    ) );
}
add_action( 'customize_register', 'justin_bio_customizer' );