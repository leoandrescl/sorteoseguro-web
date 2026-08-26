(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		var root = document.querySelector('.ss-faq');
		if (!root) return;
		if (root.getAttribute('data-ss-faq-bound') === '1') return;
		root.setAttribute('data-ss-faq-bound', '1');

		var filters = Array.prototype.slice.call(root.querySelectorAll('.ss-faq-filter'));
		var items = Array.prototype.slice.call(root.querySelectorAll('.ss-faq-item'));
		var search = root.querySelector('#ss-faq-search');
		var noResults = root.querySelector('#ss-faq-no-results');
		var activeCat = 'all';

		function normalize(str) {
			return (str || '')
				.toLowerCase()
				.normalize('NFD')
				.replace(/[\u0300-\u036f]/g, '');
		}

		function closeItem(item) {
			item.classList.remove('is-open');
			var btn = item.querySelector('.ss-faq-item__q');
			if (btn) btn.setAttribute('aria-expanded', 'false');
		}

		function openItem(item) {
			items.forEach(function (other) {
				if (other !== item) closeItem(other);
			});
			item.classList.add('is-open');
			var btn = item.querySelector('.ss-faq-item__q');
			if (btn) btn.setAttribute('aria-expanded', 'true');
		}

		function applyFilters() {
			var q = normalize(search ? search.value.trim() : '');
			var visible = 0;

			items.forEach(function (item) {
				var cats = (item.getAttribute('data-cats') || '').split(/\s+/);
				var text = normalize(item.getAttribute('data-q') || '') + ' ' + normalize(item.textContent || '');
				var catOk = activeCat === 'all' || cats.indexOf(activeCat) !== -1;
				var qOk = !q || text.indexOf(q) !== -1;
				var show = catOk && qOk;
				item.classList.toggle('is-hidden', !show);
				if (!show) closeItem(item);
				if (show) visible += 1;
			});

			if (noResults) noResults.hidden = visible > 0;
		}

		filters.forEach(function (btn) {
			btn.addEventListener('click', function () {
				activeCat = btn.getAttribute('data-cat') || 'all';
				filters.forEach(function (b) {
					var on = b === btn;
					b.classList.toggle('is-active', on);
					b.setAttribute('aria-selected', on ? 'true' : 'false');
				});
				applyFilters();
			});
		});

		if (search) {
			search.addEventListener('input', applyFilters);
		}

		items.forEach(function (item) {
			closeItem(item);
			var btn = item.querySelector('.ss-faq-item__q');
			if (!btn) return;

			btn.addEventListener('click', function () {
				if (item.classList.contains('is-open')) {
					closeItem(item);
				} else {
					openItem(item);
				}
			});
		});
	});
})();
