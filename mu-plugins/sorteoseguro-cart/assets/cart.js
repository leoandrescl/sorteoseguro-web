(function () {
	document.addEventListener('click', function (e) {
		var a = e.target && e.target.closest ? e.target.closest('a.ss-cart-item__remove') : null;
		if (!a) return;
		var href = a.getAttribute('href');
		if (!href) return;
		e.preventDefault();
		e.stopPropagation();
		window.location.assign(href);
	}, true);

	function setCouponOpen(open) {
		var toggle = document.getElementById('ss-cart-coupon-toggle');
		var panel = document.getElementById('ss-cart-coupon-panel');
		var box = document.querySelector('.ss-cart-coupon');
		if (box) box.classList.toggle('is-open', !!open);
		if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (panel) {
			if (open) panel.removeAttribute('hidden');
			else panel.setAttribute('hidden', '');
		}
	}

	document.addEventListener('click', function (e) {
		var toggle = e.target && e.target.closest ? e.target.closest('#ss-cart-coupon-toggle') : null;
		if (!toggle) return;
		e.preventDefault();
		var open = toggle.getAttribute('aria-expanded') === 'true';
		setCouponOpen(!open);
		if (!open) {
			var input = document.getElementById('ss-cart-coupon-code');
			if (input) setTimeout(function () { input.focus(); }, 50);
		}
	});

	document.addEventListener('keydown', function (e) {
		if (!e.target || e.target.id !== 'ss-cart-coupon-code') return;
		if (e.key === 'Enter' || e.keyCode === 13) {
			e.preventDefault();
			var apply = document.getElementById('ss-cart-coupon-apply');
			if (apply) apply.click();
		}
	});

	if (!window.jQuery) return;
	var $ = window.jQuery;

	$(document.body).on('click', '#ss-cart-coupon-apply', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var input = document.getElementById('ss-cart-coupon-code');
		var coupon = input ? (input.value || '').trim() : '';
		if (!coupon) {
			if (input) input.focus();
			return;
		}
		var $btn = $(this);
		var original = $btn.text();
		$btn.prop('disabled', true).text('Aplicando...');

		var params = window.wc_cart_params || {};
		var ajaxUrl = params.wc_ajax_url
			? params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon')
			: '/?wc-ajax=apply_coupon';
		var data = { coupon_code: coupon };
		if (params.apply_coupon_nonce) data.security = params.apply_coupon_nonce;

		$.ajax({
			type: 'POST',
			url: ajaxUrl,
			data: data,
			dataType: 'html',
			success: function (response) {
				$('.woocommerce-error, .woocommerce-message, .woocommerce-info').remove();
				if (response) {
					var $target = $('.ss-cart__shell').first();
					if ($target.length) $target.prepend(response);
					else $('.ss-cart').prepend(response);
					if (String(response).indexOf('woocommerce-error') === -1 && input) {
						input.value = '';
						setCouponOpen(false);
					}
				}
				$(document.body).trigger('applied_coupon', [coupon]);
				$(document.body).trigger('wc_update_cart');
			},
			complete: function () {
				$btn.prop('disabled', false).text(original);
			}
		});
	});

	$(document.body).on('updated_wc_div updated_cart_totals', function () {
		var open = document.querySelector('.ss-cart-coupon.is-open');
		if (open) setCouponOpen(true);
	});
})();
