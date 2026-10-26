from pathlib import Path

path = Path(r"C:\Proyectos\sorteoseguro\mu-plugins\sorteoseguro-packs-lottery.php")
text = path.read_text(encoding="utf-8")
text = text.replace("const VERSION        = '1.2.0';", "const VERSION        = '1.2.1';")

start = text.find("\tprivate static function front_css(): string {")
end = text.find("\tprivate static function front_js(): string {")
if start < 0 or end < 0:
    raise SystemExit(f"markers not found start={start} end={end}")

new_css_fn = r'''	private static function front_css(): string {
		return <<<'CSS'
/* Packs Lottery UI v1.2.1 — colores por pack */
:root {
	--ss-pack-ink: #141414;
	--ss-pack-muted: #6b7280;
	--ss-pack-line: #e5e7eb;
	--ss-pack-accent: #7f54b3;
	--ss-pack-accent-dark: #6841a0;
	--ss-pack-glow: rgba(127, 84, 179, 0.16);
}

.lty-lottery-ticket-container,
.lty-lottery-ticket-header,
.lty-lottery-ticket-panel,
.lty-lottery-ticket-lucky-dip-container,
.lty-lottery-ticket-wrapper,
.lty-ticket-number-wrapper,
.quantity-selector,
.buy-section .single_add_to_cart_button,
.buy-section .lty_manual_add_to_cart,
.purchase-action .buy-section,
.purchase-action .price-info,
form.form-buy-ticket > img,
.banner_packs,
#lottery-ticket-random,
input[name="search-ticket"] {
	display: none !important;
	visibility: hidden !important;
	height: 0 !important;
	overflow: hidden !important;
	margin: 0 !important;
	padding: 0 !important;
	border: 0 !important;
}

.ss-packs-wrap {
	display: block !important;
	margin: 8px 0 20px;
	padding: 0;
	border: 0;
	background: transparent;
	font-family: "Montserrat", "Poppins", "Segoe UI", sans-serif;
}
.ss-packs-wrap .ss-packs-title {
	margin: 0 0 16px;
	text-align: center;
	font-size: clamp(1.05rem, 2.6vw, 1.35rem);
	font-weight: 800;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: var(--ss-pack-ink);
	line-height: 1.25;
}
.ss-packs-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}
@media (max-width: 380px) {
	.ss-packs-grid { grid-template-columns: 1fr; }
}

.ss-pack-card.ss-pack--1x1 {
	--ss-pack-accent: #374151;
	--ss-pack-accent-dark: #1f2937;
	--ss-pack-glow: rgba(55, 65, 81, 0.14);
}
.ss-pack-card.ss-pack--3x2 {
	--ss-pack-accent: #e91e8c;
	--ss-pack-accent-dark: #c2185b;
	--ss-pack-glow: rgba(233, 30, 140, 0.18);
}
.ss-pack-card.ss-pack--5x3 {
	--ss-pack-accent: #9b7ede;
	--ss-pack-accent-dark: #7c5cbf;
	--ss-pack-glow: rgba(155, 126, 222, 0.2);
}
.ss-pack-card.ss-pack--10x5 {
	--ss-pack-accent: #c9a227;
	--ss-pack-accent-dark: #a8841a;
	--ss-pack-glow: rgba(201, 162, 39, 0.22);
}
.ss-pack-card.ss-pack--20x8 {
	--ss-pack-accent: #141414;
	--ss-pack-accent-dark: #000000;
	--ss-pack-glow: rgba(0, 0, 0, 0.18);
}

.ss-pack-card {
	position: relative;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: flex-start;
	gap: 0;
	width: 100%;
	min-height: 100%;
	padding: 22px 12px 14px;
	margin: 0;
	border: 1.5px solid var(--ss-pack-line) !important;
	border-radius: 14px !important;
	background: #fff !important;
	background-color: #fff !important;
	background-image: none !important;
	color: var(--ss-pack-ink) !important;
	text-align: center;
	cursor: pointer;
	transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
	box-shadow: 0 1px 2px rgba(20,20,20,.04) !important;
	appearance: none;
	-webkit-appearance: none;
	outline: none !important;
	text-decoration: none !important;
	filter: none !important;
}
.ss-pack-card:hover,
.ss-pack-card:focus,
.ss-pack-card:focus-visible,
.ss-pack-card:active,
.purchase-panel .ss-pack-card:hover,
.form-buy-ticket .ss-pack-card:hover {
	background: #fff !important;
	background-color: #fff !important;
	background-image: none !important;
	color: var(--ss-pack-ink) !important;
	border-color: var(--ss-pack-accent) !important;
	transform: translateY(-3px);
	box-shadow: 0 12px 28px var(--ss-pack-glow) !important;
	filter: none !important;
	outline: none !important;
}
.ss-pack-card:hover .ss-pack-label,
.ss-pack-card:focus .ss-pack-label {
	color: var(--ss-pack-accent-dark) !important;
}
.ss-pack-card:hover .ss-pack-price,
.ss-pack-card:hover .ss-pack-meta,
.ss-pack-card:hover .ss-pack-save,
.ss-pack-card:focus .ss-pack-price,
.ss-pack-card:focus .ss-pack-meta,
.ss-pack-card:focus .ss-pack-save {
	color: var(--ss-pack-ink) !important;
}
.ss-pack-card:hover .ss-pack-list,
.ss-pack-card:focus .ss-pack-list {
	color: #9ca3af !important;
}
.ss-pack-card.is-selected,
.ss-pack-card[aria-pressed="true"] {
	background: #fff !important;
	background-color: #fff !important;
	border-color: var(--ss-pack-accent) !important;
	border-width: 2px !important;
	box-shadow: 0 0 0 3px var(--ss-pack-glow), 0 12px 28px var(--ss-pack-glow) !important;
	transform: translateY(-2px);
}
.ss-pack-card:disabled,
.ss-pack-card[aria-disabled="true"] {
	opacity: .75;
	cursor: wait;
	transform: none !important;
}
.ss-pack-card.is-best {
	border-color: var(--ss-pack-accent) !important;
	box-shadow: 0 4px 16px var(--ss-pack-glow) !important;
}

.ss-pack-badge {
	position: absolute;
	top: -11px;
	left: 50%;
	transform: translateX(-50%);
	padding: 4px 10px;
	border-radius: 999px;
	background: var(--ss-pack-accent) !important;
	color: #fff !important;
	font-size: 0.65rem;
	font-weight: 800;
	letter-spacing: .04em;
	white-space: nowrap;
	line-height: 1.2;
	box-shadow: 0 4px 10px var(--ss-pack-glow);
}

.ss-pack-label {
	margin: 4px 0 6px;
	font-size: clamp(1.35rem, 4vw, 1.75rem);
	font-weight: 800;
	letter-spacing: 0.02em;
	color: var(--ss-pack-accent) !important;
	line-height: 1.1;
	text-transform: uppercase;
}
.ss-pack-meta {
	margin: 0 0 10px;
	font-size: 0.72rem;
	font-weight: 600;
	color: var(--ss-pack-ink) !important;
	line-height: 1.3;
}
.ss-pack-list {
	display: block;
	margin: 0 0 2px;
	font-size: 0.8rem;
	font-weight: 600;
	color: #9ca3af !important;
	text-decoration: line-through;
}
.ss-pack-price {
	display: block;
	margin: 0 0 4px;
	font-size: clamp(1.25rem, 3.5vw, 1.55rem);
	font-weight: 800;
	color: var(--ss-pack-ink) !important;
	line-height: 1.1;
}
.ss-pack-save {
	display: block;
	margin: 0 0 12px;
	font-size: 0.82rem;
	font-weight: 800;
	color: var(--ss-pack-ink) !important;
}
.ss-pack-cta {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: 100%;
	margin-top: auto;
	padding: 10px 12px;
	border: 1.5px solid var(--ss-pack-accent) !important;
	border-radius: 999px !important;
	background: #fff !important;
	background-image: none !important;
	color: var(--ss-pack-accent) !important;
	font-size: 0.72rem;
	font-weight: 800;
	letter-spacing: .06em;
	text-transform: uppercase;
	transition: background .15s ease, color .15s ease;
	box-shadow: none !important;
}
.ss-pack-card:hover .ss-pack-cta,
.ss-pack-card:focus .ss-pack-cta,
.ss-pack-card.is-selected .ss-pack-cta,
.ss-pack-card[aria-pressed="true"] .ss-pack-cta,
.ss-pack-card.is-loading .ss-pack-cta {
	background: var(--ss-pack-accent) !important;
	background-image: none !important;
	color: #fff !important;
	border-color: var(--ss-pack-accent) !important;
}

.ss-packs-hint {
	display: flex;
	align-items: flex-start;
	justify-content: center;
	gap: 8px;
	margin: 14px 0 0;
	padding: 0 4px;
	text-align: center;
	font-size: 0.78rem;
	line-height: 1.4;
	color: var(--ss-pack-muted);
}
.ss-packs-hint-icon {
	flex: 0 0 auto;
	width: 16px;
	height: 16px;
	margin-top: 1px;
	border-radius: 50%;
	border: 1.5px solid #9b7ede;
	color: #9b7ede;
	font-size: 0.65rem;
	font-weight: 800;
	line-height: 13px;
	text-align: center;
	font-style: normal;
}
.ss-packs-status {
	margin-top: 10px;
	text-align: center;
	font-size: 0.88rem;
	font-weight: 600;
	color: var(--ss-pack-ink);
	min-height: 1.2em;
}
.ss-packs-status.is-error { color: #b91c1c; }
CSS;
	}

'''

