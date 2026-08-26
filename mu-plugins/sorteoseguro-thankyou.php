<?php
/**
 * Plugin Name: Sorteo Seguro – Página de éxito
 * Description: Plantilla PHP de thank-you (order-received) según diseño, con chrome header/footer.
 * Author: Sorteo Seguro
 * Version: 1.0.3
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Thankyou {

	const VERSION = '1.0.3';

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_action('wp_head', [__CLASS__, 'inline_css'], 100);
		add_action('wp_footer', [__CLASS__, 'inline_js'], 5);
		add_action('wp', [__CLASS__, 'unhook_legacy']);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
	}

	public static function is_thankyou(): bool {
		return function_exists('is_order_received_page') && is_order_received_page();
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_thankyou() ? true : $enabled;
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_thankyou()) {
			$classes[] = 'ss-thankyou-template';
			$classes[] = 'woocommerce-checkout';
		}
		return $classes;
	}

	public static function bypass_page_cache(): void {
		if (!self::is_thankyou()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-thankyou');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	public static function unhook_legacy(): void {
		if (!self::is_thankyou()) {
			return;
		}
		remove_action('woocommerce_thankyou', 'agregar_botones_personalizados_agradecimiento', 20);
	}

	public static function template_include(string $template): string {
		if (!self::is_thankyou()) {
			return $template;
		}
		$custom = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-thankyou/page-thankyou.php';
		return is_readable($custom) ? $custom : $template;
	}

	public static function dir(): string {
		return WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-thankyou';
	}

	public static function url(): string {
		return content_url('mu-plugins/sorteoseguro-thankyou');
	}

	public static function assets(): void {
		if (!self::is_thankyou()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = self::url() . '/assets';
		$dir  = self::dir() . '/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($dir . '/thankyou.css')) {
			wp_enqueue_style('ss-thankyou', $base . '/thankyou.css', $deps, $ver);
		}
		if (is_readable($dir . '/thankyou.js')) {
			wp_enqueue_script('ss-thankyou', $base . '/thankyou.js', [], $ver, true);
		}
	}

	public static function inline_css(): void {
		if (!self::is_thankyou()) {
			return;
		}
		$path = self::dir() . '/assets/thankyou.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-thankyou v" . esc_html(self::VERSION) . " -->\n<style id=\"ss-thankyou-inline\">\n" . $css . "\n</style>\n";
	}

	public static function inline_js(): void {
		if (!self::is_thankyou()) {
			return;
		}
		$path = self::dir() . '/assets/thankyou.js';
		if (!is_readable($path)) {
			return;
		}
		$js = file_get_contents($path);
		if ($js === false || $js === '') {
			return;
		}
		echo "\n<script id=\"ss-thankyou-inline-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n" . $js . "\n</script>\n";
	}

	public static function current_order(): ?\WC_Order {
		if (!function_exists('wc_get_order')) {
			return null;
		}
		$order_id = absint(get_query_var('order-received'));
		if ($order_id <= 0) {
			return null;
		}
		$order = wc_get_order($order_id);
		if (!$order instanceof \WC_Order) {
			return null;
		}
		$key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
		if ($key !== '' && hash_equals($order->get_order_key(), $key)) {
			return $order;
		}
		if (is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id()) {
			return $order;
		}
		return null;
	}

	public static function money(float $amount): string {
		$neg = $amount < 0;
		$txt = '$' . number_format(abs($amount), 0, ',', '.');
		return $neg ? ('-' . $txt) : $txt;
	}

	public static function format_date(\WC_Order $order): string {
		$created = $order->get_date_created();
		$ts = $created ? $created->getTimestamp() : time();
		$months = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
		$n = (int) wp_date('n', $ts);
		return ($months[$n] ?? '') . ' ' . wp_date('j, Y', $ts);
	}

	/** @return string[] */
	public static function item_tickets($item): array {
		$tickets = $item->get_meta('_lty_lottery_tickets');
		if (is_string($tickets) && $tickets !== '') {
			$tickets = preg_split('/\s*,\s*/', wp_strip_all_tags($tickets)) ?: [];
		}
		if (!is_array($tickets) || $tickets === []) {
			$label = function_exists('lty_get_order_item_ticket_number_name')
				? lty_get_order_item_ticket_number_name()
				: 'Tus DigiTickets';
			$html = $item->get_meta($label);
			if (!is_string($html) || $html === '') {
				$html = (string) $item->get_meta('Ticket Number( s )');
			}
			if ($html !== '') {
				$tickets = preg_split('/\s*,\s*/', wp_strip_all_tags($html)) ?: [];
			}
		}
		$out = [];
		foreach ((array) $tickets as $t) {
			$t = trim((string) $t);
			if ($t !== '') {
				$out[] = $t;
			}
		}
		return $out;
	}

	public static function tickets_url(): string {
		if (function_exists('lty_get_dashboard_participated_lotteries_endpoint_url') && function_exists('wc_get_account_endpoint_url')) {
			$ep = lty_get_dashboard_participated_lotteries_endpoint_url();
			if (is_string($ep) && $ep !== '') {
				return (string) wc_get_account_endpoint_url($ep);
			}
		}
		return self::orders_url();
	}

	public static function orders_url(): string {
		if (function_exists('wc_get_account_endpoint_url')) {
			$url = (string) wc_get_account_endpoint_url('orders');
			if ($url !== '') {
				return $url;
			}
		}
		return home_url('/mi-cuenta/orders/');
	}

	public static function shop_url(): string {
		$url = function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : '';
		return $url !== '' ? $url : home_url('/');
	}

	public static function packs_url(): string {
		if (class_exists('SorteoSeguro_Home')) {
			return SorteoSeguro_Home::pack_cta_url();
		}
		return 'https://sorteoseguro.cl/producto/jeep-avenger/';
	}

	public static function faq_url(): string {
		$faq = get_permalink(15);
		if ($faq) {
			return (string) $faq;
		}
		return class_exists('SorteoSeguro_Chrome')
			? SorteoSeguro_Chrome::page_url('preguntas_frecuentes')
			: home_url('/preguntas_frecuentes/');
	}

	public static function contest_title(string $name): string {
		$name = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::clean_title($name) : $name;
		if (!preg_match('/^sorteo\b/iu', $name)) {
			$name = 'Sorteo ' . $name;
		}
		return $name;
	}

	public static function category_label(string $slug, string $fallback): string {
		if ($slug === 'vehicle') {
			return 'VEHICULAR';
		}
		if ($slug === 'realty') {
			return 'INMOBILIARIO';
		}
		$fallback = strtoupper($fallback);
		if ($fallback === 'VEHÍCULO' || $fallback === 'VEHICULO') {
			return 'VEHICULAR';
		}
		return $fallback !== '' ? $fallback : 'SORTEO';
	}

	public static function fire_thankyou(\WC_Order $order): void {
		$order_id = $order->get_id();
		remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
		remove_action('woocommerce_thankyou', 'agregar_botones_personalizados_agradecimiento', 20);
		echo '<div class="ss-ty-wc-hooks" hidden>';
		if (did_action('woocommerce_before_thankyou') === 0) {
			do_action('woocommerce_before_thankyou', $order_id);
		}
		if (did_action('woocommerce_thankyou_' . $order->get_payment_method()) === 0) {
			do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order_id);
		}
		if (did_action('woocommerce_thankyou') === 0) {
			do_action('woocommerce_thankyou', $order_id);
		}
		echo '</div>';
	}
}

SorteoSeguro_Thankyou::init();
