(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	/* Cupones: fuera del form guard — el acordeón vive en #order_review y WC lo re-renderiza. */
	var couponOpen = false;

	function setCouponOpen(open) {
		couponOpen = !!open;
		var couponBtn = document.getElementById('toggle-coupon');
		var couponPanel = document.getElementById('coupon-form-wrapper');
		var couponAccordion = document.querySelector('.ss-co-coupon');

		if (couponAccordion) {
			couponAccordion.classList.toggle('is-open', couponOpen);
		}
		if (couponBtn) {
			couponBtn.setAttribute('aria-expanded', couponOpen ? 'true' : 'false');
		}
		if (couponPanel) {
			if (couponOpen) {
				couponPanel.removeAttribute('hidden');
			} else {
				couponPanel.setAttribute('hidden', '');
			}
		}
	}

	document.addEventListener('click', function (e) {
		var toggle = e.target && e.target.closest ? e.target.closest('#toggle-coupon, .ss-co-coupon__toggle') : null;
		if (!toggle) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		setCouponOpen(!couponOpen);
		if (couponOpen) {
			var input = document.getElementById('coupon_code_custom');
			if (input) {
				setTimeout(function () { input.focus(); }, 60);
			}
		}
	}, true);

	document.addEventListener('keydown', function (e) {
		if (!e.target || e.target.id !== 'coupon_code_custom') {
			return;
		}
		if (e.key === 'Enter' || e.keyCode === 13) {
			e.preventDefault();
			e.stopPropagation();
			var apply = document.getElementById('apply_coupon_custom');
			if (apply) {
				apply.click();
			}
		}
	});

	ready(function () {
		if (window.jQuery) {
			window.jQuery(document.body).off('click.ssCouponApply', '#apply_coupon_custom').on('click.ssCouponApply', '#apply_coupon_custom', function (e) {
				e.preventDefault();
				e.stopPropagation();

				var input = document.getElementById('coupon_code_custom');
				var coupon = input ? (input.value || '').trim() : '';
				if (!coupon) {
					if (input) input.focus();
					return;
				}

				var $btn = window.jQuery(this);
				var originalText = $btn.text();
				var $form = window.jQuery('form.checkout, form.custom-checkout-form, .woocommerce-checkout');

				$btn.prop('disabled', true).text('Aplicando...');
				$form.addClass('processing');
				if (typeof $form.block === 'function') {
					$form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
				}

				var ajaxUrl = (window.wc_checkout_params && window.wc_checkout_params.wc_ajax_url)
					? window.wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon')
					: '/?wc-ajax=apply_coupon';

				var postData = { coupon_code: coupon };
				if (window.wc_checkout_params && window.wc_checkout_params.apply_coupon_nonce) {
					postData.security = window.wc_checkout_params.apply_coupon_nonce;
				}

				window.jQuery.ajax({
					type: 'POST',
					url: ajaxUrl,
					data: postData,
					dataType: 'html',
					success: function (response) {
						window.jQuery('.woocommerce-error, .woocommerce-message').remove();
						if (response) {
							var $target = window.jQuery('.ss-co-coupon').length ? window.jQuery('.ss-co-coupon') : $form;
							$target.before(response);
							if (response.indexOf('woocommerce-error') === -1 && input) {
								input.value = '';
							}
						}
						window.jQuery(document.body).trigger('applied_coupon_in_checkout', [coupon]);
						window.jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });
					},
					error: function () {
						window.jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });
					},
					complete: function () {
						$btn.prop('disabled', false).text(originalText);
						$form.removeClass('processing');
						if (typeof $form.unblock === 'function') {
							$form.unblock();
						}
					}
				});
			});

			window.jQuery(document.body).on('updated_checkout', function () {
				if (couponOpen) {
					setCouponOpen(true);
				}
			});
		}

		var root = document.querySelector('form.custom-checkout-form') || document.querySelector('.ss-co');
		if (!root) return;
		if (root.getAttribute('data-ss-co-bound') === '1') return;
		root.setAttribute('data-ss-co-bound', '1');

		var cfg = window.ssCheckout || {};
		var mins = parseInt(cfg.reserveMinutes || 30, 10);
		var key = cfg.storageKey || 'ss_checkout_reserve_until';

		/* Reserve countdown */
		(function () {
			var clock = document.querySelector('[data-ss-reserve-clock]');
			if (!clock) return;
			var until = 0;
			try {
				until = parseInt(localStorage.getItem(key) || '0', 10) || 0;
			} catch (e) { until = 0; }
			var now = Date.now();
			if (!until || until < now) {
				until = now + mins * 60 * 1000;
				try { localStorage.setItem(key, String(until)); } catch (e2) { /* ignore */ }
			}
			function tick() {
				var left = Math.max(0, until - Date.now());
				var totalSec = Math.floor(left / 1000);
				var m = Math.floor(totalSec / 60);
				var s = totalSec % 60;
				clock.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
			}
			tick();
			setInterval(tick, 1000);
		})();

		/* Mirror contact → billing */
		function mirror(fromSel, toSel) {
			var from = document.querySelector(fromSel);
			var to = document.querySelector(toSel);
			if (!from || !to) return;
			var sync = function () {
				to.value = from.value;
				if (window.jQuery) window.jQuery(to).trigger('change');
			};
			from.addEventListener('input', sync);
			from.addEventListener('change', sync);
			sync();
		}
		mirror('#contact_first_name', '#billing_first_name');
		mirror('#contact_last_name', '#billing_last_name');
		mirror('#contact_phone', '#billing_phone');

		/* Notes accordion — checkbox + chevron (diseño paso 3) */
		function setNotesOpen(open) {
			var notesRow = document.querySelector('.ss-co-agree__row--notes');
			var notesBtn = document.querySelector('#toggle-order-notes-btn');
			var notesPanel = document.querySelector('#wrapper-order-notes');
			var notesCb = document.querySelector('#toggle-order-notes');
			if (notesRow) notesRow.classList.toggle('is-open', open);
			if (notesBtn) notesBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (notesPanel) notesPanel.hidden = !open;
			if (notesCb) notesCb.checked = open;
		}

		document.addEventListener('change', function (e) {
			if (e.target && e.target.id === 'toggle-order-notes') {
				setNotesOpen(!!e.target.checked);
			}
		});

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('#toggle-order-notes-btn');
			if (btn) {
				e.preventDefault();
				var notesRow = document.querySelector('.ss-co-agree__row--notes');
				var open = !(notesRow && notesRow.classList.contains('is-open'));
				setNotesOpen(open);
			}
		});

		/* Promo UM: separar subtítulo “Podré cancelar…” */
		function formatPromoCheckboxes() {
			document.querySelectorAll('.ss-co-agree__row--promo .um-field-checkbox-option').forEach(function (el) {
				if (el.getAttribute('data-ss-split') === '1') return;
				var raw = (el.textContent || '').trim();
				var tip = 'Podré cancelar la suscripción en cualquier momento';
				var idx = raw.indexOf('Podré cancelar');
				if (idx === -1) idx = raw.toLowerCase().indexOf('podre cancelar');
				if (idx > 0) {
					var main = raw.slice(0, idx).trim().replace(/\.\s*$/, '.');
					el.innerHTML = '';
					el.appendChild(document.createTextNode(main));
					var small = document.createElement('small');
					small.textContent = tip;
					el.appendChild(small);
					el.setAttribute('data-ss-split', '1');
				}
			});
		}
		formatPromoCheckboxes();

		/* TyC UM: asegurar links morados del diseño si el texto viene plano */
		function formatTycCheckboxes() {
			document.querySelectorAll('.ss-co-agree__row--tyc .um-field-checkbox-option').forEach(function (el) {
				if (el.querySelector('a')) return;
				var html = el.innerHTML;
				html = html.replace(/Términos y condiciones/i, '<a href="/terminos-y-condiciones/">Términos y condiciones</a>');
				html = html.replace(/Política de privacidad/i, '<a href="/politica-de-privacidad/">Política de privacidad</a>');
				el.innerHTML = html;
			});
		}
		formatTycCheckboxes();

		if (window.jQuery) {
			window.jQuery(document.body).on('updated_checkout', function () {
				formatPromoCheckboxes();
				formatTycCheckboxes();
				syncPay();
			});
		}

		/* Mark selected payment method */
		function syncPay() {
			document.querySelectorAll('.payment_methods > li').forEach(function (li) {
				var on = !!(li.querySelector('input[type="radio"]:checked'));
				li.classList.toggle('ss-co-pay--active', on);
			});
		}
		syncPay();
		document.addEventListener('change', function (e) {
			if (e.target && e.target.name === 'payment_method') syncPay();
		});
	});
})();
