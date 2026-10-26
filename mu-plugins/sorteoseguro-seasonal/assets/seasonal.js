/* Sorteo Seguro – Temporada estacional
   Inyecta la capa de decoración y aplica el tema activo. */
(function () {
	'use strict';

	var cfg = window.SS_SEASONAL;
	if (!cfg || !cfg.theme || cfg.theme === 'off') return;

	var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var isMobile = !!(window.matchMedia && window.matchMedia('(max-width: 760px)').matches);

	function rand(min, max) {
		return min + Math.random() * (max - min);
	}

	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (html != null) n.innerHTML = html;
		return n;
	}

	var api = {
		theme: cfg.theme,
		cursor: !!cfg.cursor,
		assets: cfg.assets || '',
		reduceMotion: reduceMotion,
		isMobile: isMobile,
		rand: rand,
		el: el,
		layer: null,
		asset: function (file) {
			return api.assets + '/' + cfg.theme + '/' + file;
		},
		add: function (node) {
			if (api.layer && node) api.layer.appendChild(node);
			return node;
		},
		themes: {}
	};
	window.SSSeasonal = api;

	/* ===== Halloween ===================================================== */
	api.themes.halloween = function (api) {
		['tl', 'tr', 'bl', 'br'].forEach(function (pos) {
			var web = api.el('img', 'ss-hal-web ss-hal-web--' + pos);
			web.src = api.asset('telarana.png');
			web.alt = '';
			api.add(web);
		});

		['l', 'r'].forEach(function (side) {
			var pumpkin = api.el('img', 'ss-hal-pumpkin ss-hal-pumpkin--' + side + ' ss-motion');
			pumpkin.src = api.asset('calabaza.png');
			pumpkin.alt = '';
			api.add(pumpkin);
		});

		var batSvg =
			'<svg viewBox="0 0 100 46" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g fill="currentColor">' +
			'<path d="M46 12l-4-10 8 7z"/><path d="M54 12l4-10-8 7z"/>' +
			'<ellipse cx="50" cy="22" rx="7" ry="9"/><circle cx="50" cy="14" r="5"/>' +
			'<path d="M44 17C34 3 12 3 1 14c8 0 15 3 19 9-6-3-13-2-17 2 10-1 19 2 25 9 2-8 7-13 16-15z"/>' +
			'<path d="M56 17C66 3 88 3 99 14c-8 0-15 3-19 9 6-3 13-2 17 2-10-1-19 2-25 9-2-8-7-13-16-15z"/>' +
			'</g></svg>';

		var spiderSvg =
			'<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
			'<g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
			'<path d="M24 26 10 18 4 8M24 26 14 26 3 24M24 27 16 34 6 42M24 29 18 38 14 46"/>' +
			'<path d="M24 26 38 18 44 8M24 26 34 26 45 24M24 27 32 34 42 42M24 29 30 38 34 46"/>' +
			'</g>' +
			'<g fill="currentColor"><ellipse cx="24" cy="31" rx="8" ry="9"/><circle cx="24" cy="21" r="6"/></g>' +
			'<g fill="#ff5a3c"><circle cx="21.5" cy="20" r="1.3"/><circle cx="26.5" cy="20" r="1.3"/></g>' +
			'</svg>';

		var batCount = api.isMobile ? 3 : 6;
		for (var i = 0; i < batCount; i++) {
			var bat = api.el('div', 'ss-hal-bat ss-motion');
			var start = api.rand(0, 1) < 0.5 ? -14 : 114;
			var end = start < 0 ? 114 : -14;
			bat.style.setProperty('--ss-dur', api.rand(18, 30).toFixed(1) + 's');
			bat.style.setProperty('--ss-delay', api.rand(0, 16).toFixed(1) + 's');
			bat.style.setProperty('--ss-start', start + 'vw');
			bat.style.setProperty('--ss-end', end + 'vw');
			bat.style.top = api.rand(8, 48).toFixed(1) + '%';
			bat.appendChild(api.el('div', 'ss-hal-bat__bob', batSvg));
			api.add(bat);
		}

		var spiderCount = api.isMobile ? 1 : 3;
		for (var s = 0; s < spiderCount; s++) {
			var spider = api.el('div', 'ss-hal-spider ss-motion');
			spider.style.setProperty('--ss-thread', Math.round(api.rand(100, 220)) + 'px');
			spider.style.setProperty('--ss-dur', api.rand(14, 24).toFixed(1) + 's');
			spider.style.setProperty('--ss-delay', api.rand(0, 14).toFixed(1) + 's');
			spider.style.left = (api.rand(0, 1) < 0.5 ? api.rand(4, 22) : api.rand(78, 94)).toFixed(1) + '%';
			spider.appendChild(api.el('div', 'ss-hal-spider__thread'));
			spider.appendChild(api.el('div', 'ss-hal-spider__body', spiderSvg));
			api.add(spider);
		}

		var emberCount = api.isMobile ? 6 : 12;
		for (var e = 0; e < emberCount; e++) {
			var ember = api.el('div', 'ss-hal-ember ss-motion');
			ember.style.left = api.rand(2, 98).toFixed(1) + '%';
			ember.style.setProperty('--ss-dur', api.rand(7, 14).toFixed(1) + 's');
			ember.style.setProperty('--ss-delay', api.rand(0, 12).toFixed(1) + 's');
			ember.style.setProperty('--ss-rise', api.rand(45, 85).toFixed(0) + 'vh');
			ember.style.setProperty('--ss-drift', api.rand(-40, 40).toFixed(0) + 'px');
			ember.appendChild(api.el('div', 'ss-hal-ember__dot'));
			api.add(ember);
		}

		if (api.cursor && !api.isMobile) {
			var cur = api.asset('cursor-pumpkin.png');
			var ptr = api.asset('cursor-pointer.png');
			var style = document.createElement('style');
			style.id = 'ss-seasonal-cursor-css';
			style.textContent =
				'body.ss-theme--halloween.ss-seasonal-cursor{cursor:url(' + cur + ') 16 16, auto !important;}' +
				'body.ss-theme--halloween.ss-seasonal-cursor a,' +
				'body.ss-theme--halloween.ss-seasonal-cursor button,' +
				'body.ss-theme--halloween.ss-seasonal-cursor [role="button"],' +
				'body.ss-theme--halloween.ss-seasonal-cursor input[type="submit"],' +
				'body.ss-theme--halloween.ss-seasonal-cursor input[type="button"],' +
				'body.ss-theme--halloween.ss-seasonal-cursor select,' +
				'body.ss-theme--halloween.ss-seasonal-cursor label[for],' +
				'body.ss-theme--halloween.ss-seasonal-cursor summary' +
				'{cursor:url(' + ptr + ') 16 16, pointer !important;}';
			document.head.appendChild(style);
		}
	};

	function mount() {
		var layer = document.getElementById('ss-seasonal-layer');
		if (!layer) {
			layer = el('div');
			layer.id = 'ss-seasonal-layer';
			layer.setAttribute('aria-hidden', 'true');
			document.body.appendChild(layer);
		}
		api.layer = layer;

		var fn = api.themes[api.theme];
		if (typeof fn === 'function') {
			try {
				fn(api);
			} catch (e) {
				/* nunca romper la página por la decoración */
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', mount);
	} else {
		mount();
	}

	document.addEventListener('visibilitychange', function () {
		if (api.layer) {
			api.layer.classList.toggle('is-paused', document.hidden);
		}
	});
})();
