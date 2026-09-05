<?php

/* =====================================================================
 * 1. THEME SETUP
 * ===================================================================== */

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});


/* =====================================================================
 * 2. GENERAL HELPERS
 * ===================================================================== */

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
                    $preview_html .= '<div class="justin-media-thumb" data-id="' . esc_attr($id) . '">'
                        . '<img src="' . esc_url($thumb) . '" alt="" />'
                        . '<button type="button" class="justin-media-thumb-remove" aria-label="Remove image">&times;</button>'
                        . '</div>';
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

if (!function_exists('justin_photo_grid_tags')) {
    function justin_photo_grid_tags() {
        $base_tags = [
            'Roads', 'Balcony', 'Clouds', 'Street People', 'Nature', 'Food',
            'Rejections', 'Cats', 'Dogs', 'Rocks', 'Architecture', 'Myself',
            'Beds', 'Signs', 'StudioGirls', 'Guys', 'Friends', 'Home',
            'Drawings', 'Other',
        ];

        $custom_tags = get_option('justin_custom_photo_grid_tags', []);
        if (!is_array($custom_tags)) {
            $custom_tags = [];
        }

        return array_values(array_unique(array_merge($base_tags, $custom_tags)));
    }
}

if (!function_exists('justin_layout_variant_from_style')) {
    function justin_layout_variant_from_style($layout_style) {
        $map = [
            'grid_hover'          => 'photography',
            'grid_hover_painting' => 'painting',
            'grid_hover_collage'  => 'collage',
            // Book Template reuses the "photography" grid math/CSS.
            'book_template'       => 'photography',
        ];

        return $map[$layout_style] ?? '';
    }
}


/* =====================================================================
 * 3. PROJECT META BOXES
 * The post-editor fields for each project type (Photography/Painting/
 * Collage, Film, Books, Book Template, Text), plus the JS/CSS that
 * powers the admin editing experience, and the single save handler
 * that persists every field below into post meta.
 * ===================================================================== */

if (!function_exists('justin_register_meta_boxes')) {
    function justin_register_meta_boxes() {
        add_meta_box('justin-project-common', 'Lightbox Layout', 'justin_render_project_common_box', 'post', 'normal', 'high');
        add_meta_box('justin-project-gallery', 'Gallery / Visuals', 'justin_render_project_gallery_box', 'post', 'normal', 'default');
        add_meta_box('justin-project-film', 'Film', 'justin_render_project_film_box', 'post', 'normal', 'default');
        add_meta_box('justin-project-books', 'Book Template 1', 'justin_render_project_books_box', 'post', 'normal', 'default');
        // Separate box for Book Template's own thumbnail set, kept
        // apart from Gallery/Visuals so its Photo Grid tag UI never shows
        // up here. Only relevant when Lightbox Layout = Book Template
        // (hidden otherwise via justin_layout_admin_polish() below, along
        // with every other layout-specific meta box).
        add_meta_box('justin-project-book-template', 'Book Template 2', 'justin_render_project_book_template_box', 'post', 'normal', 'default');
    }
}

add_action('add_meta_boxes', 'justin_register_meta_boxes');

function justin_render_project_common_box($post) {
    wp_nonce_field('justin_save_project_meta', 'justin_project_meta_nonce');
    $hover_bg_image = justin_get_meta($post->ID, 'hover_bg_image');
    $layout_style = justin_get_meta($post->ID, 'layout_style', 'grid_hover');
    // Defaults to enabled ('1') for posts that have never saved this
    // field yet, so the auto-select-on-layout-change behavior is on by
    // default and each post remembers if it's been turned off.
    $auto_select_layout_category = justin_parse_bool(justin_get_meta($post->ID, 'auto_select_layout_category', '1'));
    ?>
    <p>
    <label for="layout_style"><strong>Choose from the dropdown:</strong></label><br />
        <select name="layout_style" id="layout_style">
            <option value="grid_hover" <?php selected($layout_style, 'grid_hover'); ?>>Photography</option>
            <option value="grid_hover_painting" <?php selected($layout_style, 'grid_hover_painting'); ?>>Painting</option>
            <option value="grid_hover_collage" <?php selected($layout_style, 'grid_hover_collage'); ?>>Collage</option>
            <option value="book_template" <?php selected($layout_style, 'book_template'); ?>>Book (beta)</option>
            <option value="video_direct" <?php selected($layout_style, 'video_direct'); ?>>Film</option>
            <option value="photo_grid" <?php selected($layout_style, 'photo_grid'); ?>>Photo Grid (Misc)</option>
            <option value="hover_only" <?php selected($layout_style, 'hover_only'); ?>>Background Hover</option>
        </select>
    </p>
    <p>
        <label>
            <input type="checkbox" name="auto_select_layout_category" id="auto_select_layout_category" value="1" <?php checked($auto_select_layout_category); ?> />
            Automatically set category to match this layout (Painting / Collage / misc. / Film / Books / Uncategorized). Uncheck to keep your own category choice — useful for Photo Grid posts that don't belong in "misc."
        </label>
    </p>
    <div id="justin-hover-only-field" style="display:none;">
        <?php justin_render_media_preview('Hover background image', 'hover_bg_image', $hover_bg_image, false); ?>
        <p style="color:#666;">This post will show only as this image on hover in the list — it won't open a lightbox. Choosing "Hover-only Item" above already marks it as hover-only; no separate checkbox needed.</p>
    </div>
    <?php
}

