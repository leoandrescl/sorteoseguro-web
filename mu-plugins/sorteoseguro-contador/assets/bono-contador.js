/* Sorteo Seguro – Contador bono. Cuenta regresiva infinita con reseteo diario (nunca en 0). */
(function () {
	'use strict';

	var DAY_MS = 86400000;

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function init(root) {
		if (!root || root.__ssContadorInit) {
			return;
		}
		root.__ssContadorInit = true;

		var hEl = root.querySelector('[data-ss-contador-h]');
		var mEl = root.querySelector('[data-ss-contador-m]');
		var sEl = root.querySelector('[data-ss-contador-s]');
		if (!hEl || !mEl || !sEl) {
			return;
		}

		var until = parseInt(root.getAttribute('data-ss-contador-until') || '0', 10);
		var target = until > 0 ? until * 1000 : (Date.now() + DAY_MS);

		var lastH = null;
		var lastM = null;
		var lastS = null;

		function render(ms) {
			var total = Math.max(1, Math.round(ms / 1000));
			if (total > 86400) {
				total = 86400;
			}
			var h = Math.floor(total / 3600);
			var m = Math.floor((total % 3600) / 60);
			var s = total % 60;

			if (h !== lastH) { hEl.textContent = pad(h); lastH = h; }
			if (m !== lastM) { mEl.textContent = pad(m); lastM = m; }
			if (s !== lastS) { sEl.textContent = pad(s); lastS = s; }
		}

		function tick() {
			var now = Date.now();
			var diff = target - now;
			if (diff <= 0) {
				do {
					target += DAY_MS;
				} while (target - now <= 0);
				diff = target - now;
			}
			render(diff);
		}

		tick();
		window.setInterval(tick, 1000);

		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				tick();
			}
		});
	}

	function boot() {
		var nodes = document.querySelectorAll('[data-ss-contador]');
		for (var i = 0; i < nodes.length; i++) {
			init(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
