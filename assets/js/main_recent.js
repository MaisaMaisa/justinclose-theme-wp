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
  // var categories = Array.isArray(data.cats) ? data.cats : [];
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

  function shuffleArray(array) {
    var shuffled = array.slice(); // don't mutate the original
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
        return vimeoId
          ? 'https://player.vimeo.com/video/' + encodeURIComponent(vimeoId) + '?autoplay=1&muted=1&loop=1&background=1'
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
    var hasDirectVideo = !!(entry.videoUrl && String(entry.videoUrl).trim().length); // NEW
    var hasBookText = !!(entry.book && entry.book.text && String(entry.book.text).trim().length);
    var hasBookImages = !!(entry.book && Array.isArray(entry.book.images) && entry.book.images.length);
    var hasTeasers = !!(entry.book && Array.isArray(entry.book.teasers) && entry.book.teasers.length);

    return !hasImages && !hasBody && !hasFilmVideo && !hasDirectVideo && !hasBookText && !hasBookImages && !hasTeasers;
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
    elements.lightboxInfoPanel.innerHTML = getEntryInfo(entry);
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
    elements.lightboxOverlay.style.removeProperty('--lightbox-tint');
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

  function renderPaintingMasonry(ghGrid, images, entry, ghPreviewImg) {
    ghGrid.classList.add('gh-grid-painting');

    var containerHeight = ghGrid.clientHeight || 600;

    // 4 distinct personalities now, so a 4th column never repeats the 1st.
    var styleCycle = [
      { name: 'rigid',    colWidth: 120, innerWidthPct: 100, marginY: 0,  ratios: [3 / 4, 1 / 1],       border: true,  shadow: false, rotate: 0 },
      { name: 'floating', colWidth: 120, innerWidthPct: 80,  marginY: 20, ratios: [4 / 5, 1 / 1],       border: false, shadow: true,  rotate: 0 },
      { name: 'sparse',   colWidth: 130, innerWidthPct: 90,  marginY: 36, ratios: [5 / 6],              border: false, shadow: true,  rotate: 0 },
      { name: 'loose',    colWidth: 110, innerWidthPct: 65,  marginY: 28, ratios: [1 / 1, 3 / 5, 5 / 4], border: false, shadow: true,  rotate: 1.5 },
    ];

    var columns = [];

    function addColumn() {
      var cycleIndex = columns.length % styleCycle.length;
      var lap = Math.floor(columns.length / styleCycle.length); // 0 on first pass, 1+ on repeats
      var base = styleCycle[cycleIndex];

      // On repeat laps, vary width/margin slightly so a 2nd trip through
      // the cycle doesn't look like a duplicate of the 1st.
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

      // Only consider a column if this specific item would actually fit
      // inside it — not just whether the column has started.
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
      thumb.style.aspectRatio = ratio;
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

      var setActive = function () {
        if (ghPreviewImg) {
          ghPreviewImg.src = imageUrl;
        }
        ghGrid.querySelectorAll('.gh-thumb').forEach(function (node) {
          node.classList.remove('gh-active');
        });
        thumb.classList.add('gh-active');
      };

      thumb.addEventListener('mouseenter', setActive);
      thumb.addEventListener('click', setActive);
      thumb.addEventListener('focus', setActive);
      thumb.setAttribute('tabindex', '0');

      target.el.appendChild(thumb);
      target.height += targetHeight;
      target.count += 1;
    });
  }

  function renderGridHoverLightbox(entry) {
    var images = getEntryImages(entry);
    var variant = entry.layoutVariant || 'photography';

    var html =
      '<div class="gh-stage" id="gh-stage" data-variant="' + escapeHtml(variant) + '">' +
        '<div class="gh-grid" id="gh-grid"></div>' +
        '<div class="gh-preview" id="gh-preview">' +
          (images[0] ? '<img id="gh-preview-img" src="' + images[0] + '" alt="' + escapeHtml(entry.text || '') + '">' : '') +
          '<button type="button" class="gh-info-toggle" id="gh-info-toggle" aria-label="Toggle info">ⓘ</button>' +
          '<div class="gh-info-panel" id="gh-info-panel"></div>' +
        '</div>' +
      '</div>';

    elements.lightboxStage.innerHTML = html;

    var ghGrid = document.getElementById('gh-grid');
    var ghPreviewImg = document.getElementById('gh-preview-img');
    var ghInfoToggle = document.getElementById('gh-info-toggle');
    var ghInfoPanel = document.getElementById('gh-info-panel');

    if (ghInfoPanel) {
      ghInfoPanel.innerHTML = getEntryInfo(entry);
    }

    if (ghInfoToggle) {
      ghInfoToggle.addEventListener('click', function () {
        ghInfoPanel.classList.toggle('open');
      });
    }

    if (variant === 'painting') {
      renderPaintingMasonry(ghGrid, images, entry, ghPreviewImg);
    } else {
      var isCollage = variant === 'collage';
      var columns = 2;
      var rows = Math.ceil(images.length / columns);
      var thumbHeightPercent = rows > 0 ? (100 / rows) : 100;

      images.forEach(function (imageUrl, index) {
        var thumb = document.createElement('div');
        thumb.className = 'gh-thumb';
        if (!isCollage) {
          thumb.style.flex = '0 0 ' + thumbHeightPercent + '%';
        }
        if (index === 0) {
          thumb.classList.add('gh-active');
        }

        var thumbImg = document.createElement('img');
        thumbImg.src = imageUrl;
        thumbImg.alt = entry.text || '';
        thumb.appendChild(thumbImg);

        var setActive = function () {
          if (ghPreviewImg) {
            ghPreviewImg.src = imageUrl;
          }
          ghGrid.querySelectorAll('.gh-thumb').forEach(function (node) {
            node.classList.remove('gh-active');
          });
          thumb.classList.add('gh-active');
        };

        thumb.addEventListener('mouseenter', setActive);
        thumb.addEventListener('click', setActive);
        thumb.addEventListener('focus', setActive);
        thumb.setAttribute('tabindex', '0');

        ghGrid.appendChild(thumb);
      });
    }

    // Hide the standard gallery chrome — this layout is fully self-contained
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
    return layoutStyle === 'grid_hover' || layoutStyle === 'grid_hover_painting' || layoutStyle === 'grid_hover_collage';
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

    var lightboxColor = categoryLightboxColors[entry.cat] || '';
    elements.lightboxOverlay.style.setProperty('--lightbox-tint', lightboxColor || '');

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

      // NEW: branch on layoutStyle, fall back to standard if no images
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
      // nav.style.color = item.color || '#000000';

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

  // ---- GOD MODE: channel flip display ----
  // Renders a single "channel" (video + title/name/number + up/down
  // controls) inside the theme's existing #god-mode-frame element.
  // Channel data comes from data.godModeChannels, localized in
  // functions.php from the Appearance > Justin Settings repeater.

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
  // ---- Footer: social-links info popup ----
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