function justin_render_project_gallery_box($post) {
    $gallery = justin_get_meta($post->ID, 'gallery');
    $gallery_tags_json = justin_get_meta($post->ID, 'gallery_image_tags', '{}');
    $all_tags = justin_photo_grid_tags();
    $custom_tags = get_option('justin_custom_photo_grid_tags', []);
    if (!is_array($custom_tags)) {
        $custom_tags = [];
    }
    ?>
    <p>Used for Photography, Painting, and Collage, and Photo Grid content.</p>
    <?php justin_render_media_preview('Gallery images', 'gallery', $gallery, true); ?>

    <div id="justin-gallery-tag-assign" data-tags='<?php echo esc_attr(wp_json_encode($all_tags)); ?>' data-custom-tags='<?php echo esc_attr(wp_json_encode($custom_tags)); ?>'>
        <p><strong>Photo Grid tags</strong> <span style="color:#777;">(only used when Lightbox Layout = Photo Grid)</span></p>
        <div class="justin-add-tag-row" style="margin:10px 0;">
            <input type="text" id="justin-new-tag-input" placeholder="New tag name" style="width:200px;" />
            <button type="button" class="button" id="justin-add-tag-btn">Add tag</button>
            <span id="justin-add-tag-error" style="color:#c00;"></span>
        </div>
        <div id="justin-custom-tag-manager" style="margin-bottom:14px;"></div>
        <input type="hidden" id="gallery_image_tags" name="gallery_image_tags" value='<?php echo esc_attr($gallery_tags_json); ?>' />
        <div class="justin-gallery-tag-list"></div>
    </div>
    <?php
}

function justin_render_project_film_box($post) {
    $film_video_url = justin_get_meta($post->ID, 'film_video_url');
    $info_text = justin_get_meta($post->ID, 'info_text');
    $disable_info_text = justin_parse_bool(justin_get_meta($post->ID, 'disable_info_text'));
    ?>
    <p>
        <label for="film_video_url"><strong>Vimeo or YouTube URL</strong></label><br />
        <input type="url" name="film_video_url" id="film_video_url" value="<?php echo esc_attr($film_video_url); ?>" style="width:100%;" placeholder="https://vimeo.com/... or https://youtube.com/watch?v=..." />
    </p>
    <p>
        <label for="info_text"><strong>Info text</strong></label><br />
        <textarea name="info_text" id="info_text" rows="4" style="width:100%;"><?php echo esc_textarea($info_text); ?></textarea>
    </p>
    <p>
        <label>
            <input type="checkbox" name="disable_info_text" value="1" <?php checked($disable_info_text); ?> />
            Disable info text (hides the ⓘ info panel entirely, even if Info text above has content)
        </label>
    </p>
    <?php
}

function justin_render_project_books_box($post) {
    $buy_url  = justin_get_meta($post->ID, 'buy_url');
    $price    = justin_get_meta($post->ID, 'buy_price');
    $currency = justin_get_meta($post->ID, 'buy_currency', 'eur');
    ?>
    <p>With this one: content now comes straight from the
    main post editor above &mdash; write/format it there (images, paragraphs,
    etc.) and it will be shown as the lightbox background.</p>
    <p>
        <label for="buy_price"><strong>Price per copy</strong></label><br />
        <input type="number" step="0.01" min="0" name="buy_price" id="buy_price" value="<?php echo esc_attr($price); ?>" style="width:150px;" />
        <select name="buy_currency" id="buy_currency">
            <?php foreach (justin_stripe_currencies() as $code => $label) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <br><span style="color:#666;">Set a price + your Stripe publishable/secret keys under Appearance &gt; Justin Settings to enable the in-page BUY ME checkout. Leave the price at 0 to skip Stripe and just use the Buy URL below.</span>
    </p>
    <p>
        <label for="buy_url"><strong>Buy URL (fallback if Stripe isn't configured)</strong></label><br />
        <input type="url" name="buy_url" id="buy_url" value="<?php echo esc_attr($buy_url); ?>" style="width:100%;" />
    </p>
    <?php
}

// Book Template's own image picker. Completely separate from
// Gallery/Visuals ('gallery' field) so its Photo Grid tag UI never
// shows up here. Also carries its own independent price/currency/buy
// url, separate from the Books box above.
function justin_render_project_book_template_box($post) {
    $book_template_images = justin_get_meta($post->ID, 'book_template_images');
    $bt_price    = justin_get_meta($post->ID, 'book_template_price');
    $bt_currency = justin_get_meta($post->ID, 'book_template_currency', 'eur');
    $bt_buy_url  = justin_get_meta($post->ID, 'book_template_buy_url');
    ?>
    <p>Similar to Photography,etc: thumbnails shown on the left
    of that layout, main image to the right + text + but me button underneath.</p>
    <?php justin_render_media_preview('Book Template images', 'book_template_images', $book_template_images, true); ?>

    <p>
        <label for="book_template_price"><strong>Price per copy</strong></label><br />
        <input type="number" step="0.01" min="0" name="book_template_price" id="book_template_price" value="<?php echo esc_attr($bt_price); ?>" style="width:150px;" />
        <select name="book_template_currency" id="book_template_currency">
            <?php foreach (justin_stripe_currencies() as $code => $label) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($bt_currency, $code); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <br><span style="color:#666;">Set a price + your Stripe publishable/secret keys under Appearance &gt; Justin Settings to enable the in-page checkout. Leave the price at 0 to skip Stripe and just use the Buy URL below.</span>
    </p>
    <p>
        <label for="book_template_buy_url"><strong>Buy URL (fallback if Stripe isn't configured)</strong></label><br />
        <input type="url" name="book_template_buy_url" id="book_template_buy_url" value="<?php echo esc_attr($bt_buy_url); ?>" style="width:100%;" />
    </p>
    <?php
}

// Admin-side JS: media picker for image fields + the gallery tag
// assignment UI (admin-metaboxes.js), on post edit screens only.
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_script('justin-admin-metaboxes', get_template_directory_uri() . '/assets/js/admin-metaboxes.js', ['jquery'], '1.3', true);
        wp_localize_script('justin-admin-metaboxes', 'JUSTIN_ADMIN', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('justin_photo_grid_tag_nonce'),
        ]);
    }
});
// add_action('admin_enqueue_scripts', function ($hook) {
//     if ($hook === 'post.php' || $hook === 'post-new.php') {
//         wp_enqueue_media();
//         wp_enqueue_script('jquery');
//         wp_enqueue_script('justin-admin-metaboxes', get_template_directory_uri() . '/assets/js/admin-metaboxes.js', ['jquery'], '1.0', true);
//     }
// });

