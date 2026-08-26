<?php
/**
 * Plugin Name: Sorteo Seguro – GTM / dataLayer
 * Description: GTM lo más arriba posible en wp_head y dataLayer compra_exitosa en thank-you. Sin Meta Pixel (fbq).
 * Author: Sorteo Seguro
 * Version: 1.0.1
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_GTM {

	const VERSION   = '1.0.1';
	const CONTAINER = 'GTM-M2XP9TVD';

	public static function init(): void {
		// GTM4WP: container pasa de wp_head:10 a :2 (dataLayer init ya está en :1).
		add_filter('option_gtm4wp-options', [__CLASS__, 'force_gtm4wp_early']);
		add_action('wp_loaded', [__CLASS__, 'rehook_gtm4wp_container'], 0);

		add_action('woocommerce_thankyou', [__CLASS__, 'datalayer_compra_exitosa'], 5, 1);
	}

	/**
	 * Activa “load GTM early” de GTM4WP (prioridad 2, justo tras el init del dataLayer).
	 *
	 * @param mixed $opts
	 * @return mixed
	 */
	public static function force_gtm4wp_early($opts) {
		if (is_array($opts)) {
			$opts['gtm-load-gtm-early'] = true;
		}
		return $opts;
	}

	public static function rehook_gtm4wp_container(): void {
		if (!function_exists('gtm4wp_wp_header_begin')) {
			return;
		}
		remove_action('wp_head', 'gtm4wp_wp_header_begin', 10);
		remove_action('wp_head', 'gtm4wp_wp_header_begin', 2);
		add_action('wp_head', 'gtm4wp_wp_header_begin', 2, 0);
	}

	public static function datalayer_compra_exitosa($order_id): void {
		static $printed = false;
		if ($printed) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}
		if ($order->has_status(['failed', 'cancelled', 'refunded'])) {
			return;
		}

		$printed         = true;
		$billing_email   = (string) $order->get_billing_email();
		$billing_phone   = (string) $order->get_billing_phone();
		$transaction_id  = (string) $order->get_id();
		$value           = (float) $order->get_total();
		$currency        = (string) $order->get_currency();
		if ($currency === '') {
			$currency = 'CLP';
		}
		$new_customer = self::is_new_customer($order) ? 'true' : 'false';
		?>
<script data-no-optimize="1" data-no-defer="1">
window.dataLayer = window.dataLayer || [];
window.dataLayer.push({
  'event': 'compra_exitosa',
  'new_customer': <?php echo $new_customer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>,
  'customer_email': '<?php echo esc_js($billing_email); ?>',
  'customer_phone': '<?php echo esc_js($billing_phone); ?>',
  'transaction_id': '<?php echo esc_js($transaction_id); ?>',
  'value': <?php echo esc_js((string) round($value, wc_get_price_decimals())); ?>,
  'currency': '<?php echo esc_js($currency); ?>'
});
</script>
		<?php
	}

	private static function is_new_customer(WC_Order $order): bool {
		$email = (string) $order->get_billing_email();
		if ($email === '' || !function_exists('wc_get_orders')) {
			return true;
		}
		$prev = wc_get_orders([
			'billing_email' => $email,
			'status'        => ['completed', 'processing'],
			'exclude'       => [$order->get_id()],
			'limit'         => 1,
			'return'        => 'ids',
		]);
		return empty($prev);
	}
}

SorteoSeguro_GTM::init();
