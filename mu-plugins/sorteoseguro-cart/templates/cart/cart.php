<?php
/**
 * Cart – diseño Sorteo Seguro
 *
 * @see sorteoseguro-cart
 */
defined('ABSPATH') || exit;

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Cart') ? SorteoSeguro_Cart::icon($name) : '';
};
?>
<div class="ss-cart">
	<div class="ss-cart__shell">
		<?php
		do_action('woocommerce_before_cart');
		woocommerce_output_all_notices();
		?>

		<div class="ss-cart__grid">
			<div class="ss-cart__intro">
				<div class="ss-cart__title-wrap">
					<span class="ss-cart__title-ico" aria-hidden="true"><?php echo $ico('cart'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h1 class="ss-cart__title">Tu carrito</h1>
						<p class="ss-cart__subtitle">Revisa tus productos antes de continuar.</p>
					</div>
				</div>
				<div class="ss-cart__secure">
					<span class="ss-cart__secure-ico" aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<strong>Compra segura</strong>
						<span>Tus DigiTickets se registran automáticamente</span>
					</div>
				</div>
			</div>
			<h2 class="ss-cart__summary-title">Resumen de tu compra</h2>

			<form class="woocommerce-cart-form ss-cart__main" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
				<?php do_action('woocommerce_before_cart_table'); ?>

				<section class="ss-cart-table" aria-label="Productos del carrito">
					<div class="ss-cart-table__head" aria-hidden="true">
						<span>Producto</span>
						<span>Precio unitario</span>
						<span>Cantidad</span>
						<span>Total</span>
					</div>

					<?php
					foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
						$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
						if (!$_product || !$_product->exists() || $cart_item['quantity'] <= 0) {
							continue;
						}

						$product_id   = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
						$permalink    = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
						$name         = wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key));
						$qty          = (int) $cart_item['quantity'];
						$unit_regular = (float) $_product->get_regular_price();
						$unit_current = (float) $_product->get_price();
						$on_sale      = $_product->is_on_sale() && $unit_regular > $unit_current;
						$unit_save    = $on_sale ? ($unit_regular - $unit_current) : 0.0;
						$line_save    = $unit_save * $qty;
						$line_total   = $unit_current * $qty;
						$tickets      = class_exists('SorteoSeguro_Cart') ? SorteoSeguro_Cart::item_tickets($cart_item) : [];
						$thumbnail    = $_product->get_image([120, 120]);
						$remove_url   = wc_get_cart_remove_url($cart_item_key);
						?>
						<article class="ss-cart-item woocommerce-cart-form__cart-item">
							<div class="ss-cart-item__product">
								<div class="ss-cart-item__media">
									<?php if ($permalink) : ?>
										<a class="ss-cart-item__thumb" href="<?php echo esc_url($permalink); ?>">
											<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<span class="ss-cart-item__badge">DigiTicket</span>
										</a>
									<?php else : ?>
										<div class="ss-cart-item__thumb">
											<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<span class="ss-cart-item__badge">DigiTicket</span>
										</div>
									<?php endif; ?>
									<div class="product-remove">
										<a
											class="remove ss-cart-item__remove"
											href="<?php echo esc_url($remove_url); ?>"
											aria-label="Eliminar producto"
											data-product_id="<?php echo esc_attr((string) $product_id); ?>"
											data-product_sku="<?php echo esc_attr($_product->get_sku()); ?>"
											data-cart_item_key="<?php echo esc_attr($cart_item_key); ?>"
										>
											<?php echo $ico('trash'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</a>
									</div>
								</div>
								<div class="ss-cart-item__info">
									<?php if ($permalink) : ?>
										<a class="ss-cart-item__name" href="<?php echo esc_url($permalink); ?>"><?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
									<?php else : ?>
										<span class="ss-cart-item__name"><?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php endif; ?>

									<?php if ($tickets) : ?>
										<p class="ss-cart-item__tickets">
											<strong>Tus DigiTickets:</strong>
											<?php echo esc_html(implode(', ', $tickets)); ?>
										</p>
									<?php endif; ?>
								</div>
							</div>

							<div class="ss-cart-item__unit" data-title="Precio unitario">
								<?php if ($on_sale) : ?>
									<s><?php echo wp_kses_post(wc_price($unit_regular)); ?></s>
								<?php endif; ?>
								<strong><?php echo wp_kses_post(wc_price($unit_current)); ?></strong>
								<?php if ($on_sale) : ?>
									<span class="ss-cart-save"><?php echo esc_html(SorteoSeguro_Cart::save_label($unit_save)); ?></span>
								<?php endif; ?>
							</div>

							<div class="ss-cart-item__qty" data-title="Cantidad">
								<div class="ss-cart-qty" aria-label="<?php echo esc_attr('Cantidad: ' . $qty); ?>">
									<input type="hidden" name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]" value="<?php echo (int) $qty; ?>">
									<span class="ss-cart-qty__value"><?php echo (int) $qty; ?></span>
								</div>
							</div>

							<div class="ss-cart-item__total" data-title="Total">
								<strong><?php echo wp_kses_post(wc_price($line_total)); ?></strong>
								<?php if ($line_save > 0) : ?>
									<span class="ss-cart-save"><?php echo esc_html(SorteoSeguro_Cart::save_label($line_save)); ?></span>
								<?php endif; ?>
							</div>
						</article>
						<?php
					}
					?>
				</section>

				<div class="ss-cart-safe">
					<span class="ss-cart-safe__ico" aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<strong>Eliminación segura</strong>
						<p>Puedes eliminar productos de tu carrito en cualquier momento.</p>
					</div>
				</div>

				<button type="submit" class="button ss-cart-update" name="update_cart" value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>" hidden disabled><?php esc_html_e('Update cart', 'woocommerce'); ?></button>
				<?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
				<?php do_action('woocommerce_after_cart_table'); ?>
			</form>

			<aside class="ss-cart__side" aria-label="Resumen de tu compra">
				<?php woocommerce_cart_totals(); ?>
			</aside>
		</div>

		<?php include __DIR__ . '/../partials/trust.php'; ?>
	</div>
</div>
<?php do_action('woocommerce_after_cart'); ?>