// Admin-side color picker (used by the Commercial Template's background
// color field, see section 11 below), also post edit screens only.
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    wp_add_inline_script('wp-color-picker', "
        jQuery(function ($) {
            $('.justin-color-field').wpColorPicker();
        });
    ");
});

// Shows/hides each project meta box based on the selected Lightbox
// Layout, so editors only ever see the fields relevant to whichever
// layout they've picked. Also forces every media-field preview
// thumbnail to a small, consistent size, AND auto-selects the category
// mapped to certain layouts (see $layout_category_slugs below) so the
// lightbox tint color never ends up mismatched with the layout.
//   - Grid + Hover (Photography/Painting/Collage) -> Gallery / Visuals
//     box only. Its Photo Grid tag checklist stays hidden — tagging is
//     Photo-Grid-specific, not relevant to these three.
//   - Photo Grid (Misc) -> Gallery / Visuals box AND its Photo Grid
//     tag checklist.
//   - Video (Film) -> Film box.
//   - Book Template -> Book Template 1 (Books box) AND Book Template 2
//     (Book Template box), together.
add_action('admin_head-post.php', 'justin_layout_admin_polish');
add_action('admin_head-post-new.php', 'justin_layout_admin_polish');

function justin_layout_admin_polish() {
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return;
    }

    // True only for a post that's never been saved yet (WordPress creates
    // an 'auto-draft' row the moment you open "Add New"). Used below to
    // apply the layout's default category once on load for brand-new
    // posts only — existing posts keep their already-saved category
    // untouched unless the editor changes the layout themselves.
    $is_new_post = ($post->post_status === 'auto-draft');

    // Layout -> category slug. Only layouts listed here get their
    // category auto-selected.
    $layout_category_slugs = [
        'grid_hover'          => 'photography',
        'grid_hover_painting' => 'painting',
        'grid_hover_collage'  => 'collage',
        'photo_grid'          => 'misc',
        'video_direct'        => 'film',
        'book_template'       => 'books',
        'hover_only'          => 'uncategorized',
    ];

    $layout_category_map = [];
    foreach ($layout_category_slugs as $layout_value => $slug) {
        $term = get_term_by('slug', $slug, 'category');
        if ($term && !is_wp_error($term)) {
            $layout_category_map[$layout_value] = (int) $term->term_id;
        }
    }
    ?>
    <style>
        .justin-media-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .justin-media-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            display: block;
        }
        .justin-media-thumb {
            position: relative;
            cursor: move;
        }
        .justin-media-thumb-remove {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 20px;
            height: 20px;
            line-height: 18px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid black;
            background: #fff;
            color: black;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        .justin-media-preview .ui-sortable-placeholder {
            width: 80px;
            height: 80px;
            border: 1px dashed #aaa;
            background: #f0f0f1;
        }
    </style>
    <script>
    (function () {
        var LAYOUT_CATEGORY_MAP = <?php echo wp_json_encode($layout_category_map); ?>;
        var IS_NEW_POST = <?php echo $is_new_post ? 'true' : 'false'; ?>;

        document.addEventListener('DOMContentLoaded', function () {
            var layoutSelect = document.getElementById('layout_style');
            var galleryBox = document.getElementById('justin-project-gallery');
            var tagAssign = document.getElementById('justin-gallery-tag-assign');
            var filmBox = document.getElementById('justin-project-film');
            var booksBox = document.getElementById('justin-project-books');
            var bookTemplateBox = document.getElementById('justin-project-book-template');
            var hoverOnlyField = document.getElementById('justin-hover-only-field');
            var autoSelectCheckbox = document.getElementById('auto_select_layout_category');

            if (!layoutSelect) {
                return;
            }

            // Layouts that use the shared 'gallery' image field.
            var GALLERY_LAYOUTS = ['grid_hover', 'grid_hover_painting', 'grid_hover_collage', 'photo_grid'];

            function syncBoxVisibility() {
                var value = layoutSelect.value;
                var shouldShowGallery = (GALLERY_LAYOUTS.indexOf(value) !== -1);

                if (galleryBox) {
                    galleryBox.style.display = shouldShowGallery ? '' : 'none';

                    // WordPress meta boxes track their own open/closed state
                    // separately from display (a "closed" class, toggled by the
                    // caret in the box header and remembered per-user in the
                    // database). That's independent of the layout-based show/hide
                    // above, so if this box was ever manually collapsed, it stays
                    // collapsed forever even when a gallery layout makes it visible
                    // again. Force it back open whenever we're showing it.
                    if (shouldShowGallery && galleryBox.classList.contains('closed')) {
                        var galleryToggle = galleryBox.querySelector('.handlediv');
                        if (galleryToggle) {
                            galleryToggle.click();
                        }
                    }
                }

                if (tagAssign) {
                    tagAssign.style.display = (value === 'photo_grid') ? '' : 'none';
                }

                if (filmBox) {
                    filmBox.style.display = (value === 'video_direct') ? '' : 'none';
                }

                if (booksBox) {
                    booksBox.style.display = (value === 'book_template') ? '' : 'none';
                }

                if (bookTemplateBox) {
                    bookTemplateBox.style.display = (value === 'book_template') ? '' : 'none';
                }

                if (hoverOnlyField) {
                    hoverOnlyField.style.display = (value === 'hover_only') ? '' : 'none';
                }
            }

            function applyCategoryForLayout() {
                if (!autoSelectCheckbox || !autoSelectCheckbox.checked) {
                    return;
                }

                var termId = LAYOUT_CATEGORY_MAP[layoutSelect.value];
                if (!termId) {
                    return;
                }

                if (window.wp && wp.data && wp.data.dispatch) {
                    wp.data.dispatch('core/editor').editPost({ categories: [termId] });
                }
            }

            layoutSelect.addEventListener('change', function () {
                syncBoxVisibility();
                applyCategoryForLayout();
            });

            syncBoxVisibility();

            // New posts only: the layout dropdown already defaults to
            // Photography, so apply its matching category once on load too,
            // instead of leaving Categories empty until the editor manually
            // touches the dropdown. Existing posts are untouched here — this
            // only runs when the post has never been saved before.
            //
            // Gutenberg's data store isn't guaranteed ready this early, and even
            // once it exists it can still overwrite an early edit when it finishes
            // initializing from the post's actual saved data (empty categories for
            // a brand-new post). So instead of a single timed attempt, poll until
            // the store confirms the category has actually stuck, then stop.
            if (IS_NEW_POST) {
                var applyAttempts = 0;
                var maxApplyAttempts = 50; // ~5 seconds at 100ms

                var applyInterval = setInterval(function () {
                    applyAttempts += 1;

                    var storeReady = window.wp && wp.data && wp.data.select && wp.data.dispatch && wp.data.select('core/editor');

                    if (storeReady) {
                        var termId = LAYOUT_CATEGORY_MAP[layoutSelect.value];
                        var currentCats = wp.data.select('core/editor').getEditedPostAttribute('categories') || [];
                        var shouldApply = autoSelectCheckbox && autoSelectCheckbox.checked && termId;

                        if (shouldApply && currentCats.indexOf(termId) === -1) {
                            wp.data.dispatch('core/editor').editPost({ categories: [termId] });
                        }

                        // Stop once it's confirmed applied, or once there's nothing
                        // left to apply (checkbox off / no mapped category).
                        if (!shouldApply || currentCats.indexOf(termId) !== -1) {
                            clearInterval(applyInterval);
                        }
                    }

                    if (applyAttempts >= maxApplyAttempts) {
                        clearInterval(applyInterval);
                    }
                }, 100);
            }
        });
    })();
    </script>
    <?php
}

