(function () {
	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function tick(root) {
		var until = parseInt(root.getAttribute('data-ss-cyber-until') || '0', 10);
		if (!until) {
			return false;
		}
		var left = until - Math.floor(Date.now() / 1000);
		if (left < 0) {
			left = 0;
		}
		var h = Math.floor(left / 3600);
		var m = Math.floor((left % 3600) / 60);
		var s = left % 60;
		var hEl = root.querySelector('[data-ss-cyber-h]');
		var mEl = root.querySelector('[data-ss-cyber-m]');
		var sEl = root.querySelector('[data-ss-cyber-s]');
		if (hEl) { hEl.textContent = pad(h); }
		if (mEl) { mEl.textContent = pad(m); }
		if (sEl) { sEl.textContent = pad(s); }
		return left > 0;
	}

	function run() {
		var roots = document.querySelectorAll('[data-ss-cyber-banner]');
		Array.prototype.forEach.call(roots, function (root) {
			if (root.getAttribute('data-ss-cyber-init') === '1') {
				return;
			}
			root.setAttribute('data-ss-cyber-init', '1');
			tick(root);
			var timer = setInterval(function () {
				if (!tick(root)) {
					clearInterval(timer);
				}
			}, 1000);
		});
	}

	if (document.readyState !== 'loading') {
		run();
	} else {
		document.addEventListener('DOMContentLoaded', run);
	}
})();
