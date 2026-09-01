(function ($) {
	'use strict';

	function scrollToCheckout() {
		var el = document.getElementById('ss-comprar-checkout');
		if (el) {
			el.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function isPreloadCheckout() {
		var root = document.getElementById('ss-comprar-checkout');
		return !!(root && root.classList.contains('is-preload'));
	}

	$(function () {
		if (!document.body.classList.contains('ss-comprar-template')) {
			return;
		}

		if (isPreloadCheckout()) {
			$(document.body).on('update_checkout checkout_error', function (e) {
				e.stopImmediatePropagation();
				return false;
			});

			$(document.body).on('submit', 'form.checkout', function (e) {
				e.preventDefault();
				return false;
			});
		}

		if (window.location.hash === '#ss-comprar-checkout') {
			setTimeout(scrollToCheckout, 300);
		}

		$(document.body).on('updated_checkout', function () {
			if (!isPreloadCheckout()) {
				scrollToCheckout();
			}
		});

		if (!isPreloadCheckout()) {
			$(document.body).on('checkout_place_order', 'form.checkout', function () {
				var tyc = document.querySelector('.ss-co-agree__row--tyc input[type="checkbox"]');
				if (tyc && !tyc.checked) {
					window.alert('Debes aceptar los Términos y condiciones y la Política de privacidad para poder finalizar tu pedido.');
					return false;
				}
			});
		}
	});
})(jQuery);
