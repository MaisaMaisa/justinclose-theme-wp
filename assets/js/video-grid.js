(function () {
  if (typeof document === 'undefined') {
    return;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var grid = document.getElementById('pg-grid');
    var tagbar = document.getElementById('pg-tagbar');
    var lightbox = document.getElementById('vgi-lightbox');
    var lightboxInner = document.getElementById('vgi-lightbox-inner');
    var closeBtn = document.getElementById('vgi-lightbox-close');

    if (!grid) {
      return;
    }

    var tiles = Array.prototype.slice.call(grid.querySelectorAll('.pg-tile'));
    var activeTags = [];

    function applyFilter() {
      tiles.forEach(function (tile) {
        if (!activeTags.length) {
          tile.classList.remove('pg-hidden');
          return;
        }
        var tileTags = (tile.getAttribute('data-tags') || '').split(' ').filter(Boolean);
        var matches = activeTags.some(function (tag) {
          return tileTags.indexOf(tag) !== -1;
        });
        tile.classList.toggle('pg-hidden', !matches);
      });
    }

    if (tagbar) {
      tagbar.addEventListener('click', function (event) {
        var tagBtn = event.target.closest('.pg-tag-btn');
        if (tagBtn) {
          var tag = tagBtn.getAttribute('data-tag');
          var index = activeTags.indexOf(tag);

          if (index === -1) {
            activeTags.push(tag);
            tagBtn.classList.add('pg-active');
          } else {
            activeTags.splice(index, 1);
            tagBtn.classList.remove('pg-active');
          }

          applyFilter();
          return;
        }

        if (event.target.closest('#pg-reset')) {
          activeTags = [];
          tagbar.querySelectorAll('.pg-tag-btn').forEach(function (b) {
            b.classList.remove('pg-active');
          });
          applyFilter();
        }
      });
    }

    function openLightbox(embedHtml) {
      if (!lightbox || !lightboxInner) {
        return;
      }
      lightboxInner.innerHTML = embedHtml;
      lightbox.classList.add('open');
      lightbox.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      // Instagram's widget script needs to be told to re-scan the DOM
      // whenever a new blockquote is injected after the initial page load.
      if (window.instgrm && window.instgrm.Embeds) {
        window.instgrm.Embeds.process();
      }
    }

    function closeLightbox() {
      if (!lightbox || !lightboxInner) {
        return;
      }
      lightbox.classList.remove('open');
      lightbox.setAttribute('aria-hidden', 'true');
      lightboxInner.innerHTML = '';
      document.body.style.overflow = '';
    }

    // Autoplay preview: each tile with a YouTube ID gets a muted, looping
    // embed swapped in as soon as it scrolls into view, and removed again
    // once it scrolls out — so tiles read as "video" without needing a
    // hover, but we're not running every video in the grid at once.
    var previewObserver = null;
    if ('IntersectionObserver' in window) {
      previewObserver = new window.IntersectionObserver(
        function (entries) {
          entries.forEach(function (entry) {
            var tile = entry.target;
            var youtubeId = tile.getAttribute('data-youtube-id');
            if (!youtubeId) {
              return;
            }

            var existingFrame = tile.querySelector('.pg-preview-iframe');

            if (entry.isIntersecting) {
              if (existingFrame) {
                return;
              }
              var previewFrame = document.createElement('iframe');
              previewFrame.className = 'pg-preview-iframe';
              previewFrame.src =
                'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(youtubeId) +
                '?autoplay=1&mute=1&loop=1&controls=0&modestbranding=1&playsinline=1&rel=0&playlist=' +
                encodeURIComponent(youtubeId);
              previewFrame.setAttribute('allow', 'autoplay; encrypted-media');
              previewFrame.setAttribute('tabindex', '-1');
              previewFrame.setAttribute('aria-hidden', 'true');
              tile.appendChild(previewFrame);
            } else if (existingFrame) {
              existingFrame.remove();
            }
          });
        },
        { rootMargin: '100px 0px' }
      );
    }

    tiles.forEach(function (tile) {
      tile.addEventListener('click', function () {
        var embed = tile.getAttribute('data-embed');
        var link = tile.getAttribute('data-link');

        if (embed) {
          openLightbox(embed);
        } else if (link) {
          window.open(link, '_blank', 'noopener');
        }
      });

      tile.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          tile.click();
        }
      });

      if (tile.getAttribute('data-youtube-id') && previewObserver) {
        previewObserver.observe(tile);
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', closeLightbox);
    }

    if (lightbox) {
      lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) {
          closeLightbox();
        }
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && lightbox && lightbox.classList.contains('open')) {
        closeLightbox();
      }
    });
  });
}());