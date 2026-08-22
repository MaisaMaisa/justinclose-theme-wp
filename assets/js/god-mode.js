(function () {
	'use strict';

	function init() {
		var channels = (window.JUSTIN_DATA && JUSTIN_DATA.godModeChannels) || [];
		if (!channels.length) {
			return;
		}

		var overlay = document.getElementById('god-mode-overlay');
		var videoWrap = document.getElementById('god-mode-video-wrap');
		var staticEl = document.getElementById('god-mode-static');
		var chnumEl = document.getElementById('god-mode-chnum');
		var chtitleEl = document.getElementById('god-mode-chtitle');
		var chnameEl = document.getElementById('god-mode-chname');
		var upBtn = document.getElementById('god-mode-up');
		var downBtn = document.getElementById('god-mode-down');
		var closeBtn = document.getElementById('god-mode-close-btn');
		var triggerBtn = document.getElementById('god-mode-btn');

		if (!overlay) {
			return;
		}

		var index = 0;
		var isOpen = false;
		var isFlipping = false;
		var currentIframe = null;

		function pad(n) {
			return n < 10 ? '0' + n : String(n);
		}

		function clearVideo() {
			if (currentIframe) {
				// Dropping the src stops playback immediately instead of
				// letting it keep running off-screen.
				currentIframe.src = 'about:blank';
				currentIframe.remove();
				currentIframe = null;
			}
		}

		function renderChannel() {
			var ch = channels[index];
			chnumEl.textContent = pad(ch.number || index + 1);
			chtitleEl.textContent = ch.title || '';
			chnameEl.textContent = ch.name || '';

			clearVideo();

			if (ch.embedUrl) {
				staticEl.style.display = 'none';

				var iframe = document.createElement('iframe');
				iframe.className = 'god-mode-iframe';
				iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
				iframe.setAttribute('allowfullscreen', '');
				iframe.setAttribute('frameborder', '0');
				iframe.src = ch.embedUrl + '&autoplay=1&muted=1&loop=1';
				videoWrap.appendChild(iframe);
				currentIframe = iframe;
			} else {
				staticEl.style.display = 'flex';
			}
		}

		function clip(direction) {
			if (isFlipping || channels.length < 2) {
				if (!isFlipping) {
					// Still allow re-render for single-channel case, just no animation.
					index = 0;
					renderChannel();
				}
				return;
			}

			isFlipping = true;
			var screen = overlay.querySelector('.god-mode-screen');
			var rotateFrom = direction === 'down' ? -90 : 90;

			screen.style.transition = 'transform 120ms ease-in';
			screen.style.transform = 'rotateX(' + rotateFrom + 'deg)';

			setTimeout(function () {
				index = direction === 'down'
					? (index + 1) % channels.length
					: (index - 1 + channels.length) % channels.length;

				renderChannel();

				screen.style.transition = 'none';
				screen.style.transform = 'rotateX(' + (direction === 'down' ? 90 : -90) + 'deg)';

				requestAnimationFrame(function () {
					screen.style.transition = 'transform 150ms ease-out';
					screen.style.transform = 'rotateX(0deg)';
				});

				setTimeout(function () {
					isFlipping = false;
				}, 160);
			}, 120);
		}

		function open() {
			isOpen = true;
			overlay.classList.add('is-open');
			overlay.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
			renderChannel();
		}

		function close() {
			isOpen = false;
			overlay.classList.remove('is-open');
			overlay.setAttribute('aria-hidden', 'true');
			document.body.style.overflow = '';
			clearVideo();
		}

		if (triggerBtn) {
			triggerBtn.addEventListener('click', open);
		}

		document.querySelectorAll('[data-god-mode]').forEach(function (el) {
			el.addEventListener('click', open);
		});

		// Fallback global, in case the trigger lives somewhere the JS
		// can't attach a listener directly (e.g. inline onclick).
		window.justinOpenGodMode = open;

		if (closeBtn) {
			closeBtn.addEventListener('click', close);
		}

		if (upBtn) {
			upBtn.addEventListener('click', function () { clip('up'); });
		}

		if (downBtn) {
			downBtn.addEventListener('click', function () { clip('down'); });
		}

		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				close();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (!isOpen) {
				return;
			}
			if (e.key === 'Escape') {
				close();
			} else if (e.key === 'ArrowUp') {
				clip('up');
			} else if (e.key === 'ArrowDown') {
				clip('down');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
