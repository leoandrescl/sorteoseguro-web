<?php
/**
 * Pago de pedido pendiente (enlace del correo / order-pay).
 *
 * @see sorteoseguro-checkout
 * @var WC_Order $order
 * @var WC_Payment_Gateway[] $available_gateways
 * @var string $order_button_text
 */
defined('ABSPATH') || exit;

if (!isset($order) || !is_a($order, 'WC_Order')) {
	return;
}

$totals              = $order->get_order_item_totals();
$available_gateways  = isset($available_gateways) && is_array($available_gateways) ? $available_gateways : array();
$order_button_text   = isset($order_button_text) ? (string) $order_button_text : __('Pay for order', 'woocommerce');
$ico_shield          = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('shield') : '';
$ico_lock            = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('lock') : '';
$ico_check           = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::icon('check') : '';
?>
<div class="ss-co ss-co-order-pay">
	<div class="ss-co-pay__shell">
		<form id="order_review" method="post">
			<div class="ss-co-order-pay__grid">

				<section class="ss-co-pay__card ss-co-order-pay__summary">
					<h3>Resumen del pedido</h3>
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
					</ul>

					<div class="ss-co-products">
						<?php foreach ($order->get_items() as $item_id => $item) : ?>
							<?php
							if (!apply_filters('woocommerce_order_item_visible', true, $item)) {
								continue;
							}
							$product = $item->get_product();
							?>
							<article class="ss-co-product">
								<div class="ss-co-product__thumb">
									<?php
									if ($product) {
										echo $product->get_image(array(88, 88)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
									<span class="ss-co-product__qty"><?php echo esc_html((string) $item->get_quantity()); ?></span>
								</div>
								<div class="ss-co-product__info">
									<div class="ss-co-product__top">
										<span class="ss-co-product__name"><?php echo wp_kses_post($item->get_name()); ?></span>
									</div>
									<div class="ss-co-product__prices">
										<span class="ss-co-product__price"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></span>
									</div>
									<div class="ss-co-product__meta">
										<?php
										ob_start();
										do_action('woocommerce_order_item_meta_start', $item_id, $item, $order, false);
										wc_display_item_meta($item);
										do_action('woocommerce_order_item_meta_end', $item_id, $item, $order, false);
										$meta = (string) ob_get_clean();
										$meta = str_replace(
											array('G-pass:', 'G-Pass:', 'Tickets:', 'Ticket:'),
											array('Tus DigiTickets:', 'Tus DigiTickets:', 'Tus DigiTickets:', 'Tus DigiTickets:'),
											$meta
										);
										echo wp_kses_post($meta);
										?>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<div class="ss-co-totals">
						<?php if ($totals) : ?>
							<?php foreach ($totals as $key => $total) : ?>
								<?php
								if ($key === 'payment_method') {
									continue;
								}
								$row_class = $key === 'order_total' ? 'ss-co-totals__row ss-co-totals__total' : 'ss-co-totals__row';
								if (strpos((string) $key, 'fee_') === 0 || $key === 'discount' || strpos((string) $key, 'coupon') !== false) {
									$row_class .= ' ss-co-totals__row--discount';
								}
								?>
								<div class="<?php echo esc_attr($row_class); ?>">
									<span><?php echo wp_kses_post($total['label']); ?></span>
									<span><?php echo wp_kses_post($total['value']); ?></span>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</section>

				<section class="ss-co-pay__card ss-co-order-pay__pay">
					<header class="ss-co-step__head">
						<span class="ss-co-step__num" aria-hidden="true">1</span>
						<div>
							<h3>Método de pago</h3>
							<p class="step-description">Elige cómo quieres pagar tu pedido de forma segura.</p>
						</div>
					</header>

					<div id="payment" class="ss-co-pay">
						<?php if ($order->needs_payment()) : ?>
							<ul class="wc_payment_methods payment_methods methods">
								<?php
								if (!empty($available_gateways)) {
									foreach ($available_gateways as $gateway) {
										wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
									}
								} else {
									echo '<li>';
									wc_print_notice(
										apply_filters(
											'woocommerce_no_available_payment_methods_message',
											esc_html__('Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce')
										),
										'notice'
									);
									echo '</li>';
								}
								?>
							</ul>
						<?php endif; ?>

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

						<div class="form-row ss-co-order-pay__submit">
							<input type="hidden" name="woocommerce_pay" value="1" />

							<?php wc_get_template('checkout/terms.php'); ?>

							<?php do_action('woocommerce_pay_order_before_submit'); ?>

							<?php
							$button = '<button type="submit" class="button alt ss-co-place" name="woocommerce_pay" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '">';
							$button .= '<span class="ss-co-place__ico" aria-hidden="true">' . $ico_lock . '</span>';
							$button .= esc_html($order_button_text);
							$button .= '</button>';
							echo apply_filters('woocommerce_pay_order_button_html', $button); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>

							<?php do_action('woocommerce_pay_order_after_submit'); ?>

							<?php wp_nonce_field('woocommerce-pay', 'woocommerce-pay-nonce'); ?>
						</div>

						<div class="ss-co-postinfo">
							<span class="ss-co-postinfo__ico" aria-hidden="true"><?php echo $ico_shield; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<p>Después de completar el pago, recibirás tus <strong>DigiTickets</strong> inmediatamente en tu correo electrónico y también estarán disponibles en tu perfil de usuario.</p>
						</div>
					</div>
				</section>
			</div>
		</form>

		<div class="ss-co-side-trust ss-co-order-pay__trust">
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
	</div>
</div>
<?php
if (class_exists('SorteoSeguro_Checkout')) {
	SorteoSeguro_Checkout::render_help_block();
}