// Saves every field from the meta boxes above (Project Common, Gallery,
// Film, Books, Book Template) in one handler.
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

    // $text_fields = ['info_text', 'film_video_url', 'body_text', 'book_text', 'buy_url'];
    $text_fields = ['info_text', 'film_video_url', 'buy_url'];
    foreach ($text_fields as $field_name) {
        if (isset($_POST[$field_name])) {
            $value = wp_unslash($_POST[$field_name]);
            $value = ($field_name === 'buy_url') ? esc_url_raw($value) : sanitize_text_field($value);
            // if ($field_name === 'info_text' || $field_name === 'body_text' || $field_name === 'book_text' || $field_name === 'teaser_text') {
            //     $value = wp_kses_post(wp_unslash($_POST[$field_name]));
            // }
            if ($field_name === 'info_text' || $field_name === 'book_text') {
                $value = wp_kses_post(wp_unslash($_POST[$field_name]));
            }
            if ($field_name === 'film_video_url') {
                $value = esc_url_raw($value);
            }
            update_post_meta($post_id, $field_name, $value);
        }
    }

    // Book price + currency (Stripe).
    if (isset($_POST['buy_price'])) {
        $buy_price = (float) wp_unslash($_POST['buy_price']);
        update_post_meta($post_id, 'buy_price', max(0, $buy_price));
    }

    if (isset($_POST['buy_currency'])) {
        $buy_currency = sanitize_text_field(wp_unslash($_POST['buy_currency']));
        if (!isset(justin_stripe_currencies()[$buy_currency])) {
            $buy_currency = 'eur';
        }
        update_post_meta($post_id, 'buy_currency', $buy_currency);
    }

    // Book Template price + currency + buy url — independent of the Books
    // box above, saved under its own meta keys.
    if (isset($_POST['book_template_price'])) {
        $bt_price = (float) wp_unslash($_POST['book_template_price']);
        update_post_meta($post_id, 'book_template_price', max(0, $bt_price));
    }

    if (isset($_POST['book_template_currency'])) {
        $bt_currency = sanitize_text_field(wp_unslash($_POST['book_template_currency']));
        if (!isset(justin_stripe_currencies()[$bt_currency])) {
            $bt_currency = 'eur';
        }
        update_post_meta($post_id, 'book_template_currency', $bt_currency);
    }

    if (isset($_POST['book_template_buy_url'])) {
        update_post_meta($post_id, 'book_template_buy_url', esc_url_raw(wp_unslash($_POST['book_template_buy_url'])));
    }

    // $image_fields = ['hover_bg_image', 'gallery', 'film_grabs', 'book_images'];
    // 'book_template_images' is separate from 'gallery', saved via the
    // Book Template meta box.
    $image_fields = ['hover_bg_image', 'gallery', 'book_template_images'];
    foreach ($image_fields as $field_name) {
        if (isset($_POST[$field_name])) {
            $value = sanitize_text_field(wp_unslash($_POST[$field_name]));
            update_post_meta($post_id, $field_name, $value);
        }
    }

    // Determined here (before use_as_hover_only below) so hover-only
    // status can be derived directly from the layout choice, rather
    // than needing its own separate checkbox.
    $saved_layout_style = 'grid_hover';
    if (isset($_POST['layout_style'])) {
        $saved_layout_style = sanitize_text_field(wp_unslash($_POST['layout_style']));
        $allowed_layout_styles = [
            'grid_hover',
            'grid_hover_painting',
            'grid_hover_collage',
            'photo_grid',
            'video_direct',
            'book_template',
            'hover_only',
        ];
        if (!in_array($saved_layout_style, $allowed_layout_styles, true)) {
            $saved_layout_style = 'grid_hover';
        }
        update_post_meta($post_id, 'layout_style', $saved_layout_style);
    }

    // 'Hover-only Item' is its own Lightbox Layout choice now — picking
    // it already implies hover-only behavior, so there's no separate
    // checkbox to tick anymore.
    update_post_meta($post_id, 'use_as_hover_only', ($saved_layout_style === 'hover_only') ? '1' : '0');
    update_post_meta($post_id, 'disable_info_text', isset($_POST['disable_info_text']) ? '1' : '0');
    update_post_meta($post_id, 'auto_select_layout_category', isset($_POST['auto_select_layout_category']) ? '1' : '0');
    // update_post_meta($post_id, 'has_teaser', isset($_POST['has_teaser']) ? '1' : '0');

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


