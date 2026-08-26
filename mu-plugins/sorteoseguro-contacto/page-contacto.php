<?php
/**
 * Plantilla Contacto – página Contacto (ID 1387)
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

$channels = class_exists('SorteoSeguro_Contacto') ? SorteoSeguro_Contacto::channels() : [];
$privacy  = class_exists('SorteoSeguro_Chrome')
	? SorteoSeguro_Chrome::page_url('politica-privacidad')
	: home_url('/politica-privacidad/');
$form     = class_exists('SorteoSeguro_Contacto')
	? SorteoSeguro_Contacto::form_shortcode()
	: '[contact-form-7 id="1390"]';

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Contacto') ? SorteoSeguro_Contacto::icon($name) : '';
};

get_header();
?>
<main class="ss-contact">
	<div class="ss-contact__shell">
		<div class="ss-contact__grid">
			<div class="ss-contact__col ss-contact__col--info">
				<div class="ss-contact-lead">
					<div class="ss-contact-lead__copy">
						<h1 class="ss-contact__title">
							¿Tienes dudas?<br>
							Estamos para <em>ayudarte</em>
						</h1>
						<span class="ss-contact__rule" aria-hidden="true"></span>
						<p>Nuestro equipo está disponible para resolver tus consultas sobre participación, pagos, bases legales, entrega de premios y funcionamiento de la plataforma.</p>
						<p>Puedes escribirnos directamente a través del formulario o por cualquiera de nuestros canales de contacto.</p>
					</div>
					<div class="ss-contact-trust">
						<div class="ss-contact-trust__badge" aria-hidden="true">
							<svg class="ss-contact-trust__art" viewBox="0 0 160 160" fill="none">
								<circle cx="80" cy="80" r="74" fill="#f3ecff"/>
								<path d="M80 34l40 16v28c0 26-17 48-40 54-23-6-40-28-40-54V50l40-16z" fill="#6b3bb8"/>
								<path d="M62 58l36 22v-8L62 50v8z" fill="#fff" opacity=".95"/>
								<path d="M62 74l36 22v-8L62 66v8z" fill="#fff" opacity=".95"/>
								<path d="M62 90l36 22v-8L62 82v8z" fill="#fff" opacity=".95"/>
								<circle cx="118" cy="112" r="18" fill="#efe6ff"/>
								<rect x="109" y="108" width="18" height="14" rx="3" fill="#6b3bb8"/>
								<path d="M112 108v-4a6 6 0 0 1 12 0v4" stroke="#6b3bb8" stroke-width="2.2" fill="none" stroke-linecap="round"/>
								<circle cx="118" cy="115" r="1.6" fill="#fff"/>
								<path d="M42 48l3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7z" fill="#c4b0ea"/>
								<path d="M122 42l2.2 5 5 2.2-5 2.2-2.2 5-2.2-5-5-2.2 5-2.2 2.2-5z" fill="#c4b0ea"/>
								<path d="M36 96l1.6 3.6 3.6 1.6-3.6 1.6-1.6 3.6-1.6-3.6-3.6-1.6 3.6-1.6 1.6-3.6z" fill="#d7c8f4"/>
							</svg>
						</div>
						<p class="ss-contact-trust__text">Premios certificados y<br>entregados públicamente</p>
						<div class="ss-contact-trust__stars" aria-hidden="true">
							<?php echo str_repeat($ico('star'), 5); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>

				<div class="ss-contact-channels">
					<?php foreach ($channels as $ch) : ?>
						<a class="ss-contact-channel" href="mailto:<?php echo esc_attr($ch['email']); ?>">
							<span class="ss-contact-channel__ico" aria-hidden="true"><?php echo $ico($ch['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="ss-contact-channel__body">
								<strong><?php echo esc_html($ch['title']); ?></strong>
								<em><?php echo esc_html($ch['email']); ?></em>
							</span>
							<span class="ss-contact-channel__chev" aria-hidden="true"><?php echo $ico('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="ss-contact-privacy">
					<span class="ss-contact-privacy__ico" aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<p>Tus datos están protegidos y solo serán utilizados para responder tu consulta. Consulta nuestra <a href="<?php echo esc_url($privacy); ?>">Política de Privacidad.</a></p>
				</div>
			</div>

			<div class="ss-contact__col ss-contact__col--form">
				<div class="ss-contact-card">
					<div class="ss-contact-card__head">
						<span class="ss-contact-card__mail" aria-hidden="true"><?php echo $ico('mail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<h2>Envíanos tu mensaje</h2>
							<p>Completa el formulario y te responderemos a la brevedad.</p>
						</div>
					</div>
					<div class="ss-contact-card__form">
						<?php echo do_shortcode($form); ?>
					</div>
					<p class="ss-contact-card__note">
						<span aria-hidden="true"><?php echo $ico('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						Nunca compartiremos tu información con terceros.
					</p>
				</div>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
