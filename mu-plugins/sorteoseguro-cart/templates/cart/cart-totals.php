<?php
/**
 * Cart totals – resumen de compra
 *
 * @see sorteoseguro-cart
 */
defined('ABSPATH') || exit;

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Cart') ? SorteoSeguro_Cart::icon($name) : '';
};
?>
<div class="cart_totals ss-cart-summary <?php echo WC()->customer->has_calculated_shipping() ? 'calculated_shipping' : ''; ?>">
	<?php do_action('woocommerce_before_cart_totals'); ?>

	<div class="ss-cart-coupon">
		<button type="button" class="ss-cart-coupon__toggle" id="ss-cart-coupon-toggle" aria-expanded="false" aria-controls="ss-cart-coupon-panel">
			<span>
				<span class="ss-cart-coupon__ico" aria-hidden="true"><?php echo $ico('tag'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				Agregar cupones
			</span>
			<span class="ss-cart-coupon__chev" aria-hidden="true"><?php echo $ico('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</button>
		<div class="ss-cart-coupon__panel" id="ss-cart-coupon-panel" hidden>
			<div class="ss-cart-coupon__row">
				<input type="text" name="coupon_code" id="ss-cart-coupon-code" placeholder="Código de cupón" autocomplete="off">
				<button type="button" id="ss-cart-coupon-apply">Aplicar</button>
			</div>
		</div>
	</div>

	<div class="ss-cart-totals">
		<div class="ss-cart-totals__row">
			<span>Subtotal</span>
			<span><?php wc_cart_totals_subtotal_html(); ?></span>
		</div>

		<?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
			<div class="ss-cart-totals__row ss-cart-totals__row--discount cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
				<span><?php wc_cart_totals_coupon_label($coupon); ?></span>
				<span><?php wc_cart_totals_coupon_html($coupon); ?></span>
			</div>
		<?php endforeach; ?>

		<?php foreach (WC()->cart->get_fees() as $fee) : ?>
			<?php
			$is_promo = strpos((string) $fee->name, '[PROMO]') !== false;
			if (!$is_promo && (float) $fee->amount >= 0) {
				?>
				<div class="ss-cart-totals__row fee">
					<span><?php echo esc_html($fee->name); ?></span>
					<span><?php wc_cart_totals_fee_html($fee); ?></span>
				</div>
				<?php
				continue;
			}
			$promo_label = $is_promo ? (string) $fee->name : $fee->name;
			?>
			<div class="ss-cart-totals__row ss-cart-totals__row--discount">
				<span><?php echo esc_html($promo_label); ?></span>
				<span><?php echo wp_kses_post(wc_price($fee->amount)); ?></span>
			</div>
		<?php endforeach; ?>

		<div class="ss-cart-totals__total order-total">
			<strong>Total a pagar</strong>
			<span><?php wc_cart_totals_order_total_html(); ?></span>
		</div>
	</div>

	<div class="ss-cart-summary__trust">
		<span aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<p>Compra segura – DigiTickets registrados automáticamente</p>
	</div>

	<div class="wc-proceed-to-checkout">
		<a class="ss-cart-cta checkout-button" href="<?php echo esc_url(wc_get_checkout_url()); ?>">
			<span class="ss-cart-cta__lock" aria-hidden="true"><?php echo $ico('lock'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span>Continuar al pago</span>
			<span class="ss-cart-cta__arrow" aria-hidden="true"><?php echo $ico('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
	</div>

	<?php do_action('woocommerce_after_cart_totals'); ?>
</div>
