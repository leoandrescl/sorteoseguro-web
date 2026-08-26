(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		var root = document.querySelector('.ss-pdp');
		if (!root) return;

		var stage = root.querySelector('.ss-pdp-media__stage');
		var video = root.querySelector('.ss-pdp-media__video');
		var iframe = root.querySelector('.ss-pdp-media__iframe');
		var image = root.querySelector('[data-ss-main-image]');
		var thumbs = Array.prototype.slice.call(root.querySelectorAll('[data-ss-thumb]'));
		var videoThumb = root.querySelector('[data-ss-thumb-video]');
		var playBtn = root.querySelector('[data-ss-play-video]');
		var prevBtn = root.querySelector('[data-ss-gallery-prev]');
		var nextBtn = root.querySelector('[data-ss-gallery-next]');
		var urls = thumbs.map(function (thumb) {
			return thumb.getAttribute('data-full') || thumb.getAttribute('data-large') || '';
		});
		var index = 0;
		var hasVideo = !!(video || iframe);
		var mode = (stage && stage.getAttribute('data-mode')) || (hasVideo ? 'video' : 'image');

		function setActiveThumb(i) {
			thumbs.forEach(function (t, n) {
				t.classList.toggle('is-active', mode === 'image' && n === i);
			});
			if (videoThumb) {
				videoThumb.classList.toggle('is-active', mode === 'video');
			}
		}

		function setMode(next) {
			mode = next;
			if (stage) stage.setAttribute('data-mode', next);
		}

		function applyImageSrc(src) {
			if (!image || !src) return;
			image.removeAttribute('srcset');
			image.removeAttribute('sizes');
			image.removeAttribute('data-src');
			image.removeAttribute('data-lazyloaded');
			image.removeAttribute('data-original');
			image.setAttribute('src', src);
			image.src = src;
		}

		function ytCommand(fn) {
			if (!iframe || !iframe.contentWindow) return;
			iframe.contentWindow.postMessage(JSON.stringify({
				event: 'command',
				func: fn,
				args: []
			}), '*');
		}

		function pauseMedia() {
			if (video) {
				try { video.pause(); } catch (e) {}
			}
			ytCommand('pauseVideo');
		}

		function playMedia() {
			if (video) {
				try { video.play(); } catch (e) {}
			}
			ytCommand('playVideo');
		}

		function showImage(i) {
			if (!thumbs.length) return;
			index = (i + thumbs.length) % thumbs.length;
			applyImageSrc(urls[index]);
			pauseMedia();
			setMode('image');
			setActiveThumb(index);
		}

		function showVideo() {
			if (!hasVideo) return;
			setMode('video');
			setActiveThumb(-1);
			playMedia();
		}

		if (playBtn) {
			playBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				showVideo();
			});
		}

		if (videoThumb) {
			videoThumb.addEventListener('click', function (e) {
				e.preventDefault();
				showVideo();
			});
		}

		thumbs.forEach(function (thumb, i) {
			thumb.addEventListener('click', function (e) {
				e.preventDefault();
				showImage(i);
			});
		});

		if (prevBtn) {
			prevBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (mode === 'video') {
					showImage(thumbs.length ? thumbs.length - 1 : 0);
					return;
				}
				if (hasVideo && index === 0) {
					showVideo();
					return;
				}
				showImage(index - 1);
			});
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (mode === 'video') {
					showImage(0);
					return;
				}
				if (hasVideo && index === thumbs.length - 1) {
					showVideo();
					return;
				}
				showImage(index + 1);
			});
		}

		var btn = root.querySelector('[data-ss-collapse]');
		var panel = root.querySelector('#ss-caracteristicas-lista');
		var label = root.querySelector('[data-ss-collapse-label]');
		if (btn && panel) {
			btn.addEventListener('click', function () {
				var open = btn.getAttribute('aria-expanded') === 'true';
				btn.setAttribute('aria-expanded', open ? 'false' : 'true');
				panel.hidden = open;
				if (label) {
					label.textContent = open ? 'VER TODAS LAS CARACTERÍSTICAS' : 'OCULTAR CARACTERÍSTICAS';
				}
				btn.blur();
			});
		}

		var tabs = root.querySelectorAll('.ss-pdp-tabs__item');
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				tabs.forEach(function (t) { t.classList.remove('is-active'); });
				tab.classList.add('is-active');
			});
		});
	});
})();
