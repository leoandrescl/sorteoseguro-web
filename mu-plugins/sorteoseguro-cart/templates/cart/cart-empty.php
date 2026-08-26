<?php
/**
 * Empty cart – diseño Sorteo Seguro
 *
 * @see sorteoseguro-cart
 */
defined('ABSPATH') || exit;

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Cart') ? SorteoSeguro_Cart::icon($name) : '';
};
$shop = get_permalink(74938) ?: home_url('/');
?>
<div class="ss-cart ss-cart--empty">
	<div class="ss-cart__shell">
		<?php woocommerce_output_all_notices(); ?>

		<div class="ss-cart__intro ss-cart__intro--empty">
			<div class="ss-cart__title-wrap">
				<span class="ss-cart__title-ico" aria-hidden="true"><?php echo $ico('cart'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div>
					<h1 class="ss-cart__title">Tu carrito</h1>
					<p class="ss-cart__subtitle">Revisa tus productos antes de continuar.</p>
				</div>
			</div>
		</div>

		<section class="ss-cart-empty">
			<span class="ss-cart-empty__ico" aria-hidden="true"><?php echo $ico('cart'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h2>Tu carrito está vacío</h2>
			<p>Cuando agregues DigiTickets, aparecerán aquí listos para pagar.</p>
			<a class="ss-cart-cta ss-cart-cta--empty" href="<?php echo esc_url($shop); ?>">
				<span>Ver concursos</span>
				<span class="ss-cart-cta__arrow" aria-hidden="true"><?php echo $ico('chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</a>
		</section>

		<?php include dirname(__DIR__) . '/partials/trust.php'; ?>
	</div>
</div>
