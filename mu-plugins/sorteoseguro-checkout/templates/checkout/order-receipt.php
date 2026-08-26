<?php
/**
 * Recibo / pago pendiente (TUU Webpay).
 * WooCommerce carga esto en order-pay en lugar del formulario de checkout.
 *
 * @see sorteoseguro-checkout
 * @var WC_Order $order
 */
defined('ABSPATH') || exit;

if (!isset($order) || !is_a($order, 'WC_Order')) {
	return;
}
?>
<div class="ss-co ss-co-pay">
	<div class="ss-co-pay__shell">
		<section class="ss-co-pay__card">
			<ul class="order_details ss-co-pay__meta">
				<li class="order">
					<span class="ss-co-pay__label"><?php esc_html_e('Order number:', 'woocommerce'); ?></span>
					<strong><?php echo esc_html($order->get_order_number()); ?></strong>
				</li>
				<li class="date">
					<span class="ss-co-pay__label"><?php esc_html_e('Date:', 'woocommerce'); ?></span>
					<strong><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></strong>
				</li>
				<li class="total">
					<span class="ss-co-pay__label"><?php esc_html_e('Total:', 'woocommerce'); ?></span>
					<strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong>
				</li>
				<?php if ($order->get_payment_method_title()) : ?>
				<li class="method">
					<span class="ss-co-pay__label"><?php esc_html_e('Payment method:', 'woocommerce'); ?></span>
					<strong><?php echo wp_kses_post($order->get_payment_method_title()); ?></strong>
				</li>
				<?php endif; ?>
			</ul>
			<div class="ss-co-pay__action">
				<?php do_action('woocommerce_receipt_' . $order->get_payment_method(), $order->get_id()); ?>
			</div>
		</section>
	</div>
</div>
