<?php
/**
 * Plantilla Registro – Ultimate Member form 481 (página ID 559)
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

$login_url = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::login_url() : home_url('/mi-cuenta/');
$shortcode = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::registro_shortcode() : '[ultimatemember form_id="481"]';
$ico_user  = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::icon('user') : '';
$ico_lock  = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::icon('lock') : '';

get_header();
?>
<main class="ss-auth ss-auth--register">
	<div class="ss-auth__shell">
		<section class="ss-auth-card ss-auth-card--register" aria-labelledby="ss-auth-register-title">
			<div class="ss-auth-card__panel ss-auth-card__panel--wide">
				<span class="ss-auth-card__ico" aria-hidden="true"><?php echo $ico_user; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<h1 class="ss-auth-card__title" id="ss-auth-register-title">Regístrate</h1>
				<p class="ss-auth-card__sub">Crea tu cuenta en Sorteo Seguro para participar, comprar DigiTickets y seguir tus pedidos en un solo lugar.</p>
				<div class="ss-auth-card__form ss-auth-card__form--um">
					<?php echo do_shortcode($shortcode); ?>
				</div>
				<p class="ss-auth-card__foot">
					<span class="ss-auth-card__foot-ico" aria-hidden="true"><?php echo $ico_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					¿Ya tienes cuenta? <a href="<?php echo esc_url($login_url); ?>">Iniciar sesión</a>
				</p>
			</div>
		</section>
	</div>
</main>
<?php
get_footer();
