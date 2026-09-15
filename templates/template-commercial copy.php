<?php get_template_part('template-parts/content', 'head'); ?>
<?php
/**
 * Template Name: Link List Template
 *
 * Renders each top-level block in the page's content as one plain-text
 * line, cycling through a different typeface per line. Embed blocks
 * (YouTube, Instagram, Google Drive, etc.) are converted straight to a
 * plain link — the actual embed/render pipeline is never invoked, so
 * nothing can turn into a video/iframe on this template.
 */

if (!function_exists('justin_link_list_extract_tag_attrs')) {
    // Pulls style="" and class="" off an opening tag (e.g. "<p class='...' style='...'>")
    // before it gets stripped, so paragraph/list-item-level colors set in the
    // block editor survive instead of being silently discarded.
    function justin_link_list_extract_tag_attrs($open_tag) {
        $style = '';
        $class = '';

        if (preg_match('/style\s*=\s*"([^"]*)"/i', $open_tag, $matches)) {
            $style = $matches[1];
        }
        if (preg_match('/class\s*=\s*"([^"]*)"/i', $open_tag, $matches)) {
            $class = $matches[1];
        }

        return ['style' => $style, 'class' => $class];
    }
}

if (!function_exists('justin_link_list_block_to_text')) {
    // Returns an array of ['text' => ..., 'style' => ..., 'class' => ...]
    // — one entry per rendered line — instead of a plain string, so any
    // inline color/style from the block editor travels with its line.
    function justin_link_list_block_to_text($block) {
        $name = $block['blockName'] ?? '';

        if ($name === 'core/paragraph') {
            $html = $block['innerHTML'] ?? '';
            $attrs = [];
            if (preg_match('/^\s*(<p\b[^>]*>)/i', $html, $matches)) {
                $attrs = justin_link_list_extract_tag_attrs($matches[1]);
            }
            $html = preg_replace('/^\s*<p\b[^>]*>/i', '', $html);
            $html = preg_replace('/<\/p>\s*$/i', '', $html);
            $text = trim(wp_kses_post($html));

            if ($text === '') {
                return [];
            }

            return [[
                'text'  => $text,
                'style' => $attrs['style'] ?? '',
                'class' => $attrs['class'] ?? '',
            ]];
        }

        if ($name === 'core/embed') {
            $url = $block['attrs']['url'] ?? '';
            if (!$url) {
                return [];
            }
            return [[
                'text'  => '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>',
                'style' => '',
                'class' => '',
            ]];
        }

        if ($name === 'core/list') {
            $items = [];
            foreach ($block['innerBlocks'] ?? [] as $item) {
                $itemHtml = $item['innerHTML'] ?? '';
                $attrs = [];
                if (preg_match('/^\s*(<li\b[^>]*>)/i', $itemHtml, $matches)) {
                    $attrs = justin_link_list_extract_tag_attrs($matches[1]);
                }
                $itemHtml = preg_replace('/^\s*<li\b[^>]*>/i', '', $itemHtml);
                $itemHtml = preg_replace('/<\/li>\s*$/i', '', $itemHtml);
                $text = trim(wp_kses_post($itemHtml));
                if ($text !== '') {
                    $items[] = [
                        'text'  => $text,
                        'style' => $attrs['style'] ?? '',
                        'class' => $attrs['class'] ?? '',
                    ];
                }
            }
            return $items;
        }

        return [];
    }
}

$font_classes = [
    'syne-mono-regular',
    'michroma-regular',
    'exo-2-regular',
    'oxanium-reg',
    // 'rubik-spray-paint-regular',
    'big-shoulders-regular',
    'anton-regular',
    // 'six-caps-regular',
    'sirin-stencil-regular',
    'jura-400',
    'syne-tactile-regular',
    'geist-mono-400',
    'sofia-sans-semi-condensed-regular',
    'nova-flat-regular',
    // 'matemasie-regular',
    'nunito-regular',
    'quicksand-REGULAR',
    'asap-400',
    'archivo-regular',
];

while (have_posts()) :
    the_post();

    $bg_color = get_post_meta(get_the_ID(), 'link_list_bg_color', true);

    if (post_password_required()) {
        ?>
        <div class="link-list-wrap" style="<?php echo $bg_color ? 'background-color:' . esc_attr($bg_color) . ';' : ''; ?>">
            <?php echo get_the_password_form(); ?>
        </div>
        <?php
        continue;
    }

    $blocks = parse_blocks(get_the_content());
    $lines = [];

    foreach ($blocks as $block) {
        foreach (justin_link_list_block_to_text($block) as $line) {
            if (trim(wp_strip_all_tags($line['text'])) !== '') {
                $lines[] = $line;
            }
        }
    }

    if (!$lines) {
        $raw = wp_strip_all_tags(get_the_content());
        foreach (array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))) as $plain_line) {
            $lines[] = ['text' => $plain_line, 'style' => '', 'class' => ''];
        }
    }
    ?>

    <div class="link-list-wrap" style="<?php echo $bg_color ? 'background-color:' . esc_attr($bg_color) . ';' : ''; ?>">
        <?php foreach ($lines as $index => $line) :
            $font_class = $font_classes[$index % count($font_classes)];
            $combined_class = trim('link-list-line ' . $font_class . ' ' . ($line['class'] ?? ''));
            $style_attr = $line['style'] ?? '';
            ?>
            <p class="<?php echo esc_attr($combined_class); ?>"<?php echo $style_attr !== '' ? ' style="' . esc_attr($style_attr) . '"' : ''; ?>><?php echo $line['text']; ?></p>
        <?php endforeach; ?>
    </div>

<?php
endwhile;

get_footer('commercial');