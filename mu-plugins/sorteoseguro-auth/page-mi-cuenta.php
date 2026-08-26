<?php
/**
 * Plantilla Mi cuenta – login / área de cliente (página ID 14)
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

$is_logged_in = is_user_logged_in();
$login_url    = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::login_url() : home_url('/mi-cuenta/');
$register_url = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::register_url() : home_url('/registro/');
$shortcode    = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::mi_cuenta_shortcode() : '[woocommerce_my_account]';
$ico_user     = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::icon('user') : '';
$ico_lock     = class_exists('SorteoSeguro_Auth') ? SorteoSeguro_Auth::icon('lock') : '';

get_header();
?>
<main class="ss-auth<?php echo $is_logged_in ? ' ss-auth--account' : ' ss-auth--login'; ?>">
	<div class="ss-auth__shell">
		<?php if (!$is_logged_in) : ?>
			<section class="ss-auth-card ss-auth-card--login" aria-labelledby="ss-auth-login-title">
				<div class="ss-auth-card__panel">
					<span class="ss-auth-card__ico" aria-hidden="true"><?php echo $ico_user; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h1 class="ss-auth-card__title" id="ss-auth-login-title">Acceder</h1>
					<p class="ss-auth-card__sub">Accede a tu cuenta para revisar tus DigiTickets y el estado de tus participaciones.</p>
					<div class="ss-auth-card__form ss-auth-card__form--wc">
						<?php echo do_shortcode($shortcode); ?>
					</div>
					<p class="ss-auth-card__foot">
						<span class="ss-auth-card__foot-ico" aria-hidden="true"><?php echo $ico_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						¿No tienes cuenta? <a href="<?php echo esc_url($register_url); ?>">Crear tu cuenta</a>
					</p>
				</div>
			</section>
		<?php else : ?>
			<section class="ss-auth-account" aria-label="Mi cuenta">
				<header class="ss-auth-account__head">
					<h1 class="ss-auth-account__title">Mi cuenta</h1>
					<p class="ss-auth-account__sub">Gestiona tus pedidos, DigiTickets y datos de contacto.</p>
				</header>
				<div class="ss-auth-account__grid">
					<?php echo do_shortcode($shortcode); ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
