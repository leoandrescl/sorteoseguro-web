<?php
/**
 * Plugin Name: Sorteo Seguro – Carrito
 * Description: Plantilla PHP del carrito según diseño, con chrome header/footer y shell 1400px.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Cart {

	const VERSION  = '1.0.5';
	const PAGE_ID  = 12;
	const DIR      = __DIR__ . '/sorteoseguro-cart';

	public static function init(): void {
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_filter('woocommerce_locate_template', [__CLASS__, 'locate_template'], 60, 3);
		add_filter('the_content', [__CLASS__, 'force_classic_shortcode'], 5);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('woocommerce_get_notices', [__CLASS__, 'filter_notices'], 50, 1);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 60);
		add_action('wp_footer', [__CLASS__, 'inline_assets'], 20);
		add_action('wp_head', [__CLASS__, 'print_font_override'], 99999);
		add_action('wp_footer', [__CLASS__, 'print_font_override'], 99999);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
		add_action('wp', [__CLASS__, 'unhook_cross_sells']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_blocks'], 100);
	}

	public static function is_cart(): bool {
		if (function_exists('is_cart') && is_cart()) {
			return true;
		}
		return is_page(self::PAGE_ID);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_cart() ? true : $enabled;
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_cart()) {
			$classes[] = 'ss-cart-template';
		}
		return $classes;
	}

	public static function bypass_page_cache(): void {
		if (!self::is_cart()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-cart');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	public static function unhook_cross_sells(): void {
		if (!self::is_cart()) {
			return;
		}
		remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
		remove_action('woocommerce_before_cart', 'woocommerce_output_all_notices', 10);
	}

	public static function dequeue_blocks(): void {
		if (!self::is_cart()) {
			return;
		}
		wp_dequeue_style('wc-blocks-style');
		wp_dequeue_style('wc-blocks-vendors-style');
		wp_dequeue_script('wc-cart-block-frontend');
		wp_dequeue_script('wc-cart-checkout-base-frontend');
		wp_dequeue_script('wc-cart-checkout-base');
	}

	public static function locate_template(string $template, string $template_name, string $template_path): string {
		$custom = self::DIR . '/templates/' . ltrim($template_name, '/');
		if (is_readable($custom)) {
			return $custom;
		}
		return $template;
	}

	public static function force_classic_shortcode(string $content): string {
		if (!self::is_cart() || is_admin()) {
			return $content;
		}
		return '[woocommerce_cart]';
	}

	/**
	 * @param mixed $value
	 * @param mixed $object_id
	 * @param mixed $meta_key
	 * @param mixed $single
	 * @return mixed
	 */
	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		if ((int) $object_id !== self::PAGE_ID) {
			return $value;
		}
		return $single ? '' : [''];
	}

	/**
	 * @param array<string, mixed> $notices
	 * @return array<string, mixed>
	 */
	public static function filter_notices(array $notices): array {
		if (!self::is_cart()) {
			return $notices;
		}
		foreach (['notice', 'success', 'error'] as $type) {
			if (empty($notices[$type]) || !is_array($notices[$type])) {
				continue;
			}
			$notices[$type] = array_values(array_filter($notices[$type], static function ($item) {
				$msg = is_array($item) ? (string) ($item['notice'] ?? '') : (string) $item;
				if ($msg === '') {
					return true;
				}
				if (stripos($msg, 'reservado por') !== false) {
					return false;
				}
				if (stripos($msg, 'is reserved for') !== false) {
					return false;
				}
				if (stripos($msg, 'completa tu compra') !== false) {
					return false;
				}
				if (stripos($msg, 'complete your purchase') !== false) {
					return false;
				}
				return true;
			}));
			if (!$notices[$type]) {
				unset($notices[$type]);
			}
		}
		return $notices;
	}

	public static function print_critical_css(): void {
		if (!self::is_cart()) {
			return;
		}
		$path = self::DIR . '/assets/cart.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-cart critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-cart-critical">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function assets(): void {
		if (!self::is_cart()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = content_url('mu-plugins/sorteoseguro-cart/assets');
		$dir  = self::DIR . '/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($dir . '/cart.css')) {
			wp_enqueue_style('ss-cart', $base . '/cart.css', $deps, $ver);
		}
		wp_enqueue_script('wc-cart');
		if (is_readable($dir . '/cart.js')) {
			wp_enqueue_script('ss-cart', $base . '/cart.js', ['jquery', 'wc-cart'], $ver, true);
		}
	}

	public static function print_font_override(): void {
		if (!self::is_cart()) {
			return;
		}
		$hook = (string) current_filter();
		static $printed = [];
		if (isset($printed[$hook])) {
			return;
		}
		$printed[$hook] = true;
		$stack = class_exists('SorteoSeguro_Chrome')
			? SorteoSeguro_Chrome::FONT_STACK
			: '"Montserrat", "Poppins", "Segoe UI", sans-serif';
		$id = $hook === 'wp_footer' ? 'ss-cart-font-footer' : 'ss-cart-font';
		echo "\n<style id=\"{$id}\">\n";
		echo ":root{--ss-font-family:{$stack};}\n";
		echo "body.woocommerce-cart .ss-cart,body.woocommerce-cart .ss-cart *:not(svg):not(svg *),";
		echo "body.ss-cart-template .ss-cart,body.ss-cart-template .ss-cart *:not(svg):not(svg *)";
		echo "{font-family:{$stack}!important;}\n";
		echo "</style>\n";
	}

	public static function inline_assets(): void {
		if (!self::is_cart()) {
			return;
		}
		$css = self::DIR . '/assets/cart.css';
		$js  = self::DIR . '/assets/cart.js';
		if (is_readable($css)) {
			echo "\n<!-- ss-cart v" . esc_html(self::VERSION) . " -->\n<style id=\"ss-cart-inline\">\n";
			echo file_get_contents($css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</style>\n";
		}
		if (is_readable($js)) {
			echo "<script id=\"ss-cart-inline-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n";
			echo file_get_contents($js); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</script>\n";
		}
	}

	/** @return array<int, string> */
	public static function item_tickets(array $cart_item): array {
		$tickets = [];
		if (!empty($cart_item['lty_lottery']['tickets']) && is_array($cart_item['lty_lottery']['tickets'])) {
			$tickets = array_map('strval', $cart_item['lty_lottery']['tickets']);
		} elseif (!empty($cart_item['lty_lottery_ticket_numbers'])) {
			$raw = $cart_item['lty_lottery_ticket_numbers'];
			if (is_array($raw)) {
				$tickets = array_map('strval', $raw);
			} else {
				$tickets = preg_split('/\s*,\s*/', (string) $raw) ?: [];
			}
		}
		$tickets = array_values(array_filter(array_map('trim', $tickets)));
		return $tickets;
	}

	public static function save_label(float $amount): string {
		return 'SAVE ' . wp_strip_all_tags(wc_price($amount));
	}

	public static function pay_logos(): array {
		return [
			'webpay' => [
				'src' => set_url_scheme(content_url('mu-plugins/sorteoseguro-chrome/assets/logo-web-pay-plus.png'), 'https'),
				'alt' => 'Webpay Plus',
			],
			'mp'     => [
				'src' => set_url_scheme(content_url('mu-plugins/sorteoseguro-chrome/assets/logo-mercado-pago.png'), 'https'),
				'alt' => 'Mercado Pago',
			],
		];
	}

	public static function icon(string $name): string {
		$map = [
			'cart'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="20" r="1.55" fill="currentColor" stroke="none"/><circle cx="18" cy="20" r="1.55" fill="currentColor" stroke="none"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3.2 4.2h1.85l.55 2.15m0 0L7.4 14.6h11.15l1.85-7.25H5.6z"/></svg>',
			'trash'    => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="2.35" stroke-linecap="round" stroke-linejoin="round" d="M3.2 7.2h17.6M8.6 7.2V5.15A2 2 0 0 1 10.6 3.2h2.8a2 2 0 0 1 2 1.95V7.2M6.2 7.2l1.15 13.3h9.3L17.8 7.2"/><path stroke="currentColor" stroke-width="2.35" stroke-linecap="round" d="M10 10.6v6.4M14 10.6v6.4"/></svg>',
			'shield'   => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
			'tag'      => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M3.8 12.2V5.8A1.8 1.8 0 0 1 5.6 4h6.4L20 11.8l-7.8 7.8z"/><circle cx="8.2" cy="8.2" r="1.15" fill="currentColor" stroke="none"/></svg>',
			'star'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 3.2 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 15.6 7.2 18.1l.9-5.4-3.9-3.8 5.4-.8z"/></svg>',
			'lock'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10.2" width="14" height="10.2" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M8.2 10.2V7.6a3.8 3.8 0 0 1 7.6 0v2.6"/></svg>',
			'chevron'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9.3 6.7 10.7 5.3 17.4 12l-6.7 6.7-1.4-1.4L14.6 12z"/></svg>',
			'ticket'   => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M3.5 9.2A2.2 2.2 0 0 0 5.7 7h12.6a2.2 2.2 0 0 0 2.2 2.2v1.1a2.2 2.2 0 0 1 0 4.4v1.1A2.2 2.2 0 0 0 18.3 18H5.7A2.2 2.2 0 0 0 3.5 15.8v-1.1a2.2 2.2 0 0 1 0-4.4V9.2z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-dasharray="2 2.2" d="M12 7.2v10.6"/></svg>',
			'headset'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M4.8 13V11a7.2 7.2 0 0 1 14.4 0v2"/><rect x="3.2" y="12.2" width="4.2" height="6.2" rx="1.4" stroke="currentColor" stroke-width="1.7"/><rect x="16.6" y="12.2" width="4.2" height="6.2" rx="1.4" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M19 18.4v.8A2.6 2.6 0 0 1 16.4 21.8h-2"/></svg>',
			'refresh'  => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M20 12a8 8 0 1 1-2.3-5.6"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="M20 5.2V12h-6.8"/></svg>',
			'minus'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M5.5 11h13v2h-13z"/></svg>',
			'plus'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 5.5h2V11h5.5v2H13v5.5h-2V13H5.5v-2H11z"/></svg>',
		];
		return $map[$name] ?? $map['cart'];
	}
}

SorteoSeguro_Cart::init();