/* =====================================================================
 * 4. STRIPE / CHECKOUT (test mode) — powers the in-page "BUY ME" modal.
 *
 * How it works:
 *  - Publishable + secret key are stored as options (Appearance >
 *    Justin Settings). Only the publishable key ever reaches the browser.
 *  - Each Book post can have a price ("buy_price") + currency
 *    ("buy_currency"). Book Template posts have their own independent
 *    price fields ("book_template_price" / "book_template_currency").
 *    If both a price and a publishable key are set, the frontend opens
 *    the Stripe modal instead of following the buy URL.
 *  - The modal (main.js) calls the justin_create_payment_intent AJAX
 *    action below every time the quantity changes, gets back a fresh
 *    PaymentIntent client secret, and mounts Stripe's Address / Link /
 *    Payment elements against it — all inside one popup, no redirect.
 * ===================================================================== */

add_action('admin_init', function () {
    register_setting('justin_settings_group', 'justin_stripe_publishable_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
    register_setting('justin_settings_group', 'justin_stripe_secret_key', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
});

if (!function_exists('justin_stripe_currencies')) {
    function justin_stripe_currencies() {
        return ['eur' => 'EUR', 'usd' => 'USD', 'gbp' => 'GBP'];
    }
}

add_action('wp_ajax_justin_create_payment_intent', 'justin_create_payment_intent');
add_action('wp_ajax_nopriv_justin_create_payment_intent', 'justin_create_payment_intent');

function justin_create_payment_intent() {
    check_ajax_referer('justin_stripe_nonce', 'nonce');

    $post_id  = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;
    // 'book' (default) reads the shared Books price fields; 'book_template'
    // reads the independent Book Template price fields instead.
    $source   = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'book';

    $secret_key = get_option('justin_stripe_secret_key', '');

    if (!$post_id || !$secret_key) {
        wp_send_json_error(['message' => 'Stripe is not configured yet.'], 400);
    }

    if ($source === 'book_template') {
        $price_major = (float) justin_get_meta($post_id, 'book_template_price', 0);
        $currency    = justin_get_meta($post_id, 'book_template_currency', 'eur');
    } else {
        $price_major = (float) justin_get_meta($post_id, 'buy_price', 0);
        $currency    = justin_get_meta($post_id, 'buy_currency', 'eur');
    }

    $currencies  = justin_stripe_currencies();

    if (!isset($currencies[$currency])) {
        $currency = 'eur';
    }

    if ($price_major <= 0) {
        wp_send_json_error(['message' => 'This item has no price set.'], 400);
    }

    $unit_amount = (int) round($price_major * 100);
    $amount      = $unit_amount * $quantity;

    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body' => [
            'amount'                              => $amount,
            'currency'                            => $currency,
            'automatic_payment_methods[enabled]'  => 'true',
            'metadata[post_id]'                   => $post_id,
            'metadata[quantity]'                  => $quantity,
            'metadata[source]'                    => $source,
            'metadata[site]'                      => home_url(),
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()], 500);
    }

    $status = wp_remote_retrieve_response_code($response);
    $body   = json_decode(wp_remote_retrieve_body($response), true);

    if ($status >= 400 || empty($body['client_secret'])) {
        $message = $body['error']['message'] ?? 'Could not start payment.';
        wp_send_json_error(['message' => $message], 500);
    }

    wp_send_json_success([
        'clientSecret' => $body['client_secret'],
        'amount'       => $amount,
        'currency'     => $currency,
    ]);
}

