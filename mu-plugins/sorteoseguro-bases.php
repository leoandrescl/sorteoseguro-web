<?php
/**
 * Plugin Name: Sorteo Seguro – Bases legales
 * Description: Página Bases legales (ID 75047): hero, sorteos activos con PDF, y bloques legales/FAQ del checkout.
 * Author: Sorteo Seguro
 * Version: 1.0.4
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Bases {

	const VERSION = '1.0.4';
	const PAGE_ID = 75047;
	const DIR     = __DIR__ . '/sorteoseguro-bases';
	/** Video oficial del hero (YouTube Shorts). */
	const HERO_YOUTUBE = 'https://www.youtube.com/shorts/TmbMQswqlzw';

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('ss_chrome_bases_url', [__CLASS__, 'footer_bases_url']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_filter('cmplz_whitelisted_script_tags', [__CLASS__, 'cmplz_whitelist_video']);
	}

	/**
	 * Evita que Complianz vacíe el iframe del hero (deja el preview/player de YouTube real).
	 *
	 * @param array<int, string> $tags
	 * @return array<int, string>
	 */
	public static function cmplz_whitelist_video(array $tags): array {
		$tags[] = 'ss-bases-hero__iframe';
		$tags[] = 'TmbMQswqlzw';
		return $tags;
	}

	public static function is_bases_page(): bool {
		return is_page(self::PAGE_ID);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_bases_page() ? true : $enabled;
	}

	public static function footer_bases_url(string $url): string {
		$permalink = get_permalink(self::PAGE_ID);
		return $permalink ? (string) $permalink : home_url('/bases-legales/');
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_bases_page()) {
			$classes[] = 'ss-bases-template';
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (!self::is_bases_page()) {
			return $template;
		}
		$custom = self::DIR . '/page-bases.php';
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
		if (!self::is_bases_page()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_bases_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}

		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];

		// Reutilizar CSS de help/legal del checkout.
		$co_css = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-checkout/assets/checkout.css';
		if (is_readable($co_css)) {
			$co_ver = class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::VERSION : self::VERSION;
			wp_enqueue_style(
				'ss-checkout',
				content_url('mu-plugins/sorteoseguro-checkout/assets/checkout.css'),
				$deps,
				$co_ver
			);
			$deps[] = 'ss-checkout';
		}

		$css = self::DIR . '/assets/bases.css';
		if (is_readable($css)) {
			wp_enqueue_style(
				'ss-bases',
				content_url('mu-plugins/sorteoseguro-bases/assets/bases.css'),
				$deps,
				self::VERSION
			);
		}
	}

	/**
	 * @return array<int, array{
	 *   id:int,title:string,url:string,image:string,category:string,
	 *   date:string,price:string,pdf_url:string,pdf_label:string
	 * }>
	 */
	public static function contest_rows(): array {
		$rows = [];
		$ids  = class_exists('SorteoSeguro_Home')
			? SorteoSeguro_Home::product_ids()
			: [1091, 45941, 49102, 53210];
		$overrides = class_exists('SorteoSeguro_Home')
			? SorteoSeguro_Home::product_overrides()
			: [];

		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id <= 0 || !function_exists('wc_get_product')) {
				continue;
			}
			$product = wc_get_product($id);
			if (!$product || $product->get_status() !== 'publish') {
				continue;
			}

			$ov = $overrides[$id] ?? [
				'category'      => 'CONCURSO',
				'category_slug' => 'other',
				'meta'          => '',
			];
			$category = (string) ($ov['category'] ?? 'CONCURSO');
			if (strcasecmp($category, 'VEHÍCULO') === 0 || strcasecmp($category, 'VEHICULO') === 0) {
				$category = 'VEHICULAR';
			}

			$title = class_exists('SorteoSeguro_Home')
				? SorteoSeguro_Home::clean_title($product->get_name())
				: trim(preg_replace('/^DigiTicket\s*\|\s*/iu', '', $product->get_name()) ?? $product->get_name());

			$end_raw = '';
			if (method_exists($product, 'get_lty_end_date')) {
				$end_raw = (string) $product->get_lty_end_date();
			}
			if ($end_raw === '') {
				$end_raw = (string) get_post_meta($id, '_lty_end_date', true);
			}
			$end_ts = $end_raw !== '' ? (int) strtotime($end_raw) : 0;
			$date   = $end_ts > 0 ? wp_date('d/m/Y', $end_ts) : '';

			$price = function_exists('wc_price')
				? wp_strip_all_tags(wc_price((float) $product->get_price()))
				: '$' . number_format((float) $product->get_price(), 0, ',', '.');

			$pdf = self::bases_pdf_url($id);
			$rows[] = [
				'id'        => $id,
				'title'     => $title,
				'url'       => (string) get_permalink($id),
				'image'     => (string) (get_the_post_thumbnail_url($id, 'large') ?: ''),
				'category'  => $category,
				'date'      => $date,
				'price'     => $price,
				'pdf_url'   => $pdf['url'],
				'pdf_label' => $pdf['label'],
			];
		}

		return $rows;
	}

	/** @return array{url:string,label:string} */
	public static function bases_pdf_url(int $product_id): array {
		$raw = (string) get_post_meta($product_id, 'bases_legales', true);
		$raw = trim($raw);
		if ($raw === '') {
			return ['url' => '', 'label' => 'PDF'];
		}

		$url = str_replace(
			'https://darkturquoise-camel-249985.hostingersite.com',
			home_url(),
			$raw
		);
		$url = esc_url_raw($url);

		$label = 'PDF';
		$path  = (string) (wp_parse_url($url, PHP_URL_PATH) ?: '');
		if ($path !== '') {
			$local = ABSPATH . ltrim($path, '/');
			if (is_file($local)) {
				$bytes = (int) filesize($local);
				if ($bytes >= 1048576) {
					$label = 'PDF - ' . number_format($bytes / 1048576, 1, '.', '') . ' MB';
				} elseif ($bytes > 0) {
					$label = 'PDF - ' . max(1, (int) round($bytes / 1024)) . ' KB';
				}
			}
		}

		return ['url' => $url, 'label' => $label];
	}

	/** @return array{embed:string,id:string,thumb:string} */
	public static function hero_video(): array {
		$yt_url = (string) apply_filters('ss_bases_hero_youtube', self::HERO_YOUTUBE);

		$id = '';
		if (class_exists('SorteoSeguro_PDP_Templates')) {
			$id = SorteoSeguro_PDP_Templates::youtube_id($yt_url);
		} elseif (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i', $yt_url, $m)) {
			$id = $m[1];
		}

		$embed = $id !== ''
			? 'https://www.youtube.com/embed/' . rawurlencode($id) . '?rel=0&modestbranding=1&playsinline=1'
			: '';
		$thumb = $id !== ''
			? 'https://i.ytimg.com/vi/' . rawurlencode($id) . '/hqdefault.jpg'
			: '';

		return ['embed' => $embed, 'id' => $id, 'thumb' => $thumb];
	}

	public static function icon(string $name): string {
		$map = [
			'shield'   => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>',
			'doc'      => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7 3.5h7.2L17.5 6.8V20a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M14 3.5V7h3.5M8.5 10.5h5M8.5 13.5h5"/></svg>',
			'calendar' => '<svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="15.5" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M8 3.5v3M16 3.5v3M3.5 10h17"/></svg>',
			'ticket'   => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M3.5 9.2A2.2 2.2 0 0 0 5.7 7h12.6a2.2 2.2 0 0 0 2.2 2.2v1.1a2.2 2.2 0 0 1 0 4.4v1.1A2.2 2.2 0 0 0 18.3 18H5.7A2.2 2.2 0 0 0 3.5 15.8v-1.1a2.2 2.2 0 0 1 0-4.4V9.2z"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-dasharray="2 2" d="M12 7.2v10.6"/></svg>',
			'download' => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0 4-4m-4 4-4-4M5 19h14"/></svg>',
		];
		return $map[$name] ?? $map['doc'];
	}
}

SorteoSeguro_Bases::init();
