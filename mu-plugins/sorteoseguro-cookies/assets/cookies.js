(function () {
	var ICO_SLIDERS =
		'<svg class="ss-cookie-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 7.5A2.5 2.5 0 0 1 6.5 5H20a1 1 0 1 1 0 2H6.5a.5.5 0 0 0 0 1H20a1 1 0 1 1 0 2H6.5A2.5 2.5 0 0 1 4 7.5Zm16 9A2.5 2.5 0 0 1 17.5 19H4a1 1 0 1 1 0-2h13.5a.5.5 0 0 0 0-1H4a1 1 0 1 1 0-2h13.5A2.5 2.5 0 0 1 20 16.5Z"/></svg>';
	var ICO_CHECK =
		'<svg class="ss-cookie-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9.2 16.2 5.8 12.8a1 1 0 1 0-1.4 1.4l4.1 4.1a1 1 0 0 0 1.4 0l9.1-9.1a1 1 0 1 0-1.4-1.4L9.2 16.2Z"/></svg>';
	var ICO_LOCK =
		'<svg class="ss-cookie-foot__lock" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 9h-1V7a4 4 0 1 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2ZM9 7a3 3 0 1 1 6 0v2H9V7Zm8 12H7v-8h10v8Zm-5-6a1.5 1.5 0 0 0-.5 2.9V18a.5.5 0 0 0 1 0v-2.1A1.5 1.5 0 0 0 12 13Z"/></svg>';

	var MESSAGE =
		'Utilizamos cookies para garantizar el <strong class="ss-cookie-hl">correcto funcionamiento</strong> del sitio, <strong class="ss-cookie-hl">mejorar tu experiencia</strong> y <strong class="ss-cookie-hl">analizar el uso</strong> de nuestra plataforma. Puedes aceptar todas las cookies o configurar tus preferencias.';

	function cfg() {
		return window.ssCookies || {};
	}

	function setButton(btn, html) {
		if (!btn) return;
		if (btn.getAttribute('data-ss-cookie-label') === html) return;
		btn.innerHTML = html;
		btn.setAttribute('data-ss-cookie-label', html);
	}

	function hrefOf(root, selector, fallback) {
		var a = root.querySelector(selector);
		if (!a) return fallback;
		var href = (a.getAttribute('href') || '').trim();
		if (!href || href === '#' || href.indexOf('{') !== -1) return fallback;
		return href;
	}

	function enhance(banner) {
		if (!banner) return;

		var title = banner.querySelector('.cmplz-title');
		if (title && title.textContent.trim() !== 'Aceptar cookies') {
			title.textContent = 'Aceptar cookies';
		}

		var msg = banner.querySelector('.cmplz-message');
		if (msg && !msg.querySelector('.ss-cookie-hl')) {
			msg.innerHTML = MESSAGE;
		}

		var img = banner.querySelector('.cmplz-logo img');
		if (img && cfg().shield) {
			img.setAttribute('src', cfg().shield);
			img.removeAttribute('srcset');
			img.removeAttribute('data-src');
			img.removeAttribute('data-srcset');
			img.setAttribute('data-no-lazy', '1');
			img.setAttribute('data-lazyloaded', '0');
			img.alt = 'Privacidad';
		}

		setButton(banner.querySelector('.cmplz-view-preferences'), ICO_SLIDERS + ' Configurar preferencias');
		setButton(banner.querySelector('.cmplz-save-preferences'), ICO_SLIDERS + ' Guardar preferencias');
		setButton(banner.querySelector('.cmplz-accept'), ICO_CHECK + ' Aceptar todas');

		var deny = banner.querySelector('.cmplz-deny');
		if (deny) deny.style.setProperty('display', 'none', 'important');
		var tcf = banner.querySelector('.cmplz-btn.cmplz-manage-options');
		if (tcf) tcf.style.setProperty('display', 'none', 'important');

		var docs = banner.querySelector('.cmplz-documents');
		if (docs && !docs.querySelector('.ss-cookie-foot')) {
			var privacy = hrefOf(banner, 'a.privacy-statement', cfg().privacy || '/politica-privacidad/');
			var cookies = hrefOf(banner, 'a.cookie-statement', cfg().cookies || '/politica-de-cookies/');
			var foot = document.createElement('div');
			foot.className = 'ss-cookie-foot';
			foot.innerHTML =
				ICO_LOCK +
				'<div>Puedes modificar tus preferencias en cualquier momento. Consulta nuestra ' +
				'<a class="privacy-statement" href="' +
				privacy +
				'">Política de Privacidad</a> y ' +
				'<a class="cookie-statement" href="' +
				cookies +
				'">Política de Cookies</a>.</div>';
			docs.appendChild(foot);
		}

		if (!banner.getAttribute('data-ss-cookie-bound')) {
			banner.setAttribute('data-ss-cookie-bound', '1');
			banner.addEventListener('click', function (e) {
				if (e.target.closest('.cmplz-view-preferences')) {
					banner.classList.add('ss-cookie-prefs-open');
				}
				if (e.target.closest('.cmplz-save-preferences') || e.target.closest('.cmplz-accept') || e.target.closest('.cmplz-deny')) {
					banner.classList.remove('ss-cookie-prefs-open');
				}
			});
		}
	}

	function scan() {
		document.querySelectorAll('#cmplz-cookiebanner-container .cmplz-cookiebanner').forEach(enhance);
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		scan();
		document.addEventListener('cmplz_cookie_warning_loaded', scan);
		document.addEventListener('cmplz_fire_categories', scan);
		document.addEventListener('cmplz_revoke', scan);

		var root = document.getElementById('cmplz-cookiebanner-container');
		if (root && window.MutationObserver) {
			new MutationObserver(scan).observe(root, { childList: true, subtree: true });
		}
	});
})();
