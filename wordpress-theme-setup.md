# Building "Justin" as a WordPress Theme — Setup Guide

## The core idea

Your HTML file is really a **single-page app with a hardcoded data array** (`ENTRIES`). The fastest, most maintainable path to WordPress is:

- **Every project = one WordPress post** (using the built-in `post` type).
- **Every category in your `CATS` array = a real WordPress Category.**
- **ACF (Advanced Custom Fields)** stores the extra per-project data (images, vimeo ID, book text, hover-bg image, etc.) — the same fields your `ENTRIES` objects currently hold.
- **PHP builds the exact same `ENTRIES` array server-side** (from posts + ACF fields) and hands it to your existing JS as JSON.
- **Your JS barely changes.** `renderList()`, `openEntry()`, the lightbox, God Mode — all of it works as-is, because it's still just reading an `ENTRIES` array. You're only swapping *where that array comes from*.

This means you are **not** writing 4 separate PHP templates. You're writing one theme shell + one data bridge, and letting the JS you already built keep doing the category-specific rendering (Photography vs Film vs Books vs Painting/Collage) exactly like it does now.

---

## 1. Plugin you'll need

Install **Advanced Custom Fields** (free version is enough) from Plugins → Add New. This gives you a UI to build the per-post fields and an Options Page for global settings (God Mode video, etc.) without touching code every time content changes.

---

## 2. Theme file structure

Create a folder `wp-content/themes/justin/` with:

```
justin/
├── style.css              ← theme header + all your existing CSS
├── functions.php          ← setup, enqueue, ACF fields, data bridge
├── index.php              ← the page shell (#page, #nav-line, #list, lightbox, bio, god mode)
├── header.php
├── footer.php
├── screenshot.png          ← 1200x900 png, just for wp-admin theme picker
└── assets/
    └── js/
        └── main.js         ← your existing <script> block, trimmed (see step 6)
```

`style.css` top of file needs the WP theme header comment:

```css
/*
Theme Name: Justin
Author: You
Version: 1.0
*/
```
Then paste in **all** your existing `<style>` block content below that comment — it doesn't need to change at all.

---

## 3. Categories = your CATS array

In wp-admin → Posts → Categories, create these 9 categories (must match exactly, since your JS keys off the name):

```
Film, Photography, Text, Installation, Collage, Books, Painting, Press, Shop
```

**Category color** (currently your hardcoded `CAT_COLORS` array): add an ACF **Term Meta** field group so you can set/edit the color per category in the backend instead of hardcoding it:

- ACF → Field Groups → New → Location: "Taxonomy Term is equal to Category"
- Field: `cat_color` (Color Picker)

**Category bio** (currently `CAT_BIOS`): just use the built-in **Category Description** field in wp-admin — no extra plugin field needed.

---

## 4. ACF Field Groups (the per-project data)

Create these field groups in ACF, each scoped with a **Location rule** so only the relevant fields show up per category when editing a post.

### Group A — "Project Common" (shows on all posts)
| Field name | Type | Notes |
|---|---|---|
| `info_text` | Textarea or WYSIWYG | The ⓘ toggle-text / caption. Falls back to post title if empty, same as your `getEntryInfo()` fallback. |
| `hover_bg_image` | Image | Optional. |
| `use_as_hover_only` | True/False | If checked (and image set), hovering the list item shows the bg image and **no popup opens** — matches your `bgImage` behavior. |

### Group B — "Gallery" — shown if Category is Photography, Film, Painting, or Collage
| Field name | Type | Notes |
|---|---|---|
| `gallery` | Gallery | Images / screengrabs, same as your `slideshow`/`gallery`/`lightbox`/`hscroll` arrays — they all collapse to one flat image array in your JS anyway (`getEntryImages()`). |

### Group C — "Film" — shown if Category is Film
| Field name | Type | Notes |
|---|---|---|
| `vimeo_id` | Text | Just the numeric ID, same as `e.vimeo` today. |

### Group D — "Books" — shown if Category is Books
| Field name | Type | Notes |
|---|---|---|
| `book_images` | Gallery | Thumbnails + main stage images. |
| `book_text` | WYSIWYG | The always-visible blurb text next to the images. |
| `buy_url` | URL | Powers the BUY ME button. |
| `has_teaser` | True/False | Toggle to turn on the "upcoming book" teaser block. |
| `teaser_images` | Gallery | Only relevant if `has_teaser` is on — rendered blurred, same as your `teaser-img`/`teaser-thumb` classes already do via CSS. |
| `teaser_text` | WYSIWYG | Only relevant if `has_teaser` is on. |