text = text[:start] + new_css_fn + text[end:]

old_js_helpers = """	function bestCampaignId(campaigns) {
		var bestId = null;
		var bestSave = -1;
		campaigns.forEach(function (c) {
			var save = Number(c.savings || 0);
			if (save > bestSave) {
				bestSave = save;
				bestId = c.id;
			}
		});
		return bestId;
	}"""

new_js_helpers = """	function packThemeClass(c) {
		var buy = Number(c.buy || 0);
		var pay = Number(c.pay || 0);
		if (buy === 1 && pay === 1) return 'ss-pack--1x1';
		if (buy === 3 && pay === 2) return 'ss-pack--3x2';
		if (buy === 5 && pay === 3) return 'ss-pack--5x3';
		if (buy === 10 && pay === 5) return 'ss-pack--10x5';
		if (buy === 20 && pay === 8) return 'ss-pack--20x8';
		return 'ss-pack--1x1';
	}

	function isMasElegido(c) {
		return Number(c.buy) === 5 && Number(c.pay) === 3;
	}"""

if old_js_helpers not in text:
    raise SystemExit("bestCampaignId block not found")
text = text.replace(old_js_helpers, new_js_helpers)

old_render = """		var grid = wrap.querySelector('.ss-packs-grid');
		var bestId = bestCampaignId(cfg.campaigns);

		cfg.campaigns.forEach(function (c) {
			var btn = document.createElement('div');
			btn.setAttribute('role', 'button');
			btn.setAttribute('tabindex', '0');
			btn.className = 'ss-pack-card';
			btn.setAttribute('data-campaign-id', c.id);
			btn.setAttribute('data-buy', c.buy);
			btn.setAttribute('aria-pressed', 'false');

			var badgeText = (c.badge && String(c.badge).trim()) ? String(c.badge).trim() : ((bestId !== null && c.id === bestId && c.buy > 1) ? cfg.i18n.bestBadge : '');
			if (badgeText) btn.classList.add('is-best');"""

new_render = """		var grid = wrap.querySelector('.ss-packs-grid');

		cfg.campaigns.forEach(function (c) {
			var btn = document.createElement('div');
			btn.setAttribute('role', 'button');
			btn.setAttribute('tabindex', '0');
			btn.className = 'ss-pack-card ' + packThemeClass(c);
			btn.setAttribute('data-campaign-id', c.id);
			btn.setAttribute('data-buy', c.buy);
			btn.setAttribute('data-pay', c.pay);
			btn.setAttribute('aria-pressed', 'false');

			var badgeText = '';
			if (c.badge && String(c.badge).trim()) {
				badgeText = String(c.badge).trim();
			} else if (isMasElegido(c)) {
				badgeText = cfg.i18n.bestBadge;
			}
			if (badgeText) btn.classList.add('is-best');"""

if old_render not in text:
    raise SystemExit("render block not found")
text = text.replace(old_render, new_render)

path.write_text(text, encoding="utf-8")
print("patched ok", path.stat().st_size)
