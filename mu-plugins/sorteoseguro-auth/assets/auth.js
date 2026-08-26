(function () {
	'use strict';

	function cleanLabelText(text) {
		return String(text || '')
			.replace(/\*/g, '')
			.replace(/Obligatorio/gi, '')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function enhanceFloatField(container, input, labelText) {
		if (!container || !input || container.classList.contains('is-float-ready')) {
			return;
		}
		container.classList.add('ss-auth-field', 'is-float', 'is-float-ready');
		if (!input.getAttribute('placeholder')) {
			input.setAttribute('placeholder', ' ');
		}
		var label = container.querySelector('label.ss-auth-float-label');
		if (!label) {
			label = document.createElement('label');
			label.className = 'ss-auth-float-label';
			container.appendChild(label);
		}
		if (labelText) {
			label.textContent = labelText;
		}
		if (input.id) {
			label.setAttribute('for', input.id);
		}
		var anchor = input.closest('.password-input') || input.closest('.um-field-area-password') || input;
		if (label.parentElement !== container || label.previousElementSibling !== anchor) {
			anchor.insertAdjacentElement('afterend', label);
		}
	}

	function wcLoginFields() {
		var form = document.querySelector('.ss-auth-card__form--wc .woocommerce-form-login');
		if (!form) {
			return;
		}
		var loginLabels = {
			username: 'Correo electrónico o nombre de usuario',
			password: 'Contraseña',
		};
		form.querySelectorAll('.form-row').forEach(function (row) {
			var input = row.querySelector('#username, #password, input.input-text');
			if (!input || row.querySelector('.woocommerce-form__label-for-checkbox')) {
				return;
			}
			var text = loginLabels[input.id] || '';
			if (!text) {
				var oldLabel = row.querySelector('label:not(.ss-auth-float-label)');
				text = oldLabel ? cleanLabelText(oldLabel.textContent) : '';
			}
			enhanceFloatField(row, input, text);
		});
		var submit = form.querySelector('.woocommerce-form-login__submit');
		if (submit && submit.value.trim() === '') {
			submit.value = 'Ingresar a tu cuenta';
		}
	}

	function umFields() {
		var root = document.querySelector('.ss-auth-card__form--um');
		if (!root) {
			return;
		}
		root.querySelectorAll('.um-field').forEach(function (field) {
			if (
				field.classList.contains('um-field-type_checkbox') ||
				field.classList.contains('um-field-type_block') ||
				field.classList.contains('um-field-type_hp')
			) {
				return;
			}
			var input = field.querySelector('input[type="text"], input[type="email"], input[type="password"], input[type="tel"]');
			if (!input) {
				return;
			}
			var labelEl = field.querySelector('.um-field-label label, .um-field-label');
			var text = labelEl ? cleanLabelText(labelEl.textContent) : '';
			var area = field.querySelector('.um-field-area') || field;
			area.classList.add('ss-auth-field', 'is-float', 'is-float-ready');
			if (!input.getAttribute('placeholder') || input.getAttribute('placeholder') === input.name) {
				if (text) {
					input.setAttribute('placeholder', ' ');
				}
			}
			enhanceFloatField(area, input, text);
		});
	}

	function removeUmEjemplo() {
		document.querySelectorAll('.ss-auth-card__form--um .um-ejemplo-custom').forEach(function (node) {
			node.remove();
		});
	}

	function watchUmEjemplo() {
		var root = document.querySelector('.ss-auth-card__form--um');
		if (!root || typeof MutationObserver === 'undefined') {
			return;
		}
		removeUmEjemplo();
		var observer = new MutationObserver(function () {
			removeUmEjemplo();
		});
		observer.observe(root, { childList: true, subtree: true });
	}

	function init() {
		wcLoginFields();
		umFields();
		removeUmEjemplo();
		watchUmEjemplo();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