### Group E — "Text-only body" — shown if Category is Text
| Field name | Type | Notes |
|---|---|---|
| `body_text` | WYSIWYG | Matches your `e.body` (the handwritten-font poetry block). |

For each group's **Location rules**, use: `Post Category is equal to Photography` (OR Film, OR Painting, OR Collage) etc. — ACF supports multiple OR rule groups in one field group.

---

## 5. `functions.php` — the data bridge

This is the one piece of real "glue" code. It loops all posts, reads their ACF fields, and rebuilds your exact `ENTRIES` shape as JSON, then hands it to your JS.

```php
<?php
// Theme setup
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});

// Enqueue styles/scripts + inject the entries JSON
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('justin-style', get_stylesheet_uri());
    wp_enqueue_style('justin-font', 'https://fonts.googleapis.com/css2?family=Nothing+You+Could+Do&display=swap');

    wp_enqueue_script('justin-main', get_template_directory_uri() . '/assets/js/main.js', [], '1.0', true);

    $data = [
        'cats'      => [],   // [{name, color}]
        'catBios'   => [],   // {name: description}
        'entries'   => [],   // same shape as your old ENTRIES array
        'godModeVideo' => get_field('god_mode_video_url', 'option') ?: 'https://assets.mixkit.co/videos/1164/1164-720.mp4',
    ];

    // Categories (order + color + bio)
    $terms = get_categories(['orderby' => 'term_order', 'hide_empty' => false]);
    foreach ($terms as $term) {
        $data['cats'][] = [
            'name'  => $term->name,
            'color' => get_field('cat_color', $term) ?: '#000000',
        ];
        $data['catBios'][$term->name] = $term->description;
    }

    // Posts → entries (newest first, matches your original array order)
    $posts = get_posts(['numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']);
    foreach ($posts as $post) {
        $cat = get_the_category($post->ID);
        $catName = $cat ? $cat[0]->name : '';

        $gallery = get_field('gallery', $post->ID) ?: [];
        $images  = array_map(fn($img) => $img['url'], $gallery);

        $entry = [
            'text'  => get_the_title($post),
            'cat'   => $catName,
            'info'  => get_field('info_text', $post->ID) ?: null,
            'images' => $images,
            'vimeo' => get_field('vimeo_id', $post->ID) ?: null,
            'body'  => get_field('body_text', $post->ID) ?: null,
            'bgImage' => null,
        ];

        if (get_field('use_as_hover_only', $post->ID) && get_field('hover_bg_image', $post->ID)) {
            $entry['bgImage'] = get_field('hover_bg_image', $post->ID)['url'];
        }

        if ($catName === 'Books') {
            $bookImgs = get_field('book_images', $post->ID) ?: [];
            $entry['book'] = [
                'images' => array_map(fn($img) => $img['url'], $bookImgs),
                'text'   => get_field('book_text', $post->ID) ?: '',
                'buyUrl' => get_field('buy_url', $post->ID) ?: '#',
            ];
            if (get_field('has_teaser', $post->ID)) {
                $teaserImgs = get_field('teaser_images', $post->ID) ?: [];
                $entry['book']['teasers'] = array_map(fn($img) => $img['url'], $teaserImgs);
                $entry['book']['teaserText'] = get_field('teaser_text', $post->ID) ?: '';
            }
        }

        $data['entries'][] = $entry;
    }

    wp_localize_script('justin-main', 'JUSTIN_DATA', $data);
});

// ACF Options Page for God Mode + global settings
add_action('acf/init', function () {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page([
            'page_title' => 'Site Settings',
            'menu_title' => 'Site Settings',
            'menu_slug'  => 'site-settings',
        ]);
    }
});
```

A few notes on this:
- `get_field('cat_color', $term)` reading ACF on a **term object** — pass the `$term` object itself (ACF accepts that).
- I flattened `slideshow`/`gallery`/`lightbox`/`hscroll` into one `images` field, since your JS's `getEntryImages()` already treats them identically — you don't need 4 different ACF gallery fields, just one `gallery` field reused across those categories.
- `isEntryEmpty()` doesn't need a PHP equivalent — keep computing it client-side exactly like you do now (`getEntryImages(e).length === 0` etc.), since `JUSTIN_DATA.entries` gives the JS everything it needs to decide.

