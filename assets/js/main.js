(function () {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }

  var data = window.JUSTIN_DATA || {};
  var state = {
    activeCategory: '',
    activeEntryIndex: -1,
    activeImageIndex: 0,
    watchMode: false,
    activeBookBuyBtn: null,
  };

  var elements = {
    bgHover: document.getElementById('bg-hover-img'),
    bioSection: document.getElementById('bio-section'),
    bioToggle: document.getElementById('bio-toggle'),
    bioCopy: document.querySelector('#bio-section .bio-copy'),
    justinLabel: document.getElementById('justin-label'),
    godModeBtn: document.getElementById('god-mode-btn'),
    godModeOverlay: document.getElementById('god-mode-overlay'),
    godModeFrame: document.getElementById('god-mode-frame'),
    page: document.getElementById('page'),
    navLine: document.getElementById('nav-line'),
    list: document.getElementById('list'),
    lightboxOverlay: document.getElementById('lightbox-overlay'),
    lightboxStage: document.getElementById('lightbox-stage'),
    lightboxInfoPanel: document.getElementById('lightbox-info-panel'),
    lightboxThumbs: document.getElementById('lightbox-thumbs'),
    lightboxInfoToggle: document.querySelector('.lightbox-info-toggle'),
    lightboxClose: document.querySelector('.lightbox-close'),
    lightboxPrev: document.querySelector('.lightbox-arrow.prev'),
    lightboxNext: document.querySelector('.lightbox-arrow.next'),
  };

  if (!elements.page || !elements.list || !elements.navLine || !elements.lightboxOverlay || !elements.lightboxStage) {
    return;
  }

  var entries = Array.isArray(data.entries) ? data.entries : [];
  var categories = shuffleArray(Array.isArray(data.cats) ? data.cats : []);
  var categoryLightboxColors = {};
  (Array.isArray(data.cats) ? data.cats : []).forEach(function (item) {
    if (item && item.name) {
      categoryLightboxColors[item.name] = item.lightboxColor || '';
    }
  });
  var categoryBios = data.catBios || {};
  var siteBio = data.siteDescription || data.siteTitle || '';
  var justinLabelFrame = null;

  // God Mode channel state — fed by data.godModeChannels (from the
  // Appearance > Justin Settings repeater), not a single video URL.
  var godModeChannels = Array.isArray(data.godModeChannels) ? data.godModeChannels : [];
  var godModeIndex = 0;
  var godModeFlipping = false;

  var mobileQuery = window.matchMedia('(max-width: 700px)');
  var scrollActiveSummary = null;
  var scrollSpyFrame = null;

  // ==========================================================
  // STRIPE "BUY ME" MODAL
  //
  // Opens over the existing lightbox as a single self-contained
  // popup: quantity stepper + Stripe's Link/Address/Payment
  // elements, all mounted against one PaymentIntent. Nothing here
  // ever sees your secret key — that lives server-side in
  // functions.php's justin_create_payment_intent AJAX handler.
  //
  // Two independent "sources" can open this modal:
  //   - 'book'          -> reads price/currency from the Books box
  //                        (entry.book), used by the default Books layout.
  //   - 'book_template' -> reads price/currency from the Book Template
  //                        box (entry.bookTemplate), used by the Book
  //                        Template layout. Fully independent data, so
  //                        Book Template keeps working even if the
  //                        Books box/category is removed entirely.
  //
  // If data.stripePublishableKey is empty, or the relevant source has no
  // priceCents set, this whole module is a no-op and the button falls
  // back to opening its buyUrl in a new tab — so nothing breaks for
  // items you haven't priced yet.
  // ==========================================================
  var stripeState = {
    stripe: null,
    elements: null,
    clientSecret: null,
    quantity: 1,
    entry: null,
    source: 'book',
    submitting: false,
    fetchToken: 0,
  };

  var stripeEls = {};

  function formatStripeMoney(cents, currency) {
    return (cents / 100).toFixed(2) + ' ' + String(currency || '').toUpperCase();
  }

  function buildStripeModal() {
    if (stripeEls.overlay) {
      return;
    }

    var overlay = document.createElement('div');
    overlay.id = 'stripe-buy-overlay';
    overlay.className = 'stripe-buy-overlay';
    overlay.innerHTML =
      '<div class="stripe-buy-modal">' +
        '<button type="button" class="stripe-buy-close" aria-label="Close">&times;</button>' +
        '<h3 class="stripe-buy-title"></h3>' +
        '<div class="stripe-buy-qty-row">' +
          '<span class="stripe-buy-qty-label">Copies</span>' +
          '<div class="stripe-qty-stepper">' +
            '<button type="button" class="stripe-qty-btn stripe-qty-minus" aria-label="Decrease quantity">&minus;</button>' +
            '<span class="stripe-qty-value">1</span>' +
            '<button type="button" class="stripe-qty-btn stripe-qty-plus" aria-label="Increase quantity">+</button>' +
          '</div>' +
          '<span class="stripe-buy-total"></span>' +
        '</div>' +
        '<div id="stripe-link-auth-element" class="stripe-buy-field"></div>' +
        '<div id="stripe-address-element" class="stripe-buy-field"></div>' +
        '<div id="stripe-payment-element" class="stripe-buy-field"></div>' +
        '<div class="stripe-buy-error" id="stripe-buy-error"></div>' +
        '<button type="button" class="stripe-buy-submit" disabled>Pay</button>' +
      '</div>';

    document.body.appendChild(overlay);

    stripeEls.overlay = overlay;
    stripeEls.title = overlay.querySelector('.stripe-buy-title');
    stripeEls.qtyValue = overlay.querySelector('.stripe-qty-value');
    stripeEls.total = overlay.querySelector('.stripe-buy-total');
    stripeEls.error = overlay.querySelector('#stripe-buy-error');
    stripeEls.submitBtn = overlay.querySelector('.stripe-buy-submit');
    stripeEls.modal = overlay.querySelector('.stripe-buy-modal');

    overlay.querySelector('.stripe-buy-close').addEventListener('click', closeStripeModal);
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeStripeModal();
      }
    });

    overlay.querySelector('.stripe-qty-minus').addEventListener('click', function () {
      setStripeQuantity(stripeState.quantity - 1);
    });
    overlay.querySelector('.stripe-qty-plus').addEventListener('click', function () {
      setStripeQuantity(stripeState.quantity + 1);
    });

    stripeEls.submitBtn.addEventListener('click', submitStripePayment);
  }

  function setStripeQuantity(quantity) {
    quantity = Math.max(1, quantity);
    stripeState.quantity = quantity;
    stripeEls.qtyValue.textContent = String(quantity);
    refreshStripeIntent();
  }

  // source: 'book' (default) or 'book_template'.
  // sourceData: the object actually carrying priceCents/currency/buyUrl/title
  // — pass it explicitly so callers never have to guess which entry.* field
  // to read. Falls back to entry.book for backward compatibility when only
  // entry is passed (the default Books layout's existing call site).
  function openStripeModal(entry, source, sourceData) {
    source = source || 'book';
    var book = sourceData || (source === 'book_template' ? (entry && entry.bookTemplate) : (entry && entry.book));
    var stripeReady = !!(data.stripePublishableKey && book && book.priceCents);

    if (!stripeReady) {
      if (book && book.buyUrl) {
        window.open(book.buyUrl, '_blank', 'noopener');
      }
      return;
    }

    buildStripeModal();

    if (!stripeState.stripe) {
      stripeState.stripe = Stripe(data.stripePublishableKey);
    }

    stripeState.entry = entry;
    stripeState.source = source;
    stripeState.quantity = 1;
    stripeEls.qtyValue.textContent = '1';
    stripeEls.title.textContent = book.title || entry.text || 'Order';
    stripeEls.error.textContent = '';

    overlayOpenStripe();
    refreshStripeIntent();
  }

  function overlayOpenStripe() {
    stripeEls.overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeStripeModal() {
    if (!stripeEls.overlay) {
      return;
    }

    stripeEls.overlay.classList.remove('open');
    document.body.style.overflow = '';
    stripeState.elements = null;
    stripeState.clientSecret = null;
    stripeState.entry = null;

    var linkEl = document.getElementById('stripe-link-auth-element');
    var addressEl = document.getElementById('stripe-address-element');
    var paymentEl = document.getElementById('stripe-payment-element');
    if (linkEl) linkEl.innerHTML = '';
    if (addressEl) addressEl.innerHTML = '';
    if (paymentEl) paymentEl.innerHTML = '';
  }

  function refreshStripeIntent() {
    var entry = stripeState.entry;
    if (!entry) {
      return;
    }

    var thisFetch = ++stripeState.fetchToken;
    stripeEls.total.textContent = 'Calculating…';
    stripeEls.error.textContent = '';
    stripeEls.submitBtn.disabled = true;

    var body = new URLSearchParams();
    body.set('action', 'justin_create_payment_intent');
    body.set('nonce', data.stripeNonce || '');
    body.set('post_id', entry.id);
    body.set('quantity', stripeState.quantity);
    body.set('source', stripeState.source || 'book');

    fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (result) {
        if (thisFetch !== stripeState.fetchToken) {
          return; // a newer quantity change superseded this response
        }

        if (!result || !result.success) {
          var message = (result && result.data && result.data.message) || 'Could not start payment.';
          stripeEls.error.textContent = message;
          stripeEls.total.textContent = '';
          return;
        }

        stripeState.clientSecret = result.data.clientSecret;
        stripeEls.total.textContent = 'Total: ' + formatStripeMoney(result.data.amount, result.data.currency);

        stripeState.elements = stripeState.stripe.elements({
          clientSecret: stripeState.clientSecret,
          appearance: { theme: 'stripe' },
        });

        var linkEl = document.getElementById('stripe-link-auth-element');
        var addressEl = document.getElementById('stripe-address-element');
        var paymentEl = document.getElementById('stripe-payment-element');
        linkEl.innerHTML = '';
        addressEl.innerHTML = '';
        paymentEl.innerHTML = '';

        var linkAuthElement = stripeState.elements.create('linkAuthentication');
        linkAuthElement.on('change', function (event) {
          stripeState.email = (event && event.value && event.value.email) || '';
        });
        linkAuthElement.mount(linkEl);

        stripeState.elements.create('address', { mode: 'shipping' }).mount(addressEl);
        stripeState.elements.create('payment').mount(paymentEl);

        stripeEls.submitBtn.disabled = false;
      })
      .catch(function () {
        if (thisFetch !== stripeState.fetchToken) {
          return;
        }
        stripeEls.error.textContent = 'Network error — please try again.';
        stripeEls.total.textContent = '';
      });
  }

  function submitStripePayment() {
    if (stripeState.submitting || !stripeState.elements) {
      return;
    }

    stripeState.submitting = true;
    stripeEls.submitBtn.disabled = true;
    stripeEls.error.textContent = '';

    var confirmParams = {
      return_url: window.location.href,
    };
    if (stripeState.email) {
      confirmParams.receipt_email = stripeState.email;
    }

    stripeState.stripe.confirmPayment({
      elements: stripeState.elements,
      confirmParams: confirmParams,
      redirect: 'if_required',
    }).then(function (result) {
      stripeState.submitting = false;

      if (result.error) {
        stripeEls.error.textContent = result.error.message || 'Payment failed. Please try again.';
        stripeEls.submitBtn.disabled = false;
        return;
      }

      stripeEls.modal.innerHTML =
        '<button type="button" class="stripe-buy-close" aria-label="Close">&times;</button>' +
        '<p class="stripe-buy-success">Thank you — a confirmation email was sent to your email address.</p>';
      stripeEls.modal.querySelector('.stripe-buy-close').addEventListener('click', closeStripeModal);
    });
  }

  function updateScrollSpy() {
    if (!mobileQuery.matches) {
      if (scrollActiveSummary) {
        scrollActiveSummary.classList.remove('scroll-active');
        scrollActiveSummary = null;
      }
      return;
    }

    var summaries = elements.list.querySelectorAll('summary');
    if (!summaries.length) {
      return;
    }

    var viewportCenter = window.innerHeight / 2;
    var closest = null;
    var closestDistance = Infinity;

    summaries.forEach(function (summary) {
      var rect = summary.getBoundingClientRect();
      var summaryCenter = rect.top + rect.height / 2;
      var distance = Math.abs(summaryCenter - viewportCenter);

      if (distance < closestDistance) {
        closestDistance = distance;
        closest = summary;
      }
    });

    if (closest !== scrollActiveSummary) {
      if (scrollActiveSummary) {
        scrollActiveSummary.classList.remove('scroll-active');
      }
      if (closest) {
        closest.classList.add('scroll-active');
      }
      scrollActiveSummary = closest;
    }
  }

  function onScrollOrResize() {
    if (scrollSpyFrame) {
      return;
    }
    scrollSpyFrame = window.requestAnimationFrame(function () {
      updateScrollSpy();
      scrollSpyFrame = null;
    });
  }

  // ---- Mobile-only infinite list loop ----
  var LIST_LOOP_SETS = 3;
  var listLoopScrollHandler = null;
  var lastLoopScrollY = null;

  function getListSetHeight() {
    if (!elements.list.children.length) {
      return 0;
    }
    return elements.list.scrollHeight / LIST_LOOP_SETS;
  }

  function setupListLoop() {
    var setHeight = getListSetHeight();
    if (!setHeight) {
      return;
    }

    lastLoopScrollY = null;

    if (!listLoopScrollHandler) {
      listLoopScrollHandler = function () {
        if (!mobileQuery.matches) {
          return;
        }

        var currentSetHeight = getListSetHeight();
        if (!currentSetHeight) {
          return;
        }

        var currentScrollY = window.scrollY;
        var maxScrollY = document.documentElement.scrollHeight - window.innerHeight;

        if (lastLoopScrollY === null) {
          lastLoopScrollY = currentScrollY;
          return;
        }

        var scrollingUp = currentScrollY < lastLoopScrollY;
        var scrollingDown = currentScrollY > lastLoopScrollY;

        if (scrollingUp && currentScrollY <= 0) {
          var newScrollUp = currentScrollY + currentSetHeight;
          window.scrollTo(0, newScrollUp);
          lastLoopScrollY = newScrollUp;
          return;
        }

        if (scrollingDown && currentScrollY >= maxScrollY - 1) {
          var newScrollDown = currentScrollY - currentSetHeight;
          window.scrollTo(0, newScrollDown);
          lastLoopScrollY = newScrollDown;
          return;
        }

        lastLoopScrollY = currentScrollY;
      };

      window.addEventListener('scroll', listLoopScrollHandler, { passive: true });
    }
  }

  function shuffleArray(array) {
    var shuffled = array.slice();
    for (var i = shuffled.length - 1; i > 0; i -= 1) {
      var j = Math.floor(Math.random() * (i + 1));
      var temp = shuffled[i];
      shuffled[i] = shuffled[j];
      shuffled[j] = temp;
    }
    return shuffled;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function isHoverOnly(entry) {
    return !!(entry && entry.hoverOnly && entry.bgImage);
  }

  // Book Template's own images (entry.bookTemplate.images) take priority
  // over the default Books images (entry.book.images), so Book Template
  // is fully independent of the Books data.
  function getEntryImages(entry) {
    if (!entry) {
      return [];
    }

    if (entry.film && Array.isArray(entry.film.images)) {
      return entry.film.images.slice();
    }

    if (entry.bookTemplate && Array.isArray(entry.bookTemplate.images)) {
      return entry.bookTemplate.images.slice();
    }

    if (entry.book && Array.isArray(entry.book.images)) {
      return entry.book.images.slice();
    }

    return Array.isArray(entry.images) ? entry.images.slice() : [];
  }

  function getEntryInfo(entry) {
    if (!entry || entry.infoDisabled) {
      return '';
    }

    if (entry.info && entry.info.length) {
      return entry.info;
    }

    return '<p>' + escapeHtml(entry.text || '') + '</p>';
  }

  function getVideoEmbedUrl(videoUrl) {
    var rawUrl = String(videoUrl || '').trim();

    if (!rawUrl) {
      return '';
    }

    try {
      var parsedUrl = new URL(rawUrl);
      var host = parsedUrl.hostname.replace(/^www\./, '');

      if (host.indexOf('youtu') !== -1) {
        var youtubeId = '';
        if (parsedUrl.searchParams.get('v')) {
          youtubeId = parsedUrl.searchParams.get('v');
        } else {
          var youtubeMatch = rawUrl.match(/(?:youtu\.be\/|youtube\.com\/(?:shorts\/|embed\/|watch\?v=))([^?&/]+)/i);
          youtubeId = youtubeMatch ? youtubeMatch[1] : '';
        }

        return youtubeId ? 'https://www.youtube.com/embed/' + encodeURIComponent(youtubeId) + '?autoplay=1&rel=0' : rawUrl;
      }

      if (host.indexOf('vimeo') !== -1) {
        var vimeoMatch = rawUrl.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
        var vimeoId = vimeoMatch ? vimeoMatch[1] : '';
        return vimeoId
          ? 'https://player.vimeo.com/video/' + encodeURIComponent(vimeoId) + '?autoplay=1&muted=1&loop=1&title=0&byline=0&portrait=0'
          : rawUrl;
      }
    } catch (error) {
      return rawUrl;
    }

    return rawUrl;
  }

  function isEntryEmpty(entry) {
    if (!entry) {
      return true;
    }

    var hasImages = getEntryImages(entry).length > 0;
    var hasBody = !!(entry.body && entry.body.length);
    var hasFilmVideo = !!(entry.film && entry.film.videoUrl && String(entry.film.videoUrl).trim().length);
    var hasDirectVideo = !!(entry.videoUrl && String(entry.videoUrl).trim().length);
    var hasBookContent = !!(
      (entry.book && entry.book.content && String(entry.book.content).trim().length) ||
      (entry.bookTemplate && entry.bookTemplate.content && String(entry.bookTemplate.content).trim().length)
    );

    return !hasImages && !hasBody && !hasFilmVideo && !hasDirectVideo && !hasBookContent;
  }

  function getCategoryBio(categoryName) {
    if (categoryName && categoryBios[categoryName]) {
      return categoryBios[categoryName];
    }

    return siteBio;
  }

  function setBioText(html) {
    if (elements.bioCopy) {
      elements.bioCopy.innerHTML = html || '';
    }
  }

  function setBackground(url) {
    if (!elements.bgHover) {
      return;
    }

    if (url) {
      elements.bgHover.style.backgroundImage = 'url("' + url + '")';
      elements.bgHover.classList.add('visible');
      return;
    }

    elements.bgHover.style.backgroundImage = '';
    elements.bgHover.classList.remove('visible');
  }

  function buildVideoStage(entry) {
    var videoUrl = entry && entry.film ? entry.film.videoUrl : '';
    var embedUrl = getVideoEmbedUrl(videoUrl);
    var caption = getEntryInfo(entry);

    if (embedUrl) {
      elements.lightboxStage.innerHTML =
        '<div class="film-watch-wrap">' +
          '<iframe class="film-vimeo" src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>' +
        '</div>' +
        (caption ? '<div class="film-caption">' + caption + '</div>' : '');
    } else {
      elements.lightboxStage.innerHTML = '';
    }

    elements.lightboxThumbs.innerHTML = '';
  }

  function renderVideoDirectLightbox(entry) {
    var embedUrl = getVideoEmbedUrl(entry.videoUrl);

    if (embedUrl) {
      elements.lightboxStage.innerHTML = '<iframe class="film-vimeo" src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
    } else {
      elements.lightboxStage.innerHTML = '';
    }

    elements.lightboxThumbs.innerHTML = '';
    elements.lightboxThumbs.style.display = 'none';
    elements.lightboxPrev.style.display = 'none';
    elements.lightboxNext.style.display = 'none';
    elements.lightboxInfoToggle.style.display = '';
    elements.lightboxInfoPanel.style.display = '';
    var videoDirectInfo = getEntryInfo(entry);
    elements.lightboxInfoPanel.innerHTML = videoDirectInfo;
    if (!videoDirectInfo) {
      elements.lightboxInfoToggle.style.display = 'none';
      elements.lightboxInfoPanel.style.display = 'none';
    }
  }

  function syncMainImageAspectRatio(mainImage) {
    if (!mainImage) {
      return;
    }

    var applyRatio = function () {
      if (mainImage.naturalWidth && mainImage.naturalHeight) {
        mainImage.style.aspectRatio = mainImage.naturalWidth + ' / ' + mainImage.naturalHeight;
      }
    };

    if (mainImage.complete) {
      applyRatio();
      return;
    }

    mainImage.addEventListener('load', applyRatio, { once: true });
  }

  // Shared swipe/drag navigation for any mobile layout that has a
  // "current image": dragging bottom-to-top (up) advances to the next
  // image, top-to-bottom (down) goes back. Built on Pointer Events
  // rather than Touch Events, so it responds identically to a real
  // finger swipe on a phone AND a plain mouse drag — which also makes
  // it testable by dragging with the mouse in a normal desktop browser,
  // not just in a DevTools device emulation profile.
  function attachSwipeNavigation(el, onSwipeUp, onSwipeDown) {
    if (!el) {
      return;
    }

    var startY = 0;
    var startX = 0;
    var tracking = false;
    var swipeThreshold = 40; // px — minimum vertical movement to count as a swipe

    el.addEventListener('pointerdown', function (event) {
      tracking = true;
      startY = event.clientY;
      startX = event.clientX;
    });

    el.addEventListener('pointerup', function (event) {
      if (!tracking) {
        return;
      }
      tracking = false;

      var deltaY = event.clientY - startY;
      var deltaX = event.clientX - startX;

      // Only treat it as a swipe if vertical movement dominates —
      // avoids hijacking horizontal gestures or accidental taps/clicks.
      if (Math.abs(deltaY) < swipeThreshold || Math.abs(deltaY) < Math.abs(deltaX)) {
        return;
      }

      if (deltaY < 0) {
        onSwipeUp(); // dragged bottom-to-top -> next
      } else {
        onSwipeDown(); // dragged top-to-bottom -> previous
      }
    });

    el.addEventListener('pointercancel', function () {
      tracking = false;
    });

    el.addEventListener('pointerleave', function () {
      tracking = false;
    });
  }

  function moveJustinLabelToElement(targetElement) {
    if (!elements.page || !elements.justinLabel || !targetElement || !elements.page.contains(targetElement)) {
      return;
    }

    if (justinLabelFrame) {
      window.cancelAnimationFrame(justinLabelFrame);
    }

    justinLabelFrame = window.requestAnimationFrame(function () {
      var targetRect = targetElement.getBoundingClientRect();
      elements.justinLabel.style.top = targetRect.top + 'px';
      justinLabelFrame = null;
    });
  }

  function clearJustinLabelPosition() {
    if (justinLabelFrame) {
      window.cancelAnimationFrame(justinLabelFrame);
      justinLabelFrame = null;
    }

    if (elements.justinLabel) {
      elements.justinLabel.style.top = '';
      elements.justinLabel.style.fontSize = '';
    }
  }

  function setLightboxChromeVisible(visible) {
    var displayValue = visible ? '' : 'none';
    elements.lightboxPrev.style.display = displayValue;
    elements.lightboxNext.style.display = displayValue;
    elements.lightboxThumbs.style.display = displayValue;
    elements.lightboxInfoToggle.style.display = displayValue;
    elements.lightboxInfoPanel.style.display = visible ? '' : 'none';
  }

  function closeLightbox() {
    state.activeEntryIndex = -1;
    state.activeImageIndex = 0;
    state.watchMode = false;
    document.body.classList.remove('lb-upside-down');
    elements.lightboxOverlay.style.removeProperty('--lightbox-tint');
    elements.lightboxOverlay.classList.remove('open', 'watch-mode', 'lb-book');
    if (elements.godModeBtn) {
      elements.godModeBtn.classList.remove('behind-popup');
    }
    if (state.activeBookBuyBtn && state.activeBookBuyBtn.parentNode) {
      state.activeBookBuyBtn.parentNode.removeChild(state.activeBookBuyBtn);
    }
    state.activeBookBuyBtn = null;
    document.body.style.overflow = '';
    elements.lightboxOverlay.classList.remove('open', 'watch-mode');
    elements.lightboxOverlay.setAttribute('aria-hidden', 'true');
    elements.lightboxStage.innerHTML = '';
    elements.lightboxInfoPanel.innerHTML = '';
    elements.lightboxInfoPanel.classList.remove('open');
    elements.lightboxThumbs.innerHTML = '';
    elements.lightboxInfoPanel.style.display = '';
    elements.lightboxThumbs.style.display = '';
    elements.lightboxPrev.style.display = '';
    elements.lightboxNext.style.display = '';
    elements.lightboxInfoToggle.style.display = '';
  }

  function renderImageLightbox(entry) {
    var images = getEntryImages(entry);
    var isFilmEntry = !!(entry && entry.cat === 'Film');
    var videoUrl = isFilmEntry && entry.film ? entry.film.videoUrl : '';

    elements.lightboxOverlay.classList.remove('watch-mode');
    state.watchMode = false;
    state.activeImageIndex = 0;

    if (!images.length && videoUrl) {
      state.watchMode = true;
      elements.lightboxOverlay.classList.add('watch-mode');
      buildVideoStage(entry);
      setLightboxChromeVisible(false);
      return;
    }

    var initialImage = images[0] || '';
    elements.lightboxStage.innerHTML = initialImage ? '<img id="lightbox-main-image" src="' + initialImage + '" alt="' + escapeHtml(entry.text || '') + '">' : '';
    syncMainImageAspectRatio(document.getElementById('lightbox-main-image'));
    elements.lightboxThumbs.innerHTML = '';

    for (var i = 0; i < images.length; i += 1) {
      var thumb = document.createElement('img');
      thumb.src = images[i];
      thumb.alt = entry.text || '';

      if (i === 0) {
        thumb.classList.add('active');
      }

      (function (index, thumbElement) {
        thumbElement.addEventListener('click', function () {
          state.activeImageIndex = index;
          var mainImage = document.getElementById('lightbox-main-image');
          if (mainImage) {
            mainImage.src = images[index];
            mainImage.style.aspectRatio = '';
            syncMainImageAspectRatio(mainImage);
          }

          var activeThumbs = elements.lightboxThumbs.querySelectorAll('img');
          activeThumbs.forEach(function (node) {
            node.classList.remove('active');
          });
          thumbElement.classList.add('active');
        });
      })(i, thumb);

      elements.lightboxThumbs.appendChild(thumb);
    }

    if (videoUrl) {
      var watchThumb = document.createElement('button');
      watchThumb.type = 'button';
      watchThumb.className = 'lightbox-watch-thumb';
      watchThumb.innerHTML = '<span>watch</span>';
      watchThumb.addEventListener('click', function () {
        state.watchMode = true;
        elements.lightboxOverlay.classList.add('watch-mode');
        buildVideoStage(entry);
        setLightboxChromeVisible(false);
      });
      elements.lightboxThumbs.appendChild(watchThumb);
    }

    // Swipe up/down on the main image, same gesture as the grid-hover
    // layouts. Reuses showNextImage/showPreviousImage directly so it
    // stays perfectly in sync with the thumbnails and arrow buttons.
    if (images.length > 1) {
      attachSwipeNavigation(document.getElementById('lightbox-main-image'), showNextImage, showPreviousImage);
    }

    setLightboxChromeVisible(true);
    var standardInfo = getEntryInfo(entry);
    elements.lightboxInfoPanel.innerHTML = standardInfo;
    if (!standardInfo) {
      elements.lightboxInfoToggle.style.display = 'none';
      elements.lightboxInfoPanel.style.display = 'none';
    }
  }

  function renderPaintingMasonry(ghGrid, images, entry, ghPreviewImg, ghCounter, selectImage, thumbEls) {
    ghGrid.classList.add('gh-grid-painting');

    var containerHeight = ghGrid.clientHeight || 600;

    var styleCycle = [
      { name: 'rigid',    colWidth: 120, innerWidthPct: 100, marginY: 0,  ratios: [3 / 4, 1 / 1],       border: true,  shadow: false, rotate: 0 },
      { name: 'floating', colWidth: 120, innerWidthPct: 80,  marginY: 20, ratios: [4 / 5, 1 / 1],       border: false, shadow: true,  rotate: 0 },
      { name: 'sparse',   colWidth: 130, innerWidthPct: 90,  marginY: 36, ratios: [5 / 6],              border: false, shadow: true,  rotate: 0 },
      { name: 'loose',    colWidth: 110, innerWidthPct: 65,  marginY: 28, ratios: [1 / 1, 3 / 5, 5 / 4], border: false, shadow: true,  rotate: 1.5 },
    ];

    var columns = [];

    function addColumn() {
      var cycleIndex = columns.length % styleCycle.length;
      var lap = Math.floor(columns.length / styleCycle.length);
      var base = styleCycle[cycleIndex];

      var style = {
        name: base.name,
        colWidth: base.colWidth - lap * 6,
        innerWidthPct: Math.max(55, base.innerWidthPct - lap * 8),
        marginY: base.marginY + lap * 6,
        ratios: base.ratios,
        border: base.border,
        shadow: base.shadow,
        rotate: base.rotate + (lap % 2 === 0 ? 0 : 1),
      };

      var col = document.createElement('div');
      col.className = 'gh-col gh-col-' + style.name;
      col.style.width = style.colWidth + 'px';
      ghGrid.appendChild(col);
      var record = { el: col, height: 0, style: style, count: 0 };
      columns.push(record);
      return record;
    }

    addColumn();

    images.forEach(function (imageUrl, index) {
      var target = null;
      var targetHeight = 0;

      for (var i = 0; i < columns.length; i += 1) {
        var col = columns[i];
        var style = col.style;
        var ratio = style.ratios[col.count % style.ratios.length];
        var innerWidth = style.colWidth * (style.innerWidthPct / 100);
        var thumbHeight = innerWidth / ratio + style.marginY * 2;
        var projectedHeight = col.height + thumbHeight;

        if (projectedHeight <= containerHeight && (!target || col.height < target.height)) {
          target = col;
          targetHeight = thumbHeight;
        }
      }

      if (!target) {
        target = addColumn();
        var newStyle = target.style;
        var newRatio = newStyle.ratios[target.count % newStyle.ratios.length];
        var newInnerWidth = newStyle.colWidth * (newStyle.innerWidthPct / 100);
        targetHeight = newInnerWidth / newRatio + newStyle.marginY * 2;
      }

      var style = target.style;
      var ratio = style.ratios[target.count % style.ratios.length];
      var innerWidth = style.colWidth * (style.innerWidthPct / 100);

      var thumb = document.createElement('div');
      thumb.className = 'gh-thumb';
      if (index === 0) {
        thumb.classList.add('gh-active');
      }
      thumb.style.width = style.innerWidthPct + '%';
      thumb.style.margin = style.marginY ? style.marginY + 'px auto' : '0';
      if (style.border) {
        thumb.style.border = '1px solid #f2f2f2';
      }
      if (style.shadow) {
        thumb.style.boxShadow = '1px 2px 8px rgba(0, 0, 0, 0.15)';
      }
      if (style.rotate) {
        var sign = target.count % 2 === 0 ? 1 : -1;
        thumb.style.transform = 'rotate(' + (style.rotate * sign) + 'deg)';
      }

      var thumbImg = document.createElement('img');
      thumbImg.src = imageUrl;
      thumbImg.alt = entry.text || '';
      thumb.appendChild(thumbImg);

      thumb.addEventListener('mouseenter', function () { selectImage(index); });
      thumb.addEventListener('click', function () { selectImage(index); });
      thumb.addEventListener('focus', function () { selectImage(index); });
      thumb.setAttribute('tabindex', '0');

      target.el.appendChild(thumb);
      target.height += targetHeight;
      target.count += 1;

      if (thumbEls) {
        thumbEls[index] = thumb;
      }
    });

    if (columns.length > 3) {
      var leftWrap = document.createElement('div');
      leftWrap.className = 'gh-painting-left';
      var rightWrap = document.createElement('div');
      rightWrap.className = 'gh-painting-right';

      columns.forEach(function (col, idx) {
        if (idx < 3) {
          leftWrap.appendChild(col.el);
        } else {
          rightWrap.appendChild(col.el);
        }
      });

      ghGrid.innerHTML = '';
      ghGrid.appendChild(leftWrap);
      ghGrid.appendChild(rightWrap);
      ghGrid.classList.add('gh-grid-painting-split');
    }
  }

  function computeOptimalGrid(containerWidth, containerHeight, count, aspectRatio, minCols, maxCols) {
    minCols = minCols || 1;
    maxCols = maxCols || 8;
    if (!count) {
      return { cols: minCols, rows: 1 };
    }

    for (var cols = minCols; cols <= maxCols; cols += 1) {
      var thumbWidth = containerWidth / cols;
      var thumbHeight = thumbWidth / aspectRatio;
      var rows = Math.ceil(count / cols);
      var totalHeight = rows * thumbHeight;

      if (totalHeight <= containerHeight || cols === maxCols) {
        return { cols: cols, rows: rows };
      }
    }
  }

  function renderGridHoverLightbox(entry) {
    var images = getEntryImages(entry);
    var variant = entry.layoutVariant || 'photography';
    var isBookTemplate = entry.layoutStyle === 'book_template';
    // Book Template reads from entry.bookTemplate — its own independent
    // data, completely separate from entry.book (the default Books layout).
    var bookForTemplate = entry.bookTemplate || {};
    var bookText = bookForTemplate.content || '';
    var hasBookTemplateBuy = !!(bookForTemplate.buyUrl || bookForTemplate.priceCents);

    var previewHtml =
        '<div class="gh-preview" id="gh-preview">' +
          (images[0] ? '<img id="gh-preview-img" src="' + images[0] + '" alt="' + escapeHtml(entry.text || '') + '">' : '') +
          '<button type="button" class="gh-info-toggle" id="gh-info-toggle" aria-label="Toggle info">ⓘ</button>' +
          '<div class="gh-info-panel" id="gh-info-panel"></div>' +
          (images.length ?
          '<div class="gh-counter-wrap" id="gh-counter-wrap">' +
            (images.length > 1 ? '<button type="button" class="gh-nav-arrow gh-nav-prev" id="gh-nav-prev" aria-label="Previous image">↑</button>' : '') +
            '<span class="gh-counter" id="gh-counter">1/' + images.length + '</span>' +
            (images.length > 1 ? '<button type="button" class="gh-nav-arrow gh-nav-next" id="gh-nav-next" aria-label="Next image">↓</button>' : '') +
          '</div>'
          : '') +
        '</div>';

    var html;

    if (isBookTemplate) {
      html =
        '<div class="gh-stage gh-stage-book" id="gh-stage" data-variant="' + escapeHtml(variant) + '">' +
          '<div class="gh-grid" id="gh-grid"></div>' +
          '<div class="gh-right-col" id="gh-right-col">' +
            previewHtml +
            '<div class="gh-book-footer">' +
              '<div class="gh-book-text">' + bookText + '</div>' +
              (hasBookTemplateBuy ? '<button type="button" class="book-template-buy-btn" id="book-template-buy-btn">Buy</button>' : '') +
            '</div>' +
          '</div>' +
        '</div>';
    } else {
      html =
        '<div class="gh-stage" id="gh-stage" data-variant="' + escapeHtml(variant) + '">' +
          '<div class="gh-grid" id="gh-grid"></div>' +
          previewHtml +
        '</div>';
    }

    elements.lightboxStage.innerHTML = html;

    var ghGrid = document.getElementById('gh-grid');
    var ghPreviewImg = document.getElementById('gh-preview-img');
    var ghInfoToggle = document.getElementById('gh-info-toggle');
    var ghInfoPanel = document.getElementById('gh-info-panel');
    var ghCounter = document.getElementById('gh-counter');

    // New, smaller, non-circular Book Template buy button — sits under
    // the text inside .gh-right-col (not fixed/floating like the default
    // Books "BUY ME" circle), and uses its own independent price data.
    var bookTemplateBuyBtn = document.getElementById('book-template-buy-btn');
    if (bookTemplateBuyBtn) {
      bookTemplateBuyBtn.addEventListener('click', function () {
        openStripeModal(entry, 'book_template', bookForTemplate);
      });
    }

    if (ghInfoPanel) {
      var ghInfo = getEntryInfo(entry);
      ghInfoPanel.innerHTML = ghInfo;
      if (!ghInfo && ghInfoToggle) {
        ghInfoToggle.style.display = 'none';
      }
    }

    if (ghInfoToggle) {
      ghInfoToggle.addEventListener('click', function () {
        ghInfoPanel.classList.toggle('open');
      });
    }

    var currentIndex = 0;
    var thumbEls = [];

    var selectImage = function (index) {
      if (!images.length) {
        return;
      }
      index = (index + images.length) % images.length;
      currentIndex = index;
      if (ghPreviewImg) {
        ghPreviewImg.src = images[index];
      }
      if (ghCounter) {
        ghCounter.textContent = (index + 1) + '/' + images.length;
      }
      thumbEls.forEach(function (node, i) {
        if (node) {
          node.classList.toggle('gh-active', i === index);
        }
      });
    };

    if (variant === 'painting') {
      renderPaintingMasonry(ghGrid, images, entry, ghPreviewImg, ghCounter, selectImage, thumbEls);
    } else {
      var isCollage = variant === 'collage';
      var containerWidth = ghGrid.clientWidth || 250;
      var containerHeight = ghGrid.clientHeight || 600;
      var aspectRatio = isCollage ? 1 : (3 / 2);

      var columns = 2;

      if (!isCollage) {
        var grid = computeOptimalGrid(containerWidth, containerHeight, images.length, aspectRatio, 2, 6);
        columns = grid.cols;
        ghGrid.style.setProperty('--gh-cols', columns);
        ghGrid.style.gridAutoFlow = 'column';
        ghGrid.style.gridTemplateRows = 'repeat(' + grid.rows + ', 1fr)';
      } else {
        ghGrid.style.setProperty('--gh-cols', columns);
      }

      images.forEach(function (imageUrl, index) {
        var thumb = document.createElement('div');
        thumb.className = 'gh-thumb';
        if (index === 0) {
          thumb.classList.add('gh-active');
        }

        var thumbImg = document.createElement('img');
        thumbImg.src = imageUrl;
        thumbImg.alt = entry.text || '';
        thumb.appendChild(thumbImg);

        thumb.addEventListener('mouseenter', function () { selectImage(index); });
        thumb.addEventListener('click', function () { selectImage(index); });
        thumb.addEventListener('focus', function () { selectImage(index); });
        thumb.setAttribute('tabindex', '0');

        ghGrid.appendChild(thumb);
        thumbEls.push(thumb);
      });
    }

    var ghPreview = document.getElementById('gh-preview');
    if (images.length > 1) {
      attachSwipeNavigation(ghPreview, function () {
        selectImage(currentIndex + 1); // swiped up -> next image
      }, function () {
        selectImage(currentIndex - 1); // swiped down -> previous image
      });
    }

    var navPrev = document.getElementById('gh-nav-prev');
    var navNext = document.getElementById('gh-nav-next');
    if (navPrev) {
      navPrev.addEventListener('click', function () { selectImage(currentIndex - 1); });
    }
    if (navNext) {
      navNext.addEventListener('click', function () { selectImage(currentIndex + 1); });
    }

    elements.lightboxThumbs.innerHTML = '';
    elements.lightboxThumbs.style.display = 'none';
    elements.lightboxInfoToggle.style.display = 'none';
    elements.lightboxInfoPanel.style.display = 'none';
    elements.lightboxPrev.style.display = 'none';
    elements.lightboxNext.style.display = 'none';
  }

  function getEntryPhotoGridImages(entry) {
    if (!entry || !Array.isArray(entry.photoGrid)) {
      return [];
    }
    return entry.photoGrid;
  }

  function isGridHoverLayout(layoutStyle) {
    return layoutStyle === 'grid_hover' || layoutStyle === 'grid_hover_painting' || layoutStyle === 'grid_hover_collage' || layoutStyle === 'book_template';
  }

  function renderPhotoGridLightbox(entry) {
    var photoGridImages = getEntryPhotoGridImages(entry);
    var allTags = Array.isArray(data.photoGridTags) ? data.photoGridTags : [];
    var activeFilters = [];

    var html =
      '<div class="pg-wrap" id="pg-wrap">' +
        '<div class="pg-tagbar" id="pg-tagbar"></div>' +
        '<div class="pg-grid" id="pg-grid"></div>' +
      '</div>';

    elements.lightboxStage.innerHTML = html;
    elements.lightboxStage.style.display = 'block';
    elements.lightboxStage.style.width = '100%';
    elements.lightboxStage.style.height = '100%';
    elements.lightboxStage.style.padding = '0';
    elements.lightboxStage.style.margin = '0';

    var pgTagbar = document.getElementById('pg-tagbar');
    var pgGrid = document.getElementById('pg-grid');

    function renderTiles() {
      pgGrid.innerHTML = '';

      var visibleImages = photoGridImages.filter(function (image) {
        if (!activeFilters.length) {
          return true;
        }
        var imageTags = Array.isArray(image.tags) ? image.tags : [];
        return activeFilters.some(function (tag) {
          return imageTags.indexOf(tag) !== -1;
        });
      });

      if (!visibleImages.length) {
        var emptyState = document.createElement('div');
        emptyState.className = 'pg-empty';
        emptyState.textContent = 'No images match the selected filters.';
        pgGrid.appendChild(emptyState);
        return;
      }

      visibleImages.forEach(function (image) {
        var tile = document.createElement('div');
        tile.className = 'pg-tile';

        var img = document.createElement('img');
        img.className = 'pg-media';
        img.src = image.url;
        img.alt = entry.text || '';
        tile.appendChild(img);

        var imageTags = Array.isArray(image.tags) ? image.tags : [];
        if (imageTags.length) {
          var caption = document.createElement('div');
          caption.className = 'pg-tile-caption';
          caption.textContent = imageTags.join(' · ');
          tile.appendChild(caption);
        }

        pgGrid.appendChild(tile);
      });
    }

    function renderTagbar() {
      pgTagbar.innerHTML = '';

      allTags.forEach(function (tag) {
        var tagButton = document.createElement('button');
        tagButton.type = 'button';
        tagButton.className = 'pg-tag-btn';
        tagButton.textContent = tag;

        if (activeFilters.indexOf(tag) !== -1) {
          tagButton.classList.add('pg-active');
        }

        tagButton.addEventListener('click', function () {
          var tagIndex = activeFilters.indexOf(tag);
          if (tagIndex === -1) {
            activeFilters.push(tag);
          } else {
            activeFilters.splice(tagIndex, 1);
          }
          renderTagbar();
          renderTiles();
        });

        pgTagbar.appendChild(tagButton);
      });

      var resetButton = document.createElement('button');
      resetButton.type = 'button';
      resetButton.className = 'pg-reset';
      resetButton.textContent = 'Reset';
      resetButton.addEventListener('click', function () {
        activeFilters = [];
        renderTagbar();
        renderTiles();
      });
      pgTagbar.appendChild(resetButton);
    }

    renderTagbar();
    renderTiles();

    setLightboxChromeVisible(true);
    elements.lightboxThumbs.innerHTML = '';
    elements.lightboxThumbs.style.display = 'none';
    elements.lightboxPrev.style.display = 'none';
    elements.lightboxNext.style.display = 'none';
    var photoGridInfo = getEntryInfo(entry);
    elements.lightboxInfoPanel.innerHTML = photoGridInfo;
    if (!photoGridInfo) {
      elements.lightboxInfoToggle.style.display = 'none';
      elements.lightboxInfoPanel.style.display = 'none';
    }
  }

  function renderBookStage(entry) {
    var book = entry.book || {};

    elements.lightboxOverlay.classList.add('lb-book');

    var html =
      '<div class="book-stage-v3" id="book-stage-v3">' +
        '<div class="book-content">' + (book.content || '') + '</div>' +
      '</div>';

    elements.lightboxStage.innerHTML = html;
    elements.lightboxStage.style.display = 'block';
    elements.lightboxStage.style.width = '100%';
    elements.lightboxStage.style.height = '100%';
    elements.lightboxStage.style.padding = '0';
    elements.lightboxStage.style.margin = '0';
    elements.lightboxThumbs.innerHTML = '';

    if (book.buyUrl || book.priceCents) {
      var buyBtnWrap = document.createElement('div');
      buyBtnWrap.className = 'buy-btn-wrap buy-btn-wrap-fixed';

      var buyBtn = document.createElement('button');
      buyBtn.type = 'button';
      buyBtn.className = 'buy-btn';
      buyBtn.textContent = 'BUY ME';

      buyBtn.addEventListener('click', function () {
        openStripeModal(entry, 'book', book);
      });

      buyBtnWrap.appendChild(buyBtn);
      elements.lightboxOverlay.appendChild(buyBtnWrap);
      state.activeBookBuyBtn = buyBtnWrap;
    }

    elements.lightboxThumbs.style.display = 'none';
    elements.lightboxInfoToggle.style.display = 'none';
    elements.lightboxInfoPanel.style.display = 'none';
    elements.lightboxPrev.style.display = 'none';
    elements.lightboxNext.style.display = 'none';
  }

  function renderTextStage(entry) {
    elements.lightboxStage.innerHTML = '<div class="text-body">' + (entry.body || '') + '</div>';
    elements.lightboxThumbs.innerHTML = '';
    setLightboxChromeVisible(false);
  }

  function openEntry(index) {
    var entry = entries[index];

    if (!entry || isHoverOnly(entry) || isEntryEmpty(entry)) {
      return;
    }

    state.activeEntryIndex = index;

    elements.lightboxOverlay.classList.remove('lb-book');

    var lightboxColor = categoryLightboxColors[entry.cat] || '';
    elements.lightboxOverlay.style.setProperty('--lightbox-tint', lightboxColor || '');

    elements.lightboxOverlay.classList.add('open');
    elements.lightboxOverlay.setAttribute('aria-hidden', 'false');
    if (elements.godModeBtn) {
      elements.godModeBtn.classList.add('behind-popup');
    }
    document.body.style.overflow = 'hidden';

    // Book Template is checked first and is independent of category —
    // driven purely by layoutStyle, so it works whether or not the post
    // is in the 'Books' category (or if 'Books' is removed entirely).
    if (entry.layoutStyle === 'book_template' && getEntryImages(entry).length) {
      document.body.classList.remove('lb-upside-down');
      renderGridHoverLightbox(entry);
    } else if (entry.cat === 'Books') {
      document.body.classList.remove('lb-upside-down');
      renderBookStage(entry);
    } else if (entry.cat === 'Text') {
      document.body.classList.remove('lb-upside-down');
      renderTextStage(entry);
    } else {
      if (isGridHoverLayout(entry.layoutStyle) && getEntryImages(entry).length) {
        renderGridHoverLightbox(entry);
      } else if (entry.layoutStyle === 'photo_grid' && getEntryPhotoGridImages(entry).length) {
        renderPhotoGridLightbox(entry);
      } else if (entry.layoutStyle === 'video_direct' && entry.videoUrl) {
        renderVideoDirectLightbox(entry);
      } else {
        renderImageLightbox(entry);
      }
    }
  }

  function showPreviousImage() {
    var entry = entries[state.activeEntryIndex];
    var images = getEntryImages(entry);

    if (!entry || state.watchMode || images.length < 2) {
      return;
    }

    state.activeImageIndex = (state.activeImageIndex - 1 + images.length) % images.length;
    var mainImage = document.getElementById('lightbox-main-image');
    if (mainImage) {
      mainImage.src = images[state.activeImageIndex];
      mainImage.style.aspectRatio = '';
      syncMainImageAspectRatio(mainImage);
    }

    var thumbs = elements.lightboxThumbs.querySelectorAll('img');
    thumbs.forEach(function (thumb, index) {
      thumb.classList.toggle('active', index === state.activeImageIndex);
    });
  }

  function showNextImage() {
    var entry = entries[state.activeEntryIndex];
    var images = getEntryImages(entry);

    if (!entry || state.watchMode || images.length < 2) {
      return;
    }

    state.activeImageIndex = (state.activeImageIndex + 1) % images.length;
    var mainImage = document.getElementById('lightbox-main-image');
    if (mainImage) {
      mainImage.src = images[state.activeImageIndex];
      mainImage.style.aspectRatio = '';
      syncMainImageAspectRatio(mainImage);
    }

    var thumbs = elements.lightboxThumbs.querySelectorAll('img');
    thumbs.forEach(function (thumb, index) {
      thumb.classList.toggle('active', index === state.activeImageIndex);
    });
  }

  function isImageLightboxOpen() {
    var entry = entries[state.activeEntryIndex];
    return !!(elements.lightboxOverlay.classList.contains('open') && entry && !state.watchMode && getEntryImages(entry).length > 1);
  }

  function renderNav() {
    var navItems = categories.slice();
    elements.navLine.innerHTML = '';

    navItems.forEach(function (item) {
      var nav = document.createElement('span');
      nav.className = 'cat';
      nav.dataset.category = item.name;
      nav.textContent = item.name;

      if (item.name === state.activeCategory) {
        nav.classList.add('active');
      }

      nav.addEventListener('click', function () {
        state.activeCategory = item.name;
        setBioText(getCategoryBio(item.name));
        renderNav();
        renderList();
      });

      elements.navLine.appendChild(nav);
    });
  }

  function createListItem(entry, index) {
    var li = document.createElement('li');
    li.dataset.entryIndex = String(index);
    li.dataset.category = entry.cat || '';

    var details = document.createElement('details');
    var summary = document.createElement('summary');
    summary.appendChild(document.createTextNode(entry.text || 'Untitled'));

    if (state.activeCategory && entry.cat === state.activeCategory) {
      li.classList.add('category-active');
      summary.classList.add('category-active');
    }

    summary.addEventListener('click', function (event) {
      event.preventDefault();
      if (isHoverOnly(entry)) {
        return;
      }
      openEntry(index);
    });

    summary.addEventListener('mouseenter', function () {
      setBackground(entry.bgImage);
      summary.classList.add('highlighted');
      moveJustinLabelToElement(summary);
    });

    summary.addEventListener('mouseleave', function () {
      setBackground('');
      summary.classList.remove('highlighted');
      clearJustinLabelPosition();
    });

    if (state.activeEntryIndex === index) {
      summary.classList.add('is-open');
    }

    if (state.activeCategory && entry.cat === state.activeCategory) {
      summary.classList.add('category-active');
      li.classList.add('category-active');
    }

    details.appendChild(summary);
    li.appendChild(details);
    return li;
  }

  function renderList() {
    elements.list.innerHTML = '';

    if (!entries.length) {
      var empty = document.createElement('li');
      empty.className = 'empty-state';
      empty.textContent = 'No projects yet.';
      elements.list.appendChild(empty);
      return;
    }

    var loopSets = mobileQuery.matches ? LIST_LOOP_SETS : 1;

    for (var setIndex = 0; setIndex < loopSets; setIndex += 1) {
      entries.forEach(function (entry) {
        var index = entries.indexOf(entry);
        elements.list.appendChild(createListItem(entry, index));
      });
    }

    if (loopSets > 1) {
      setupListLoop();
    }
  }

  function toggleBioSection() {
    var isOpen = elements.bioSection.classList.toggle('is-open');
    if (elements.bioToggle) {
      elements.bioToggle.textContent = isOpen ? '−' : '+';
      elements.bioToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
  }

  function padChannelNumber(number) {
    return number < 10 ? '0' + number : String(number);
  }

  function renderGodModeChannel() {
    if (!elements.godModeFrame || !godModeChannels.length) {
      return;
    }

    var channel = godModeChannels[godModeIndex] || {};

    var mediaHtml = channel.embedUrl
      ? '<iframe class="gm-iframe" src="' + channel.embedUrl + '&autoplay=1&muted=1&loop=1" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen frameborder="0"></iframe>'
      : '<div class="gm-static">no signal</div>';

    elements.godModeFrame.innerHTML =
      '<div class="gm-screen">' +
        mediaHtml +
        '<div class="gm-info">' +
          '<span class="gm-chnum">' + padChannelNumber(channel.number || (godModeIndex + 1)) + '</span>' +
          '<span class="gm-chtitle">' + escapeHtml(channel.title || '') + '</span>' +
          '<span class="gm-chname">' + escapeHtml(channel.name || '') + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="gm-controls">' +
        '<button type="button" class="gm-up" aria-label="previous channel">&#9650;</button>' +
        '<button type="button" class="gm-down" aria-label="next channel">&#9660;</button>' +
      '</div>';

    var upBtn = elements.godModeFrame.querySelector('.gm-up');
    var downBtn = elements.godModeFrame.querySelector('.gm-down');

    if (upBtn) {
      upBtn.addEventListener('click', function () { clipGodModeChannel('up'); });
    }
    if (downBtn) {
      downBtn.addEventListener('click', function () { clipGodModeChannel('down'); });
    }
  }

  function clipGodModeChannel(direction) {
    if (godModeFlipping || godModeChannels.length < 2 || !elements.godModeFrame) {
      return;
    }

    var screen = elements.godModeFrame.querySelector('.gm-screen');
    if (!screen) {
      return;
    }

    godModeFlipping = true;

    var rotateFrom = direction === 'down' ? -90 : 90;
    screen.style.transition = 'transform 120ms ease-in';
    screen.style.transform = 'rotateX(' + rotateFrom + 'deg)';

    setTimeout(function () {
      godModeIndex = direction === 'down'
        ? (godModeIndex + 1) % godModeChannels.length
        : (godModeIndex - 1 + godModeChannels.length) % godModeChannels.length;

      renderGodModeChannel();

      var newScreen = elements.godModeFrame.querySelector('.gm-screen');
      if (newScreen) {
        newScreen.style.transition = 'none';
        newScreen.style.transform = 'rotateX(' + (direction === 'down' ? 90 : -90) + 'deg)';

        requestAnimationFrame(function () {
          newScreen.style.transition = 'transform 150ms ease-out';
          newScreen.style.transform = 'rotateX(0deg)';
        });
      }

      setTimeout(function () {
        godModeFlipping = false;
      }, 160);
    }, 120);
  }

  function toggleGodMode(event) {
    if (event) {
      event.preventDefault();
    }

    var isVisible = elements.godModeOverlay.classList.toggle('visible');
    document.body.classList.toggle('god-mode-active', isVisible);

    if (elements.godModeBtn) {
      elements.godModeBtn.classList.toggle('is-active', isVisible);
    }

    if (!elements.godModeFrame) {
      return;
    }

    if (isVisible && godModeChannels.length) {
      renderGodModeChannel();
    } else {
      elements.godModeFrame.innerHTML = '';
    }
  }

  renderNav();
  setBioText(siteBio);
  renderList();

  if (elements.bioToggle) {
    elements.bioToggle.addEventListener('click', toggleBioSection);
  }

  if (elements.godModeBtn) {
    elements.godModeBtn.addEventListener('click', toggleGodMode);
  }

  if (elements.godModeOverlay) {
    elements.godModeOverlay.addEventListener('click', function (event) {
      if (event.target === elements.godModeOverlay) {
        toggleGodMode(event);
      }
    });
  }

  if (elements.lightboxClose) {
    elements.lightboxClose.addEventListener('click', closeLightbox);
  }

  if (elements.lightboxOverlay) {
    elements.lightboxOverlay.addEventListener('click', function (event) {
      if (event.target === elements.lightboxOverlay) {
        closeLightbox();
      }
    });
  }

  if (elements.lightboxInfoToggle) {
    elements.lightboxInfoToggle.addEventListener('click', function () {
      elements.lightboxInfoPanel.classList.toggle('open');
    });
  }

  if (elements.lightboxPrev) {
    elements.lightboxPrev.addEventListener('click', showPreviousImage);
  }

  if (elements.lightboxNext) {
    elements.lightboxNext.addEventListener('click', showNextImage);
  }

  window.addEventListener('scroll', onScrollOrResize, { passive: true });
  window.addEventListener('resize', onScrollOrResize);
  updateScrollSpy();

  mobileQuery.addEventListener('change', function () {
    renderList();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (stripeEls.overlay && stripeEls.overlay.classList.contains('open')) {
        closeStripeModal();
        return;
      }

      if (elements.lightboxOverlay.classList.contains('open')) {
        closeLightbox();
        return;
      }

      if (elements.godModeOverlay.classList.contains('visible')) {
        toggleGodMode(event);
      }
      return;
    }

    if (elements.godModeOverlay.classList.contains('visible')) {
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        clipGodModeChannel('up');
        return;
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        clipGodModeChannel('down');
        return;
      }
    }

    if (!isImageLightboxOpen()) {
      return;
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      showPreviousImage();
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      showNextImage();
    }
  });
}());

// FOOTER STUFF
document.querySelectorAll('.footer-info-toggle').forEach(function (toggle) {
  var popup = toggle.nextElementSibling;
  if (!popup || !popup.classList.contains('footer-info-popup')) {
    return;
  }

  toggle.addEventListener('click', function (event) {
    event.stopPropagation();
    var isOpen = !popup.hasAttribute('hidden');

    document.querySelectorAll('.footer-info-popup').forEach(function (p) {
      p.setAttribute('hidden', '');
    });
    document.querySelectorAll('.footer-info-toggle').forEach(function (t) {
      t.setAttribute('aria-expanded', 'false');
    });

    if (!isOpen) {
      popup.removeAttribute('hidden');
      toggle.setAttribute('aria-expanded', 'true');
    }
  });
});

document.addEventListener('click', function (event) {
  if (!event.target.closest('.footer-widget')) {
    document.querySelectorAll('.footer-info-popup').forEach(function (p) {
      p.setAttribute('hidden', '');
    });
    document.querySelectorAll('.footer-info-toggle').forEach(function (t) {
      t.setAttribute('aria-expanded', 'false');
    });
  }
});