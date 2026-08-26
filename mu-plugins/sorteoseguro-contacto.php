<?php
/**
 * Plugin Name: Sorteo Seguro – Contacto
 * Description: Plantilla PHP para la página Contacto (ID 1387), con chrome header/footer.
 * Author: Sorteo Seguro
 * Version: 1.0.2
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Contacto {

	const VERSION = '1.0.2';
	const PAGE_ID = 1387;
	const CF7_ID  = 1390;

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_action('wp_head', [__CLASS__, 'inline_css'], 100);
		add_action('wp_footer', [__CLASS__, 'inline_js'], 5);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
		add_filter('wpcf7_form_elements', [__CLASS__, 'enhance_form']);
		add_filter('wpcf7_posted_data', [__CLASS__, 'prepend_asunto']);
	}

	public static function is_contact_page(): bool {
		return is_page(self::PAGE_ID);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_contact_page() ? true : $enabled;
	}

	public static function bypass_page_cache(): void {
		if (!self::is_contact_page()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-contacto');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_contact_page()) {
			$classes[] = 'ss-contact-template';
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (!self::is_contact_page()) {
			return $template;
		}
		$custom = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-contacto/page-contacto.php';
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
		if (!self::is_contact_page()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_contact_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		if (function_exists('wpcf7_enqueue_scripts')) {
			wpcf7_enqueue_scripts();
		}
		if (function_exists('wpcf7_enqueue_styles')) {
			wpcf7_enqueue_styles();
		}
		$base = content_url('mu-plugins/sorteoseguro-contacto/assets');
		$dir  = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-contacto/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($dir . '/contacto.css')) {
			wp_enqueue_style('ss-contacto', $base . '/contacto.css', $deps, $ver);
		}
		if (is_readable($dir . '/contacto.js')) {
			wp_enqueue_script('ss-contacto', $base . '/contacto.js', [], $ver, true);
		}
	}

	public static function inline_css(): void {
		if (!self::is_contact_page()) {
			return;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-contacto/assets/contacto.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-contacto v" . esc_html(self::VERSION) . " -->\n";
		echo "<style id=\"ss-contacto-inline\">\n" . $css . "\n</style>\n";
	}

	public static function inline_js(): void {
		if (!self::is_contact_page()) {
			return;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-contacto/assets/contacto.js';
		if (!is_readable($path)) {
			return;
		}
		$js = file_get_contents($path);
		if ($js === false || $js === '') {
			return;
		}
		echo "\n<script id=\"ss-contacto-inline-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n" . $js . "\n</script>\n";
	}

	/** @return array<int, array{title:string,email:string,icon:string}> */
	public static function channels(): array {
		return [
			[
				'title' => 'Soporte y consultas generales',
				'email' => 'ayuda@sorteoseguro.com',
				'icon'  => 'mail',
			],
			[
				'title' => 'Consultas comerciales',
				'email' => 'ventas@sorteoseguro.com',
				'icon'  => 'tag',
			],
			[
				'title' => 'Publicar un evento o colaboración',
				'email' => 'contacto@sorteoseguro.com',
				'icon'  => 'megaphone',
			],
			[
				'title' => 'Fundaciones u organizaciones sin fines de lucro',
				'email' => 'ong-fundacion@sorteoseguro.com',
				'icon'  => 'heart',
			],
		];
	}

	public static function form_shortcode(): string {
		return '[contact-form-7 id="' . self::CF7_ID . '"]';
	}

	public static function enhance_form(string $html): string {
		if (!self::is_contact_page()) {
			return $html;
		}

		$options = '';
		foreach (self::channels() as $ch) {
			$options .= '<option value="' . esc_attr($ch['title']) . '">' . esc_html($ch['title']) . '</option>';
		}

		$asunto  = '<div class="cf7-asunto">';
		$asunto .= '<span class="ss-contact-field ss-contact-field--select">';
		$asunto .= '<span class="ss-contact-field__ico" aria-hidden="true">' . self::icon('chat') . '</span>';
		$asunto .= '<select name="ss_asunto" class="ss-contact-asunto" aria-label="Asunto" required>';
		$asunto .= '<option value="" selected disabled>Asunto</option>';
		$asunto .= $options;
		$asunto .= '</select>';
		$asunto .= '<span class="ss-contact-field__chev" aria-hidden="true">' . self::icon('chevron-down') . '</span>';
		$asunto .= '</span></div>';

		if (strpos($html, 'cf7-asunto') === false) {
			$html = preg_replace('/(<div class="cf7-mensaje">)/', $asunto . '$1', $html, 1) ?? $html;
		}

		$html = str_replace('value="Enviar"', 'value="Enviar mensaje"', $html);
		$html = str_replace(
			'<div class="cf7-boton">',
			'<div class="cf7-boton ss-contact-submit"><span class="ss-contact-submit__ico" aria-hidden="true">' . self::icon('send') . '</span>',
			$html
		);

		$wraps = [
			'cf7-nombre'   => 'user',
			'cf7-apellido' => 'user',
			'cf7-email'    => 'mail-line',
			'cf7-telefono' => 'phone',
			'cf7-mensaje'  => 'pencil',
		];
		foreach ($wraps as $cls => $icon) {
			$html = preg_replace(
				'/(<div class="' . preg_quote($cls, '/') . '">)(.*?)(<\/div>)/s',
				'$1<span class="ss-contact-field">'
				. '<span class="ss-contact-field__ico" aria-hidden="true">' . self::icon($icon) . '</span>'
				. '$2</span>$3',
				$html,
				1
			) ?? $html;
		}

		return $html;
	}

	/** @param array<string, mixed> $posted */
	public static function prepend_asunto(array $posted): array {
		$asunto = isset($_POST['ss_asunto']) ? sanitize_text_field(wp_unslash((string) $_POST['ss_asunto'])) : '';
		if ($asunto === '' || !isset($posted['mensaje'])) {
			return $posted;
		}
		$msg = (string) $posted['mensaje'];
		if (strpos($msg, 'Asunto: ') !== 0) {
			$posted['mensaje'] = 'Asunto: ' . $asunto . "\n\n" . $msg;
		}
		return $posted;
	}

	public static function icon(string $name): string {
		$icons = [
			'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>',
			'mail-line' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 7 9-7"/></svg>',
			'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 12.4 21.6a2 2 0 0 1-2.8 0L2.4 14.4a2 2 0 0 1 0-2.8L10.6 3.4A2 2 0 0 1 12 3h7.5A1.5 1.5 0 0 1 21 4.5V12a2 2 0 0 1-.4 1.4z"/><circle cx="16.2" cy="7.8" r="1.1" fill="currentColor" stroke="none"/></svg>',
			'megaphone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11v2a2 2 0 0 0 2 2h1l2 5h2l-1.2-5H15"/><path d="M4 11c0-1.2.5-2.3 1.4-3.1L20 3v16L5.4 14.1A4 4 0 0 1 4 11z"/></svg>',
			'heart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></svg>',
			'chevron' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>',
			'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.4-7 9.5C8 19.4 5 15.5 5 11V6l7-3z"/></svg>',
			'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c.8-3.1 3.4-5 6.5-5s5.7 1.9 6.5 5"/></svg>',
			'phone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M7.2 3.8 9.4 3.2a1.5 1.5 0 0 1 1.7.8l1 2.2a1.5 1.5 0 0 1-.4 1.7L10.4 9.2a11.5 11.5 0 0 0 4.4 4.4l1.3-1.3a1.5 1.5 0 0 1 1.7-.4l2.2 1a1.5 1.5 0 0 1 .8 1.7l-.6 2.2a1.5 1.5 0 0 1-1.6 1.1C10.4 17.4 6.6 13.6 5.1 5.4a1.5 1.5 0 0 1 1.1-1.6z"/></svg>',
			'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 16.5V7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v6A2.5 2.5 0 0 1 16.5 16H9l-4 3v-2.5z"/></svg>',
			'pencil' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4.2L19.4 8.8a1.8 1.8 0 0 0 0-2.5l-1.7-1.7a1.8 1.8 0 0 0-2.5 0L4 15.8V20z"/><path d="M13.5 6.5l4 4"/></svg>',
			'send' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12 20 4l-7 16-2.2-6.3L4 12z"/><path d="M10.8 13.7 20 4"/></svg>',
			'lock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>',
			'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2.4l2.7 5.6 6.2.9-4.5 4.4 1.1 6.1L12 16.6 6.5 19.4l1.1-6.1L3.1 8.9l6.2-.9z"/></svg>',
		];
		return $icons[$name] ?? '';
	}
}

SorteoSeguro_Contacto::init();