---

## 6. Updating `main.js`

Take your existing `<script>` block and make these small edits:

**Delete** these hardcoded consts (they now come from PHP):
```js
const CATS = [...]
const CAT_COLORS = [...]
const CAT_BIOS = {...}
const ENTRIES = [...]
```

**Replace** with:
```js
const CATS = JUSTIN_DATA.cats.map(c => c.name);
const CAT_COLORS = JUSTIN_DATA.cats.map(c => c.color);
const CAT_BIOS = JUSTIN_DATA.catBios;
CAT_BIOS['all'] = CAT_BIOS['all'] || 'Justin is an artist...'; // optional fallback
const ENTRIES = JUSTIN_DATA.entries;
```

**Update `getEntryImages()`** since everything is now flattened into `e.images`:
```js
function getEntryImages(e) {
  if (e.book) return e.book.images;
  return e.images || [];
}
```

**Update the God Mode video source** in `index.php` (see step 7) to use `JUSTIN_DATA.godModeVideo` instead of a hardcoded `src`.

Everything else — `openEntry()`, `renderLightboxContent()`, `renderBookStage()`, the `upside-down` class logic for Painting/Collage, `isEntryEmpty()`, the hover-bg logic, `closeLightbox()` — stays **exactly as you already wrote it.** That's the whole point of this architecture: the four "templates" you described (Photography, Film, Books, Painting/Collage) are just branches your JS already handles based on `e.cat`, and that logic doesn't care whether the array came from a `<script>` tag or from PHP.

---

## 7. `index.php`

This is just your existing `<body>` markup, converted to a WP template:

```php
<?php get_header(); ?>

<div id="justin-label"><span>Justin</span></div>
<a id="god-mode-btn" href="#">(GOD MODE)</a>

<div id="god-mode-overlay">
  <video id="god-mode-video" src="<?php echo esc_url($godModeVideo ?? ''); ?>" autoplay loop muted playsinline></video>
</div>

<div id="page">
  <div id="nav-line"></div>
  <ul id="list"></ul>
</div>

<!-- lightbox-overlay, bg-hover-img, bio-section markup — copy verbatim from your HTML file -->

<?php get_footer(); ?>
```

Set the video `src` via PHP so it's editable in Site Settings → ACF Options Page rather than hardcoded — or just let the JS assign it from `JUSTIN_DATA.godModeVideo` on load (either works; PHP is simpler).

`header.php` / `footer.php` just wrap the standard `wp_head()` / `wp_footer()` calls; nothing fancy needed since this isn't a multi-page site.

---

## 8. Editorial workflow, once it's built

For someone editing content in wp-admin, day-to-day looks like:

1. **Posts → Add New**
2. Title = the sentence fragment (e.g. `is building a house on a far away island.`) — this becomes the list text exactly like today.
3. Pick a **Category** (Film, Photography, Books, etc.) — this determines which popup template applies, automatically.
4. Fill in whichever ACF fields appear for that category (gallery images, vimeo ID, book text, etc.).
5. Optionally check **"Use as hover-only"** + upload a background image, instead of filling in gallery/body fields, to get the bgImage hover behavior with no popup.
6. Leave everything blank → it auto-shows `(empty)` on hover and doesn't open, no extra flag needed.
7. Publish. It appears at the top of the list immediately (standard reverse-chronological post order).

God Mode's video and any other sitewide settings live under **Site Settings** (the ACF Options Page), editable without touching a post at all.

---

## 9. Suggested build order

1. Scaffold the theme folder + `style.css` header, activate a blank theme so wp-admin doesn't error.
2. Install ACF, build the field groups from Section 4.
3. Create the 9 categories + set colors/bios.
4. Write `functions.php` data bridge (Section 5), confirm `JUSTIN_DATA` shows up correctly by `console.log`-ing it in the browser.
5. Copy your CSS into `style.css` under the theme header.
6. Copy your JS into `assets/js/main.js`, apply the edits from Section 6.
7. Build `index.php`/`header.php`/`footer.php` from your existing HTML body.
8. Add a couple of test posts per category and click through every popup type to confirm parity with your static file.

---

If you want, I can scaffold the actual theme files (functions.php, index.php, main.js) as a starting zip once you've got ACF installed and the categories created — that'll save you the copy-paste work in steps 5–7.
