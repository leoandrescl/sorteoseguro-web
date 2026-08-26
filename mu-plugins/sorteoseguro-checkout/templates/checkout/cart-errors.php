<?php
/**
 * Checkout cart errors — reserva expirada / ítems inválidos.
 * No es el formulario de pago; WooCommerce carga esto en lugar de form-checkout.php.
 *
 * @see sorteoseguro-checkout
 */
defined('ABSPATH') || exit;

$home = home_url('/');
?>
<div class="ss-co ss-co--errors">
	<div class="ss-co-errors">
		<p class="ss-co-errors__msg">
			<?php esc_html_e('There are some issues with the items in your cart. Please go back to the cart page and resolve these issues before checking out.', 'woocommerce'); ?>
		</p>
		<?php do_action('woocommerce_cart_has_errors'); ?>
		<a class="ss-co-errors__cta" href="<?php echo esc_url($home); ?>">Ver concursos</a>
	</div>
</div>
