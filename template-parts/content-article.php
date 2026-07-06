<?php get_template_part('template-parts/content', 'breakbtn'); ?>

<?php
// Get the product information fields
$year = get_post_meta(get_the_ID(), 'year', true);
$project = get_post_meta(get_the_ID(), 'project', true);
$collection = get_post_meta(get_the_ID(), 'collection', true);
$material = get_post_meta(get_the_ID(), 'material', true);
$dimensions = get_post_meta(get_the_ID(), 'dimensions', true);
$photography = get_post_meta(get_the_ID(), 'photography', true);
$exhibitions = get_post_meta(get_the_ID(), 'exhibitions', true);
$price = get_post_meta(get_the_ID(), 'price', true);
?>

<div class="product-data">
    <!-- Collapse Button -->
    <button class="collapse-btn" onclick="toggleProductData()">-</button>
    
    <ul class="collapsible">
        <?php if ($year): ?>
            <li><strong>Year:</strong> <?php echo esc_html($year); ?></li>
        <?php endif; ?>
        <?php if ($project): ?>
            <li><strong>Project:</strong> <?php echo esc_html($project); ?></li>
        <?php endif; ?>
        <?php if ($collection): ?>
            <li><strong>Collection:</strong> <?php echo esc_html($collection); ?></li>
        <?php endif; ?>

        <?php if ($material): ?>
            <?php
                $materials = array_map('trim', explode(',', $material));
            ?>
            <li class="product-material">
                <strong>Material:</strong>
                <ul class="material-list">
                    <?php foreach ($materials as $item): ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endif; ?>
        
        <?php if ($dimensions): ?>
            <li><strong>Dimensions:</strong> <?php echo esc_html($dimensions); ?></li>
        <?php endif; ?>
        <?php if ($photography): ?>
            <li><strong>Photography:</strong> <?php echo esc_html($photography); ?></li>
        <?php endif; ?>
        <?php if ($exhibitions): ?>
            <li><strong>Exhibitions:</strong> <?php echo esc_html($exhibitions); ?></li>
        <?php endif; ?>
        <?php if ($price): ?>
            <li><strong>Price:</strong> <?php echo esc_html($price); ?></li>
        <?php endif; ?>
        <?php
        // Retrieve custom fields for email subject and body
        $email_subject = get_post_meta(get_the_ID(), 'email_subject', true) ?: 'Product Inquiry';
        $email_body = get_post_meta(get_the_ID(), 'email_body', true) ?: 'I am interested in purchasing this product. Please provide more details.';
        ?>

        <li>
            <button class="buy-btn" onclick="createEmailDraft(<?php echo htmlspecialchars(json_encode([
                'subject' => $email_subject,
                'body' => $email_body,
            ])); ?>)">
                Buy Me
            </button>
        </li>
    </ul>
</div>

<div class="product-container">
    <div class="content-container">
        <?php
        $image_ids = get_post_meta(get_the_ID(), '_custom_slideshow_images', true);
        if (is_array($image_ids) && !empty($image_ids)) : ?>
            <div class="custom-slideshow">
                <?php foreach ($image_ids as $image_id) : ?>
                    <div class="slide">
                        <?php echo wp_get_attachment_image($image_id, 'large'); ?>
                    </div>
                <?php endforeach; ?>
                <button class="prev-slide"><</button>
                <button class="next-slide">></button>
            </div>
        <?php endif; ?>
        <?php the_content();?>
    </div>
    <?php
        $image_ids = get_post_meta(get_the_ID(), '_exhibition_slideshow_images', true);
        if (is_array($image_ids) && !empty($image_ids)) : ?>
            <div class="exhibition-slideshow">
                <?php foreach ($image_ids as $image_id) : ?>
                    <div class="expo-slide">
                        <?php echo wp_get_attachment_image($image_id, 'large'); ?>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php endif; ?>
</div>

<script>
//SLIDESHOWS (PRODUCT + EXHIBITION) SLIDE PHOTOS AND DETECT WHETHER IMAGES ARE PORTRAIT OR LANDSCAPE
document.addEventListener('DOMContentLoaded', function () {
    const slideshows = document.querySelectorAll('.custom-slideshow');

    slideshows.forEach(slideshow => {
        const slides = slideshow.querySelectorAll('.slide');
        const prevButton = slideshow.querySelector('.prev-slide');
        const nextButton = slideshow.querySelector('.next-slide');
        let currentSlide = 0;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.style.display = i === index ? 'block' : 'none';
            });
        }

        prevButton.addEventListener('click', function () {
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            showSlide(currentSlide);
        });

        nextButton.addEventListener('click', function () {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        });

        showSlide(currentSlide);
    });
});

