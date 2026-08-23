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

    if (!function_exists('justin_render_circular_text_button')) {
    /**
     * Renders a circular button: text curved around a ring, with an X
     * in the center. Reusable anywhere — just pass different $text and
     * a unique 'id' (required whenever more than one appears on a page,
     * since SVG <textPath> needs a unique path id per instance).
     */
        function justin_render_circular_text_button($text, $args = []) {
            $defaults = [
                'id'             => 'circ-btn-' . wp_unique_id(),
                'size'           => 70,   // overall button diameter in px
                'class'          => '',
                'letter_spacing' => 3.5,  // px between letters — tune per text length
                'start_offset'   => '75%', // where the text begins along the ring
                'font_size'      => 11,
            ];
            $args = wp_parse_args($args, $defaults);

            $id     = esc_attr($args['id']);
            $size   = (int) $args['size'];
            $cx     = $size / 2;
            $cy     = $size / 2;
            $radius = $size / 2 - 10; // leaves room for the ring stroke + text

            ob_start();
            ?>
            <span class="justin-circ-btn <?php echo esc_attr($args['class']); ?>" style="--circ-size: <?php echo $size; ?>px;">
                <svg viewBox="0 0 <?php echo $size; ?> <?php echo $size; ?>" width="<?php echo $size; ?>" height="<?php echo $size; ?>" overflow="visible">
                    <defs>
                        <path id="<?php echo $id; ?>-path"
                            d="M <?php echo $cx; ?>,<?php echo $cy; ?>
                            m -<?php echo $radius; ?>,0
                            a <?php echo $radius; ?>,<?php echo $radius; ?> 0 1,1 <?php echo $radius * 2; ?>,0
                            a <?php echo $radius; ?>,<?php echo $radius; ?> 0 1,1 -<?php echo $radius * 2; ?>,0" />
                    </defs>

                    <circle class="justin-circ-btn-ring"
                        cx="<?php echo $cx; ?>" cy="<?php echo $cy; ?>" r="<?php echo $radius + 7; ?>" />

                    <text class="justin-circ-btn-text" style="font-size:<?php echo (int) $args['font_size']; ?>px; letter-spacing:<?php echo esc_attr($args['letter_spacing']); ?>px;">
                        <textPath href="#<?php echo $id; ?>-path" startOffset="<?php echo esc_attr($args['start_offset']); ?>" text-anchor="middle">
                            <?php echo esc_html($text); ?>
                        </textPath>
                    </text>

                    <line class="justin-circ-btn-x" x1="<?php echo $cx - 6; ?>" y1="<?php echo $cy - 6; ?>" x2="<?php echo $cx + 6; ?>" y2="<?php echo $cy + 6; ?>" />
                    <line class="justin-circ-btn-x" x1="<?php echo $cx + 6; ?>" y1="<?php echo $cy - 6; ?>" x2="<?php echo $cx - 6; ?>" y2="<?php echo $cy + 6; ?>" />
                </svg>
            </span>
            <?php
            return ob_get_clean();
        }
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

if (!function_exists('justin_layout_variant_from_style')) {
    function justin_layout_variant_from_style($layout_style) {
        $map = [
            'grid_hover'          => 'photography',
            'grid_hover_painting' => 'painting',
            'grid_hover_collage'  => 'collage',
        ];

        return $map[$layout_style] ?? '';
    }
}

//FOOTER WIDGETS

class Justin_Social_Links_Widget extends WP_Widget {
    const MAX_LINKS = 6;

    public function __construct() {
        parent::__construct(
            'justin_social_links_widget',
            'Justin: Social Links (Info Icon)',
            ['description' => 'Info icon that opens a popup with social/contact links.']
        );
    }

    public function widget($args, $instance) {
        $links = [];
        for ($i = 0; $i < self::MAX_LINKS; $i++) {
            $label = trim($instance['label_' . $i] ?? '');
            $url   = trim($instance['url_' . $i] ?? '');
            if ($label !== '' && $url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }

        if (!$links) {
            return;
        }

        echo $args['before_widget']; ?>
        <button type="button" class="footer-info-toggle" aria-haspopup="true" aria-expanded="false" aria-label="Social links">ⓘ</button>
        <div class="footer-info-popup" hidden>
            <ul>
                <?php foreach ($links as $link) : ?>
                    <li><a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($link['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php echo $args['after_widget'];
    }

    public function form($instance) { ?>
        <p>Up to <?php echo self::MAX_LINKS; ?> links. Leave a label blank to skip that row. Use <code>mailto:you@example.com</code> for email.</p>
        <?php for ($i = 0; $i < self::MAX_LINKS; $i++) :
            $label = esc_attr($instance['label_' . $i] ?? '');
            $url   = esc_attr($instance['url_' . $i] ?? ''); ?>
            <p style="border-top:1px solid #ddd;padding-top:8px;">
                <label for="<?php echo $this->get_field_id('label_' . $i); ?>">Label</label>
                <input class="widefat" type="text" id="<?php echo $this->get_field_id('label_' . $i); ?>" name="<?php echo $this->get_field_name('label_' . $i); ?>" value="<?php echo $label; ?>" placeholder="instagram" />
                <label for="<?php echo $this->get_field_id('url_' . $i); ?>">URL</label>
                <input class="widefat" type="text" id="<?php echo $this->get_field_id('url_' . $i); ?>" name="<?php echo $this->get_field_name('url_' . $i); ?>" value="<?php echo $url; ?>" placeholder="https://instagram.com/you or mailto:you@example.com" />
            </p>
        <?php endfor;
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        for ($i = 0; $i < self::MAX_LINKS; $i++) {
            $instance['label_' . $i] = sanitize_text_field($new_instance['label_' . $i] ?? '');
            $instance['url_' . $i]   = esc_url_raw(trim((string) ($new_instance['url_' . $i] ?? '')));
        }
        return $instance;
    }
}

class Justin_Eyes_Link_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'justin_eyes_link_widget',
            'Justin: Eyes Emoji Link',
            ['description' => '👀 emoji linking to a page you choose.']
        );
    }

    public function widget($args, $instance) {
        $page_id = absint($instance['page_id'] ?? 0);
        if (!$page_id || get_post_status($page_id) !== 'publish') {
            return;
        }
        $url = get_permalink($page_id);
        if (!$url) {
            return;
        }

        echo $args['before_widget'];
        printf(
            '<a class="footer-eyes-link" href="%s" aria-label="%s">👀</a>',
            esc_url($url),
            esc_attr(get_the_title($page_id))
        );
        echo $args['after_widget'];
    }

    public function form($instance) {
        $page_id = absint($instance['page_id'] ?? 0); ?>
        <p>
            <label for="<?php echo $this->get_field_id('page_id'); ?>">Link to page</label>
            <?php wp_dropdown_pages([
                'name'              => $this->get_field_name('page_id'),
                'id'                => $this->get_field_id('page_id'),
                'selected'          => $page_id,
                'show_option_none'  => '— Select a page —',
                'option_none_value' => '0',
                'class'             => 'widefat',
            ]); ?>
        </p>
    <?php }

    public function update($new_instance, $old_instance) {
        return ['page_id' => absint($new_instance['page_id'] ?? 0)];
    }
}

// Step 1: register the sidebar
add_action('widgets_init', function () {
    register_sidebar([
        'name'          => 'Footer Widgets',
        'id'            => 'footer-widgets',
        'description'   => 'Icon widgets shown in the site footer. Drag to reorder.',
        'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h2 class="screen-reader-text">',
        'after_title'   => '</h2>',
    ]);

    // register the widgets too, in the same hook
    register_widget('Justin_Social_Links_Widget');
    register_widget('Justin_Eyes_Link_Widget');
});

/**
 * ---- GOD MODE CHANNELS ----
 * 11 fixed channel slots, each with a title, a name, and a Vimeo URL.
 * Stored as a single option (array of 11 rows) so it's editable from
 * Appearance > Justin Settings without needing a custom post type.
 */

if (!function_exists('justin_god_mode_channel_count')) {
    function justin_god_mode_channel_count() {
        return 11;
    }
}

if (!function_exists('justin_default_god_mode_channels')) {
    function justin_default_god_mode_channels() {
        $rows = [];
        for ($i = 0; $i < justin_god_mode_channel_count(); $i++) {
            $rows[] = [
                'title'     => '',
                'name'      => '',
                'vimeo_url' => '',
            ];
        }
        return $rows;
    }
}

if (!function_exists('justin_sanitize_god_mode_channels')) {
    function justin_sanitize_god_mode_channels($input) {
        $clean = [];
        $count = justin_god_mode_channel_count();

        for ($i = 0; $i < $count; $i++) {
            $row = is_array($input) && isset($input[$i]) && is_array($input[$i]) ? $input[$i] : [];

            $clean[$i] = [
                'title'     => isset($row['title']) ? sanitize_text_field(wp_unslash($row['title'])) : '',
                'name'      => isset($row['name']) ? sanitize_text_field(wp_unslash($row['name'])) : '',
                'vimeo_url' => isset($row['vimeo_url']) ? esc_url_raw(wp_unslash($row['vimeo_url'])) : '',
            ];
        }

        return $clean;
    }
}

if (!function_exists('justin_vimeo_embed_url')) {
    /**
     * Turns a plain vimeo.com URL (or an already-correct player URL)
     * into a player.vimeo.com embed src. Returns '' if it doesn't look
     * like a Vimeo URL, so the frontend can show a "no signal" state
     * instead of a broken iframe.
     */
    function justin_vimeo_embed_url($url) {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1] . '?title=0&byline=0&portrait=0';
        }

        return '';
    }
}

