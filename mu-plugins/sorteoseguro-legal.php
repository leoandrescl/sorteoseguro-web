<?php
/**
 * Plugin Name: Sorteo Seguro – Páginas legales
 * Description: Plantilla compartida (hero + apartados numerados) para Términos, privacidad, cookies, envío y concursos.
 * Author: Sorteo Seguro
 * Version: 1.1.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Legal {

	const VERSION = '1.1.0';
	const DIR     = __DIR__ . '/sorteoseguro-legal';

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
		add_filter('document_title_parts', [__CLASS__, 'document_title_parts'], 40);
		add_filter('wpseo_title', [__CLASS__, 'seo_title'], 40);
		add_filter('rank_math/frontend/title', [__CLASS__, 'seo_title'], 40);
	}

	/**
	 * Páginas legales. Añadir acá privacidad, cookies, envío y concursos.
	 *
	 * @return array<int, array{file:string,slug:string}>
	 */
	public static function page_map(): array {
		return [
			56521 => ['file' => 'terminos.php', 'slug' => 'terminos-condiciones'],
			56573 => ['file' => 'privacidad.php', 'slug' => 'politica-privacidad'],
			56545 => ['file' => 'concursos.php', 'slug' => 'politica-funcionamiento-concurso'],
			57070 => ['file' => 'envio.php', 'slug' => 'politicas-de-envio-reembolso'],
			56586 => ['file' => 'cookies.php', 'slug' => 'politica-de-cookies'],
		];
	}

	/** @return array<string, mixed>|null */
	public static function current_page(): ?array {
		$meta = self::current_meta();
		if (!$meta) {
			return null;
		}
		$file = self::DIR . '/pages/' . $meta['file'];
		if (!is_readable($file)) {
			return null;
		}
		$data = include $file;
		return is_array($data) ? $data : null;
	}

	/** @return array{file:string,slug:string}|null */
	public static function current_meta(): ?array {
		if (!function_exists('is_page')) {
			return null;
		}
		foreach (self::page_map() as $id => $meta) {
			if (is_page((int) $id) || (!empty($meta['slug']) && is_page((string) $meta['slug']))) {
				return $meta;
			}
		}
		return null;
	}

	public static function is_legal_page(): bool {
		return self::current_meta() !== null;
	}

	/** @param array<string, string> $parts */
	public static function document_title_parts(array $parts): array {
		if (!self::is_legal_page()) {
			return $parts;
		}
		$legal = self::current_page();
		if ($legal && !empty($legal['doc_title'])) {
			$parts['title'] = (string) $legal['doc_title'];
		}
		unset($parts['page']);
		return $parts;
	}

	public static function seo_title($title) {
		if (!self::is_legal_page()) {
			return $title;
		}
		$legal = self::current_page();
		if ($legal && !empty($legal['doc_title'])) {
			$site = wp_strip_all_tags(get_bloginfo('name'));
			return (string) $legal['doc_title'] . ' – ' . $site;
		}
		return $title;
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_legal_page() ? true : $enabled;
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		$meta = self::current_meta();
		if ($meta) {
			$classes[] = 'ss-legal-template';
			$slug      = sanitize_html_class((string) ($meta['slug'] ?? ''));
			if ($slug !== '') {
				$classes[] = 'ss-legal-' . $slug;
			}
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (!self::is_legal_page()) {
			return $template;
		}
		$custom = self::DIR . '/page-legal.php';
		return is_readable($custom) ? $custom : $template;
	}

	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		$map   = self::page_map();
		$pid   = (int) $object_id;
		$slugs = [];
		foreach ($map as $meta) {
			if (!empty($meta['slug'])) {
				$slugs[] = (string) $meta['slug'];
			}
		}
		if (!isset($map[$pid])) {
			$post = get_post($pid);
			if (!$post || !in_array((string) $post->post_name, $slugs, true)) {
				return $value;
			}
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_legal_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$css  = self::DIR . '/assets/legal.css';
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($css)) {
			wp_enqueue_style(
				'ss-legal',
				content_url('mu-plugins/sorteoseguro-legal/assets/legal.css'),
				$deps,
				self::VERSION
			);
		}
	}

	public static function print_critical_css(): void {
		if (!self::is_legal_page()) {
			return;
		}
		$path = self::DIR . '/assets/legal.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-legal critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-legal-critical">' . $css . "</style>\n";
	}

	public static function asset_url(string $file): string {
		return content_url('mu-plugins/sorteoseguro-legal/assets/' . ltrim($file, '/'));
	}

	public static function site_link(): string {
		$url = function_exists('home_url') ? home_url('/') : 'https://sorteoseguro.cl/';
		return '<a class="ss-legal-site" href="' . esc_url($url) . '">sorteoseguro.cl</a>';
	}

	public static function kses(string $html): string {
		return wp_kses($html, [
			'p'      => ['class' => []],
			'ul'     => ['class' => []],
			'ol'     => ['class' => []],
			'li'     => ['class' => []],
			'strong' => ['class' => []],
			'em'     => ['class' => []],
			'br'     => [],
			'a'      => [
				'href'   => [],
				'title'  => [],
				'target' => [],
				'rel'    => [],
				'class'  => [],
			],
		]);
	}

	public static function icon(string $name): string {
		$s = 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"';
		$map = [
			'doc-badge'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.4h7.1L18.4 7.6V14"/><path d="M7 3.4A1.6 1.6 0 0 0 5.4 5v14A1.6 1.6 0 0 0 7 20.6h4"/><path d="M14 3.4V7.4h4.2M8.2 10.2h5.4M8.2 13.2h3.6"/><path d="M16.2 14.2 19.6 15.6v3.1c0 2.2-1.6 4.1-3.4 4.7-1.8-.6-3.4-2.5-3.4-4.7v-3.1l3.4-1.4z"/><path d="m14.7 18.1 1.3 1.3 2.4-2.5"/></svg>',
			'user'         => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c.8-3.1 3.4-5 6.5-5s5.7 1.9 6.5 5"/></svg>',
			'shield-check' => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
			'monitor'      => '<svg viewBox="0 0 24 24" ' . $s . '><rect x="3.2" y="4.2" width="17.6" height="12.2" rx="1.8"/><path d="M8 20.2h8M12 16.4v3.8"/></svg>',
			'ticket'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M3.5 9.2A2.2 2.2 0 0 0 5.7 7h12.6a2.2 2.2 0 0 0 2.2 2.2v1.1a2.2 2.2 0 0 1 0 4.4v1.1A2.2 2.2 0 0 0 18.3 18H5.7A2.2 2.2 0 0 0 3.5 15.8v-1.1a2.2 2.2 0 0 1 0-4.4V9.2z"/><path stroke-dasharray="2 2" d="M12 7.2v10.6"/></svg>',
			'gavel'        => '<svg viewBox="0 0 24 24" ' . $s . '><path d="m14.2 7.2 3.6 3.6M8.4 13 4.8 16.6M12.6 5.6l5.8 5.8-2.2 2.2-5.8-5.8 2.2-2.2z"/><path d="M4.2 19.4h9.4"/><path d="m7.2 14.2 4.6-4.6"/></svg>',
			'calendar'     => '<svg viewBox="0 0 24 24" ' . $s . '><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3.5v3M16 3.5v3M3.5 10h17"/></svg>',
			'shuffle'      => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M4 7h3.2c.9 0 1.7.4 2.2 1.2L16.6 19H20"/><path d="m17.2 16.4 2.8 2.6-2.8 2.4"/><path d="M4 17h3.2c.9 0 1.7-.4 2.2-1.2L11 13.4"/><path d="M14.4 9.2 16.6 5H20"/><path d="m17.2 2.6 2.8 2.4-2.8 2.6"/></svg>',
			'megaphone'    => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M4 11v2a2 2 0 0 0 2 2h1l2 5h2l-1.2-5H15"/><path d="M4 11c0-1.2.5-2.3 1.4-3.1L20 3v16L5.4 14.1A4 4 0 0 1 4 11z"/></svg>',
			'box'          => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M3.8 8.2 12 4.2l8.2 4-8.2 4-8.2-4z"/><path d="M3.8 8.2V16L12 20l8.2-4V8.2"/><path d="M12 12.2V20"/></svg>',
			'gift'         => '<svg viewBox="0 0 24 24" ' . $s . '><rect x="3.5" y="10" width="17" height="10.5" rx="1.4"/><path d="M3.5 14.2h17M12 10v10.5"/><path d="M12 10c0-2.4-1.6-4.4-3.6-4.4S7.2 7.8 12 10c4.8-2.2 5.6-4.4 3.6-4.4S12 7.6 12 10z"/></svg>',
			'alert'        => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="12" cy="12" r="8.4"/><path d="M12 8v5.2"/><circle cx="12" cy="16.4" r="0.85" fill="currentColor" stroke="none"/></svg>',
			'shield'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/></svg>',
			'lock'         => '<svg viewBox="0 0 24 24" ' . $s . '><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
			'lock-badge'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><rect x="4.4" y="9.4" width="11.4" height="8.8" rx="1.8"/><path d="M7.2 9.4V7.2a3.1 3.1 0 0 1 6.2 0v2.2"/><circle cx="10.1" cy="13.8" r="1"/><path d="M16.2 13.6 19.8 15.1v3.2c0 2.2-1.7 4.2-3.6 4.8-1.9-.6-3.6-2.6-3.6-4.8v-3.2l3.6-1.5z"/><path d="m14.7 17.6 1.3 1.3 2.5-2.5"/></svg>',
			'id-card'      => '<svg viewBox="0 0 24 24" ' . $s . '><rect x="3.2" y="5.2" width="17.6" height="13.6" rx="2"/><circle cx="9" cy="11.2" r="2.1"/><path d="M5.8 16.2c.5-1.7 1.9-2.7 3.2-2.7s2.7 1 3.2 2.7M13.6 10.2h5.2M13.6 13.4h4.2"/></svg>',
			'target'       => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="4.4"/><circle cx="12" cy="12" r="1.25" fill="currentColor" stroke="none"/></svg>',
			'shield-lock'  => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><rect x="9" y="11.2" width="6" height="5" rx="1.1"/><path d="M10.3 11.2V9.6a1.7 1.7 0 0 1 3.4 0v1.6"/></svg>',
			'database'     => '<svg viewBox="0 0 24 24" ' . $s . '><ellipse cx="12" cy="6.2" rx="7.2" ry="2.6"/><path d="M4.8 6.2v4.6c0 1.5 3.2 2.6 7.2 2.6s7.2-1.1 7.2-2.6V6.2"/><path d="M4.8 10.8v4.6c0 1.5 3.2 2.6 7.2 2.6s7.2-1.1 7.2-2.6v-4.6"/></svg>',
			'user-check'   => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="9.2" cy="8" r="3"/><path d="M3.6 19c.7-2.9 3.1-4.6 5.6-4.6 1.4 0 2.7.5 3.8 1.4"/><path d="m13.4 15.6 2.1 2.1 4.2-4.3"/></svg>',
			'cookie'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 3.6a8.4 8.4 0 1 0 8.2 10.3 3.2 3.2 0 0 1-2.6-3.1 3.2 3.2 0 0 1-3.3-3.2A3.2 3.2 0 0 1 12 3.6z"/><circle cx="9" cy="10" r="0.9" fill="currentColor" stroke="none"/><circle cx="13.2" cy="14.2" r="0.8" fill="currentColor" stroke="none"/><circle cx="8.4" cy="15.4" r="0.7" fill="currentColor" stroke="none"/></svg>',
			'mail'         => '<svg viewBox="0 0 24 24" ' . $s . '><rect x="3" y="5.2" width="18" height="13.6" rx="2"/><path d="m3.4 7.2 8.6 6.4 8.6-6.4"/></svg>',
			'doc'          => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M7 3.5h7.2L17.5 6.8V20A1.5 1.5 0 0 1 16 21.5H7A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path d="M14 3.5V7h3.5M8.5 11h7M8.5 14.5h7M8.5 18h4.5"/></svg>',
			'ban'          => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="12" cy="12" r="8.3"/><path d="M6.2 6.2 17.8 17.8"/></svg>',
			'pencil'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M4 20h4.2L19.4 8.8a1.8 1.8 0 0 0 0-2.5l-1.7-1.7a1.8 1.8 0 0 0-2.5 0L4 15.8V20z"/><path d="m13.5 6.5 4 4"/></svg>',
			'book'         => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M4.5 5.2A3.2 3.2 0 0 1 7.7 4H20v14.5H7.7A3.2 3.2 0 0 0 4.5 21.7V5.2z"/><path d="M7.7 4v14.5"/></svg>',
			'courthouse'   => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M4 20.5h16M6.2 17.8V11.2h2.2v6.6m3.4-6.6v6.6h2.2V11.2m3.4 0v6.6h2.2V11.2"/><path d="M3.6 11.2h16.8L12 4.2 3.6 11.2z"/></svg>',
			'chat'         => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M5 16.5V7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v6A2.5 2.5 0 0 1 16.5 16H9l-4 3v-2.5z"/></svg>',
			'users'        => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="9" cy="8" r="2.8"/><path d="M3.8 18.2c.7-2.6 2.9-4.2 5.2-4.2s4.5 1.6 5.2 4.2"/><circle cx="16.4" cy="8.4" r="2.3"/><path d="M15.2 14.2c1.9.2 3.6 1.5 4.4 4"/></svg>',
			'info'         => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="12" cy="12" r="8.3"/><path d="M12 10.4V17"/><circle cx="12" cy="7.6" r="0.85" fill="currentColor" stroke="none"/></svg>',
			'scales'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 3.5v15.2"/><path d="M8 20.2h8"/><path d="M4.5 8.2h15"/><path d="M7.2 8.2 5 14.2c.9.8 2.5.8 3.4 0L6.2 8.2"/><path d="m16.8 8.2 2.2 6c-.9.8-2.5.8-3.4 0l1.2-6"/><circle cx="12" cy="3.8" r="1" fill="currentColor" stroke="none"/></svg>',
			'doc-download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.4h7.1L18.4 7.6V14"/><path d="M7 3.4A1.6 1.6 0 0 0 5.4 5v14A1.6 1.6 0 0 0 7 20.6h4.2"/><path d="M14 3.4V7.4h4.2M8.2 10.2h5.2M8.2 13h3.4"/><circle cx="17.2" cy="17.6" r="4.1"/><path d="M17.2 15.6v3.4m0 0 1.5-1.5m-1.5 1.5-1.5-1.5"/></svg>',
			'check'        => '<svg viewBox="0 0 24 24" ' . $s . '><circle cx="12" cy="12" r="8.3"/><path d="m8.4 12.2 2.4 2.4 4.8-5"/></svg>',
			'headset'      => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M5 13.2v-1.6A7 7 0 0 1 12 4.6a7 7 0 0 1 7 7v1.6"/><rect x="3.6" y="12.2" width="4.2" height="5.6" rx="1.4"/><rect x="16.2" y="12.2" width="4.2" height="5.6" rx="1.4"/><path d="M19 17.2v.8A3.4 3.4 0 0 1 15.6 21H14"/></svg>',
			'refresh'      => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 5.2V12h-6.8"/></svg>',
			'trophy'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M8 4h8v5a4 4 0 0 1-8 0V4z"/><path d="M8 6H5.5A2.5 2.5 0 0 0 8 10.2M16 6h2.5A2.5 2.5 0 0 1 16 10.2"/><path d="M12 13v3M8 20h8M10 20v-2h4v2"/></svg>',
			'sparkle'      => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 3.2 13.6 9 19.5 10.6 13.6 12.2 12 18 10.4 12.2 4.5 10.6 10.4 9 12 3.2z"/></svg>',
			'layers'       => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M12 4.2 3.8 8.4 12 12.6l8.2-4.2L12 4.2z"/><path d="M3.8 12 12 16.2 20.2 12"/><path d="M3.8 15.6 12 19.8 20.2 15.6"/></svg>',
			'sliders'      => '<svg viewBox="0 0 24 24" ' . $s . '><path d="M4 8h16M4 16h16"/><circle cx="9" cy="8" r="2.15"/><circle cx="15" cy="16" r="2.15"/></svg>',
		];
		return $map[$name] ?? $map['doc'];
	}
}

SorteoSeguro_Legal::init();
