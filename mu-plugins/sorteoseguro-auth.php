<?php
/**
 * Plugin Name: Sorteo Seguro – Auth (Mi cuenta / Registro)
 * Description: Plantillas y estilos para login (/mi-cuenta/) y registro (/registro/) con chrome y inputs alineados al checkout.
 * Author: Sorteo Seguro
 * Version: 1.0.6
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Auth {

	const VERSION = '1.0.6';
	const DIR     = __DIR__ . '/sorteoseguro-auth';

	const PAGE_MI_CUENTA = 14;
	const PAGE_REGISTRO  = 559;
	const UM_FORM_ID     = 481;

	/** Custom CSS & JS legacy (mi-cuenta / registro). */
	const LEGACY_CCJ_IDS = [23025, 50188];

	/** CSS completo partido (límite FTP ~12KB por archivo). */
	const CSS_FILES = ['auth-base.css', 'auth-wc.css', 'auth-um.css', 'auth-account.css'];

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 55);
		add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_legacy_ccj'], 1000);
		add_action('wp_footer', [__CLASS__, 'inline_assets'], 20);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
		add_action('template_redirect', [__CLASS__, 'maybe_redirect_logged_in'], 5);
		add_action('woocommerce_login_form_start', [__CLASS__, 'login_form_redirect_field']);
		add_filter('woocommerce_login_redirect', [__CLASS__, 'login_redirect'], 20, 2);
		add_filter('the_content', [__CLASS__, 'strip_legacy_page_content'], 1);
		add_filter('do_shortcode_tag', [__CLASS__, 'neutralize_wpcode'], 10, 4);
	}

	public static function is_auth_page(): bool {
		return self::is_mi_cuenta_page() || self::is_registro_page();
	}

	public static function is_mi_cuenta_page(): bool {
		return is_page(self::PAGE_MI_CUENTA);
	}

	public static function is_registro_page(): bool {
		return is_page(self::PAGE_REGISTRO);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_auth_page() ? true : $enabled;
	}

	public static function bypass_page_cache(): void {
		if (!self::is_auth_page()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-auth');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_mi_cuenta_page()) {
			$classes[] = 'ss-auth-template';
			$classes[] = 'ss-auth-mi-cuenta';
			if (is_user_logged_in()) {
				$classes[] = 'ss-auth-logged-in';
			} else {
				$classes[] = 'ss-auth-guest';
			}
		}
		if (self::is_registro_page()) {
			$classes[] = 'ss-auth-template';
			$classes[] = 'ss-auth-registro';
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (self::is_mi_cuenta_page()) {
			$custom = self::DIR . '/page-mi-cuenta.php';
			return is_readable($custom) ? $custom : $template;
		}
		if (self::is_registro_page()) {
			$custom = self::DIR . '/page-registro.php';
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
		$page_id = (int) $object_id;
		if (!in_array($page_id, [self::PAGE_MI_CUENTA, self::PAGE_REGISTRO], true)) {
			return $value;
		}
		if (!self::is_auth_page()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function strip_legacy_page_content(string $content): string {
		if (!self::is_auth_page() || is_admin()) {
			return $content;
		}
		return '';
	}

	/**
	 * Neutraliza snippets WPCode legacy en auth (layout registro, CSS mi-cuenta).
	 *
	 * @param string               $output
	 * @param string               $tag
	 * @param array<string, mixed> $attr
	 * @param array<int, string>   $m
	 */
	public static function neutralize_wpcode($output, $tag, $attr, $m) {
		if (!self::is_auth_page()) {
			return $output;
		}
		if ($tag !== 'wpcode' && $tag !== 'wpcode_snippet') {
			return $output;
		}
		$id = 0;
		if (is_array($attr) && isset($attr['id'])) {
			$id = (int) $attr['id'];
		} elseif (is_array($m) && isset($m[1]) && preg_match('/\bid=["\']?(\d+)["\']?/i', (string) $m[1], $match)) {
			$id = (int) $match[1];
		}
		if ($id && in_array($id, self::LEGACY_CCJ_IDS, true)) {
			return '<!-- ss-auth: wpcode ' . $id . ' neutralizado -->';
		}
		return $output;
	}

	public static function print_critical_css(): void {
		if (!self::is_auth_page()) {
			return;
		}
		$path = self::DIR . '/assets/auth-critical.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-auth critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-auth-critical">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function assets(): void {
		if (!self::is_auth_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = content_url('mu-plugins/sorteoseguro-auth/assets');
		$dir  = self::DIR . '/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		foreach (self::CSS_FILES as $i => $file) {
			$path = $dir . '/' . $file;
			if (!is_readable($path)) {
				continue;
			}
			$handle = $i === 0 ? 'ss-auth' : 'ss-auth-' . ($i + 1);
			wp_enqueue_style($handle, $base . '/' . $file, $deps, $ver);
			$deps = [$handle];
		}
		if (is_readable($dir . '/auth.js')) {
			wp_enqueue_script('ss-auth', $base . '/auth.js', [], $ver, true);
		}
	}

	public static function dequeue_legacy_ccj(): void {
		if (!self::is_auth_page()) {
			return;
		}
		foreach (self::LEGACY_CCJ_IDS as $id) {
			wp_dequeue_style('ccj-' . $id);
			wp_deregister_style('ccj-' . $id);
			wp_dequeue_script('ccj-' . $id);
			wp_deregister_script('ccj-' . $id);
			wp_dequeue_style('custom-css-js-' . $id);
			wp_dequeue_script('custom-css-js-' . $id);
		}
	}

	public static function inline_assets(): void {
		if (!self::is_auth_page()) {
			return;
		}
		$js = self::DIR . '/assets/auth.js';
		$css_blob = '';
		foreach (self::CSS_FILES as $file) {
			$path = self::DIR . '/assets/' . $file;
			if (is_readable($path)) {
				$css_blob .= file_get_contents($path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		if ($css_blob !== '') {
			echo "\n<!-- ss-auth v" . esc_html(self::VERSION) . " -->\n<style id=\"ss-auth-inline\">\n";
			echo $css_blob; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</style>\n";
		}
		if (is_readable($js)) {
			echo "<script id=\"ss-auth-inline-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n";
			echo file_get_contents($js); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</script>\n";
		}
	}

	public static function login_url(): string {
		$account = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/mi-cuenta/');
		return $account ?: home_url('/mi-cuenta/');
	}

	public static function register_url(): string {
		return home_url('/registro/');
	}

	public static function login_url_with_redirect(string $redirect): string {
		return add_query_arg('redirect_to', rawurlencode($redirect), self::login_url());
	}

	public static function redirect_target_from_request(): string {
		if (empty($_GET['redirect_to'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$raw = wp_unslash((string) $_GET['redirect_to']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$url = esc_url_raw(rawurldecode($raw));
		if (!$url || !wp_validate_redirect($url, home_url('/'))) {
			return '';
		}
		return $url;
	}

	public static function maybe_redirect_logged_in(): void {
		if (!self::is_mi_cuenta_page() || !is_user_logged_in()) {
			return;
		}
		$target = self::redirect_target_from_request();
		if ($target) {
			wp_safe_redirect($target);
			exit;
		}
	}

	public static function login_form_redirect_field(): void {
		$target = self::redirect_target_from_request();
		if ($target) {
			echo '<input type="hidden" name="redirect" value="' . esc_attr($target) . '" />';
		}
	}

	/**
	 * @param string           $redirect
	 * @param WP_User|WP_Error $user
	 */
	public static function login_redirect(string $redirect, $user): string {
		unset($user);
		$target = self::redirect_target_from_request();
		if (!$target && !empty($_POST['redirect'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$candidate = esc_url_raw(wp_unslash((string) $_POST['redirect'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ($candidate && wp_validate_redirect($candidate, home_url('/'))) {
				$target = $candidate;
			}
		}
		return $target ?: $redirect;
	}

	public static function mi_cuenta_shortcode(): string {
		return '[woocommerce_my_account]';
	}

	public static function registro_shortcode(): string {
		return '[ultimatemember form_id="' . self::UM_FORM_ID . '"]';
	}

	public static function icon(string $name): string {
		$icons = [
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c.8-3.1 3.4-5 6.5-5s5.7 1.9 6.5 5"/></svg>',
			'lock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
			'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>',
		];
		return $icons[$name] ?? '';
	}
}

SorteoSeguro_Auth::init();