add_action('wp_ajax_justin_add_photo_grid_tag', function () {
    check_ajax_referer('justin_photo_grid_tag_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Not allowed.'], 403);
    }

    $new_tag = trim(sanitize_text_field(wp_unslash($_POST['tag'] ?? '')));

    if ($new_tag === '') {
        wp_send_json_error(['message' => 'Tag cannot be empty.'], 400);
    }

    $existing = justin_photo_grid_tags();
    foreach ($existing as $tag) {
        if (strcasecmp($tag, $new_tag) === 0) {
            wp_send_json_success(['tags' => $existing, 'added' => $tag]);
        }
    }

    $custom_tags = get_option('justin_custom_photo_grid_tags', []);
    if (!is_array($custom_tags)) {
        $custom_tags = [];
    }
    $custom_tags[] = $new_tag;
    update_option('justin_custom_photo_grid_tags', array_values(array_unique($custom_tags)));

    wp_send_json_success([
        'tags'  => justin_photo_grid_tags(),
        'added' => $new_tag,
    ]);
});

add_action('wp_ajax_justin_delete_photo_grid_tag', function () {
    check_ajax_referer('justin_photo_grid_tag_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Not allowed.'], 403);
    }

    $tag_to_delete = trim(sanitize_text_field(wp_unslash($_POST['tag'] ?? '')));

    if ($tag_to_delete === '') {
        wp_send_json_error(['message' => 'No tag specified.'], 400);
    }

    $custom_tags = get_option('justin_custom_photo_grid_tags', []);
    if (!is_array($custom_tags)) {
        $custom_tags = [];
    }

    $filtered = array_values(array_filter($custom_tags, function ($tag) use ($tag_to_delete) {
        return strcasecmp($tag, $tag_to_delete) !== 0;
    }));

    if (count($filtered) === count($custom_tags)) {
        wp_send_json_error(['message' => 'That tag is a built-in tag and cannot be deleted.'], 400);
    }

    update_option('justin_custom_photo_grid_tags', $filtered);

    // Best-effort cleanup: strip the deleted tag from any post that
    // already has it saved against an image, so it doesn't linger in
    // gallery_image_tags until that post happens to be re-saved.
    $posts_with_tags = get_posts([
        'post_type'    => 'post',
        'post_status'  => 'any',
        'numberposts'  => -1,
        'meta_key'     => 'gallery_image_tags',
        'fields'       => 'ids',
    ]);

    foreach ($posts_with_tags as $post_id) {
        $raw = get_post_meta($post_id, 'gallery_image_tags', true);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }

        $changed = false;
        foreach ($decoded as $image_id => $tags) {
            if (!is_array($tags)) {
                continue;
            }
            $new_tags = array_values(array_filter($tags, function ($tag) use ($tag_to_delete) {
                return strcasecmp($tag, $tag_to_delete) !== 0;
            }));
            if (count($new_tags) !== count($tags)) {
                $decoded[$image_id] = $new_tags;
                $changed = true;
            }
        }

        if ($changed) {
            update_post_meta($post_id, 'gallery_image_tags', wp_json_encode($decoded));
        }
    }

    wp_send_json_success([
        'tags'    => justin_photo_grid_tags(),
        'deleted' => $tag_to_delete,
    ]);
});


/* =====================================================================
 * 5. ADMIN SETTINGS PAGE (Appearance > Justin Settings)
 * Renders both the Stripe key fields (section 4) and the God Mode
 * channel table (section 6) on one settings page.
 * ===================================================================== */

add_action('admin_menu', function () {
    add_theme_page('Justin Settings', 'Justin Settings', 'edit_theme_options', 'justin-settings', 'justin_render_settings_page');
});

