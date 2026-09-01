<?php
/**
 * Plugin Name: Sorteo Seguro – Compra directa
 * Description: Página /comprar/{slug}/ con packs + checkout embebido. Aislada de la ficha PDP y del checkout standalone.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * Rollback: borrar este archivo y la carpeta sorteoseguro-comprar/ (+ páginas bajo /comprar/ en WP si se desea).
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Comprar {

	const VERSION        = '1.0.21';
	const DIR            = __DIR__ . '/sorteoseguro-comprar';
	const PARENT_SLUG    = 'comprar';
	const META_PRODUCT   = '_ss_comprar_product_id';
	const OPTION_SEEDED  = 'ss_comprar_pages_seeded_v1';

	/** slug => product_id (sorteos con ficha PDP piloto). */
	private static array $product_slugs = [
		'yamaha-fz25'       => 874,
		'vista-mar-dunares' => 1091,
		'jeep-avenger'      => 45941,
		'peugeot-208'       => 53210,
		'parcela-choros'    => 49102,
	];

	public static function init(): void {
		add_action('init', [__CLASS__, 'maybe_seed_pages'], 30);
		add_filter('template_include', [__CLASS__, 'template_include'], 99998);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 55);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
		add_action('template_redirect', [__CLASS__, 'boot_wc_cart'], 5);
		add_action('wp_enqueue_scripts', [__CLASS__, 'boot_wc_cart'], 1);

		add_filter('ss_packs_lottery_product_id', [__CLASS__, 'filter_packs_product_id']);
		add_filter('ss_packs_lottery_config', [__CLASS__, 'filter_packs_config']);
		add_filter('ss_packs_select_redirect', [__CLASS__, 'filter_packs_redirect'], 10, 3);
		add_filter('ss_packs_select_embedded', [__CLASS__, 'filter_packs_embedded'], 10, 3);

		add_filter('ss_checkout_active_surface', [__CLASS__, 'filter_checkout_active_surface']);
		add_filter('woocommerce_is_checkout', [__CLASS__, 'filter_wc_is_checkout'], 99999);
		add_filter('ss_checkout_form_action', [__CLASS__, 'filter_checkout_form_action']);
		add_filter('woocommerce_get_checkout_order_received_url', [__CLASS__, 'filter_order_received_url'], 10, 2);
		add_action('wp_enqueue_scripts', [__CLASS__, 'override_wc_checkout_ajax_url'], 100);
		add_action('wp_footer', [__CLASS__, 'override_wc_checkout_ajax_url'], 6);
		add_filter('woocommerce_checkout_redirect_empty_cart', [__CLASS__, 'filter_checkout_redirect_empty_cart']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_dequeue_wc_checkout_on_preload'], 101);
		add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_wc_checkout_with_cart'], 999);
		add_filter('script_loader_tag', [__CLASS__, 'checkout_script_loader_tag'], 10, 3);
	}

	public static function product_slugs(): array {
		return self::$product_slugs;
	}

	public static function is_parent_page(): bool {
		if (!is_page()) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post
			&& $post->post_name === self::PARENT_SLUG
			&& (int) $post->post_parent === 0;
	}

	public static function is_comprar_child(): bool {
		if (!is_page()) {
			return false;
		}
		$post = get_queried_object();
		if (!$post instanceof WP_Post || (int) $post->post_parent <= 0) {
			return false;
		}
		$parent = get_post((int) $post->post_parent);
		return $parent instanceof WP_Post && $parent->post_name === self::PARENT_SLUG;
	}

	public static function is_comprar_surface(): bool {
		return self::is_parent_page() || self::is_comprar_child();
	}

	public static function referer_is_comprar_child(): bool {
		$ref = wp_get_referer();
		if (!$ref) {
			return false;
		}
		$path = wp_parse_url($ref, PHP_URL_PATH);
		if (!is_string($path) || $path === '') {
			return false;
		}
		return (bool) preg_match('#/' . preg_quote(self::PARENT_SLUG, '#') . '/[^/]+/?$#', untrailingslashit($path) . '/');
	}

	public static function is_embedded_context(): bool {
		return self::is_comprar_child() || self::referer_is_comprar_child();
	}

	public static function current_product_id(): int {
		if (!self::is_comprar_child()) {
			return 0;
		}
		$page_id = (int) get_queried_object_id();
		$meta    = (int) get_post_meta($page_id, self::META_PRODUCT, true);
		if ($meta > 0) {
			return $meta;
		}
		$slug = (string) get_post_field('post_name', $page_id);
		return (int) (self::$product_slugs[ $slug ] ?? 0);
	}

	public static function current_bases_url(): string {
		if (!self::is_comprar_child()) {
			return '';
		}
		$pid = self::current_product_id();
		if ($pid <= 0) {
			return '';
		}
		$cfg = self::product_config($pid);
		$url = is_array($cfg) ? (string) ($cfg['bases_url'] ?? '') : '';
		return $url;
	}

	/** @return array<string, mixed> */
	public static function product_config(int $product_id): array {
		$from_pdp = self::load_ss_from_pdp_template($product_id);
		if ($from_pdp !== []) {
			return $from_pdp;
		}
		$path = self::DIR . '/product-configs.php';
		if (!is_readable($path)) {
			return [];
		}
		$all = include $path;
		if (!is_array($all)) {
			return [];
		}
		$cfg = $all[ $product_id ] ?? [];
		return is_array($cfg) ? $cfg : [];
	}

	/** @return array<string, mixed> */
	private static function load_ss_from_pdp_template(int $product_id): array {
		$path = '';
		if (class_exists('SorteoSeguro_PDP_Templates')) {
			$path = SorteoSeguro_PDP_Templates::template_path($product_id);
		}
		if ($path === '') {
			foreach (self::$product_slugs as $slug => $pid) {
				if ((int) $pid !== (int) $product_id) {
					continue;
				}
				$candidate = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/' . $slug . '.php';
				if (is_readable($candidate)) {
					$path = $candidate;
				}
				break;
			}
		}
		if ($path === '' || !is_readable($path)) {
			return [];
		}
		$src = file_get_contents($path);
		if ($src === false) {
			return [];
		}
		$start = strpos($src, '$ss = [');
		if ($start === false) {
			return [];
		}
		$start += strlen('$ss = ');
		$end = strpos($src, '];', $start);
		if ($end === false) {
			return [];
		}
		$array_code = substr($src, $start, $end - $start + 1);
		try {
			/** @var mixed $ss */
			$ss = eval('return ' . $array_code . ';'); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		} catch (Throwable $e) {
			return [];
		}
		return is_array($ss) ? $ss : [];
	}

	public static function product_is_available(int $product_id): bool {
		if ($product_id <= 0 || !function_exists('wc_get_product')) {
			return false;
		}
		$product = wc_get_product($product_id);
		if (!$product) {
			return false;
		}
		if (function_exists('lty_is_lottery_product') && !lty_is_lottery_product($product)) {
			return false;
		}
		if (method_exists($product, 'is_closed') && $product->is_closed()) {
			return false;
		}
		if (!$product->is_purchasable()) {
			return false;
		}
		return true;
	}

	public static function cart_has_current_product(): bool {
		if (!function_exists('WC') || !WC()->cart) {
			return false;
		}
		$pid = self::current_product_id();
		if ($pid <= 0) {
			return false;
		}
		if (WC()->cart->is_empty()) {
			return false;
		}
		foreach (WC()->cart->get_cart() as $item) {
			if ((int) ($item['product_id'] ?? 0) === $pid) {
				return true;
			}
		}
		return false;
	}

	public static function should_preload_embedded_checkout(): bool {
		if (!self::is_comprar_child()) {
			return false;
		}
		$pid = self::current_product_id();
		return $pid > 0 && self::product_is_available($pid);
	}

	public static function filter_checkout_redirect_empty_cart(bool $redirect): bool {
		if (self::should_preload_embedded_checkout()) {
			return false;
		}
		return $redirect;
	}

	public static function is_checkout_preload(): bool {
		return self::should_preload_embedded_checkout() && !self::cart_has_current_product();
	}

	public static function render_embedded_checkout(): void {
		if (!function_exists('WC')) {
			return;
		}
		$checkout = WC()->checkout();
		if (!$checkout) {
			return;
		}
		/**
		 * Siempre el template custom: form-checkout.php ya dispara before/after.
		 * woocommerce_checkout() no está cargada en /comprar/ y rompe al elegir pack.
		 */
		wc_get_template('checkout/form-checkout.php', ['checkout' => $checkout]);
	}

	public static function maybe_dequeue_wc_checkout_on_preload(): void {
		self::boot_wc_cart();
		if (!self::is_checkout_preload()) {
			return;
		}
		wp_dequeue_script('wc-checkout');
	}

	/** WooCommerce no encola wc-checkout en /comprar/ aunque is_checkout sea true vía filtro. */
	public static function maybe_enqueue_wc_checkout_with_cart(): void {
		self::boot_wc_cart();
		if (!self::is_comprar_child() || !self::cart_has_current_product()) {
			return;
		}
		if (!function_exists('WC') || !class_exists('WC_AJAX')) {
			return;
		}

		if (class_exists('WC_Frontend_Scripts')) {
			WC_Frontend_Scripts::load_scripts();
		}

		wp_enqueue_style('select2');
		wp_enqueue_script('selectWoo');
		wp_enqueue_script('wc-checkout');

		$scripts = wp_scripts();
		if (!empty($scripts->registered['wc-checkout']->extra['data'])) {
			return;
		}

		global $wp;
		wp_localize_script(
			'wc-checkout',
			'wc_checkout_params',
			[
				'ajax_url'                  => WC()->ajax_url(),
				'wc_ajax_url'               => WC_AJAX::get_endpoint('%%endpoint%%'),
				'update_order_review_nonce' => wp_create_nonce('update-order-review'),
				'apply_coupon_nonce'        => wp_create_nonce('apply-coupon'),
				'remove_coupon_nonce'       => wp_create_nonce('remove-coupon'),
				'option_guest_checkout'     => get_option('woocommerce_enable_guest_checkout'),
				'checkout_url'              => WC_AJAX::get_endpoint('checkout'),
				'is_checkout'               => (is_checkout() && empty($wp->query_vars['order-pay']) && !isset($wp->query_vars['order-received'])) ? '1' : '0',
				'debug_mode'                => (defined('WP_DEBUG') && WP_DEBUG) ? '1' : '0',
				'i18n_checkout_error'       => sprintf(
					/* translators: %s: order history URL */
					esc_attr__('There was an error processing your order. Please check for any charges in your payment method and review your <a href="%s">order history</a> before placing the order again.', 'woocommerce'),
					esc_url(wc_get_account_endpoint_url('orders'))
				),
			]
		);
		SorteoSeguro_Comprar::override_wc_checkout_ajax_url();
	}

	/** Evita que LiteSpeed difiera/combine wc-checkout en /comprar/ con pack. */
	public static function checkout_script_loader_tag(string $tag, string $handle, string $src): string {
		if (!self::is_comprar_child() || !self::cart_has_current_product()) {
			return $tag;
		}
		$handles = ['wc-checkout', 'woocommerce', 'selectWoo', 'jquery-blockui', 'wc-country-select'];
		if (!in_array($handle, $handles, true)) {
			return $tag;
		}
		if (strpos($tag, 'data-no-optimize') !== false) {
			return $tag;
		}
		return str_replace('<script ', '<script data-no-optimize="1" data-no-defer="1" ', $tag);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_comprar_surface() ? true : $enabled;
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_parent_page()) {
			$classes[] = 'ss-comprar-index';
		}
		if (self::is_comprar_child()) {
			$classes[] = 'ss-comprar-template';
			$classes[] = 'ss-pdp-template';
			if (self::should_preload_embedded_checkout()) {
				$classes[] = 'woocommerce-checkout';
				$classes[] = 'ss-checkout-template';
				if (!is_user_logged_in()) {
					$classes[] = 'ss-checkout-guest';
				}
				if (!self::cart_has_current_product()) {
					$classes[] = 'ss-comprar-checkout-preload';
				}
			}
		}
		return $classes;
	}

	public static function bypass_page_cache(): void {
		if (!self::is_comprar_surface()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-comprar');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	public static function boot_wc_cart(): void {
		if (!self::is_comprar_child() || !function_exists('WC')) {
			return;
		}
		if (is_null(WC()->cart) || !WC()->session) {
			if (function_exists('wc_load_cart')) {
				wc_load_cart();
			}
		}
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
		if (WC()->cart && WC()->session) {
			WC()->cart->get_cart_from_session();
		}
	}

	public static function template_include(string $template): string {
		if (self::is_parent_page()) {
			$custom = self::DIR . '/page-comprar-index.php';
			return is_readable($custom) ? $custom : $template;
		}
		if (self::is_comprar_child()) {
			$custom = self::DIR . '/page-comprar.php';
			return is_readable($custom) ? $custom : $template;
		}
		return $template;
	}

	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		if (!self::is_comprar_surface()) {
			return $value;
		}
		if ((int) $object_id !== (int) get_queried_object_id()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function print_critical_css(): void {
		if (!self::is_comprar_surface()) {
			return;
		}
		$path = self::DIR . '/assets/comprar.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-comprar critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-comprar-critical" data-no-optimize="1">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function assets(): void {
		if (!self::is_comprar_surface()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = content_url('mu-plugins/sorteoseguro-comprar/assets');
		$dir  = self::DIR . '/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];

		$pdp_dir  = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/assets';
		$pdp_base = content_url('mu-plugins/sorteoseguro-pdp/assets');
		$pdp_ver  = class_exists('SorteoSeguro_PDP_Templates') ? SorteoSeguro_PDP_Templates::VERSION : '1.0.34';
		if (self::is_comprar_child() && is_readable($pdp_dir . '/pdp.css')) {
			wp_enqueue_style('ss-pdp', $pdp_base . '/pdp.css', $deps, $pdp_ver);
			$deps = ['ss-pdp'];
		}
		if (is_readable($dir . '/comprar.css')) {
			wp_enqueue_style('ss-comprar', $base . '/comprar.css', $deps, $ver);
		}
		if (self::is_comprar_child() && is_readable($pdp_dir . '/pdp.js')) {
			wp_enqueue_script('ss-pdp', $pdp_base . '/pdp.js', [], $pdp_ver, true);
		}
		if (self::is_comprar_child() && is_readable($dir . '/comprar.js')) {
			wp_enqueue_script('ss-comprar', $base . '/comprar.js', ['jquery', 'ss-pdp'], $ver, true);
		}
	}

	public static function filter_packs_product_id(int $product_id): int {
		if ($product_id > 0) {
			return $product_id;
		}
		return self::is_comprar_child() ? self::current_product_id() : 0;
	}

	/** @param array<string, mixed> $config */
	public static function filter_packs_config(array $config): array {
		if (!self::is_comprar_child()) {
			return $config;
		}
		$config['embeddedCheckout'] = true;
		$config['checkoutAnchor']   = '#ss-comprar-checkout';
		return $config;
	}

	public static function filter_packs_redirect(string $redirect, int $product_id, int $campaign_id): string {
		if (!self::is_comprar_child() && !self::referer_is_comprar_child()) {
			return $redirect;
		}
		$url = self::comprar_child_url_for_product($product_id);
		if ($url === '') {
			return $redirect;
		}
		return add_query_arg('ss_pack', (string) $campaign_id, $url) . '#ss-comprar-checkout';
	}

	public static function filter_packs_embedded(bool $embedded, int $product_id, int $campaign_id): bool {
		if ($embedded) {
			return true;
		}
		return self::is_comprar_child() || self::referer_is_comprar_child();
	}

	public static function filter_checkout_active_surface(bool $active): bool {
		return $active || self::is_embedded_context();
	}

	public static function filter_wc_is_checkout(bool $is_checkout): bool {
		if ($is_checkout) {
			return true;
		}
		if (defined('DOING_AJAX') && DOING_AJAX) {
			$action = isset($_REQUEST['action']) ? (string) wp_unslash($_REQUEST['action']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ($action === 'ss_packs_select') {
				return false;
			}
			return self::referer_is_comprar_child();
		}
		return self::should_preload_embedded_checkout();
	}

	public static function filter_checkout_form_action(string $url): string {
		if (!self::is_comprar_child()) {
			return $url;
		}
		return (string) get_permalink();
	}

	/** Thank-you sigue en /finalizar-compra/order-received/ (no romper confirmación standalone). */
	public static function filter_order_received_url(string $url, $order): string {
		if (!self::is_embedded_context()) {
			return $url;
		}
		if (!function_exists('wc_get_page_id') || !function_exists('wc_get_endpoint_url') || !($order instanceof WC_Order)) {
			return $url;
		}
		$checkout_id = (int) wc_get_page_id('checkout');
		if ($checkout_id <= 0) {
			return $url;
		}
		$standard = wc_get_endpoint_url('order-received', $order->get_id(), get_permalink($checkout_id));
		return add_query_arg('key', $order->get_order_key(), $standard);
	}

	public static function override_wc_checkout_ajax_url(): void {
		if (!self::is_comprar_child() || !self::cart_has_current_product()) {
			return;
		}
		if (!wp_script_is('wc-checkout', 'enqueued')) {
			return;
		}
		// Mantener endpoint wc-ajax=checkout (no la URL de la página /comprar/).
		$ajax_checkout = class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('checkout') : '';
		if ($ajax_checkout === '') {
			return;
		}
		wp_add_inline_script(
			'wc-checkout',
			'if(window.wc_checkout_params){window.wc_checkout_params.checkout_url=' . wp_json_encode($ajax_checkout) . ';}',
			'before'
		);
	}

	public static function comprar_child_url_for_product(int $product_id): string {
		if ($product_id <= 0) {
			return '';
		}
		$pages = get_posts([
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => self::META_PRODUCT,
			'meta_value'     => (string) $product_id,
			'fields'         => 'ids',
		]);
		if (!empty($pages[0])) {
			$link = get_permalink((int) $pages[0]);
			return is_string($link) ? $link : '';
		}
		foreach (self::$product_slugs as $slug => $pid) {
			if ((int) $pid === (int) $product_id) {
				return home_url('/' . self::PARENT_SLUG . '/' . $slug . '/');
			}
		}
		return '';
	}

	public static function maybe_seed_pages(): void {
		if (get_option(self::OPTION_SEEDED) === 'yes') {
			return;
		}
		if (!function_exists('wp_insert_post')) {
			return;
		}

		$parent = get_page_by_path(self::PARENT_SLUG);
		if (!$parent) {
			$parent_id = wp_insert_post([
				'post_title'   => 'Comprar',
				'post_name'    => self::PARENT_SLUG,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			], true);
			if (is_wp_error($parent_id)) {
				return;
			}
		} else {
			$parent_id = (int) $parent->ID;
		}

		foreach (self::$product_slugs as $slug => $product_id) {
			$path = self::PARENT_SLUG . '/' . $slug;
			if (get_page_by_path($path)) {
				continue;
			}
			$title = get_the_title($product_id);
			if ($title === '') {
				$title = ucwords(str_replace('-', ' ', $slug));
			}
			wp_insert_post([
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_parent'  => $parent_id,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
				'meta_input'   => [
					self::META_PRODUCT => (int) $product_id,
				],
			]);
		}

		update_option(self::OPTION_SEEDED, 'yes', false);
	}
}

SorteoSeguro_Comprar::init();
