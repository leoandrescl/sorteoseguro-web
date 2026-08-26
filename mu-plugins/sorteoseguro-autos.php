<?php
/**
 * Plugin Name: Sorteo Seguro – Autos
 * Description: Plantilla PHP para la página Autos (ID 300). Reutiliza CSS de Propiedades / Home.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Autos {

	const VERSION = '1.0.0';
	const PAGE_ID = 300;

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_action('wp_head', [__CLASS__, 'inline_css'], 100);
		add_action('wp_footer', [__CLASS__, 'inline_js'], 5);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
	}

	public static function is_autos_page(): bool {
		return is_page(self::PAGE_ID);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_autos_page() ? true : $enabled;
	}

	public static function bypass_page_cache(): void {
		if (!self::is_autos_page()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-autos');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_autos_page()) {
			$classes[] = 'ss-home-template';
			$classes[] = 'ss-realty-template';
			$classes[] = 'ss-autos-template';
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (!self::is_autos_page()) {
			return $template;
		}
		$custom = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-autos/page-autos.php';
		return is_readable($custom) ? $custom : $template;
	}

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
		if (!self::is_autos_page()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_autos_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$home_base = content_url('mu-plugins/sorteoseguro-home/assets');
		$home_dir  = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-home/assets';
		$css_base  = content_url('mu-plugins/sorteoseguro-propiedades/assets');
		$css_dir   = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-propiedades/assets';
		$ver       = self::VERSION;
		$home_ver  = class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::VERSION : $ver;
		$css_ver   = class_exists('SorteoSeguro_Propiedades') ? SorteoSeguro_Propiedades::VERSION : $ver;

		if (is_readable($home_dir . '/home.css')) {
			wp_enqueue_style('ss-home', $home_base . '/home.css', [], $home_ver);
		}
		if (is_readable($css_dir . '/propiedades.css')) {
			wp_enqueue_style('ss-propiedades', $css_base . '/propiedades.css', ['ss-home'], $css_ver);
		}
		if (is_readable($home_dir . '/home.js')) {
			wp_enqueue_script('ss-home', $home_base . '/home.js', [], $home_ver, true);
		}
	}

	public static function inline_css(): void {
		if (!self::is_autos_page()) {
			return;
		}
		echo "\n<!-- ss-autos v" . esc_html(self::VERSION) . " -->\n";
		$files = [
			'ss-home-inline' => WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-home/assets/home.css',
			'ss-propiedades-inline' => WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-propiedades/assets/propiedades.css',
		];
		foreach ($files as $id => $path) {
			if (!is_readable($path)) {
				continue;
			}
			$css = file_get_contents($path);
			if ($css === false || $css === '') {
				continue;
			}
			echo "<style id=\"" . esc_attr($id) . "\">\n" . $css . "\n</style>\n";
		}
	}

	public static function inline_js(): void {
		if (!self::is_autos_page()) {
			return;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-home/assets/home.js';
		if (!is_readable($path)) {
			return;
		}
		$js = file_get_contents($path);
		if ($js === false || $js === '') {
			return;
		}
		echo "\n<script id=\"ss-home-inline-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n" . $js . "\n</script>\n";
	}

	/**
	 * Hero vehicular: Jeep + Peugeot (mismos slides del home).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function hero_slides(): array {
		if (!class_exists('SorteoSeguro_Home')) {
			return [];
		}
		$contests = SorteoSeguro_Home::get_contest_cards();
		$all = SorteoSeguro_Home::hero_slides($contests);
		$keep = ['jeep', 'peugeot'];
		$out = [];
		foreach ($all as $slide) {
			$key = (string) ($slide['key'] ?? '');
			if (in_array($key, $keep, true)) {
				$out[] = $slide;
			}
		}
		return $out;
	}

	/**
	 * Hasta 2 concursos vehiculares activos.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function contest_cards(): array {
		if (!class_exists('SorteoSeguro_Home')) {
			return [];
		}
		$out = [];
		foreach (SorteoSeguro_Home::get_contest_cards() as $c) {
			if (($c['category_slug'] ?? '') !== 'vehicle') {
				continue;
			}
			$out[] = $c;
			if (count($out) >= 2) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function coming_soon_cards(int $count = 2): array {
		$out = [];
		for ($i = 0; $i < $count; $i++) {
			$out[] = [
				'soon'     => true,
				'title'    => 'Próximamente',
				'category' => 'VEHÍCULO',
				'meta'     => '',
				'url'      => '',
				'image'    => '',
			];
		}
		return $out;
	}
}

SorteoSeguro_Autos::init();
