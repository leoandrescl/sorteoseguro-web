<?php
/**
 * Footer Sorteo Seguro – partial reutilizable.
 * Incluir con SorteoSeguro_Chrome::render_footer() o ss_chrome_footer().
 */
if (!defined('ABSPATH')) {
	exit;
}

$d = isset($ss_footer) && is_array($ss_footer) ? $ss_footer : [];
$home      = esc_url($d['home'] ?? home_url('/'));
$logo_icon = esc_url($d['logo_icon'] ?? 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo-1-e1751661451410.png');
$logo_full = esc_url($d['logo_full'] ?? 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo_sin_fondo.png');
$notaria   = esc_url($d['notaria'] ?? 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo-notaria.jpg');
$bases     = esc_url($d['bases'] ?? home_url('/politica-funcionamiento-concurso/'));
$ig        = esc_url($d['instagram'] ?? 'https://www.instagram.com/ssdigitalchile/');
$fb        = esc_url($d['facebook'] ?? 'https://www.facebook.com/share/1AeiDamo9U');
$tt        = esc_url($d['tiktok'] ?? 'https://www.tiktok.com/@sorteoseguro.cl');
$yt        = esc_url($d['youtube'] ?? 'https://www.youtube.com/@SorteoSeguro');
$email     = sanitize_email($d['email'] ?? 'contacto@sorteoseguro.com');
$hours     = esc_html($d['hours'] ?? 'Lunes a Viernes 09:00 - 19:00 hrs.');
$legal     = $d['legal'] ?? [];
$help      = $d['help'] ?? [];
$pay_mp    = esc_url($d['pay_mp'] ?? content_url('mu-plugins/sorteoseguro-chrome/assets/logo-mercado-pago.png'));
$pay_wp    = esc_url($d['pay_wp'] ?? content_url('mu-plugins/sorteoseguro-chrome/assets/logo-web-pay-plus.png'));

$ico_ig = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm5 4.8A4.2 4.2 0 1 0 16.2 12 4.2 4.2 0 0 0 12 7.8zm0 6.9A2.7 2.7 0 1 1 14.7 12 2.7 2.7 0 0 1 12 14.7zM17.6 6.4a1 1 0 1 0 1 1 1 1 0 0 0-1-1z"/></svg>';
$ico_fb = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>';
$ico_tt = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14.5 3c.4 2.6 1.8 4.4 4.5 4.6v3.1c-1.5 0-2.9-.5-4.1-1.3v6.4A6.2 6.2 0 1 1 8.2 9.7v3.2a3.1 3.1 0 1 0 2.2 3V3h4.1z"/></svg>';
$ico_yt = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23 12.2s0-3.2-.4-4.6c-.2-.9-.9-1.6-1.8-1.8C19.2 5.4 12 5.4 12 5.4s-7.2 0-8.8.4c-.9.2-1.6.9-1.8 1.8C1 9 1 12.2 1 12.2s0 3.2.4 4.6c.2.9.9 1.6 1.8 1.8 1.6.4 8.8.4 8.8.4s7.2 0 8.8-.4c.9-.2 1.6-.9 1.8-1.8.4-1.4.4-4.6.4-4.6zM9.8 15.6V8.8l6.2 3.4z"/></svg>';
$ico_clock = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 11H7V11h4V6h2z"/></svg>';
$ico_mail = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5z"/></svg>';
$ico_doc = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm1 7V3.5L18.5 9zM8 13h8v2H8zm0 4h8v2H8zm0-8h5v2H8z"/></svg>';
$ico_help = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 15.2a1.2 1.2 0 1 1 1.2-1.2 1.2 1.2 0 0 1-1.2 1.2zm1.6-5.3-.5.3V13h-2.2v-1.5l1.1-.6A1.6 1.6 0 0 0 13 9.4a1.1 1.1 0 0 0-1.2-1.1 1.3 1.3 0 0 0-1.3 1.1H8.4A3.3 3.3 0 0 1 11.8 6 3.2 3.2 0 0 1 15.3 9.3c0 1.3-.7 2-1.7 2.6z"/></svg>';
$ico_card = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4H4V6h16z"/></svg>';
$ico_star = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" d="M12 3.2 14.4 9l6.2.5-4.7 4 1.6 6.1L12 16.6 6.5 19.6 8.1 13.5 3.4 9.5 9.6 9z"/></svg>';
$ico_arrow = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.3 6.7 10.7 5.3 17.4 12l-6.7 6.7-1.4-1.4L14.6 12z"/></svg>';

$print_links = static function (array $items) use ($ico_arrow): void {
	echo '<ul class="ss-footer__links">';
	foreach ($items as $item) {
		$href = esc_url($item['url'] ?? '#');
		$label = esc_html($item['label'] ?? '');
		echo '<li><a href="' . $href . '">' . $ico_arrow . '<span>' . $label . '</span></a></li>';
	}
	echo '</ul>';
};
?>
<footer class="ss-footer" role="contentinfo">
	<div class="ss-footer__cta-wrap">
		<div class="ss-footer__shell">
			<section class="ss-footer__cta">
				<a class="ss-footer__brand ss-footer__brand--cta" href="<?php echo $home; ?>">
					<img src="<?php echo $logo_icon; ?>" alt="" width="42" height="44" decoding="async">
					<span>Sorteo seguro</span>
				</a>
				<h2 class="ss-footer__cta-title">
					Tu próximo premio podría estar <em>más cerca</em> de lo que imaginas
				</h2>
				<span class="ss-footer__star" aria-hidden="true"><?php echo $ico_star; ?></span>
				<p class="ss-footer__cta-sub">Síguenos y entérate de nuevos sorteos, ganadores y promociones exclusivas.</p>
				<div class="ss-footer__social">
					<a class="ss-footer__social-btn is-ig" href="<?php echo $ig; ?>" target="_blank" rel="noopener noreferrer">
						<?php echo $ico_ig; ?><span>Instagram</span>
					</a>
					<a class="ss-footer__social-btn is-fb" href="<?php echo $fb; ?>" target="_blank" rel="noopener noreferrer">
						<?php echo $ico_fb; ?><span>Facebook</span>
					</a>
					<a class="ss-footer__social-btn is-tt" href="<?php echo $tt; ?>" target="_blank" rel="noopener noreferrer">
						<?php echo $ico_tt; ?><span>TikTok</span>
					</a>
					<a class="ss-footer__social-btn is-yt" href="<?php echo $yt; ?>" target="_blank" rel="noopener noreferrer">
						<?php echo $ico_yt; ?><span>YouTube</span>
					</a>
				</div>
			</section>
		</div>
	</div>

	<div class="ss-footer__body">
		<div class="ss-footer__shell">
			<div class="ss-footer__grid">
				<div class="ss-footer__col ss-footer__col--brand">
					<a class="ss-footer__brand ss-footer__brand--dark" href="<?php echo $home; ?>">
						<img src="<?php echo $logo_icon; ?>" alt="" width="42" height="44" decoding="async">
						<span>Sorteo seguro</span>
					</a>
					<p class="ss-footer__lead">Plataforma de concursos Promocionales online, certificados y 100% seguros.</p>
					<ul class="ss-footer__contact">
						<li>
							<span class="ss-footer__contact-ico" aria-hidden="true"><?php echo $ico_clock; ?></span>
							<span><?php echo $hours; ?></span>
						</li>
						<li>
							<span class="ss-footer__contact-ico" aria-hidden="true"><?php echo $ico_mail; ?></span>
							<a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
						</li>
					</ul>
				</div>

				<nav class="ss-footer__col" aria-label="Información legal">
					<h3 class="ss-footer__heading">
						<span class="ss-footer__heading-ico" aria-hidden="true"><?php echo $ico_doc; ?></span>
						Información legal
					</h3>
					<?php $print_links($legal); ?>
				</nav>

				<nav class="ss-footer__col" aria-label="Ayuda y soporte">
					<h3 class="ss-footer__heading">
						<span class="ss-footer__heading-ico" aria-hidden="true"><?php echo $ico_help; ?></span>
						Ayuda y soporte
					</h3>
					<?php $print_links($help); ?>
				</nav>

				<div class="ss-footer__col ss-footer__col--payments">
					<h3 class="ss-footer__heading">
						<span class="ss-footer__heading-ico" aria-hidden="true"><?php echo $ico_card; ?></span>
						Medios de pago
					</h3>
					<p class="ss-footer__payments-lead">Pagos seguros a través de pasarelas oficiales.</p>
					<div class="ss-footer__payments">
						<div class="ss-footer__pay">
							<img src="<?php echo $pay_wp; ?>" alt="Webpay Plus" width="240" height="80" loading="lazy" decoding="async">
						</div>
						<div class="ss-footer__pay">
							<img src="<?php echo $pay_mp; ?>" alt="Mercado Pago" width="240" height="80" loading="lazy" decoding="async">
						</div>
					</div>
				</div>
			</div>

			<div class="ss-footer__bar">
				<div class="ss-footer__notary">
					<img src="<?php echo $notaria; ?>" alt="Notaría Gervasio Chile" width="72" height="72" decoding="async">
					<p>Los concursos se rigen por bases legales protocolizadas ante notario público.</p>
				</div>
				<div class="ss-footer__bases">
					<span class="ss-footer__bases-ico" aria-hidden="true"><?php echo $ico_doc; ?></span>
					<p>Revisa nuestras bases legales y toda la información de nuestros concursos activos.</p>
					<a class="ss-footer__bases-btn" href="<?php echo $bases; ?>" target="_blank" rel="noopener noreferrer">
						VER BASES LEGALES
						<?php echo $ico_arrow; ?>
					</a>
				</div>
				<div class="ss-footer__copy">
					<p>© 2025 Sorteo Seguro. Todos los derechos reservados.</p>
					<p>Powered by Poliniza - 2026</p>
				</div>
			</div>
		</div>
	</div>
</footer>
