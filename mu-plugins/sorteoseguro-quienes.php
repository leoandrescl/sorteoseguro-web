<?php
/**
 * Plugin Name: Sorteo Seguro – Quiénes somos
 * Description: Plantilla PHP para la página Quiénes somos (ID 76256), con chrome header/footer.
 * Author: Sorteo Seguro
 * Version: 1.0.8
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Quienes {

	const VERSION = '1.0.8';
	const PAGE_ID = 76256;
	const DIR     = __DIR__ . '/sorteoseguro-quienes';

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
	}

	public static function is_quienes_page(): bool {
		if (is_page(self::PAGE_ID) || is_page('quienes-somos')) {
			return true;
		}
		return false;
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_quienes_page() ? true : $enabled;
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_quienes_page()) {
			$classes[] = 'ss-qs-template';
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (!self::is_quienes_page()) {
			return $template;
		}
		$custom = self::DIR . '/page-quienes.php';
		return is_readable($custom) ? $custom : $template;
	}

	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		$pid = (int) $object_id;
		if ($pid !== self::PAGE_ID) {
			$post = get_post($pid);
			if (!$post || $post->post_name !== 'quienes-somos') {
				return $value;
			}
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_quienes_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$css  = self::DIR . '/assets/quienes.css';
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($css)) {
			wp_enqueue_style(
				'ss-quienes',
				content_url('mu-plugins/sorteoseguro-quienes/assets/quienes.css'),
				$deps,
				self::VERSION
			);
		}
	}

	public static function print_critical_css(): void {
		if (!self::is_quienes_page()) {
			return;
		}
		$path = self::DIR . '/assets/quienes.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-quienes critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-quienes-critical">' . $css . "</style>\n";
	}

	public static function bases_url(): string {
		if (class_exists('SorteoSeguro_Bases')) {
			$permalink = get_permalink(SorteoSeguro_Bases::PAGE_ID);
			if ($permalink) {
				return (string) $permalink;
			}
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			return (string) apply_filters(
				'ss_chrome_bases_url',
				SorteoSeguro_Chrome::page_url('politica-funcionamiento-concurso')
			);
		}
		return home_url('/bases-legales/');
	}

	public static function asset_url(string $file): string {
		return content_url('mu-plugins/sorteoseguro-quienes/assets/' . ltrim($file, '/'));
	}

	public static function media_img(string $file, string $alt = '', bool $eager = false): string {
		$path = self::DIR . '/assets/' . ltrim($file, '/');
		if (!is_readable($path)) {
			return '<span class="ss-qs-media__ph" aria-hidden="true">' . self::icon('image') . '</span>';
		}
		$loading = $eager ? 'eager' : 'lazy';
		return '<img src="' . esc_url(self::asset_url($file)) . '" alt="' . esc_attr($alt) . '" loading="' . $loading . '" decoding="async">';
	}

	public static function icon(string $name): string {
		$map = [
			'shield-ticket' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.1 19 6.2v5c0 4.6-3.1 8.5-7 9.7-3.9-1.2-7-5.1-7-9.7v-5l7-3.1z"/><path d="M8.1 11.15h7.8c.45 0 .7.28.7.68v.55a1.35 1.35 0 0 1 0 2.24v.55c0 .4-.25.68-.7.68H8.1c-.45 0-.7-.28-.7-.68v-.55a1.35 1.35 0 0 1 0-2.24v-.55c0-.4.25-.68.7-.68z"/><path stroke-dasharray="1.4 1.7" d="M12 11.15v4.7"/></svg>',
			'medal'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13.2" r="6.2"/><path d="M9.2 7.4 7 3.4h4.1L12 6.2l.9-2.8H17L14.8 7.4"/><path d="m10.3 13.2 1.2 1.2 2.4-2.5"/></svg>',
			'pencil'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4.2L19.4 8.8a1.8 1.8 0 0 0 0-2.5l-1.7-1.7a1.8 1.8 0 0 0-2.5 0L4 15.8V20z"/><path d="m13.5 6.5 4 4"/></svg>',
			'ticket'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 9.2A2.2 2.2 0 0 0 5.7 7h12.6a2.2 2.2 0 0 0 2.2 2.2v1.1a2.2 2.2 0 0 1 0 4.4v1.1A2.2 2.2 0 0 0 18.3 18H5.7A2.2 2.2 0 0 0 3.5 15.8v-1.1a2.2 2.2 0 0 1 0-4.4V9.2z"/><path stroke-dasharray="2 2" d="M12 7.2v10.6"/></svg>',
			'user'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c.8-3.1 3.4-5 6.5-5s5.7 1.9 6.5 5"/></svg>',
			'doc'           => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h7.2L17.5 6.8V20A1.5 1.5 0 0 1 16 21.5H7A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path d="M14 3.5V7h3.5M8.5 11h7M8.5 14.5h7M8.5 18h4.5"/></svg>',
			'trophy'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v5a4 4 0 0 1-8 0V4z"/><path d="M8 6H5.5A2.5 2.5 0 0 0 8 10.2M16 6h2.5A2.5 2.5 0 0 1 16 10.2"/><path d="M12 13v3M8 20h8M10 20v-2h4v2"/></svg>',
			'shield-check'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
			'mail'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 7 9-7"/></svg>',
			'users'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="2.8"/><path d="M3.8 18.2c.7-2.6 2.9-4.2 5.2-4.2s4.5 1.6 5.2 4.2"/><circle cx="16.4" cy="8.4" r="2.3"/><path d="M15.2 14.2c1.9.2 3.6 1.5 4.4 4"/></svg>',
			'lock'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
			'plane'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12 20 4l-7 16-2.2-6.3L4 12z"/><path d="M10.8 13.7 20 4"/></svg>',
			'target'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="4.4"/><circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/></svg>',
			'checklist'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h10.5M9 12h10.5M9 19h10.5"/><path d="m4 5.2 1.4 1.4L8 4.2M4 12.2l1.4 1.4L8 11.2M4 19.2l1.4 1.4L8 18.2"/></svg>',
			'search'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.2"/><path d="m16 16 4.2 4.2"/></svg>',
			'shield'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.4-7 9.5C8 19.4 5 15.5 5 11V6l7-3z"/></svg>',
			'bulb'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9.2 17h5.6M10 20h4"/><path d="M12 3.5a6.2 6.2 0 0 1 3.6 11.2c-.5.4-.9 1.1-1 1.8H9.4c-.1-.7-.5-1.4-1-1.8A6.2 6.2 0 0 1 12 3.5z"/></svg>',
			'heart'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>',
			'image'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.2" y="5" width="17.6" height="14" rx="2"/><circle cx="9" cy="10.2" r="1.5"/><path d="m5.5 16.5 4.2-4.2 3.1 3.1 2.2-2.2 3.5 3.3"/></svg>',
			'check'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m6.2 12.2 3.6 3.6 8-8"/></svg>',
		];
		return $map[$name] ?? '';
	}
}

SorteoSeguro_Quienes::init();