function justin_render_settings_page() {
    $channels = get_option('justin_god_mode_channels', justin_default_god_mode_channels());
    ?>
    <div class="wrap">
        <h1>Justin Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('justin_settings_group'); ?>

            <h2>Stripe (test mode)</h2>
            <p>Paste your <strong>test</strong> keys from the Stripe Dashboard &rarr; Developers &rarr; API keys. Nothing here is live until you swap in your live keys later.</p>
            <table class="form-table">
                <tr>
                    <th><label for="justin_stripe_publishable_key">Publishable key</label></th>
                    <td><input type="text" style="width:420px;" id="justin_stripe_publishable_key" name="justin_stripe_publishable_key" value="<?php echo esc_attr(get_option('justin_stripe_publishable_key', '')); ?>" placeholder="pk_test_..." /></td>
                </tr>
                <tr>
                    <th><label for="justin_stripe_secret_key">Secret key</label></th>
                    <td><input type="password" style="width:420px;" id="justin_stripe_secret_key" name="justin_stripe_secret_key" value="<?php echo esc_attr(get_option('justin_stripe_secret_key', '')); ?>" placeholder="sk_test_..." autocomplete="off" /></td>
                </tr>
            </table>

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

function justin_render_site_settings_box() {
    ?>
    <p>Use the Justin Settings page in Appearance for site-wide options.</p>
    <?php
}


/* =====================================================================
 * 6. GOD MODE CHANNELS
 * 11 fixed channel slots, each with a title, a name, and a Vimeo URL.
 * Stored as a single option (array of 11 rows) so it's editable from
 * Appearance > Justin Settings without needing a custom post type.
 * ===================================================================== */

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

add_action('admin_init', function () {
    register_setting('justin_settings_group', 'justin_god_mode_channels', [
        'type'              => 'array',
        'sanitize_callback' => 'justin_sanitize_god_mode_channels',
        'default'           => justin_default_god_mode_channels(),
    ]);
});


/* =====================================================================
 * 7. CATEGORY LIGHTBOX COLOR
 * Adds a "Lightbox Background Color" field to each category, used as
 * the tint behind the frontend lightbox for posts in that category.
 * ===================================================================== */

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


/* =====================================================================
 * 8. FOOTER WIDGETS
 * ===================================================================== */

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
        // Backend Widgets screen just shows a plain label while collapsed,
        // instead of live-previewing the real (icon-only) markup.
        // if (is_admin()) {
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            echo $args['before_widget'];
            echo '<p style="margin:0;padding:8px;font-weight:600;">Justin: Social Links</p>';
            echo $args['after_widget'];
            return;
        }

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
    const DEFAULT_EMOJI = '👀';

    public function __construct() {
        parent::__construct(
            'justin_eyes_link_widget',
            'Justin: Eyes Emoji Link',
            ['description' => 'Emoji linking to a page you choose.']
        );
    }

    public function widget($args, $instance) {
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        // if (is_admin()) {
            echo $args['before_widget'];
            echo '<p style="margin:0;padding:8px;font-weight:600;">Justin: Eyes Emoji Link</p>';
            echo $args['after_widget'];
            return;
        }

        $enabled = isset($instance['enabled']) ? (bool) $instance['enabled'] : true;
        if (!$enabled) {
            return;
        }

        $page_id = absint($instance['page_id'] ?? 0);
        if (!$page_id || get_post_status($page_id) !== 'publish') {
            return;
        }
        $url = get_permalink($page_id);
        if (!$url) {
            return;
        }

        $emoji = trim((string) ($instance['emoji'] ?? ''));
        if ($emoji === '') {
            $emoji = self::DEFAULT_EMOJI;
        }

        echo $args['before_widget'];
        printf(
        '<span class="footer-eyes-link-wrap"><a class="footer-eyes-link" href="%s" aria-label="%s">%s</a></span>',
        esc_url($url),
        esc_attr(get_the_title($page_id)),
        esc_html($emoji)
        );
        echo $args['after_widget'];
    }

    public function form($instance) {
        $page_id = absint($instance['page_id'] ?? 0);
        $emoji   = $instance['emoji'] ?? self::DEFAULT_EMOJI;
        $enabled = isset($instance['enabled']) ? (bool) $instance['enabled'] : true;
        ?>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr($this->get_field_name('enabled')); ?>" value="1" <?php checked($enabled); ?> />
                Show this widget
            </label>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('emoji')); ?>">Emoji</label>
            <input class="widefat" type="text"
                id="<?php echo esc_attr($this->get_field_id('emoji')); ?>"
                name="<?php echo esc_attr($this->get_field_name('emoji')); ?>"
                value="<?php echo esc_attr($emoji); ?>"
                placeholder="<?php echo esc_attr(self::DEFAULT_EMOJI); ?>" />
            <span style="color:#666;">Leave blank to use the default (<?php echo esc_html(self::DEFAULT_EMOJI); ?>).</span>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('page_id')); ?>">Link to page</label>
            <?php wp_dropdown_pages([
                'name'              => $this->get_field_name('page_id'),
                'id'                => $this->get_field_id('page_id'),
                'selected'          => $page_id,
                'show_option_none'  => '— Select a page —',
                'option_none_value' => '0',
                'class'             => 'widefat',
            ]); ?>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $emoji = sanitize_text_field($new_instance['emoji'] ?? '');
        return [
            'page_id' => absint($new_instance['page_id'] ?? 0),
            'emoji'   => $emoji !== '' ? $emoji : self::DEFAULT_EMOJI,
            'enabled' => !empty($new_instance['enabled']),
        ];
    }
}

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

    register_widget('Justin_Social_Links_Widget');
    register_widget('Justin_Eyes_Link_Widget');
});


/* =====================================================================
 * 9. CUSTOMIZER
 * ===================================================================== */

// function justin_bio_customizer( $wp_customize ) {
//     $wp_customize->add_section( 'justin_bio_section', array(
//         'title'    => 'Bio Copy',
//         'priority' => 30,
//     ) );

//     $wp_customize->add_setting( 'justin_bio_copy', array(
//         'default'           => '',
//         'sanitize_callback' => 'wp_kses_post', // allows <a>, <em>, <strong>, etc. but strips dangerous tags/scripts
//         'transport'         => 'refresh',
//     ) );

//     $wp_customize->add_control( new WP_Customize_Control(
//         $wp_customize,
//         'justin_bio_copy_control',
//         array(
//             'label'    => 'Bio text (HTML links allowed, e.g. <a href="https://example.com">text</a>)',
//             'section'  => 'justin_bio_section',
//             'settings' => 'justin_bio_copy',
//             'type'     => 'textarea',
//         )
//     ) );
// }
// add_action( 'customize_register', 'justin_bio_customizer' );


