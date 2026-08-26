<?php
/**
 * Plugin Name: Sorteo Seguro – TUU guard
 * Description: Restaura checkout en /finalizar-compra/ si TUU lo reemplazó. Evita redirect al home tras pagar con TUU.
 * Author: Sorteo Seguro
 * Version: 1.0.1
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('init', static function (): void {
	if (get_option('ss_tuu_checkout_restored')) {
		return;
	}
	$page = get_page_by_path('finalizar-compra');
	if (!$page instanceof WP_Post) {
		return;
	}
	$target = (int) $page->ID;
	$current = (int) get_option('woocommerce_checkout_page_id');
	if ($current !== $target) {
		update_option('woocommerce_checkout_page_id', $target);
	}
	update_option('ss_tuu_checkout_restored', 1);
}, 1);

/**
 * TUU (wcplugingateway) imprime un setTimeout al home en thank-you.
 * Completamos el pago igual, sin redirigir.
 */
add_action('wp', static function (): void {
	if (!function_exists('is_order_received_page') || !is_order_received_page()) {
		return;
	}
	remove_all_actions('woocommerce_thankyou_wcplugingateway');
	add_action('woocommerce_thankyou_wcplugingateway', 'ss_tuu_complete_without_home_redirect', 10, 1);
}, 20);

/**
 * @param mixed $order_id
 */
function ss_tuu_complete_without_home_redirect($order_id): void {
	$result = isset($_GET['x_result']) ? sanitize_text_field(wp_unslash((string) $_GET['x_result'])) : '';
	if ($result !== 'completed') {
		return;
	}
	$order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
	if (!$order instanceof WC_Order) {
		return;
	}
	if (!$order->is_paid()) {
		$order->payment_complete();
		$order->add_order_note(__('Pago completado', 'woocommerce'));
	}
	if ($order->get_status() !== 'completed') {
		$order->update_status('completed', __('Pago completado', 'woocommerce'));
	}
	if (function_exists('WC') && WC()->cart) {
		WC()->cart->empty_cart();
	}
}

add_action('wp_head', static function (): void {
	if (!function_exists('is_order_received_page') || !is_order_received_page()) {
		return;
	}
	echo "\n<script id=\"ss-tuu-no-home-redirect\" data-no-optimize=\"1\">\n";
	echo "(function(){var _st=window.setTimeout;window.setTimeout=function(fn,ms){";
	echo "if(typeof fn==='function'&&ms>=4000&&ms<=8000){try{var s=Function.prototype.toString.call(fn);";
	echo "if(/location\\.href/.test(s)){return 0;}}catch(e){}}";
	echo "return _st.apply(this,arguments);};})();\n";
	echo "</script>\n";
}, 1);
