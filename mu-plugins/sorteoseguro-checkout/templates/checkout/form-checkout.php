<?php
/**
 * Checkout Form — diseño Sorteo Seguro
 *
 * @see sorteoseguro-checkout
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);

do_action('woocommerce_before_checkout_form', $checkout);

$ico_user  = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('user') : '';
$ico_mail  = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('mail') : '';
$ico_phone = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('phone') : '';
$ico_lock  = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('lock') : '';
$ico_shield = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('shield') : '';
$ico_check = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('check') : '';
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout custom-checkout-form ss-co" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e('Checkout', 'woocommerce'); ?>">

	<div class="checkout-main-wrapper ss-co__layout">

		<div class="checkout-left-column ss-co__main">

			<section class="checkout-step ss-co-step" data-step="1">
				<header class="ss-co-step__head">
					<span class="ss-co-step__num" aria-hidden="true">1</span>
					<div>
						<h3>Información de contacto</h3>
						<p class="step-description">Utilizaremos estos datos para enviarte detalles y actualizaciones sobre tu pedido.</p>
					</div>
				</header>

				<div class="ss-co-fields">
					<div class="ss-co-row ss-co-row--split">
						<span class="ss-co-row__ico" aria-hidden="true"><?php echo $ico_user; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="ss-co-row__fields">
							<p class="form-row ss-co-field is-float" id="contact_first_name_field">
								<input type="text" class="input-text" name="contact_first_name" id="contact_first_name" placeholder=" " autocomplete="given-name" value="<?php echo esc_attr($checkout->get_value('billing_first_name')); ?>">
								<label for="contact_first_name">Nombre</label>
							</p>
							<p class="form-row ss-co-field is-float" id="contact_last_name_field">
								<input type="text" class="input-text" name="contact_last_name" id="contact_last_name" placeholder=" " autocomplete="family-name" value="<?php echo esc_attr($checkout->get_value('billing_last_name')); ?>">
								<label for="contact_last_name">Apellidos</label>
							</p>
						</div>
					</div>

					<div class="ss-co-row">
						<span class="ss-co-row__ico" aria-hidden="true"><?php echo $ico_mail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="ss-co-row__fields">
							<p class="form-row ss-co-field is-float ss-co-field--wide" id="billing_email_field">
								<input type="email" class="input-text" name="billing_email" id="billing_email" placeholder=" " autocomplete="email" value="<?php echo esc_attr($checkout->get_value('billing_email')); ?>">
								<label for="billing_email">Correo electrónico</label>
							</p>
						</div>
					</div>

					<div class="ss-co-row">
						<span class="ss-co-row__ico" aria-hidden="true"><?php echo $ico_phone; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="ss-co-row__fields">
							<p class="form-row ss-co-field is-float ss-co-field--wide" id="contact_phone_field">
								<input type="tel" class="input-text" name="contact_phone" id="contact_phone" placeholder=" " autocomplete="tel" value="<?php echo esc_attr($checkout->get_value('billing_phone')); ?>">
								<label for="contact_phone">Teléfono</label>
							</p>
						</div>
					</div>
				</div>

				<?php
				// Campos billing espejo (incl. RUT) ocultos pero sincronizados / disponibles.
				$fields = $checkout->get_checkout_fields();
				if (!empty($fields['billing'])) :
					?>
					<div id="billing-address-fields-panel" class="ss-co-billing-mirror" hidden>
						<?php
						$allowed = ['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_rut'];
						foreach ($fields['billing'] as $key => $field) {
							if (!in_array($key, $allowed, true)) {
								continue;
							}
							woocommerce_form_field($key, $field, $checkout->get_value($key));
						}
						?>
					</div>
				<?php endif; ?>
			</section>

			<section class="checkout-step ss-co-step" data-step="2">
				<header class="ss-co-step__head">
					<span class="ss-co-step__num" aria-hidden="true">2</span>
					<div>
						<h3>Método de pago</h3>
						<p class="step-description">Elige cómo quieres pagar tu pedido de forma segura.</p>
					</div>
				</header>
				<div class="payment-method-wrap ss-co-pay">
					<?php woocommerce_checkout_payment(); ?>
				</div>
				<div class="ss-co-pay-trust" aria-label="Beneficios de pago">
					<div class="ss-co-pay-trust__item">
						<span aria-hidden="true"><?php echo $ico_shield; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Pago con tus tarjetas guardadas</span>
					</div>
					<div class="ss-co-pay-trust__item">
						<span aria-hidden="true"><?php echo $ico_shield; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span>Compra 100% segura</span>
					</div>
				</div>
			</section>

			<section class="checkout-step ss-co-step ss-co-step--confirm" data-step="3">
				<header class="ss-co-step__head">
					<span class="ss-co-step__num" aria-hidden="true">3</span>
					<div>
						<h3>Revisa y confirma</h3>
					</div>
				</header>

				<div class="ss-co-agree" data-ss-agree>
					<div class="ss-co-agree__row ss-co-agree__row--notes">
						<label class="ss-co-check" for="toggle-order-notes">
							<input type="checkbox" id="toggle-order-notes" class="ss-co-check__input ss-co-notes__cb" value="1">
							<span class="ss-co-check__box" aria-hidden="true"></span>
							<span class="ss-co-check__text">¿Deseas agregar una nota especial a tu pedido?</span>
						</label>
						<button type="button" class="ss-co-agree__chev" id="toggle-order-notes-btn" aria-expanded="false" aria-controls="wrapper-order-notes" title="Agregar nota">
							<span aria-hidden="true">▾</span>
						</button>
						<div id="wrapper-order-notes" class="ss-co-agree__notes-panel" hidden>
							<?php do_action('woocommerce_checkout_shipping'); ?>
						</div>
					</div>

					<?php if (class_exists('UM')) : ?>
						<?php
						foreach (
							[
								'checkbox_registro_acepto' => 'promo',
								'tyc' => 'tyc',
							] as $um_key => $row
						) :
							$data = UM()->fields()->get_field($um_key);
							if (!$data) {
								continue;
							}
							$data['type'] = 'checkbox';
							?>
							<div class="ss-co-agree__row ss-co-agree__row--<?php echo esc_attr($row); ?> ss-co-agree__row--um">
								<?php echo UM()->fields()->edit_field($um_key, $data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="ss-co-agree__row ss-co-agree__row--promo">
							<label class="ss-co-check">
								<input type="checkbox" class="ss-co-check__input" name="ss_promo_optin" value="1">
								<span class="ss-co-check__box" aria-hidden="true"></span>
								<span class="ss-co-check__text">
									Acepto recibir información, novedades y promociones de Sorteo Seguro en mi correo electrónico.
									<small>Podré cancelar la suscripción en cualquier momento.</small>
								</span>
							</label>
						</div>
						<div class="ss-co-agree__row ss-co-agree__row--tyc">
							<label class="ss-co-check">
								<input type="checkbox" class="ss-co-check__input" name="ss_tyc" value="1" required>
								<span class="ss-co-check__box" aria-hidden="true"></span>
								<span class="ss-co-check__text">
									Acepto los <a href="<?php echo esc_url(home_url('/terminos-y-condiciones/')); ?>">Términos y condiciones</a> y la <a href="<?php echo esc_url(home_url('/politica-de-privacidad/')); ?>">Política de privacidad</a>
								</span>
							</label>
						</div>
					<?php endif; ?>
				</div>

				<div class="ss-co-confirm">
					<div class="checkout-actions ss-co-actions">
						<a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="back-to-cart ss-co-back">
							← Volver al carrito
						</a>
						<button type="submit" class="ss-co-place" name="woocommerce_checkout_place_order" id="place_order" value="<?php esc_attr_e('Place order', 'woocommerce'); ?>">
							<span class="ss-co-place__ico" aria-hidden="true"><?php echo $ico_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							Realizar el pedido
						</button>
					</div>

					<div class="post-info ss-co-postinfo">
						<span class="ss-co-postinfo__ico" aria-hidden="true"><?php echo $ico_shield; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<p>Después de completar el pago, recibirás tus <strong>DigiTickets</strong> inmediatamente en tu correo electrónico y también estarán disponibles en tu perfil de usuario.</p>
					</div>
				</div>
			</section>
		</div>

		<aside class="checkout-right-sidebar ss-co__side">
			<div class="order-review-card ss-co-summary">
				<h3>Resumen del pedido</h3>
				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action('woocommerce_checkout_order_review'); ?>
				</div>
			</div>

			<div class="ss-co-side-trust">
				<p class="ss-co-side-trust__title">
					<span aria-hidden="true"><?php echo $ico_shield; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					Compra 100% segura
				</p>
				<ul>
					<li><span aria-hidden="true"><?php echo $ico_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span> Tus datos están protegidos</li>
					<li><span aria-hidden="true"><?php echo $ico_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span> Proceso de pago cifrado</li>
					<li><span aria-hidden="true"><?php echo $ico_check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span> DigiTickets registrados automáticamente</li>
				</ul>
			</div>
		</aside>
	</div>
</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
