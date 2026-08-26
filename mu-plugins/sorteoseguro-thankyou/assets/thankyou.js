(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	ready(function () {
		var root = document.querySelector('.ss-ty');
		if (!root || root.getAttribute('data-ss-ty-bound') === '1') return;
		root.setAttribute('data-ss-ty-bound', '1');

		root.querySelectorAll('[data-ss-ty-carousel]').forEach(function (carousel) {
			var track = carousel.querySelector('.ss-ty-contests__track');
			var nextBtn = carousel.querySelector('[data-ss-ty-next]');
			var prevBtn = carousel.querySelector('[data-ss-ty-prev]');
			if (!track || track.children.length < 2) return;

			var animating = false;
			function gap() {
				var style = window.getComputedStyle(track);
				return parseFloat(style.columnGap || style.gap || '0') || 0;
			}
			function stepSize() {
				var first = track.children[0];
				return first ? first.getBoundingClientRect().width + gap() : 0;
			}
			function advance() {
				if (animating) return;
				animating = true;
				var first = track.children[0];
				var step = stepSize();
				track.style.transition = 'transform 0.45s ease';
				track.style.transform = 'translateX(-' + step + 'px)';
				window.setTimeout(function () {
					track.style.transition = 'none';
					track.appendChild(first);
					track.style.transform = 'translateX(0)';
					void track.offsetHeight;
					animating = false;
				}, 460);
			}
			function rewind() {
				if (animating) return;
				animating = true;
				var last = track.children[track.children.length - 1];
				var step = stepSize();
				track.style.transition = 'none';
				track.insertBefore(last, track.firstChild);
				track.style.transform = 'translateX(-' + step + 'px)';
				void track.offsetHeight;
				track.style.transition = 'transform 0.45s ease';
				track.style.transform = 'translateX(0)';
				window.setTimeout(function () {
					animating = false;
				}, 460);
			}
			if (nextBtn) nextBtn.addEventListener('click', advance);
			if (prevBtn) prevBtn.addEventListener('click', rewind);
		});

		/* Flash promo: siempre 3h desde la carga (mismo markup que home) */
		var flash = root.querySelector('[data-ss-ty-flash-hours]');
		if (flash) {
			var hours = parseFloat(flash.getAttribute('data-ss-ty-flash-hours') || '3') || 3;
			var endsAt = Date.now() + Math.round(hours * 3600 * 1000);
			function tickFlash() {
				var ms = Math.max(0, endsAt - Date.now());
				var totalSec = Math.floor(ms / 1000);
				var d = Math.floor(totalSec / 86400);
				var h = Math.floor((totalSec % 86400) / 3600);
				var m = Math.floor((totalSec % 3600) / 60);
				var s = totalSec % 60;
				var dEl = flash.querySelector('[data-ss-promo-d]');
				var hEl = flash.querySelector('[data-ss-promo-h]');
				var mEl = flash.querySelector('[data-ss-promo-m]');
				var sEl = flash.querySelector('[data-ss-promo-s]');
				if (dEl) dEl.textContent = pad(d);
				if (hEl) hEl.textContent = pad(h);
				if (mEl) mEl.textContent = pad(m);
				if (sEl) sEl.textContent = pad(s);
			}
			tickFlash();
			setInterval(tickFlash, 1000);
		}

		Array.prototype.slice.call(root.querySelectorAll('[data-ss-copy]')).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var code = btn.getAttribute('data-ss-copy') || '';
				var offer = btn.closest('.ss-home-promo__offer') || root;
				var toast = offer.querySelector('[data-ss-copy-toast]');
				var done = function () {
					btn.classList.add('is-copied');
					if (toast) {
						toast.hidden = false;
						toast.classList.add('is-visible');
						window.setTimeout(function () {
							toast.classList.remove('is-visible');
							toast.hidden = true;
						}, 1800);
					}
					window.setTimeout(function () {
						btn.classList.remove('is-copied');
					}, 1800);
				};
				if (!code) return;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(done).catch(function () {
						try {
							var ta = document.createElement('textarea');
							ta.value = code;
							document.body.appendChild(ta);
							ta.select();
							document.execCommand('copy');
							document.body.removeChild(ta);
							done();
						} catch (e) { /* ignore */ }
					});
				} else {
					try {
						var ta2 = document.createElement('textarea');
						ta2.value = code;
						document.body.appendChild(ta2);
						ta2.select();
						document.execCommand('copy');
						document.body.removeChild(ta2);
						done();
					} catch (e2) { /* ignore */ }
				}
			});
		});
	});
})();
