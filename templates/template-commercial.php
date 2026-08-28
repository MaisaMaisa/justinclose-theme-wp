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

if (!function_exists('justin_link_list_block_to_text')) {
    function justin_link_list_block_to_text($block) {
        $name = $block['blockName'] ?? '';

        if ($name === 'core/paragraph') {
            $html = $block['innerHTML'] ?? '';
            $html = preg_replace('/^\s*<p\b[^>]*>/i', '', $html);
            $html = preg_replace('/<\/p>\s*$/i', '', $html);
            return trim(wp_kses_post($html));
        }

        if ($name === 'core/embed') {
            $url = $block['attrs']['url'] ?? '';
            if (!$url) {
                return '';
            }
            return '<a href="' . esc_url($url) . '">' . esc_html($url) . '</a>';
        }

        if ($name === 'core/list') {
            $items = [];
            foreach ($block['innerBlocks'] ?? [] as $item) {
                $itemHtml = $item['innerHTML'] ?? '';
                $itemHtml = preg_replace('/^\s*<li\b[^>]*>/i', '', $itemHtml);
                $itemHtml = preg_replace('/<\/li>\s*$/i', '', $itemHtml);
                $text = trim(wp_kses_post($itemHtml));
                if ($text !== '') {
                    $items[] = $text;
                }
            }
            return implode("\n", $items);
        }

        return '';
    }
}

$font_classes = [
    'syne-mono-regular',
    'michroma-regular',
    'exo-2-regular',
    'oxanium-reg',
    'rubik-spray-paint-regular',
    'big-shoulders-regular',
    'anton-regular',
    'six-caps-regular',
    'sirin-stencil-regular',
    'jura-400',
    'syne-tactile-regular',
    'geist-mono-400',
    'sofia-sans-semi-condensed-regular',
    'nova-flat-regular',
    'matemasie-regular',
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
        $text = justin_link_list_block_to_text($block);
        if ($text === '') {
            continue;
        }
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    if (!$lines) {
        $raw = wp_strip_all_tags(get_the_content());
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
    }
    ?>

    <div class="link-list-wrap" style="<?php echo $bg_color ? 'background-color:' . esc_attr($bg_color) . ';' : ''; ?>">
        <?php foreach ($lines as $index => $line) :
            $class = $font_classes[$index % count($font_classes)];
            ?>
            <p class="link-list-line <?php echo esc_attr($class); ?>"><?php echo $line; ?></p>
        <?php endforeach; ?>
    </div>

<?php
endwhile;

get_footer('commercial');