if (!function_exists('justin_god_mode_trigger_button')) {
    /**
     * Optional helper — call this in header.php (or wherever) if you
     * don't already have a God Mode button in your markup:
     *   <?php justin_god_mode_trigger_button(); ?>
     * If you already have your own button, just make sure it has
     * id="god-mode-btn" and the JS below will pick it up automatically.
     */
    function justin_god_mode_trigger_button($label = 'God Mode') {
        printf(
            '<button type="button" id="god-mode-btn" class="god-mode-trigger">%s</button>',
            esc_html($label)
        );
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
    register_setting('justin_settings_group', 'justin_god_mode_channels', [
        'type'              => 'array',
        'sanitize_callback' => 'justin_sanitize_god_mode_channels',
        'default'           => justin_default_god_mode_channels(),
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

    // wp_enqueue_script('justin-main', get_template_directory_uri() . '/assets/js/main.js', [], '1.1', true);
    wp_enqueue_script('justin-main', get_template_directory_uri() . '/assets/js/main.js', [], '1.3', true);

    // God Mode styling only — the channel logic itself lives in main.js,
    // reusing the existing #god-mode-overlay / #god-mode-btn / #god-mode-frame
    // elements already in the theme's templates.
    wp_enqueue_style('justin-god-mode', get_template_directory_uri() . '/assets/css/god-mode.css', [], '1.0');

    $data = [
        'siteTitle' => get_bloginfo('name'),
        'siteDescription' => wp_kses_post( get_theme_mod( 'justin_bio_copy', get_bloginfo('description') ) ),
        'cats' => [],
        'catBios' => [],
        'entries' => [],
        'photoGridTags' => justin_photo_grid_tags(),
        'godModeChannels' => [],
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

            $lightbox_color = get_term_meta($term->term_id, 'justin_cat_lightbox_color', true);

            $data['cats'][] = [
                'name' => $term->name,
                'slug' => $term->slug,
                'lightboxColor' => $lightbox_color ?: '',
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

        $layout_style = justin_get_meta($post->ID, 'layout_style', 'grid_hover');

        $entry = [
            'id' => $post->ID,
            'text' => get_the_title($post),
            'cat' => $cat_name,
            'info' => wp_kses_post(justin_get_meta($post->ID, 'info_text')),
            'images' => $images,
            'body' => wp_kses_post(justin_get_meta($post->ID, 'body_text')),
            'bgImage' => $hover_only && $hover_bg_image ? justin_normalize_image_url($hover_bg_image) : '',
            'hoverOnly' => $hover_only,
            'layoutStyle' => $layout_style,
            'layoutVariant' => justin_layout_variant_from_style($layout_style),
            'photoGrid' => $photo_grid_images,
            'videoUrl' => trim((string) justin_get_meta($post->ID, 'film_video_url')),
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

    // God Mode channels for the frontend flip-widget.
    $god_mode_channels = get_option('justin_god_mode_channels', justin_default_god_mode_channels());
    foreach ($god_mode_channels as $i => $row) {
        $data['godModeChannels'][] = [
            'number'   => $i + 1,
            'title'    => $row['title'] ?? '',
            'name'     => $row['name'] ?? '',
            'embedUrl' => justin_vimeo_embed_url($row['vimeo_url'] ?? ''),
        ];
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

    if (isset($_POST['layout_style'])) {
        $layout_style = sanitize_text_field(wp_unslash($_POST['layout_style']));
        $allowed_layout_styles = [
            'grid_hover',
            'grid_hover_painting',
            'grid_hover_collage',
            'photo_grid',
            'video_direct',
            'standard',
        ];
        if (!in_array($layout_style, $allowed_layout_styles, true)) {
            $layout_style = 'grid_hover';
        }
        update_post_meta($post_id, 'layout_style', $layout_style);
    }

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

    $lightbox_color = isset($_POST['justin_cat_lightbox_color']) ? sanitize_hex_color(wp_unslash($_POST['justin_cat_lightbox_color'])) : '';

    if ($lightbox_color) {
        update_term_meta($term_id, 'justin_cat_lightbox_color', $lightbox_color);
    } else {
        delete_term_meta($term_id, 'justin_cat_lightbox_color');
    }
}

add_action('category_add_form_fields', function () {
    ?>
    <div class="form-field term-group">
        <label for="justin_cat_lightbox_color">Lightbox Background Color</label>
        <input type="color" id="justin_cat_lightbox_color" name="justin_cat_lightbox_color" value="#828282" />
        <p class="description">Tint color shown behind the lightbox popup for projects in this category.</p>
    </div>
    <?php wp_nonce_field('justin_save_cat_color', 'justin_cat_color_nonce'); ?>
    <?php
});

add_action('category_edit_form_fields', function ($term) {
    $lightbox_color = get_term_meta($term->term_id, 'justin_cat_lightbox_color', true) ?: '#828282';
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="justin_cat_lightbox_color">Lightbox Background Color</label></th>
        <td>
            <input type="color" id="justin_cat_lightbox_color" name="justin_cat_lightbox_color" value="<?php echo esc_attr($lightbox_color); ?>" />
            <p class="description">Tint color shown behind the lightbox popup for projects in this category.</p>
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
    $layout_style = justin_get_meta($post->ID, 'layout_style', 'grid_hover');
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
            <option value="grid_hover" <?php selected($layout_style, 'grid_hover'); ?>>Grid + Hover Preview (Photography)</option>
            <option value="grid_hover_painting" <?php selected($layout_style, 'grid_hover_painting'); ?>>Grid + Hover Preview (Painting)</option>
            <option value="grid_hover_collage" <?php selected($layout_style, 'grid_hover_collage'); ?>>Grid + Hover Preview (Collage)</option>
            <option value="video_direct" <?php selected($layout_style, 'video_direct'); ?>>Video (Film)</option>
            <option value="photo_grid" <?php selected($layout_style, 'photo_grid'); ?>>Photo Grid (Misc)</option>
            <option value="standard" <?php selected($layout_style, 'standard'); ?>>Standard (image slideshow)</option>
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
    $channels = get_option('justin_god_mode_channels', justin_default_god_mode_channels());
    ?>
    <div class="wrap">
        <h1>Justin Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('justin_settings_group'); ?>

            <h2>God Mode channels</h2>
            <p>Each row is one channel in the God Mode flip display. Paste a normal Vimeo link (e.g. <code>https://vimeo.com/123456789</code>) — it's converted to an embed automatically. Leave the URL blank to show "no signal" for that channel.</p>

            <table class="widefat" style="max-width:900px;">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Title</th>
                        <th>Name</th>
                        <th>Vimeo URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < justin_god_mode_channel_count(); $i++) :
                        $row = $channels[$i] ?? ['title' => '', 'name' => '', 'vimeo_url' => ''];
                        ?>
                        <tr>
                            <td><?php echo esc_html(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)); ?></td>
                            <td>
                                <input type="text" style="width:100%;" name="justin_god_mode_channels[<?php echo $i; ?>][title]" value="<?php echo esc_attr($row['title'] ?? ''); ?>" />
                            </td>
                            <td>
                                <input type="text" style="width:100%;" name="justin_god_mode_channels[<?php echo $i; ?>][name]" value="<?php echo esc_attr($row['name'] ?? ''); ?>" />
                            </td>
                            <td>
                                <input type="url" style="width:100%;" name="justin_god_mode_channels[<?php echo $i; ?>][vimeo_url]" value="<?php echo esc_attr($row['vimeo_url'] ?? ''); ?>" placeholder="https://vimeo.com/..." />
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
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