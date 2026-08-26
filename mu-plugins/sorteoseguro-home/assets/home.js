(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function parseEnd(value) {
		if (!value) return 0;
		value = String(value).trim();
		if (/^\d+$/.test(value)) {
			var n = parseInt(value, 10);
			if (!n) return 0;
			return n < 1e12 ? n * 1000 : n;
		}
		/* Lottery ficha: "YYYY/MM/DD HH:MM:SS" se interpreta en hora local. */
		var m = value.match(/^(\d{4})\/(\d{2})\/(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
		if (m) {
			var local = new Date(
				parseInt(m[1], 10),
				parseInt(m[2], 10) - 1,
				parseInt(m[3], 10),
				parseInt(m[4] || '0', 10),
				parseInt(m[5] || '0', 10),
				parseInt(m[6] || '0', 10)
			);
			var localTs = local.getTime();
			return isNaN(localTs) ? 0 : localTs;
		}
		var ts = Date.parse(value);
		return isNaN(ts) ? 0 : ts;
	}

	function formatRemain(ms) {
		if (ms <= 0) return '0 días';
		var totalMin = Math.floor(ms / 60000);
		var days = Math.floor(totalMin / (60 * 24));
		var hours = Math.floor((totalMin % (60 * 24)) / 60);
		var mins = totalMin % 60;
		if (days >= 1) {
			return days === 1 ? '1 día' : days + ' días';
		}
		if (hours >= 1) {
			return hours === 1 ? '1 hora' : hours + ' hrs';
		}
		if (mins <= 1) return '1 min';
		return mins + ' min';
	}

	ready(function () {
		var root = document.querySelector('.ss-home');
		if (!root) return;
		if (root.getAttribute('data-ss-home-bound') === '1') return;
		root.setAttribute('data-ss-home-bound', '1');

		/* Contest countdowns */
		var countdownEls = Array.prototype.slice.call(root.querySelectorAll('[data-ss-countdown]'));
		function tickCountdowns() {
			var now = Date.now();
			countdownEls.forEach(function (el) {
				var end = parseEnd(el.getAttribute('data-ss-countdown'));
				var label = el.querySelector('[data-ss-countdown-label]') || el;
				if (!end) {
					label.textContent = '';
					return;
				}
				label.textContent = formatRemain(end - now);
			});
		}
		if (countdownEls.length) {
			tickCountdowns();
			setInterval(tickCountdowns, 30000);
		}

		/* Hero slider */
		var hero = root.querySelector('[data-ss-hero-slider]');
		if (hero) {
			var slides = Array.prototype.slice.call(hero.querySelectorAll('[data-ss-hero-slide]'));
			var dots = Array.prototype.slice.call(hero.querySelectorAll('[data-ss-hero-dot]'));
			var jumps = Array.prototype.slice.call(hero.querySelectorAll('[data-ss-hero-goto]'));
			if (slides.length > 1) {
				var heroIndex = 0;
				var heroTimer = null;
				var heroMs = parseInt(hero.getAttribute('data-ss-hero-interval') || '5000', 10);
				var heroReduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

				function heroGo(n) {
					heroIndex = ((n % slides.length) + slides.length) % slides.length;
					slides.forEach(function (slide, idx) {
						var on = idx === heroIndex;
						slide.classList.toggle('is-active', on);
						slide.setAttribute('aria-hidden', on ? 'false' : 'true');
					});
					dots.forEach(function (dot, idx) {
						var on = idx === heroIndex;
						dot.classList.toggle('is-active', on);
						dot.setAttribute('aria-selected', on ? 'true' : 'false');
					});
				}
				function heroNext() {
					heroGo(heroIndex + 1);
				}
				function heroStop() {
					if (heroTimer) {
						window.clearInterval(heroTimer);
						heroTimer = null;
					}
				}
				function heroStart() {
					heroStop();
					if (!heroReduce) {
						heroTimer = window.setInterval(heroNext, heroMs);
					}
				}

				dots.forEach(function (dot) {
					dot.addEventListener('click', function () {
						heroGo(parseInt(dot.getAttribute('data-ss-hero-dot') || '0', 10));
						if (!heroReduce) heroStart();
					});
				});
				jumps.forEach(function (btn) {
					btn.addEventListener('click', function () {
						heroGo(parseInt(btn.getAttribute('data-ss-hero-goto') || '0', 10));
						if (!heroReduce) heroStart();
					});
				});
				hero.addEventListener('mouseenter', heroStop);
				hero.addEventListener('mouseleave', heroStart);
				hero.addEventListener('focusin', heroStop);
				hero.addEventListener('focusout', heroStart);
				document.addEventListener('visibilitychange', function () {
					if (document.hidden) heroStop();
					else if (!heroReduce) heroStart();
				});
				heroStart();
			}
		}

		/* Contests carousel */
		Array.prototype.slice.call(root.querySelectorAll('[data-ss-carousel]')).forEach(function (carousel) {
			var track = carousel.querySelector('.ss-home-contests__track');
			var nextBtn = carousel.querySelector('[data-ss-carousel-next]');
			if (!track || track.children.length < 2) return;

			var intervalMs = parseInt(carousel.getAttribute('data-ss-carousel-interval') || '5000', 10);
			var animating = false;
			var timer = null;
			var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

			function gap() {
				var style = window.getComputedStyle(track);
				return parseFloat(style.columnGap || style.gap || '0') || 0;
			}

			function advance() {
				if (animating || track.children.length < 2) return;
				animating = true;
				var first = track.children[0];
				var step = first.getBoundingClientRect().width + gap();
				track.style.transition = 'transform 0.55s ease';
				track.style.transform = 'translateX(-' + step + 'px)';
				window.setTimeout(function () {
					track.style.transition = 'none';
					track.appendChild(first);
					track.style.transform = 'translateX(0)';
					void track.offsetHeight;
					animating = false;
				}, 560);
			}

			function start() {
				stop();
				timer = window.setInterval(advance, intervalMs);
			}
			function stop() {
				if (timer) {
					window.clearInterval(timer);
					timer = null;
				}
			}

			if (nextBtn) {
				nextBtn.addEventListener('click', function () {
					advance();
					if (!reduceMotion) start();
				});
			}
			if (!reduceMotion) {
				carousel.addEventListener('mouseenter', stop);
				carousel.addEventListener('mouseleave', start);
				carousel.addEventListener('focusin', stop);
				carousel.addEventListener('focusout', start);
				start();
			}
		});

		/* Winners carousel */
		Array.prototype.slice.call(root.querySelectorAll('[data-ss-winners-carousel]')).forEach(function (carousel) {
			var track = carousel.querySelector('.ss-home-winners__track');
			var nextBtn = carousel.querySelector('[data-ss-winners-next]');
			var panel = carousel.closest('.ss-home-winners__panel');
			var section = carousel.closest('.ss-home-winners') || carousel.parentElement;
			var dotsRoot = section || panel || carousel;
			var dots = Array.prototype.slice.call(dotsRoot.querySelectorAll('[data-ss-winners-dot]'));
			if (!track || track.children.length < 2) return;

			var intervalMs = parseInt(carousel.getAttribute('data-ss-carousel-interval') || '5000', 10);
			var animating = false;
			var timer = null;
			var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var total = track.children.length;

			function gap() {
				var style = window.getComputedStyle(track);
				return parseFloat(style.columnGap || style.gap || '0') || 0;
			}

			function activeIndex() {
				var first = track.children[0];
				return first ? parseInt(first.getAttribute('data-ss-winner-index') || '0', 10) : 0;
			}

			function syncDots() {
				var active = activeIndex();
				dots.forEach(function (dot) {
					var idx = parseInt(dot.getAttribute('data-ss-winners-dot') || '0', 10);
					var on = idx === active;
					dot.classList.toggle('is-active', on);
					dot.setAttribute('aria-selected', on ? 'true' : 'false');
				});
			}

			function advance(onDone) {
				if (animating || track.children.length < 2) return;
				animating = true;
				var first = track.children[0];
				var step = first.getBoundingClientRect().width + gap();
				track.style.transition = 'transform 0.55s ease';
				track.style.transform = 'translateX(-' + step + 'px)';
				window.setTimeout(function () {
					track.style.transition = 'none';
					track.appendChild(first);
					track.style.transform = 'translateX(0)';
					void track.offsetHeight;
					animating = false;
					syncDots();
					if (typeof onDone === 'function') onDone();
				}, 560);
			}

			function goTo(targetIdx) {
				if (animating) return;
				targetIdx = ((targetIdx % total) + total) % total;
				if (activeIndex() === targetIdx) return;

				function stepToward() {
					if (activeIndex() === targetIdx) {
						if (!reduceMotion) start();
						return;
					}
					advance(stepToward);
				}
				stop();
				stepToward();
			}

			function start() {
				stop();
				timer = window.setInterval(function () {
					advance();
				}, intervalMs);
			}
			function stop() {
				if (timer) {
					window.clearInterval(timer);
					timer = null;
				}
			}

			if (nextBtn) {
				nextBtn.addEventListener('click', function () {
					advance();
					if (!reduceMotion) start();
				});
			}
			dots.forEach(function (dot) {
				dot.addEventListener('click', function () {
					var idx = parseInt(dot.getAttribute('data-ss-winners-dot') || '0', 10);
					goTo(idx);
				});
			});
			syncDots();
			if (!reduceMotion) {
				carousel.addEventListener('mouseenter', stop);
				carousel.addEventListener('mouseleave', start);
				carousel.addEventListener('focusin', stop);
				carousel.addEventListener('focusout', start);
				start();
			}
		});

		/* Promo countdown */
		var promo = root.querySelector('[data-ss-promo-end]');
		function tickPromo() {
			if (!promo) return;
			var end = parseEnd(promo.getAttribute('data-ss-promo-end'));
			var now = Date.now();
			var ms = Math.max(0, end - now);
			var totalSec = Math.floor(ms / 1000);
			var d = Math.floor(totalSec / 86400);
			var h = Math.floor((totalSec % 86400) / 3600);
			var m = Math.floor((totalSec % 3600) / 60);
			var s = totalSec % 60;
			var dEl = promo.querySelector('[data-ss-promo-d]');
			var hEl = promo.querySelector('[data-ss-promo-h]');
			var mEl = promo.querySelector('[data-ss-promo-m]');
			var sEl = promo.querySelector('[data-ss-promo-s]');
			if (dEl) dEl.textContent = pad(d);
			if (hEl) hEl.textContent = pad(h);
			if (mEl) mEl.textContent = pad(m);
			if (sEl) sEl.textContent = pad(s);
		}
		if (promo) {
			tickPromo();
			setInterval(tickPromo, 1000);
		}

		/* Copy coupon */
		Array.prototype.slice.call(root.querySelectorAll('[data-ss-copy]')).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var code = btn.getAttribute('data-ss-copy') || '';
				var label = btn.querySelector('[data-ss-copy-label]');
				var offer = btn.closest('.ss-home-promo__offer') || root;
				var toast = offer.querySelector('[data-ss-copy-toast]');
				var done = function () {
					btn.classList.add('is-copied');
					if (label) label.textContent = '¡Copiado!';
					btn.setAttribute('aria-label', 'Código copiado');
					if (toast) {
						toast.hidden = false;
						toast.classList.add('is-visible');
					}
					setTimeout(function () {
						btn.classList.remove('is-copied');
						if (label) label.textContent = 'Copiar código';
						btn.setAttribute('aria-label', 'Copiar código ' + code);
						if (toast) {
							toast.classList.remove('is-visible');
							window.setTimeout(function () {
								if (!toast.classList.contains('is-visible')) toast.hidden = true;
							}, 250);
						}
					}, 1800);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(done).catch(function () {
						fallbackCopy(code, done);
					});
				} else {
					fallbackCopy(code, done);
				}
			});
		});

		function fallbackCopy(text, cb) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			try {
				document.execCommand('copy');
				if (cb) cb();
			} catch (e) { /* ignore */ }
			document.body.removeChild(ta);
		}

		/* Partners carousel (mobile) */
		Array.prototype.slice.call(root.querySelectorAll('[data-ss-partners-carousel]')).forEach(function (carousel) {
			var track = carousel.querySelector('.ss-home-partners__grid');
			if (!track || track.children.length < 4) return;

			var intervalMs = parseInt(carousel.getAttribute('data-ss-carousel-interval') || '4000', 10);
			var animating = false;
			var timer = null;
			var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var mobileMq = window.matchMedia ? window.matchMedia('(max-width: 760px)') : null;

			function isMobile() {
				return mobileMq ? mobileMq.matches : window.innerWidth <= 760;
			}

			function gap() {
				var style = window.getComputedStyle(track);
				return parseFloat(style.columnGap || style.gap || '0') || 0;
			}

			function resetTransform() {
				track.style.transition = 'none';
				track.style.transform = '';
			}

			function advance() {
				if (!isMobile() || animating || track.children.length < 2) return;
				animating = true;
				var first = track.children[0];
				var step = first.getBoundingClientRect().width + gap();
				track.style.transition = 'transform 0.55s ease';
				track.style.transform = 'translateX(-' + step + 'px)';
				window.setTimeout(function () {
					track.style.transition = 'none';
					track.appendChild(first);
					track.style.transform = 'translateX(0)';
					void track.offsetHeight;
					animating = false;
				}, 560);
			}

			function start() {
				stop();
				if (!isMobile() || reduceMotion) return;
				timer = window.setInterval(advance, intervalMs);
			}
			function stop() {
				if (timer) {
					window.clearInterval(timer);
					timer = null;
				}
			}

			function onBreakpoint() {
				stop();
				resetTransform();
				if (isMobile() && !reduceMotion) start();
			}

			if (mobileMq) {
				if (mobileMq.addEventListener) mobileMq.addEventListener('change', onBreakpoint);
				else if (mobileMq.addListener) mobileMq.addListener(onBreakpoint);
			}
			carousel.addEventListener('mouseenter', stop);
			carousel.addEventListener('mouseleave', start);
			carousel.addEventListener('focusin', stop);
			carousel.addEventListener('focusout', start);
			onBreakpoint();
		});

		/* FAQ accordion + search */
		var items = Array.prototype.slice.call(root.querySelectorAll('.ss-home-faq__item'));
		var search = root.querySelector('#ss-home-faq-search');
		var noResults = root.querySelector('#ss-home-faq-no-results');

		function normalize(str) {
			return (str || '')
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '');
		}

		function closeItem(item) {
			item.classList.remove('is-open');
			var btn = item.querySelector('.ss-home-faq__q');
			if (btn) btn.setAttribute('aria-expanded', 'false');
		}

		function openItem(item) {
			items.forEach(function (other) {
				if (other !== item) closeItem(other);
			});
			item.classList.add('is-open');
			var btn = item.querySelector('.ss-home-faq__q');
			if (btn) btn.setAttribute('aria-expanded', 'true');
		}

		function applySearch() {
			var q = normalize(search ? search.value.trim() : '');
			var visible = 0;
			items.forEach(function (item) {
				var text = normalize(item.getAttribute('data-q') || '') + ' ' + normalize(item.textContent || '');
				var show = !q || text.indexOf(q) !== -1;
				item.classList.toggle('is-hidden', !show);
				if (!show) closeItem(item);
				if (show) visible += 1;
			});
			if (noResults) noResults.hidden = visible > 0;
		}

		items.forEach(function (item) {
			closeItem(item);
			var btn = item.querySelector('.ss-home-faq__q');
			if (!btn) return;
			btn.addEventListener('click', function () {
				if (item.classList.contains('is-open')) closeItem(item);
				else openItem(item);
			});
		});

		if (search) {
			search.addEventListener('input', applySearch);
		}

		/* Smooth scroll for in-page anchors */
		Array.prototype.slice.call(root.querySelectorAll('a[href^="#"]')).forEach(function (link) {
			link.addEventListener('click', function (e) {
				var hash = link.getAttribute('href');
				if (!hash || hash === '#') return;
				var target = root.querySelector(hash);
				if (!target) return;
				e.preventDefault();
				target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				if (history && history.pushState) {
					history.pushState(null, '', hash);
				}
			});
		});
	});
})();
