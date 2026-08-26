<?php
/**
 * Trust chips + pasarelas (carrito)
 */
defined('ABSPATH') || exit;

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Cart') ? SorteoSeguro_Cart::icon($name) : '';
};
$logos = class_exists('SorteoSeguro_Cart') ? SorteoSeguro_Cart::pay_logos() : [];
?>
<section class="ss-cart-trust" aria-label="Compra protegida">
	<article class="ss-cart-trust__item">
		<span aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<strong>Pago 100% seguro</strong>
			<p>Tus datos están protegidos</p>
		</div>
	</article>
	<article class="ss-cart-trust__item">
		<span aria-hidden="true"><?php echo $ico('ticket'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<strong>DigiTickets automáticos</strong>
			<p>Recibes al instante</p>
		</div>
	</article>
	<article class="ss-cart-trust__item">
		<span aria-hidden="true"><?php echo $ico('headset'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<strong>Soporte confiable</strong>
			<p>Estamos para ayudarte</p>
		</div>
	</article>
	<article class="ss-cart-trust__item">
		<span aria-hidden="true"><?php echo $ico('refresh'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<strong>Proceso rápido</strong>
			<p>Compra en pocos pasos</p>
		</div>
	</article>
</section>

<section class="ss-cart-pay">
	<div class="ss-cart-pay__copy">
		<span class="ss-cart-pay__ico" aria-hidden="true"><?php echo $ico('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<div>
			<strong>Tu compra está protegida</strong>
			<p>En Sorteo Seguro utilizamos las mejores tecnologías para cuidar tu información y tu compra.</p>
		</div>
	</div>
	<div class="ss-cart-pay__logos">
		<?php if (!empty($logos['webpay']['src'])) : ?>
			<img src="<?php echo esc_url($logos['webpay']['src']); ?>" alt="<?php echo esc_attr($logos['webpay']['alt']); ?>" width="220" height="64" loading="lazy" decoding="async">
		<?php endif; ?>
		<?php if (!empty($logos['mp']['src'])) : ?>
			<img src="<?php echo esc_url($logos['mp']['src']); ?>" alt="<?php echo esc_attr($logos['mp']['alt']); ?>" width="220" height="64" loading="lazy" decoding="async">
		<?php endif; ?>
	</div>
</section>
