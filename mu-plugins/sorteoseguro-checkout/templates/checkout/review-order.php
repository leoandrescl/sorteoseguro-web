<?php
/**
 * Review order — diseño Sorteo Seguro
 *
 * @see sorteoseguro-checkout
 */
defined('ABSPATH') || exit;
?>
<div class="shop_table woocommerce-checkout-review-order-table ss-co-review">

	<div class="checkout-products-list ss-co-products">
		<?php
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
			if (!$_product || !$_product->exists() || $cart_item['quantity'] <= 0) {
				continue;
			}
			$subtotal = WC()->cart->get_product_subtotal($_product, $cart_item['quantity']);
			$regular_raw = (float) $_product->get_regular_price() * (int) $cart_item['quantity'];
			$current_raw = (float) $_product->get_price() * (int) $cart_item['quantity'];
			$on_sale = $_product->is_on_sale() && $regular_raw > $current_raw;
			$meta = wc_get_formatted_cart_item_data($cart_item, true);
			$meta = str_replace(['G-pass:', 'G-Pass:', 'Tickets:', 'Ticket:'], ['Tus DigiTickets:', 'Tus DigiTickets:', 'Tus DigiTickets:', 'Tus DigiTickets:'], $meta);
			$meta = str_replace(',', ', ', $meta);
			?>
			<article class="checkout-product-item ss-co-product">
				<div class="product-thumbnail-wrapper ss-co-product__thumb">
					<?php echo $_product->get_image([88, 88]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="product-quantity-badge ss-co-product__qty"><?php echo (int) $cart_item['quantity']; ?></span>
				</div>
				<div class="product-info-wrapper ss-co-product__info">
					<div class="ss-co-product__top">
						<span class="ss-co-product__name"><?php echo wp_kses_post($_product->get_name()); ?></span>
					</div>
					<div class="ss-co-product__prices">
						<?php if ($on_sale) : ?>
							<span class="ss-co-product__old"><?php echo wp_kses_post(wc_price($regular_raw)); ?></span>
						<?php endif; ?>
						<span class="ss-co-product__price"><?php echo wp_kses_post(wc_price($current_raw)); ?></span>
						<?php if ($on_sale) : ?>
							<span class="ss-co-product__save">Ahorra <?php echo wp_kses_post(wc_price($regular_raw - $current_raw)); ?></span>
						<?php endif; ?>
					</div>
					<?php if ($meta !== '') : ?>
						<div class="ss-co-product__meta">
							<strong>Premio Principal:</strong>
							<div><?php echo wp_kses_post($meta); ?></div>
						</div>
					<?php endif; ?>
				</div>
			</article>
			<?php
		}
		?>
	</div>

	<div class="checkout-coupon-accordion ss-co-coupon">
		<button type="button" id="toggle-coupon" class="ss-co-coupon__toggle" aria-expanded="false" aria-controls="coupon-form-wrapper">
			<span>Agregar cupones</span>
			<span class="arrow-icon" aria-hidden="true">▾</span>
		</button>
		<div id="coupon-form-wrapper" class="ss-co-coupon__panel" hidden>
			<div class="ss-co-coupon__row">
				<input type="text" id="coupon_code_custom" placeholder="Código de cupón" autocomplete="off">
				<button type="button" id="apply_coupon_custom">Aplicar</button>
			</div>
		</div>
	</div>

	<div class="checkout-totals-section ss-co-totals">
		<div class="ss-co-totals__row">
			<span>Subtotal</span>
			<span><?php wc_cart_totals_subtotal_html(); ?></span>
		</div>

		<?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
			<div class="ss-co-totals__row ss-co-totals__row--discount">
				<span>Cupón: <?php echo esc_html($code); ?></span>
				<span><?php wc_cart_totals_coupon_html($coupon); ?></span>
			</div>
		<?php endforeach; ?>

		<?php
		foreach (WC()->cart->get_fees() as $fee) {
			if (strpos($fee->name, '[PROMO]') === false) {
				continue;
			}
			$promo_name = trim(str_replace('[PROMO]', '', $fee->name));
			$clean_label = 'PROMO - ' . strtoupper($promo_name);
			?>
			<div class="ss-co-totals__row ss-co-totals__row--discount">
				<span><?php echo esc_html($clean_label); ?></span>
				<span><?php echo wp_kses_post(wc_price($fee->amount)); ?></span>
			</div>
			<?php
		}
		?>

		<div class="order-total ss-co-totals__total">
			<strong>Total a pagar</strong>
			<div class="total-price-container"><?php wc_cart_totals_order_total_html(); ?></div>
		</div>
	</div>
</div>