/* =====================================================================
 * 10. FRONTEND ASSETS + DATA
 * Enqueues the theme's stylesheet/font/JS, and localizes JUSTIN_DATA —
 * the full dataset (categories, entries with all their layout-specific
 * fields, God Mode channels, Stripe config) that main.js runs on.
 * ===================================================================== */

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('justin-style', get_stylesheet_uri(), [], '1.1');
    wp_enqueue_style('justin-font', 'https://fonts.googleapis.com/css2?family=Nothing+You+Could+Do&display=swap', [], null);

    // Stripe's own JS SDK must load before main.js when Stripe is
    // configured, since main.js calls the global Stripe() constructor.
    $stripe_publishable_key = get_option('justin_stripe_publishable_key', '');
    $main_js_deps = [];

    if ($stripe_publishable_key) {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        $main_js_deps[] = 'stripe-js';
    }

    // wp_enqueue_script('justin-main', get_template_directory_uri() . '/assets/js/main.js', [], '1.1', true);
    wp_enqueue_script('justin-main', get_template_directory_uri() . '/assets/js/main.js', $main_js_deps, '1.3', true);

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
        // Stripe: only the publishable key ever reaches the browser.
        // If this is empty, main.js falls back to plain buy_url links.
        'stripePublishableKey' => $stripe_publishable_key,
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'stripeNonce' => wp_create_nonce('justin_stripe_nonce'),
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
            'infoDisabled' => justin_parse_bool(justin_get_meta($post->ID, 'disable_info_text')),
            'images' => $images,
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
            setup_postdata($post);

            $price_major = (float) justin_get_meta($post->ID, 'buy_price', 0);
            $currency    = justin_get_meta($post->ID, 'buy_currency', 'eur');

            // Book Template's own thumbnail set, completely separate
            // from the regular 'gallery' field. getEntryImages() in
            // main.js checks entry.book.images first, so Book Template
            // posts use these instead of falling back to entry.images.
            $book_template_ids = justin_get_attachment_ids(justin_get_meta($post->ID, 'book_template_images'));

            $entry['book'] = [
                'content'    => apply_filters('the_content', $post->post_content),
                'buyUrl'     => esc_url_raw(justin_get_meta($post->ID, 'buy_url')),
                // priceCents/currency drive the Stripe modal. If priceCents
                // is 0, main.js falls back to plain buyUrl behavior.
                'priceCents' => (int) round($price_major * 100),
                'currency'   => $currency,
                'title'      => get_the_title($post),
                'images'     => justin_extract_image_urls($book_template_ids),
            ];

            wp_reset_postdata();
        }

        // if ($cat_name === 'Books') {
        //     $book_images = justin_extract_image_urls(justin_get_attachment_ids(justin_get_meta($post->ID, 'book_images')));

        //     $entry['book'] = [
        //         'images' => $book_images,
        //         'text' => wp_kses_post(justin_get_meta($post->ID, 'book_text')),
        //         'buyUrl' => esc_url_raw(justin_get_meta($post->ID, 'buy_url')),
        //     ];
        // }

        // Book Template: fully independent of the 'Books' category — driven
        // only by layout_style, so this keeps working even on non-Books
        // posts, and even if the Books box/category is removed entirely.
        if ($layout_style === 'book_template') {
            setup_postdata($post);

            $bt_price_major = (float) justin_get_meta($post->ID, 'book_template_price', 0);
            $bt_currency    = justin_get_meta($post->ID, 'book_template_currency', 'eur');
            $bt_buy_url     = esc_url_raw(justin_get_meta($post->ID, 'book_template_buy_url'));
            $bt_image_ids   = justin_get_attachment_ids(justin_get_meta($post->ID, 'book_template_images'));

            $entry['bookTemplate'] = [
                'content'    => apply_filters('the_content', $post->post_content),
                'images'     => justin_extract_image_urls($bt_image_ids),
                'buyUrl'     => $bt_buy_url,
                'priceCents' => (int) round($bt_price_major * 100),
                'currency'   => $bt_currency,
                'title'      => get_the_title($post),
            ];

            wp_reset_postdata();
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


/* =====================================================================
 * 11. COMMERCIAL TEMPLATE PAGE
 * A "Page Background Color" field, only shown on Pages using
 * templates/template-commercial.php.
 * ===================================================================== */

if (!function_exists('justin_link_list_meta_box')) {
    add_action('add_meta_boxes', function () {
        add_meta_box(
            'justin-link-list-bg',
            'Page Background Color',
            'justin_render_link_list_bg_box',
            'page',
            'side',
            'default'
        );
    });

    function justin_render_link_list_bg_box($post) {
        $template = get_page_template_slug($post->ID);

        // Match by filename only, so this works whether the file lives in
        // the theme root or inside a subfolder like templates/.
        if (basename((string) $template) !== 'template-commercial.php') {
            echo '<p style="color:#777;">Only available on pages using the "Commercial Template".</p>';
            return;
        }

        wp_nonce_field('justin_save_link_list_bg', 'justin_link_list_bg_nonce');
        $color = get_post_meta($post->ID, 'link_list_bg_color', true) ?: '#ffffff';
        ?>
        <p>
            <label for="link_list_bg_color"><strong>Background color</strong></label><br />
            <input type="text" id="link_list_bg_color" name="link_list_bg_color" value="<?php echo esc_attr($color); ?>" class="justin-color-field" data-default-color="#ffffff" />
        </p>
        <?php
    }
}

add_action('save_post_page', function ($post_id) {
    if (!isset($_POST['justin_link_list_bg_nonce']) || !wp_verify_nonce($_POST['justin_link_list_bg_nonce'], 'justin_save_link_list_bg')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_page', $post_id)) {
        return;
    }

    if (isset($_POST['link_list_bg_color'])) {
        $color = sanitize_hex_color(wp_unslash($_POST['link_list_bg_color']));
        if ($color) {
            update_post_meta($post_id, 'link_list_bg_color', $color);
        } else {
            delete_post_meta($post_id, 'link_list_bg_color');
        }
    }
});