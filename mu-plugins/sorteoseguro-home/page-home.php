<?php
/**
 * Plantilla Home v2 – página ID 74938
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!have_posts()) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}
the_post();

$contests = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::get_contest_cards() : [];
$packs    = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::get_pack_cards() : [];
$promo    = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::promo_config() : [];
$wins     = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::testimonials() : [];
$partners = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::partners() : [];
$faqs     = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::faq_teaser(5) : [];

$faq_url = get_permalink(15) ?: (class_exists('SorteoSeguro_Chrome') ? SorteoSeguro_Chrome::page_url('preguntas_frecuentes') : home_url('/preguntas_frecuentes/'));
$first_url = $contests[0]['url'] ?? '#ss-home-concursos';
$packs_url = class_exists('SorteoSeguro_Home')
	? SorteoSeguro_Home::pack_cta_url()
	: 'https://sorteoseguro.cl/producto/jeep-avenger/';
$shop_url = function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : home_url('/tienda/');
if ($shop_url === '') {
	$shop_url = home_url('/tienda/');
}
$how_assets = content_url('mu-plugins/sorteoseguro-home/assets');
$hero_base = $how_assets . '/hero';
$hero_slides = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::hero_slides($contests) : [];

$ico = [
	'arrow'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.3 6.7 10.7 5.3 17.4 12l-6.7 6.7-1.4-1.4L14.6 12z"/></svg>',
	'nav_arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.1 3.6 10.3 1.8 20.2 12 10.3 22.2 8.1 20.4 15.8 12z"/></svg>',
	'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5.2 3.4 9.8 8 11 4.6-1.2 8-5.8 8-11V5z"/></svg>',
	'card'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4H4V6h16z"/></svg>',
	'ticket' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22 10V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4a2 2 0 1 1 0 4v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 1 1 0-4z"/></svg>',
	'live'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 5a7 7 0 1 0 7 7 7 7 0 0 0-7-7zm0 10a3 3 0 1 1 3-3 3 3 0 0 1-3 3z"/></svg>',
	'clock'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 11H7v-2h4V6h2z"/></svg>',
	'pin'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>',
	'gift'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 7h-1.3A3.2 3.2 0 0 0 16 3c-1.7 0-2.7 1.1-4 2.8C10.7 4.1 9.7 3 8 3a3.2 3.2 0 0 0-2.7 4H4a1 1 0 0 0-1 1v3h9V8h2v3h9V8a1 1 0 0 0-1-1zM8 5c.7 0 1.7.9 2.7 2H8.2A1.2 1.2 0 0 1 8 5zm8 0a1.2 1.2 0 0 1-.2 2h-2.5C14.3 5.9 15.3 5 16 5zM3 20a1 1 0 0 0 1 1h7v-8H3zm11 1h7a1 1 0 0 0 1-1v-7h-8z"/></svg>',
	'home'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>',
	'bed'    => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" d="M3 18v-5.5A2.5 2.5 0 0 1 5.5 10H21v8M3 18h18M4 14h16M7 10V7.6A1.6 1.6 0 0 1 8.6 6h4.2A1.6 1.6 0 0 1 14.4 7.6V10"/></svg>',
	'bath'   => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M4 13h16v3.2A2.8 2.8 0 0 1 17.2 19H6.8A2.8 2.8 0 0 1 4 16.2V13zM6 13V8.2A2.2 2.2 0 0 1 8.2 6H10"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M7 19v1.4M17 19v1.4"/></svg>',
	'ruler'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="8" width="17" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M7 8v3M11 8v2M15 8v3M19 8v2"/></svg>',
	'trees'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 20v-5M6.5 20h11M12 4l4.2 7.5H7.8L12 4zM16.2 9.8 20 15h-5"/></svg>',
	'land'   => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="m3 16 5.5-7 4 5 3-3.5L21 16H3z"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M4 19h16"/></svg>',
	'play'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>',
	'copy'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11z"/></svg>',
	'bolt'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13 2 4.5 13.2h6L9.2 22 19.5 9.8h-6.2L13 2z"/></svg>',
	'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.8l-.3-.3a6.5 6.5 0 1 0-.7.7l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>',
	'trophy' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18 2H6v3H3v2a5 5 0 0 0 5 5h.2A5 5 0 0 0 11 14.9V17H8v2h8v-2h-3v-2.1A5 5 0 0 0 15.8 12H16a5 5 0 0 0 5-5V5h-3V2z"/></svg>',
	'doc'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm1 7V3.5L18.5 9z"/></svg>',
	'heart'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 21s-7.2-4.4-9.5-8.2A5.6 5.6 0 0 1 12 5.2a5.6 5.6 0 0 1 9.5 7.6C19.2 16.6 12 21 12 21z"/></svg>',
	'users'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 0a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 8 11zm0 2c-2.7 0-8 1.4-8 4v2h10v-2c0-1.2.6-2.2 1.6-3A10.2 10.2 0 0 0 8 13zm8 0c-.4 0-.8 0-1.2.1A5.5 5.5 0 0 1 18 18v2h6v-2c0-2.6-5.3-4-8-4z"/></svg>',
	'check'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.2 16.6 4.8 12.2l1.4-1.4 3 3 8.6-8.6 1.4 1.4z"/></svg>',
	'shield_check' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.1 2.1 4.3-4.3"/></svg>',
	'lock' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.2" fill="currentColor"/></svg>',
	'ticket_outline' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M3 9.5V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2.5a2 2 0 1 0 0 5V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2.5a2 2 0 1 0 0-5z"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-dasharray="2.2 2.4" d="M12 6.5v11"/></svg>',
	'broadcast' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="1.8" fill="currentColor"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M8.2 8.2a5.4 5.4 0 0 0 0 7.6M15.8 8.2a5.4 5.4 0 0 1 0 7.6M5.5 5.5a9.2 9.2 0 0 0 0 13M18.5 5.5a9.2 9.2 0 0 1 0 13"/></svg>',
	'car' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M3.5 14.5 5.2 9.2A2 2 0 0 1 7.1 8h9.8a2 2 0 0 1 1.9 1.2l1.7 5.3"/><path stroke="currentColor" stroke-width="1.8" d="M3.5 14.5h17v3.2a1.3 1.3 0 0 1-1.3 1.3h-1.1a1.3 1.3 0 0 1-1.3-1.3v-.5H7.2v.5a1.3 1.3 0 0 1-1.3 1.3H4.8a1.3 1.3 0 0 1-1.3-1.3v-3.2z"/><circle cx="7.2" cy="14.5" r="1.15" fill="currentColor"/><circle cx="16.8" cy="14.5" r="1.15" fill="currentColor"/></svg>',
	'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 3.2 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 15.6 7.2 18.1l.9-5.4-3.9-3.8 5.4-.8z"/></svg>',
	'arrow_curve' => '<svg viewBox="0 0 64 36" fill="none" aria-hidden="true"><path d="M4 8c12 2 24 18 42 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M40 18l8 8 8-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	'ticket_pack' => '<svg viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M4.5 9.2c0-1.2.9-2.1 2.1-2.1h15.2c1.2 0 2.1.9 2.1 2.1v1.3a2 2 0 0 1 0 3.8v1.4a2 2 0 0 1 0 3.8v1.3c0 1.2-.9 2.1-2.1 2.1H6.6c-1.2 0-2.1-.9-2.1-2.1v-1.3a2 2 0 0 1 0-3.8v-1.4a2 2 0 0 1 0-3.8V9.2zm8.3 1.2v11.2" opacity=".95"/><path fill="currentColor" d="M7 11.2h1.4v1.4H7zm0 3h1.4v1.4H7zm0 3h1.4v1.4H7z" opacity=".55"/></svg>',
	'quote' => '<svg viewBox="0 0 32 24" aria-hidden="true"><path fill="currentColor" d="M0 24V13.1C0 5.9 3.9 1.5 11.2 0l1.3 2.7C8.2 4.1 6 6.8 5.7 10.5H12V24H0zm18 0V13.1C18 5.9 21.9 1.5 29.2 0L30.5 2.7C26.2 4.1 24 6.8 23.7 10.5H30V24H18z"/></svg>',
	'dash_arrow' => '<svg viewBox="0 0 72 24" fill="none" aria-hidden="true"><rect x="0" y="10" width="9" height="4" rx="1.5" fill="currentColor"/><rect x="14" y="10" width="9" height="4" rx="1.5" fill="currentColor"/><rect x="28" y="10" width="9" height="4" rx="1.5" fill="currentColor"/><rect x="42" y="10" width="9" height="4" rx="1.5" fill="currentColor"/><path fill="currentColor" d="M56 5.2 68 12 56 18.8V5.2z"/></svg>',
	'tl_doc' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7 3.5h7.2L17.5 6.8V20a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M14 3.5V7h3.5M8.5 10.5h5M8.5 13.5h5"/><circle cx="16.2" cy="16.2" r="3.2" stroke="currentColor" stroke-width="1.6"/><path stroke="currentColor" stroke-width="1.5" stroke-linecap="round" d="M16.2 14.7v3M14.7 16.2h3"/></svg>',
	'tl_card' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M2.5 9.5h19M6.5 14h4"/><rect x="14.2" y="13.2" width="5.6" height="5.2" rx="1" stroke="currentColor" stroke-width="1.5"/><path stroke="currentColor" stroke-width="1.5" stroke-linecap="round" d="M15.6 13.2v-1.1a1.4 1.4 0 0 1 2.8 0v1.1"/></svg>',
	'tl_ticket' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M3.5 8.2V7a1.8 1.8 0 0 1 1.8-1.8h13.4A1.8 1.8 0 0 1 20.5 7v1.2a1.7 1.7 0 1 0 0 3.4V13a1.7 1.7 0 1 0 0 3.4V17a1.8 1.8 0 0 1-1.8 1.8H5.3A1.8 1.8 0 0 1 3.5 17v-1.2a1.7 1.7 0 1 0 0-3.4V11.6a1.7 1.7 0 1 0 0-3.4z"/><path fill="currentColor" d="m12 9.2 0.85 1.75 1.9.28-1.38 1.35.33 1.92L12 13.6l-1.7.9.33-1.92-1.38-1.35 1.9-.28z"/></svg>',
	'tl_live' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M12 4.5v11"/><circle cx="12" cy="16.8" r="1.6" fill="currentColor"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M8.2 7.2a5.2 5.2 0 0 0 0 7.6M15.8 7.2a5.2 5.2 0 0 1 0 7.6M5.4 5a8.5 8.5 0 0 0 0 12M18.6 5a8.5 8.5 0 0 1 0 12"/></svg>',
	'tl_shield' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
	'cta_digital' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="3.5" width="16" height="17" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M8 8.2h8M8 12h5.5M8 15.8h3"/><circle cx="15.8" cy="15.8" r="1.1" fill="currentColor"/></svg>',
	'cta_public' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7 4.5h7.5L18.5 8v11.5A1.5 1.5 0 0 1 17 21H7a1.5 1.5 0 0 1-1.5-1.5v-14A1.5 1.5 0 0 1 7 4.5z"/><path stroke="currentColor" stroke-width="1.6" stroke-linecap="round" d="M14.2 4.5V8H18M8.5 12h7M8.5 15.2h7M8.5 18.2h4.5"/></svg>',
	'cta_ticket_bg' => '<svg viewBox="0 0 120 160" fill="none" aria-hidden="true"><path fill="currentColor" d="M28 8h64a12 12 0 0 1 12 12v18a14 14 0 1 0 0 28v18a14 14 0 1 0 0 28v18a12 12 0 0 1-12 12H28a12 12 0 0 1-12-12v-18a14 14 0 1 0 0-28V66a14 14 0 1 0 0-28V20A12 12 0 0 1 28 8z" opacity=".95"/><path stroke="#fff" stroke-opacity=".25" stroke-width="3" stroke-dasharray="6 8" d="M60 28v104"/></svg>',
	'cta_note_arrow' => '<svg viewBox="0 0 64 36" fill="none" aria-hidden="true"><path d="M8 28c6-2 14-10 22-14 8-4 18-6 28-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M48 6c4 2 8 4 10 8-4 0-8 0-12-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
];

get_header();
?>
<main class="ss-home">

	<!-- Hero slider -->
	<section class="ss-home-hero" aria-label="Concursos destacados" data-ss-hero-slider data-ss-hero-interval="5000">
		<div class="ss-home-hero__viewport">
			<?php foreach ($hero_slides as $i => $slide) :
				$is_active = $i === 0;
				$theme = sanitize_html_class((string) ($slide['theme'] ?? 'studio'));
				$key = sanitize_html_class((string) ($slide['key'] ?? 'slide'));
				$badge_icon = (string) ($slide['badge_icon'] ?? 'dot');
				$title_em = (string) ($slide['title_em'] ?? '');
				$title_tag = $i === 0 ? 'h1' : 'h2';
				$cta_url = (string) ($slide['url'] ?? '#ss-home-concursos');
				?>
				<article
					class="ss-home-hero-slide is-theme-<?php echo esc_attr($theme); ?> is-key-<?php echo esc_attr($key); ?><?php echo $is_active ? ' is-active' : ''; ?>"
					data-ss-hero-slide="<?php echo (int) $i; ?>"
					aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>"
					aria-label="<?php echo esc_attr((string) ($slide['aria'] ?? $slide['title'] ?? '')); ?>"
				>
					<picture class="ss-home-hero-slide__media" aria-hidden="true">
						<source media="(max-width: 760px)" srcset="<?php echo esc_url($hero_base . '/' . $slide['img_mobile']); ?>">
						<img
							class="ss-home-hero-slide__bg"
							src="<?php echo esc_url($hero_base . '/' . $slide['img_pc']); ?>"
							alt=""
							width="1920"
							height="707"
							<?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
							decoding="<?php echo $i === 0 ? 'sync' : 'async'; ?>"
						>
					</picture>
					<span class="ss-home-hero-slide__scrim" aria-hidden="true"></span>
					<div class="ss-home__shell ss-home-hero-slide__inner">
						<div class="ss-home-hero-slide__copy">
							<span class="ss-home-hero__badge">
								<?php if ($badge_icon === 'dot') : ?>
									<span class="ss-home-hero__badge-dot" aria-hidden="true"></span>
								<?php elseif (!empty($ico[$badge_icon])) : ?>
									<span class="ss-home-hero__badge-ico" aria-hidden="true"><?php echo $ico[$badge_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php endif; ?>
								<?php echo esc_html((string) $slide['badge']); ?>
							</span>

							<<?php echo $title_tag; ?> class="ss-home-hero__title">
								<?php if (!empty($slide['title_lines']) && is_array($slide['title_lines'])) : ?>
									<?php foreach ($slide['title_lines'] as $li => $line) :
										$line_text = (string) ($line['text'] ?? '');
										if ($li > 0) {
											echo '<br>';
										}
										if (!empty($line['em'])) {
											echo '<em>' . esc_html($line_text) . '</em>';
										} else {
											echo esc_html($line_text);
										}
									endforeach; ?>
								<?php else : ?>
									<?php echo esc_html((string) $slide['title']); ?>
									<?php if ($title_em !== '') : ?>
										<em><?php echo esc_html($title_em); ?></em>
									<?php endif; ?>
									<?php if (!empty($slide['title_after'])) : ?>
										<?php echo esc_html((string) $slide['title_after']); ?>
									<?php endif; ?>
								<?php endif; ?>
							</<?php echo $title_tag; ?>>

							<?php if (!empty($slide['kicker'])) : ?>
								<p class="ss-home-hero__kicker"><?php echo esc_html((string) $slide['kicker']); ?></p>
							<?php endif; ?>

							<?php if (!empty($slide['meta'])) :
								$meta_icon = (string) ($slide['meta_icon'] ?? '');
								?>
								<p class="ss-home-hero__meta">
									<?php if ($meta_icon !== '' && !empty($ico[$meta_icon])) : ?>
										<span aria-hidden="true"><?php echo $ico[$meta_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php endif; ?>
									<?php echo esc_html((string) $slide['meta']); ?>
								</p>
							<?php endif; ?>

							<?php if (!empty($slide['sub'])) : ?>
								<p class="ss-home-hero__sub"><?php echo nl2br(esc_html((string) $slide['sub']), false); ?></p>
							<?php endif; ?>
							<?php if (!empty($slide['sub_line2']) || !empty($slide['sub_em']) || !empty($slide['sub_after'])) : ?>
								<p class="ss-home-hero__sub">
									<?php echo nl2br(esc_html((string) ($slide['sub_line2'] ?? '')), false); ?>
									<?php if (!empty($slide['sub_em'])) : ?>
										<strong><?php echo esc_html((string) $slide['sub_em']); ?></strong>
									<?php endif; ?>
									<?php echo nl2br(esc_html((string) ($slide['sub_after'] ?? '')), false); ?>
								</p>
							<?php endif; ?>

							<?php if (!empty($slide['specs'])) : ?>
								<ul class="ss-home-hero__specs">
									<?php foreach ($slide['specs'] as $spec) :
										$s_icon = (string) ($spec['icon'] ?? '');
										?>
										<li class="ss-home-hero__spec">
											<?php if ($s_icon !== '' && !empty($ico[$s_icon])) : ?>
												<span class="ss-home-hero__spec-ico" aria-hidden="true"><?php echo $ico[$s_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<?php endif; ?>
											<?php if (!empty($spec['value'])) : ?>
												<span class="ss-home-hero__spec-copy">
													<strong><?php echo esc_html((string) $spec['value']); ?></strong>
													<small><?php echo esc_html((string) ($spec['label'] ?? '')); ?></small>
												</span>
											<?php else : ?>
												<?php echo esc_html((string) ($spec['label'] ?? '')); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<div class="ss-home-hero__foot">
							<?php if (!empty($slide['show_jumps'])) : ?>
								<div class="ss-home-hero__jumps">
									<?php foreach ($hero_slides as $ji => $jump) :
										if (empty($jump['chip_title'])) {
											continue;
										}
										$j_icon = (string) ($jump['chip_icon'] ?? '');
										?>
										<button type="button" class="ss-home-hero__jump" data-ss-hero-goto="<?php echo (int) $ji; ?>">
											<?php if ($j_icon !== '' && !empty($ico[$j_icon])) : ?>
												<span class="ss-home-hero__jump-ico" aria-hidden="true"><?php echo $ico[$j_icon]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<?php endif; ?>
											<span>
												<strong><?php echo esc_html((string) $jump['chip_title']); ?></strong>
												<small><?php echo nl2br(esc_html((string) ($jump['chip_meta'] ?? '')), false); ?></small>
											</span>
										</button>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<div class="ss-home-hero__ctas">
								<?php if (!empty($slide['price'])) : ?>
									<span class="ss-home-hero__ticket">
										<span class="ss-home-hero__ticket-star" aria-hidden="true"><?php echo $ico['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span>
											<?php echo esc_html((string) ($slide['price_label'] ?? 'DigiTicket')); ?>
											<strong><?php echo esc_html((string) $slide['price']); ?></strong>
										</span>
									</span>
								<?php endif; ?>
								<a class="ss-home-btn ss-home-btn--primary<?php echo !empty($slide['price']) ? ' ss-home-btn--hero-dark' : ''; ?>" href="<?php echo esc_url($cta_url); ?>">
									<?php echo esc_html((string) ($slide['cta'] ?? 'Participar ahora')); ?>
									<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								</a>
							</div>
							</div>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if (count($hero_slides) > 1) : ?>
			<div class="ss-home-hero__nav">
				<div class="ss-home-hero__dots" role="tablist" aria-label="Banners del inicio">
					<?php foreach ($hero_slides as $i => $slide) : ?>
						<button
							type="button"
							class="ss-home-hero__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
							data-ss-hero-dot="<?php echo (int) $i; ?>"
							role="tab"
							aria-label="<?php echo esc_attr((string) ($slide['dot_label'] ?? ('Banner ' . ($i + 1)))); ?>"
							aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						></button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<!-- Trust strip -->
	<section class="ss-home-trust" aria-label="Por qué confiar">
		<div class="ss-home__shell">
			<div class="ss-home-trust__panel">
				<article class="ss-home-trust__item">
					<span class="ss-home-trust__ico" aria-hidden="true"><?php echo $ico['shield_check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="ss-home-trust__copy">
						<strong>Bases ante Notario</strong>
						<span>Concursos con bases legales protocolizadas.</span>
					</div>
				</article>
				<article class="ss-home-trust__item">
					<span class="ss-home-trust__ico" aria-hidden="true"><?php echo $ico['lock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="ss-home-trust__copy">
						<strong>Pago 100% seguro</strong>
						<span>Pasarelas oficiales y certificadas en Chile.</span>
					</div>
				</article>
				<article class="ss-home-trust__item">
					<span class="ss-home-trust__ico" aria-hidden="true"><?php echo $ico['ticket_outline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="ss-home-trust__copy">
						<strong>DigiTicket inmediato</strong>
						<span>Recibes tu ticket digital numerado al instante en tu email.</span>
					</div>
				</article>
				<article class="ss-home-trust__item">
					<span class="ss-home-trust__ico" aria-hidden="true"><?php echo $ico['broadcast']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="ss-home-trust__copy">
						<strong>Resultados verificables</strong>
						<span>Sorteos transmitidos en vivo y resultados públicos.</span>
					</div>
				</article>
			</div>
		</div>
	</section>

	<!-- Concursos -->
	<section class="ss-home-contests" id="ss-home-concursos" aria-labelledby="ss-home-contests-title">
		<div class="ss-home__shell">
			<header class="ss-home-contests__head">
				<div class="ss-home-contests__titles">
					<h2 id="ss-home-contests-title">Elige tu <em>próximo premio</em></h2>
					<p>Concursos activos con premios increíbles.</p>
				</div>
				<a class="ss-home-contests__all" href="<?php echo esc_url($shop_url); ?>">
					Ver todos los concursos
					<span class="ss-home-contests__all-ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</header>

			<?php if (!$contests) : ?>
				<p class="ss-home-empty">Pronto publicaremos nuevos concursos.</p>
			<?php else : ?>
				<div class="ss-home-contests__carousel" data-ss-carousel data-ss-carousel-interval="5000">
					<div class="ss-home-contests__viewport">
						<div class="ss-home-contests__track">
							<?php foreach ($contests as $c) :
								$end_attr = '';
								if (!empty($c['end_countdown'])) {
									$end_attr = (string) $c['end_countdown'];
								} elseif (!empty($c['end_ts'])) {
									$end_attr = gmdate('c', (int) $c['end_ts']);
								}
								$is_vehicle = ($c['category_slug'] ?? '') === 'vehicle';
								$meta_ico = $is_vehicle ? $ico['car'] : $ico['pin'];
								$price_txt = '$' . number_format((float) ($c['price_raw'] ?? 0), 0, ',', '.');
								$sold_txt = number_format_i18n((int) $c['sold']);
								$max_txt = number_format_i18n((int) $c['max']);
								$pct = rtrim(rtrim(number_format((float) $c['progress'], 1, '.', ''), '0'), '.');
								?>
								<article class="ss-home-contest">
									<div class="ss-home-contest__media">
										<a class="ss-home-contest__img" href="<?php echo esc_url($c['url']); ?>">
											<?php if (!empty($c['image'])) : ?>
												<img src="<?php echo esc_url($c['image']); ?>" alt="<?php echo esc_attr($c['title']); ?>" loading="lazy" decoding="async">
											<?php else : ?>
												<span class="ss-home-contest__ph" aria-hidden="true"></span>
											<?php endif; ?>
										</a>
										<span class="ss-home-contest__cat"><?php echo esc_html($c['category']); ?></span>
										<span class="ss-home-contest__badge">ACTIVO</span>
										<?php if ($end_attr) : ?>
											<div class="ss-home-contest__timebox" data-ss-countdown="<?php echo esc_attr($end_attr); ?>">
												<span class="ss-home-contest__timebox-ico" aria-hidden="true"><?php echo $ico['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<span class="ss-home-contest__timebox-txt">
													<small>Quedan</small>
													<strong data-ss-countdown-label>…</strong>
												</span>
											</div>
										<?php endif; ?>
									</div>
									<div class="ss-home-contest__body">
										<h3 class="ss-home-contest__title">
											<a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['title']); ?></a>
										</h3>
										<?php if (!empty($c['meta'])) : ?>
											<p class="ss-home-contest__meta">
												<span aria-hidden="true"><?php echo $meta_ico; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<?php echo esc_html($c['meta']); ?>
											</p>
										<?php endif; ?>
										<div class="ss-home-contest__cta-row">
											<div class="ss-home-contest__price">
												<span>Desde</span>
												<strong><?php echo esc_html($price_txt); ?> <em>/ DigiTicket</em></strong>
											</div>
											<a class="ss-home-contest__btn" href="<?php echo esc_url($c['url']); ?>">
												Participar ahora
												<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											</a>
										</div>
										<div class="ss-home-contest__progress" aria-label="<?php echo esc_attr(sprintf('Avance %s%%', $pct)); ?>">
											<p class="ss-home-contest__sold">
												<span aria-hidden="true"><?php echo $ico['ticket_outline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<?php echo esc_html($sold_txt . ' / ' . $max_txt); ?> DigiTickets vendidos
											</p>
											<div class="ss-home-contest__bar-row">
												<div class="ss-home-contest__bar"><span style="width: <?php echo esc_attr((string) $c['progress']); ?>%"></span></div>
												<strong><?php echo esc_html($pct); ?>%</strong>
											</div>
										</div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
					<button type="button" class="ss-home-navbtn ss-home-contests__next" data-ss-carousel-next aria-label="Siguiente concurso">
						<?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- Packs banner -->
	<section class="ss-home-packs" aria-label="Packs de DigiTickets">
		<div class="ss-home__shell">
			<div class="ss-home-packs__panel">
				<div class="ss-home-packs__intro">
					<div class="ss-home-packs__intro-copy">
						<h2>
							Más <em>DigiTickets</em><br>
							más <em>oportunidades</em>
						</h2>
						<p>Elige el DigiPack que más te convenga y multiplica tus opciones de ganar.</p>
					</div>
				</div>

				<?php if ($packs) : ?>
					<div class="ss-home-packs__cards" role="list">
						<?php foreach ($packs as $pack) :
							$mods = 'ss-home-pack ss-home-pack--' . sanitize_html_class($pack['theme']);
							if (!empty($pack['badge_slug'])) {
								$mods .= ' is-featured ss-home-pack--' . sanitize_html_class($pack['badge_slug']);
							}
							?>
							<article class="<?php echo esc_attr($mods); ?>" role="listitem">
								<?php if (!empty($pack['badge'])) : ?>
									<span class="ss-home-pack__badge">
										<span aria-hidden="true"><?php echo $ico['star']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<?php echo esc_html($pack['badge']); ?>
									</span>
								<?php endif; ?>
								<div class="ss-home-pack__top">
									<span class="ss-home-pack__ico" aria-hidden="true"><?php echo $ico['ticket_pack']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<div class="ss-home-pack__info">
										<strong class="ss-home-pack__name"><?php echo esc_html($pack['title']); ?></strong>
										<span class="ss-home-pack__deal">Recibes <?php echo (int) $pack['buy']; ?></span>
										<span class="ss-home-pack__deal">Pagas <?php echo (int) $pack['pay']; ?></span>
									</div>
								</div>
								<div class="ss-home-pack__foot">
									<span>Desde</span>
									<strong><?php echo esc_html($pack['price']); ?></strong>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="ss-home-packs__cta">
					<div class="ss-home-packs__cta-head">
						<span class="ss-home-packs__cta-ico" aria-hidden="true"><?php echo $ico['gift']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<p>Más DigiTickets,<br>más posibilidades</p>
					</div>
					<a class="ss-home-packs__btn" href="<?php echo esc_url($packs_url); ?>">
						Ver todos los packs
						<span aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</a>
				</div>
			</div>
		</div>
	</section>

	<!-- Promo -->
	<?php if ($promo) :
		$promo_end = !empty($promo['end']) ? gmdate('c', (int) strtotime((string) $promo['end'])) : '';
		$promo_cta = !empty($promo['cta_url']) ? (string) $promo['cta_url'] : '#ss-home-concursos';
		$promo_code = (string) ($promo['code'] ?? 'SSD30OFF');
		?>
		<section class="ss-home-promo" aria-label="Promoción especial">
			<div class="ss-home__shell">
				<div class="ss-home-promo__panel">
					<div class="ss-home-promo__body">
						<div class="ss-home-promo__copy">
							<span class="ss-home-promo__badge">
								<span class="ss-home-promo__badge-ico" aria-hidden="true"><?php echo $ico['bolt']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php echo esc_html($promo['badge'] ?? 'Promoción por tiempo limitado'); ?>
							</span>
							<h2>
								<?php echo esc_html($promo['title_before'] ?? 'Beneficio'); ?>
								<em><?php echo esc_html($promo['title_em'] ?? 'especial'); ?></em>
								<?php echo esc_html($promo['title_after'] ?? 'activo'); ?>
							</h2>
							<p class="ss-home-promo__sub"><?php echo esc_html($promo['subtitle'] ?? ''); ?></p>
						</div>

						<div class="ss-home-promo__deal">
							<div class="ss-home-promo__discount" aria-hidden="true">
								<strong><?php echo esc_html(($promo['discount'] ?? '30%') . ($promo['discount_unit'] ?? 'dcto.')); ?></strong>
								<span><?php echo esc_html($promo['discount_note'] ?? ''); ?></span>
							</div>
							<?php if ($promo_end) : ?>
								<div class="ss-home-promo__timer" data-ss-promo-end="<?php echo esc_attr($promo_end); ?>" aria-live="polite">
									<div class="ss-home-promo__unit"><strong data-ss-promo-d>00</strong><span>dias</span></div>
									<span class="ss-home-promo__sep" aria-hidden="true">:</span>
									<div class="ss-home-promo__unit"><strong data-ss-promo-h>00</strong><span>horas</span></div>
									<span class="ss-home-promo__sep" aria-hidden="true">:</span>
									<div class="ss-home-promo__unit"><strong data-ss-promo-m>00</strong><span>min</span></div>
									<span class="ss-home-promo__sep" aria-hidden="true">:</span>
									<div class="ss-home-promo__unit"><strong data-ss-promo-s>00</strong><span>seg</span></div>
								</div>
							<?php endif; ?>
						</div>

						<span class="ss-home-promo__divider" aria-hidden="true"></span>

						<div class="ss-home-promo__offer">
							<span class="ss-home-promo__code-label">Usa el código:</span>
							<div class="ss-home-promo__code-row">
								<div class="ss-home-promo__codebox">
									<code><?php echo esc_html($promo_code); ?></code>
									<button type="button" class="ss-home-promo__copybtn" data-ss-copy="<?php echo esc_attr($promo_code); ?>" aria-label="Copiar código <?php echo esc_attr($promo_code); ?>">
										<span aria-hidden="true"><?php echo $ico['copy']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</button>
								</div>
								<a class="ss-home-btn ss-home-btn--light ss-home-promo__cta" href="<?php echo esc_url($promo_cta); ?>">
									<?php echo esc_html($promo['cta_label'] ?? 'Ver concursos'); ?>
									<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								</a>
							</div>
							<p class="ss-home-promo__copied" data-ss-copy-toast hidden aria-live="polite">Código copiado</p>
						</div>
					</div>
					<?php if (!empty($promo['legal'])) : ?>
						<p class="ss-home-promo__legal"><?php echo esc_html($promo['legal']); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- Ganadores -->
	<section class="ss-home-winners" aria-labelledby="ss-home-winners-title">
		<div class="ss-home__shell">
			<div class="ss-home-winners__panel">
				<div class="ss-home-winners__body">
					<div class="ss-home-winners__intro">
						<h2 id="ss-home-winners-title">
							Ellos ya ganaron <span class="ss-home-winners__accent">sus premios</span>
						</h2>
						<p>Personas reales, historias reales.<br>Tú podrías ser el próximo.</p>
						<a class="ss-home-btn ss-home-btn--ghost" href="#ss-home-concursos">
							Ver más ganadores
							<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</a>
					</div>

					<?php if ($wins) : ?>
						<div class="ss-home-winners__carousel" data-ss-winners-carousel data-ss-carousel-interval="5000">
							<div class="ss-home-winners__viewport">
								<div class="ss-home-winners__track">
									<?php foreach ($wins as $idx => $w) : ?>
										<article class="ss-home-winner" data-ss-winner-index="<?php echo (int) $idx; ?>">
											<span class="ss-home-winner__qmark" aria-hidden="true"><?php echo $ico['quote']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<blockquote class="ss-home-winner__quote">
												<p>“<?php echo esc_html($w['quote']); ?>”</p>
											</blockquote>
											<footer class="ss-home-winner__meta">
												<strong class="ss-home-winner__name"><?php echo esc_html($w['name']); ?></strong>
												<span class="ss-home-winner__prize">Ganó <?php echo esc_html($w['prize']); ?></span>
												<span class="ss-home-winner__date"><?php echo esc_html($w['date']); ?></span>
											</footer>
										</article>
									<?php endforeach; ?>
								</div>
							</div>
							<button type="button" class="ss-home-navbtn ss-home-winners__next" data-ss-winners-next aria-label="Siguiente testimonio">
								<?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</button>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ($wins) : ?>
				<div class="ss-home-winners__dots" role="tablist" aria-label="Navegación de testimonios">
					<?php foreach ($wins as $idx => $w) : ?>
						<button
							type="button"
							class="ss-home-winners__dot<?php echo $idx === 0 ? ' is-active' : ''; ?>"
							data-ss-winners-dot="<?php echo (int) $idx; ?>"
							aria-label="<?php echo esc_attr(sprintf('Testimonio %d', $idx + 1)); ?>"
							aria-selected="<?php echo $idx === 0 ? 'true' : 'false'; ?>"
						></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- Cómo funciona -->
	<section class="ss-home-how" id="ss-home-como" aria-labelledby="ss-home-how-title">
		<div class="ss-home-how__shell">
			<header class="ss-home-how__head">
				<h2 id="ss-home-how-title">Así de <span class="ss-home-how__accent">simple</span> funciona</h2>
				<p>Participar es rápido, 100% online y seguro.</p>
			</header>

			<div class="ss-home-how__flow">
				<article class="ss-home-how__step">
					<span class="ss-home-how__num" aria-hidden="true">1</span>
					<span class="ss-home-how__visual">
						<img src="<?php echo esc_url($how_assets . '/elige-tu-premio.jpg'); ?>" alt="" width="141" height="139" loading="lazy" decoding="async">
					</span>
					<h3>Elige tu premio</h3>
					<p>Explora nuestros concursos activos y elige el premio que más te gustaría ganar.</p>
				</article>

				<span class="ss-home-how__arrow" aria-hidden="true"><?php echo $ico['dash_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

				<article class="ss-home-how__step">
					<span class="ss-home-how__num" aria-hidden="true">2</span>
					<span class="ss-home-how__visual">
						<img src="<?php echo esc_url($how_assets . '/participa.jpg'); ?>" alt="" width="138" height="137" loading="lazy" decoding="async">
					</span>
					<h3>Participa con tu DigiTicket</h3>
					<p>Compra tu DigiTicket digital de forma segura y recíbelo al instante en tu email con un número único.</p>
				</article>

				<span class="ss-home-how__arrow" aria-hidden="true"><?php echo $ico['dash_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

				<article class="ss-home-how__step">
					<span class="ss-home-how__num" aria-hidden="true">3</span>
					<span class="ss-home-how__visual">
						<img src="<?php echo esc_url($how_assets . '/sigue.jpg'); ?>" alt="" width="146" height="128" loading="lazy" decoding="async">
					</span>
					<h3>Sigue el sorteo y gana</h3>
					<p>Los sorteos se transmiten en vivo y los resultados son 100% públicos y verificables.</p>
				</article>
			</div>

			<div class="ss-home-how__cta">
				<a class="ss-home-btn ss-home-btn--ghost" href="#ss-home-concursos">
					Ver concursos activos
					<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</div>
		</div>
	</section>

	<!-- Timeline -->
	<section class="ss-home-timeline" aria-labelledby="ss-home-timeline-title">
		<div class="ss-home__shell">
			<div class="ss-home-timeline__panel">
				<div class="ss-home-timeline__intro">
					<h2 id="ss-home-timeline-title">
						Tu participación, respaldada de
						<span class="ss-home-timeline__accent">principio a fin.</span>
					</h2>
					<p class="ss-home-timeline__sub">Tecnología, seguridad y transparencia en cada etapa del proceso.</p>
					<div class="ss-home-timeline__commit">
						<span class="ss-home-timeline__commit-ico" aria-hidden="true"><?php echo $ico['tl_shield']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="ss-home-timeline__commit-copy">
							<strong>Compromiso Sorteo Seguro</strong>
							<p>Cada detalle está diseñado para garantizar sorteos justos, verificables y auditables.</p>
						</div>
					</div>
				</div>

				<ol class="ss-home-timeline__track">
					<li>
						<span class="ss-home-timeline__ico" aria-hidden="true"><?php echo $ico['tl_doc']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong>1. Bases legales</strong>
						<p>Protocolizadas ante Notario Público y disponibles para todos.</p>
					</li>
					<li>
						<span class="ss-home-timeline__ico" aria-hidden="true"><?php echo $ico['tl_card']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong>2. Compra segura</strong>
						<p>Paga con pasarelas oficiales y certificadas en Chile y Latinoamérica.</p>
					</li>
					<li>
						<span class="ss-home-timeline__ico" aria-hidden="true"><?php echo $ico['tl_ticket']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong>3. DigiTicket único</strong>
						<p>Recibes tu ticket digital numerado al instante por email.</p>
					</li>
					<li>
						<span class="ss-home-timeline__ico" aria-hidden="true"><?php echo $ico['tl_live']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong>4. Sorteo en vivo</strong>
						<p>Transmitido por nuestros canales oficiales y con respaldo notarial.</p>
					</li>
					<li>
						<span class="ss-home-timeline__ico" aria-hidden="true"><?php echo $ico['tl_shield']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong>5. Resultados públicos</strong>
						<p>Resultados y ganadores publicados en la web y redes sociales.</p>
					</li>
				</ol>
			</div>
		</div>
	</section>

	<!-- Partners -->
	<section class="ss-home-partners" aria-labelledby="ss-home-partners-title">
		<div class="ss-home__shell">
			<header class="ss-home-partners__head">
				<h2 id="ss-home-partners-title">
					Respaldo <span class="ss-home-partners__accent">que nos acompaña</span>
				</h2>
				<p class="ss-home-partners__sub">Trabajamos junto a marcas líderes que confían en nuestro compromiso con la transparencia.</p>
			</header>
			<div class="ss-home-partners__carousel" data-ss-partners-carousel data-ss-carousel-interval="4000">
				<div class="ss-home-partners__viewport">
					<ul class="ss-home-partners__grid">
						<?php foreach ($partners as $p) : ?>
							<li class="ss-home-partners__item">
								<?php if (!empty($p['image'])) : ?>
									<img src="<?php echo esc_url($p['image']); ?>" alt="<?php echo esc_attr($p['alt'] ?: $p['name']); ?>" loading="lazy" decoding="async">
								<?php else : ?>
									<span><?php echo esc_html($p['name']); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</section>

	<!-- FAQ teaser -->
	<section class="ss-home-faq" aria-labelledby="ss-home-faq-title">
		<div class="ss-home__shell ss-home-faq__layout">
			<div class="ss-home-faq__intro">
				<p class="ss-home-faq__eyebrow">FAQ'S</p>
				<h2 id="ss-home-faq-title">
					Resolvemos tus
					<span class="ss-home-faq__accent">dudas más comunes</span>
				</h2>
				<p class="ss-home-faq__lead">Encuentra respuestas rápidas sobre participación, compras, sorteos y más.</p>
				<a class="ss-home-btn ss-home-btn--ghost" href="<?php echo esc_url($faq_url); ?>">
					Ver todas las preguntas
					<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</a>
			</div>

			<div class="ss-home-faq__main">
				<label class="ss-home-faq__search">
					<span class="ss-home-faq__search-ico" aria-hidden="true"><?php echo $ico['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input type="search" id="ss-home-faq-search" placeholder="Buscar en preguntas frecuentes..." autocomplete="off">
				</label>

				<?php if (!$faqs) : ?>
					<p class="ss-home-empty">Pronto publicaremos las preguntas frecuentes.</p>
				<?php else : ?>
					<div class="ss-home-faq__list" id="ss-home-faq-list">
						<?php foreach ($faqs as $faq) :
							$q_lower = function_exists('mb_strtolower') ? mb_strtolower($faq['q']) : strtolower($faq['q']);
							?>
							<article
								class="ss-home-faq__item"
								data-q="<?php echo esc_attr($q_lower); ?>"
							>
								<button type="button" class="ss-home-faq__q" aria-expanded="false">
									<span><?php echo esc_html($faq['q']); ?></span>
									<span class="ss-home-faq__toggle" aria-hidden="true"></span>
								</button>
								<div class="ss-home-faq__a">
									<div class="ss-home-faq__a-inner">
										<?php echo $faq['a']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<p class="ss-home-empty" id="ss-home-faq-no-results" hidden>No encontramos resultados para tu búsqueda.</p>
				<?php endif; ?>
			</div>

			<div class="ss-home-faq__visual" aria-hidden="true">
				<img
					src="<?php echo esc_url(content_url('mu-plugins/sorteoseguro-home/assets/faq-visual.png')); ?>"
					alt=""
					width="440"
					height="420"
					loading="lazy"
					decoding="async"
				>
			</div>
		</div>
	</section>

	<!-- CTA final -->
	<section class="ss-home-cta" aria-label="Llamado a la acción">
		<div class="ss-home__shell">
			<div class="ss-home-cta__panel">
				<div class="ss-home-cta__copy">
					<span class="ss-home-cta__watermark" aria-hidden="true"><?php echo $ico['cta_ticket_bg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h2>
						<span class="ss-home-cta__line">Tu próximo premio</span>
						<span class="ss-home-cta__line">podría comenzar con</span>
						<span class="ss-home-cta__line">un <span class="ss-home-cta__accent">DigiTicket.</span></span>
					</h2>
				</div>

				<ul class="ss-home-cta__features">
					<li>
						<span class="ss-home-cta__fico" aria-hidden="true"><?php echo $ico['cta_digital']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>100% digital</span>
					</li>
					<li>
						<span class="ss-home-cta__fico" aria-hidden="true"><?php echo $ico['tl_shield']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Seguro y transparente</span>
					</li>
					<li>
						<span class="ss-home-cta__fico" aria-hidden="true"><?php echo $ico['cta_public']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Resultados públicos</span>
					</li>
				</ul>

				<div class="ss-home-cta__action">
					<a class="ss-home-btn ss-home-btn--light" href="#ss-home-concursos">
						Ver concursos activos
						<span class="ss-home-btn__ico" aria-hidden="true"><?php echo $ico['nav_arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</a>
					<p class="ss-home-cta__note">¡Participa hoy!</p>
				</div>
			</div>
		</div>
	</section>

</main>
<?php
get_footer();
