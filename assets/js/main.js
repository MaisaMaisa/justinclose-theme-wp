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
  };

  var elements = {
    bgHover: document.getElementById('bg-hover-img'),
    bioSection: document.getElementById('bio-section'),
    bioToggle: document.getElementById('bio-toggle'),
    bioCopy: document.querySelector('#bio-section .bio-copy'),
    justinLabel: document.getElementById('justin-label'),
    godModeBtn: document.getElementById('god-mode-btn'),
    godModeOverlay: document.getElementById('god-mode-overlay'),
    godModeVideo: document.getElementById('god-mode-video'),
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
  var categories = Array.isArray(data.cats) ? data.cats : [];
  var categoryBios = data.catBios || {};
  var siteBio = data.siteDescription || data.siteTitle || '';
  var justinLabelFrame = null;

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

  function getEntryImages(entry) {
    if (!entry) {
      return [];
    }

    if (entry.film && Array.isArray(entry.film.images)) {
      return entry.film.images.slice();
    }

    if (entry.book && Array.isArray(entry.book.images)) {
      return entry.book.images.slice();
    }

    return Array.isArray(entry.images) ? entry.images.slice() : [];
  }

  function getEntryTeasers(entry) {
    if (!entry || !entry.book || !Array.isArray(entry.book.teasers)) {
      return [];
    }

    return entry.book.teasers.slice();
  }

  function getEntryInfo(entry) {
    if (!entry) {
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
        return vimeoId ? 'https://player.vimeo.com/video/' + encodeURIComponent(vimeoId) + '?autoplay=1&autopause=0' : rawUrl;
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
    var hasBookText = !!(entry.book && entry.book.text && String(entry.book.text).trim().length);
    var hasBookImages = !!(entry.book && Array.isArray(entry.book.images) && entry.book.images.length);
    var hasTeasers = !!(entry.book && Array.isArray(entry.book.teasers) && entry.book.teasers.length);

    return !hasImages && !hasBody && !hasFilmVideo && !hasBookText && !hasBookImages && !hasTeasers;
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

    if (embedUrl) {
      elements.lightboxStage.innerHTML = '<iframe class="film-vimeo" src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
    } else {
      elements.lightboxStage.innerHTML = '';
    }

    elements.lightboxThumbs.innerHTML = '';
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

  function moveJustinLabelToElement(targetElement) {
    if (!elements.page || !elements.justinLabel || !targetElement || !elements.page.contains(targetElement)) {
      return;
    }

    if (justinLabelFrame) {
      window.cancelAnimationFrame(justinLabelFrame);
    }

    justinLabelFrame = window.requestAnimationFrame(function () {
      var targetStyles = window.getComputedStyle(targetElement);
      var labelStyles = window.getComputedStyle(elements.justinLabel);
      var targetFontSize = targetStyles.fontSize || labelStyles.fontSize;

      elements.justinLabel.style.fontSize = targetFontSize;

      var targetRect = targetElement.getBoundingClientRect();
      var labelRect = elements.justinLabel.getBoundingClientRect();
      var labelTop = Math.max(0, targetRect.top + ((targetRect.height - labelRect.height) / 2));

      elements.justinLabel.style.top = labelTop + 'px';
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
    if (elements.godModeBtn) {
      elements.godModeBtn.classList.remove('behind-popup');
    }
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

  function buildVideoStage(entry) {
    var videoUrl = entry && entry.film ? entry.film.videoUrl : '';
    var embedUrl = getVideoEmbedUrl(videoUrl);

    if (embedUrl) {
      elements.lightboxStage.innerHTML = '<iframe class="film-vimeo" src="' + embedUrl + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
    } else {
      elements.lightboxStage.innerHTML = '';
    }

    elements.lightboxThumbs.innerHTML = '';
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
      setLightboxChromeVisible(true);
      elements.lightboxInfoPanel.innerHTML = getEntryInfo(entry);
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
        setLightboxChromeVisible(true);
        elements.lightboxInfoPanel.innerHTML = getEntryInfo(entry);
      });
      elements.lightboxThumbs.appendChild(watchThumb);
    }

    setLightboxChromeVisible(true);
    elements.lightboxInfoPanel.innerHTML = getEntryInfo(entry);
  }

  function renderBookStage(entry) {
    var book = entry.book || {};
    var images = Array.isArray(book.images) ? book.images.slice() : [];
    var teasers = getEntryTeasers(entry);
    var allImages = images.concat(teasers);
    var mainImage = allImages[0] || '';
    var html = '<div class="book-stage" style="position:absolute; inset:0; display:grid; grid-template-columns:42% 58%; width:100%; height:100%; overflow:hidden;">' +
      '<div class="book-text" style="padding:40px 30px; overflow:hidden; display:flex; flex-direction:column; justify-content:center; font-size:16px; line-height:1.6; color:#111;">' + (book.text || '') +
      (book.buyUrl ? '<a class="buy-btn" href="' + book.buyUrl + '" target="_blank" rel="noopener">BUY ME</a>' : '') +
      '</div>' +
      '<div class="book-gallery" style="position:relative; min-width:0; min-height:0;">' +
      '<div class="book-images" style="position:absolute; inset:0 0 110px 0; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#eee;">' +
      (mainImage ? '<img id="book-main-image" src="' + mainImage + '" alt="' + escapeHtml(entry.text || '') + '">' : '') +
      '</div>' +
      '<div class="book-thumb-strip" id="book-thumb-strip" style="position:absolute; left:0; right:0; bottom:0; height:110px; display:flex; align-items:center; gap:8px; overflow-x:auto; overflow-y:hidden; padding:10px 12px; background:#efefef; flex-shrink:0;"></div>' +
      '</div></div>';

    elements.lightboxStage.innerHTML = html;
    elements.lightboxStage.style.display = 'block';
    elements.lightboxStage.style.width = '100%';
    elements.lightboxStage.style.height = '100%';
    elements.lightboxStage.style.padding = '0';
    elements.lightboxStage.style.margin = '0';
    elements.lightboxThumbs.innerHTML = '';

    var bookThumbStrip = document.getElementById('book-thumb-strip');
    for (var i = 0; i < allImages.length; i += 1) {
      var thumb = document.createElement('img');
      thumb.src = allImages[i];
      thumb.alt = entry.text || '';
      thumb.style.height = '100%';
      thumb.style.width = 'auto';
      thumb.style.flexShrink = '0';
      thumb.style.cursor = 'pointer';
      thumb.style.objectFit = 'contain';
      thumb.style.border = '2px solid transparent';

      if (i >= images.length) {
        thumb.classList.add('teaser-thumb');
      }

      if (i === 0) {
        thumb.classList.add('active');
      }

      if (i >= images.length) {
        thumb.classList.add('teaser-thumb');
      }

      (function (index, thumbElement) {
        thumbElement.addEventListener('click', function () {
          var mainImageElement = document.getElementById('book-main-image');
          if (mainImageElement) {
            mainImageElement.src = allImages[index];
            mainImageElement.style.aspectRatio = '';
            syncMainImageAspectRatio(mainImageElement);
          }

          var activeThumbs = elements.lightboxThumbs.querySelectorAll('img');
          activeThumbs.forEach(function (node) {
            node.classList.remove('active');
          });
          thumbElement.classList.add('active');
        });
      })(i, thumb);

      if (bookThumbStrip) {
        bookThumbStrip.appendChild(thumb);
      }
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
    elements.lightboxOverlay.classList.add('open');
    elements.lightboxOverlay.setAttribute('aria-hidden', 'false');
    if (elements.godModeBtn) {
      elements.godModeBtn.classList.add('behind-popup');
    }
    document.body.style.overflow = 'hidden';

    if (entry.cat === 'Books') {
      document.body.classList.remove('lb-upside-down');
      renderBookStage(entry);
    } else if (entry.cat === 'Text') {
      document.body.classList.remove('lb-upside-down');
      renderTextStage(entry);
    } else {
      if (entry.cat === 'Painting' || entry.cat === 'Collage') {
        document.body.classList.add('lb-upside-down');
      } else {
        document.body.classList.remove('lb-upside-down');
      }

      renderImageLightbox(entry);
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
      nav.style.color = item.color || '#000000';

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

  function renderList() {
    elements.list.innerHTML = '';

    if (!entries.length) {
      var empty = document.createElement('li');
      empty.className = 'empty-state';
      empty.textContent = 'No projects yet.';
      elements.list.appendChild(empty);
      return;
    }

    entries.forEach(function (entry) {
      var index = entries.indexOf(entry);
      var li = document.createElement('li');
      li.dataset.entryIndex = String(index);
      li.dataset.category = entry.cat || '';

      var details = document.createElement('details');
      var summary = document.createElement('summary');
      summary.appendChild(document.createTextNode(entry.text || 'Untitled'));

      var catDot = document.createElement('span');
      catDot.className = 'cat-dot';
      catDot.textContent = '●';
      summary.appendChild(catDot);

      if (isHoverOnly(entry) || (!getEntryImages(entry).length && !entry.body && !entry.vimeo && !(entry.book && entry.book.images && entry.book.images.length))) {
        var emptyTag = document.createElement('span');
        emptyTag.className = 'empty-tag';
        emptyTag.textContent = isHoverOnly(entry) ? 'hover only' : ' = empty';
        summary.appendChild(emptyTag);
      }

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
      elements.list.appendChild(li);
    });
  }

  function toggleBioSection() {
    var isOpen = elements.bioSection.classList.toggle('is-open');
    if (elements.bioToggle) {
      elements.bioToggle.textContent = isOpen ? '−' : '+';
      elements.bioToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
  }

  function toggleGodMode(event) {
    if (event) {
      event.preventDefault();
    }

    var isVisible = elements.godModeOverlay.classList.toggle('visible');
    document.body.classList.toggle('god-mode-active', isVisible);

    if (elements.godModeVideo) {
      elements.godModeVideo.src = data.godModeVideo || '';
      if (isVisible) {
        elements.godModeVideo.play().catch(function () {});
      } else {
        elements.godModeVideo.pause();
      }
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

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (elements.lightboxOverlay.classList.contains('open')) {
        closeLightbox();
        return;
      }

      if (elements.godModeOverlay.classList.contains('visible')) {
        toggleGodMode(event);
      }
      return;
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