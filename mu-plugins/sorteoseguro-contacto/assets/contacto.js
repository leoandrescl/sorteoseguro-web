(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		var root = document.querySelector('.ss-contact');
		if (!root || root.getAttribute('data-ss-contact-bound') === '1') return;
		root.setAttribute('data-ss-contact-bound', '1');

		var form = root.querySelector('.wpcf7-form');
		var asunto = root.querySelector('.ss-contact-asunto');
		if (!form || !asunto) return;

		form.addEventListener('submit', function (e) {
			if (asunto.value) return;
			e.preventDefault();
			e.stopPropagation();
			asunto.focus();
			asunto.classList.add('is-empty');
		});

		function syncAsunto() {
			asunto.classList.toggle('has-value', !!asunto.value);
			if (asunto.value) asunto.classList.remove('is-empty');
		}

		syncAsunto();
		asunto.addEventListener('change', syncAsunto);
	});
})();
