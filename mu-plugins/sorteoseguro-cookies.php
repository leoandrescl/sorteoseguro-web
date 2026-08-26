<?php
/**
 * Plugin Name: Sorteo Seguro – Cookie banner
 * Description: Rediseña el banner de Complianz (layout, textos e ilustración) sin cambiar el consentimiento.
 * Author: Sorteo Seguro
 * Version: 1.0.4
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Cookies {

	const VERSION = '1.0.4';

	public static function init(): void {
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_fonts'], 5);
		add_action('wp_head', [__CLASS__, 'inline_css'], 99);
		add_action('wp_footer', [__CLASS__, 'inline_js'], 5);
		add_filter('cmplz_banner_css', [__CLASS__, 'append_banner_css']);
	}

	public static function enqueue_fonts(): void {
		if (is_admin()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
	}

	public static function dir(): string {
		return WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-cookies';
	}

	public static function url(): string {
		return content_url('mu-plugins/sorteoseguro-cookies');
	}

	public static function shield_url(): string {
		return self::url() . '/assets/escudo-cookies.jpg';
	}

	public static function page_url(string $path, string $fallback = ''): string {
		if (class_exists('SorteoSeguro_Chrome')) {
			return SorteoSeguro_Chrome::page_url($path, $fallback);
		}
		$page = get_page_by_path(trim($path, '/'));
		if ($page instanceof WP_Post) {
			return (string) get_permalink($page);
		}
		return $fallback !== '' ? $fallback : home_url('/' . trim($path, '/') . '/');
	}

	public static function css_contents(): string {
		$path = self::dir() . '/assets/cookies.css';
		if (!is_readable($path)) {
			return '';
		}
		$css = (string) file_get_contents($path);
		return str_replace('{{SS_COOKIE_SHIELD}}', esc_url_raw(self::shield_url()), $css);
	}

	public static function config_js(): string {
		$data = [
			'shield'  => self::shield_url(),
			'privacy' => self::page_url('politica-privacidad'),
			'cookies' => self::page_url('politica-de-cookies'),
		];
		return 'window.ssCookies=' . wp_json_encode($data) . ';';
	}

	public static function inline_js(): void {
		if (is_admin()) {
			return;
		}
		$path = self::dir() . '/assets/cookies.js';
		if (!is_readable($path)) {
			return;
		}
		$js = (string) file_get_contents($path);
		echo "\n<script id=\"ss-cookies-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n" . self::config_js() . "\n" . $js . "\n</script>\n";
	}

	public static function inline_css(): void {
		if (is_admin()) {
			return;
		}
		$css = self::css_contents();
		if ($css === '') {
			return;
		}
		echo "\n<!-- ss-cookies v" . esc_html(self::VERSION) . " -->\n<style id=\"ss-cookies-inline\">\n" . $css . "\n</style>\n";
	}

	public static function append_banner_css($css) {
		$extra = self::css_contents();
		if ($extra === '') {
			return $css;
		}
		return $css . "\n" . $extra;
	}

}

SorteoSeguro_Cookies::init();