window.addEventListener('load', () => {
    // Mobile only
    if (!window.matchMedia('(max-width: 768px)').matches) return;

    const slider = document.querySelector('.exhibition-slideshow');
    if (!slider) return;

    // Respect reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    let dir = 1;
    let speed = 100; // pixels per second
    let paused = true;
    let lastTime = performance.now();
    let pos = slider.scrollLeft;
    let delayTimeout = null;

    slider.style.scrollBehavior = 'auto';

    // --- Keep internal position in sync ---
    function syncPosition() {
        pos = slider.scrollLeft;
        lastTime = performance.now();
    }

    // --- Main loop ---
    function loop(now) {
        if (!paused) {
            const delta = (now - lastTime) / 1000;
            pos += speed * delta * dir;
            slider.scrollLeft = pos;

            const max = slider.scrollWidth - slider.clientWidth;

            if (pos >= max) {
                pos = max;
                dir = -1;
            }

            if (pos <= 0) {
                pos = 0;
                dir = 1;
            }
        }

        lastTime = now;
        requestAnimationFrame(loop);
    }

    requestAnimationFrame(loop);

    // --- Hover pause (desktop simulators / scrollbar) ---
    slider.addEventListener('mouseenter', () => paused = true);
    slider.addEventListener('mouseleave', () => {
        syncPosition();
        paused = false;
    });

    // --- Touch interaction ---
    slider.addEventListener('touchstart', () => {
        paused = true;
        syncPosition();
    }, { passive: true });

    slider.addEventListener('touchend', () => {
        syncPosition();
        paused = false;
    }, { passive: true });

    // --- Sync during momentum / scrollbar scroll ---
    slider.addEventListener('scroll', () => {
        if (paused) {
            syncPosition();
        }
    });

    // ===== Custom pause / resume events =====
    document.addEventListener('expo:pause', () => {
        paused = true;
        syncPosition();
    });

    document.addEventListener('expo:resume', () => {
        syncPosition();
        paused = false;
    });

    // --- Visibility control ---
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                delayTimeout = setTimeout(() => {
                    syncPosition();
                    paused = false;
                }, 3000);
            } else {
                paused = true;
                clearTimeout(delayTimeout);
            }
        });
    }, {
        threshold: 0.1
    });

    observer.observe(slider);
});

// ====== Break Button ======
document.addEventListener('DOMContentLoaded', function () {
    const breakButton = document.querySelector('.break-btn');
    const header = document.querySelector('header');
    const productData = document.querySelector('.product-data'); 
    const productItems = document.querySelectorAll('.product-data .collapsible li');
    const buyMeButton = document.querySelector('.buy-btn');
    const collapsible = document.querySelector('.product-data .collapsible');
    const toggleButton = document.querySelector('.product-data .collapse-btn');
    const bodyContent = document.querySelectorAll('body > *:not(header):not(.break-btn-wrapper):not(.product-data)');
    let scrollPosition = 0;

    breakButton.addEventListener('click', function () {
        const isBlurred = document.body.classList.contains('blur-active-2');
        const isMobile = window.innerWidth <= 768;

        if (isBlurred) {
            // ===== Unfreeze =====
            bodyContent.forEach(element => {
                element.style.filter = 'none';
                element.style.pointerEvents = 'auto';
            });
            productItems.forEach(item => {
                item.style.opacity = '1';
                item.style.visibility = 'visible'; // Reset visibility to make items reappear
                item.style.transition = 'opacity 0.5s ease';
            });
            buyMeButton.style.animation = 'none';
            document.body.classList.remove('blur-active-2');
            document.body.style.overflow = '';
            window.scrollTo(0, scrollPosition);

            breakButton.textContent = 'break';

            // Resume slideshow
            document.dispatchEvent(new Event('expo:resume'));
        } else {
            // ===== Freeze =====
            scrollPosition = window.scrollY; 
            bodyContent.forEach(element => {
                element.style.filter = 'blur(10px)';
                element.style.pointerEvents = 'none';
            });
            // productItems.forEach((item, index) => {
            //     if (index !== productItems.length - 1) {
            //         item.style.opacity = '0';
            //         item.style.transition = 'opacity 0.5s ease'; 
            //     }
            // });

            productItems.forEach((item, index) => {
                console.log(`Processing item ${index}:`, item); // Debug: Log each item

                if (index !== productItems.length - 1) {
                    // Apply opacity and visibility for hiding the element
                    item.style.transition = 'opacity 0.5s ease, visibility 0.5s ease';
                    item.style.opacity = '0';
                    item.style.visibility = 'hidden'; // Hides the element but keeps its space in the layout

                    // Debug: Check if styles are applied
                    console.log(`Item ${index} styles applied:`, item.style.opacity, item.style.visibility);
                }
            });

            buyMeButton.style.animation = isMobile 
                ? 'dance-mobile 7s infinite ease-in-out'
                : 'dance 7s infinite ease-in-out';

            document.body.classList.add('blur-active-2');
            document.body.style.overflow = 'hidden';

            breakButton.textContent = 'run';

            if (isMobile && collapsible && collapsible.classList.contains('collapsed')) {
                collapsible.classList.remove('collapsed');
                if (toggleButton) toggleButton.textContent = '-';
            }

            // Pause slideshow
            document.dispatchEvent(new Event('expo:pause'));
        }
    });
});

//EMAIL DRAFT WHEN THE USER CLICKS BUY-ME BUTTON
function createEmailDraft(data) {
    // Destructure the data object
    const { subject, body } = data;

    // Open the email draft
    window.location.href = `mailto:info@lauramrksa.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

//ON MOBILE COLLAPSE PRODUCT DATA
function toggleProductData() {
    // Select the collapsible element and the toggle button
    const collapsible = document.querySelector('.product-data .collapsible');
    const toggleButton = document.querySelector('.product-data .collapse-btn');

    // Debug: Check if elements are selected
    console.log('Collapsible:', collapsible);
    console.log('Toggle Button:', toggleButton);

    // Ensure the elements exist before proceeding
    if (!collapsible || !toggleButton) {
        console.error('Collapsible or toggle button not found!');
        return;
    }

    // Toggle the 'collapsed' class
    collapsible.classList.toggle('collapsed');

    // Debug: Check if the class is toggled
    console.log('Collapsed class applied:', collapsible.classList.contains('collapsed'));

    // Update the button text based on the state
    if (collapsible.classList.contains('collapsed')) {
        toggleButton.textContent = '+';
    } else {
        toggleButton.textContent = '-';
    }
}
</script>