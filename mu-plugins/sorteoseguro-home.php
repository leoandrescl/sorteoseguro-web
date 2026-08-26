<?php
/**
 * Plugin Name: Sorteo Seguro – Home v2
 * Description: Plantilla PHP para la página Home v2 (ID 74938), con chrome header/footer.
 * Author: Sorteo Seguro
 * Version: 1.3.26
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Home {

	const VERSION = '1.3.26';
	const PAGE_ID = 74938;

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

	public static function is_home_v2(): bool {
		return is_page(self::PAGE_ID);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_home_v2() ? true : $enabled;
	}

	/**
	 * Home v2 aún se itera: LiteSpeed/Hostinger CDN ignoran Ctrl+F5.
	 * Quitar este bypass cuando la home quede como portada definitiva.
	 */
	public static function bypass_page_cache(): void {
		if (!self::is_home_v2()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-home-v2');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_home_v2()) {
			$classes[] = 'ss-home-template';
		}
		return $classes;
	}

	public static function template_include(string $template): string {
		if (!self::is_home_v2()) {
			return $template;
		}
		$custom = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-home/page-home.php';
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
		if (!self::is_home_v2()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_home_v2()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		wp_enqueue_style(
			'ss-home-caveat',
			'https://fonts.googleapis.com/css2?family=Caveat:wght@500;600;700&display=swap',
			[],
			null
		);
		$base = content_url('mu-plugins/sorteoseguro-home/assets');
		$dir  = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-home/assets';
		$ver  = self::VERSION;
		if (is_readable($dir . '/home.css')) {
			wp_enqueue_style('ss-home', $base . '/home.css', [], $ver);
		}
		if (is_readable($dir . '/home.js')) {
			wp_enqueue_script('ss-home', $base . '/home.js', [], $ver, true);
		}
	}

	/** Inline de respaldo (LiteSpeed/Complianz a veces omiten el enqueue). */
	public static function inline_css(): void {
		if (!self::is_home_v2()) {
			return;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-home/assets/home.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-home v" . esc_html(self::VERSION) . " -->\n";
		echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@500;600;700&display=swap" media="all">' . "\n";
		echo "<style id=\"ss-home-inline\">\n" . $css . "\n</style>\n";
	}

	public static function inline_js(): void {
		if (!self::is_home_v2()) {
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

	/** @return int[] */
	public static function product_ids(): array {
		return apply_filters('ss_home_product_ids', [1091, 45941, 49102, 53210]);
	}

	/**
	 * Meta estática por producto (categoría / ubicación / specs).
	 *
	 * @return array<int, array{category:string,category_slug:string,meta:string}>
	 */
	public static function product_overrides(): array {
		return [
			1091  => [
				'category'      => 'INMOBILIARIO',
				'category_slug' => 'realty',
				'meta'          => 'Mantagua, V Región',
			],
			45941 => [
				'category'      => 'VEHÍCULO',
				'category_slug' => 'vehicle',
				'meta'          => '0 km · Automático',
			],
			49102 => [
				'category'      => 'INMOBILIARIO',
				'category_slug' => 'realty',
				'meta'          => 'Región de Coquimbo',
			],
			53210 => [
				'category'      => 'VEHÍCULO',
				'category_slug' => 'vehicle',
				'meta'          => 'Active PureTech 1.2',
			],
		];
	}

	public static function clean_title(string $name): string {
		$name = preg_replace('/^DigiTicket\s*\|\s*/iu', '', $name) ?? $name;
		return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
	}

	/**
	 * @return array<int, array{
	 *   id:int,title:string,url:string,image:string,price:string,price_raw:float,
	 *   category:string,category_slug:string,meta:string,end_date:string,end_ts:int,
	 *   end_countdown:string,sold:int,max:int,progress:float
	 * }>
	 */
	public static function get_contest_cards(): array {
		$out = [];
		$overrides = self::product_overrides();

		foreach (self::product_ids() as $id) {
			$id = (int) $id;
			if ($id <= 0 || !function_exists('wc_get_product')) {
				continue;
			}
			$product = wc_get_product($id);
			if (!$product || $product->get_status() !== 'publish') {
				continue;
			}

			$max  = (int) get_post_meta($id, '_lty_maximum_tickets', true);
			$sold = 0;
			if (method_exists($product, 'get_purchased_ticket_count')) {
				$sold = (int) $product->get_purchased_ticket_count();
			}
			if ($max <= 0) {
				$max = max($sold, 1);
			}
			$progress = min(100, round(($sold / $max) * 100, 1));

			$end_raw = '';
			if (method_exists($product, 'get_lty_end_date')) {
				$end_raw = (string) $product->get_lty_end_date();
			}
			if ($end_raw === '') {
				$end_raw = (string) get_post_meta($id, '_lty_end_date', true);
			}
			$end_ts = $end_raw !== '' ? (int) strtotime($end_raw) : 0;
			$end_countdown = '';
			if (method_exists($product, 'get_countdown_timer_enddate')) {
				$end_countdown = (string) $product->get_countdown_timer_enddate();
			}
			if ($end_countdown === '') {
				$end_countdown = self::lottery_countdown_end($end_raw);
			}

			$ov = $overrides[$id] ?? [
				'category'      => 'CONCURSO',
				'category_slug' => 'other',
				'meta'          => '',
			];

			$image = get_the_post_thumbnail_url($id, 'large') ?: '';
			$price = function_exists('wc_price')
				? wc_price((float) $product->get_price())
				: '$' . number_format((float) $product->get_price(), 0, ',', '.');

			$out[] = [
				'id'            => $id,
				'title'         => self::clean_title($product->get_name()),
				'url'           => (string) get_permalink($id),
				'image'         => $image,
				'price'         => $price,
				'price_raw'     => (float) $product->get_price(),
				'category'      => $ov['category'],
				'category_slug' => $ov['category_slug'],
				'meta'          => $ov['meta'],
				'end_date'      => $end_raw,
				'end_ts'        => $end_ts,
				'end_countdown' => $end_countdown,
				'sold'          => $sold,
				'max'           => $max,
				'progress'      => $progress,
			];
		}

		return $out;
	}

	/**
	 * Misma fecha que el countdown de la ficha (Lottery).
	 * Lottery convierte a UTC y el JS la lee como hora local (`YYYY/MM/DD HH:MM:SS`).
	 */
	public static function lottery_countdown_end(string $end_raw): string {
		$end_raw = trim($end_raw);
		if ($end_raw === '') {
			return '';
		}
		if (class_exists('LTY_Date_Time') && method_exists('LTY_Date_Time', 'get_date_time_object')) {
			try {
				$date_object = LTY_Date_Time::get_date_time_object($end_raw, false, 'UTC');
				if ($date_object instanceof DateTimeInterface) {
					return $date_object->format('Y/m/d H:i:s');
				}
			} catch (Exception $e) {
				// Fallback below.
			}
		}
		try {
			$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('America/Santiago');
			$dt = date_create($end_raw, $tz);
			if (!$dt) {
				return '';
			}
			$dt->setTimezone(new DateTimeZone('UTC'));
			return $dt->format('Y/m/d H:i:s');
		} catch (Exception $e) {
			return '';
		}
	}

	public static function hero_asset_url(string $file): string {
		return content_url('mu-plugins/sorteoseguro-home/assets/hero/' . ltrim($file, '/'));
	}

	/**
	 * 5 banners: 1 general + 1 por concurso activo.
	 *
	 * @param array<int, array<string, mixed>> $contests
	 * @return array<int, array<string, mixed>>
	 */
	public static function hero_slides(array $contests): array {
		$by_id = [];
		foreach ($contests as $c) {
			$by_id[(int) ($c['id'] ?? 0)] = $c;
		}

		$fmt_price = static function (array $c): string {
			return '$' . number_format((float) ($c['price_raw'] ?? 0), 0, ',', '.');
		};

		$slides = [
			[
				'key'         => 'general',
				'theme'       => 'studio',
				'badge'       => 'CONCURSOS ACTIVOS',
				'badge_icon'  => 'dot',
				'title'       => 'Premios que pueden cambiarlo todo.',
				'title_lines' => [
					['text' => 'Premios que', 'em' => false],
					['text' => 'pueden', 'em' => true],
					['text' => 'cambiarlo todo.', 'em' => true],
				],
				'title_em'    => '',
				'kicker'      => '',
				'meta'        => '',
				'meta_icon'   => '',
				'sub'         => "Una casa, un Jeep, una parcela o un auto pueden estar\nmás cerca de lo que imaginas.",
				'sub_line2'   => 'Participa 100% online con tus ',
				'sub_em'      => 'DigiTickets',
				'sub_after'   => " y elige el\nconcurso que más te guste.",
				'specs'       => [],
				'prizes'      => [],
				'price'       => '',
				'price_label' => '',
				'cta'         => 'Ver concursos activos',
				'url'         => '#ss-home-concursos',
				'img_pc'      => 'slide-1-pc.jpg',
				'img_mobile'  => 'slide-1-mobile.jpg',
				'dot_label'   => 'Todos los concursos',
				'aria'        => 'Todos los concursos',
				'show_jumps'  => true,
			],
		];

		$defs = [
			1091 => [
				'key'         => 'casa',
				'theme'       => 'photo',
				'badge'       => 'CONCURSO INMOBILIARIO',
				'badge_icon'  => 'home',
				'title'       => 'Una casa con vista al mar podría ser tuya.',
				'title_lines' => [
					['text' => 'Una casa con', 'em' => false],
					['text' => 'vista al mar', 'em' => true],
					['text' => 'podría ser tuya.', 'em' => true],
				],
				'title_em'    => '',
				'kicker'      => 'Gran Casa Vista Mar Dunares',
				'meta'        => 'Mantagua, V Región',
				'meta_icon'   => 'pin',
				'sub'         => '',
				'specs'       => [
					['icon' => 'bed', 'value' => '5', 'label' => 'Dormitorios'],
					['icon' => 'bath', 'value' => '3', 'label' => 'Baños'],
					['icon' => 'ruler', 'value' => '260 m²', 'label' => 'Construidos'],
					['icon' => 'trees', 'value' => '850 m²', 'label' => 'Terreno'],
				],
				'prizes'      => [
					['badge' => '1º PREMIO', 'name' => "Gran Casa\nVista Mar Dunares"],
					['badge' => '2º PREMIO', 'name' => "MG New HS\n2026", 'image' => 2266],
					['badge' => '3º PREMIO', 'name' => "MG3\n2026", 'image' => 2265],
				],
				'price_label' => 'DigiTicket desde',
				'cta'         => 'Participar ahora',
				'img_pc'      => 'slide-2-pc.jpg',
				'img_mobile'  => 'slide-2-mobile.jpg',
				'chip_title'  => 'CASA',
				'chip_meta'   => "Vista Mar Dunares\nMantagua, V Región",
				'chip_icon'   => 'home',
				'dot_label'   => 'Casa Vista Mar Dunares',
			],
			45941 => [
				'key'         => 'jeep',
				'theme'       => 'dusk',
				'badge'       => 'CONCURSO VEHICULAR',
				'badge_icon'  => 'car',
				'title'       => 'Estrena un Jeep Avenger 0 km.',
				'title_lines' => [
					['text' => 'Estrena un', 'em' => false],
					['text' => 'Jeep Avenger', 'em' => true],
					['text' => '0 km.', 'em' => true],
				],
				'title_em'    => '',
				'kicker'      => 'Jeep Avenger 2026',
				'meta'        => '',
				'meta_icon'   => '',
				'sub'         => "Tecnología, diseño y libertad para\ndisfrutarlo desde el primer kilómetro.",
				'specs'       => [],
				'prizes_layout' => 'cols',
				'prizes'      => [
					[
						'badge'      => '1º PREMIO',
						'icon'       => 'trophy',
						'name'       => 'Jeep Avenger',
						'detail'     => '0 km',
						'image'      => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/Jeep-300x169.png',
						'image_kind' => 'logo',
					],
					[
						'badge'      => '2º PREMIO',
						'icon'       => 'gift',
						'name'       => 'Gift Card',
						'amount'     => '$2.000.000',
						'image'      => 'img-giftcard.png',
						'image_kind' => 'card',
					],
					[
						'badge'      => '3º PREMIO',
						'icon'       => 'gift',
						'name'       => 'Gift Card',
						'amount'     => '$1.000.000',
						'image'      => 'img-giftcard.png',
						'image_kind' => 'card',
					],
				],
				'price_label' => 'DigiTicket',
				'cta'         => 'Quiero participar',
				'img_pc'      => 'slide-3-pc.jpg',
				'img_mobile'  => 'slide-3-mobile.jpg',
				'chip_title'  => 'JEEP AVENGER 2026',
				'chip_meta'   => '0 km · Híbrido',
				'chip_icon'   => 'car',
				'dot_label'   => 'Jeep Avenger 2026',
			],
			49102 => [
				'key'         => 'parcela',
				'theme'       => 'photo',
				'badge'       => 'CONCURSO INMOBILIARIO',
				'badge_icon'  => 'land',
				'title'       => '5.500 m² pueden cambiar tus planes.',
				'title_lines' => [
					['text' => '5.500 m² pueden', 'em' => false],
					['text' => 'cambiar tus planes.', 'em' => true],
				],
				'title_em'    => '',
				'kicker'      => 'Parcela en Punta de Choros',
				'meta'        => '',
				'meta_icon'   => '',
				'sub'         => "Una oportunidad para disfrutar, invertir o desarrollar\ntu propio proyecto en uno de los entornos naturales\nmás atractivos del norte de Chile.",
				'specs'       => [
					['icon' => 'ruler', 'value' => '5.500 m²', 'label' => 'de terreno'],
					['icon' => 'pin', 'value' => 'Punta de Choros', 'label' => 'Región de Coquimbo'],
					['icon' => 'doc', 'value' => 'Transferencia', 'label' => 'por escritura pública'],
				],
				'prizes'      => [],
				'price_label' => 'DigiTicket',
				'cta'         => 'Participar por la parcela',
				'img_pc'      => 'slide-4-pc.jpg',
				'img_mobile'  => 'slide-4-mobile.jpg',
				'chip_title'  => 'PARCELA 5.500 m²',
				'chip_meta'   => "Punta de Choros\nRegión de Coquimbo",
				'chip_icon'   => 'land',
				'dot_label'   => 'Parcela Punta de Choros',
			],
			53210 => [
				'key'         => 'peugeot',
				'theme'       => 'studio',
				'badge'       => 'CONCURSO VEHICULAR',
				'badge_icon'  => 'car',
				'title'       => 'Un Peugeot 208 puede ser tuyo.',
				'title_lines' => [
					['text' => 'Un Peugeot 208', 'em' => false],
					['text' => 'puede ser tuyo.', 'em' => true],
				],
				'title_em'    => '',
				'kicker'      => 'Peugeot 208 Active PureTech 1.2',
				'meta'        => '',
				'meta_icon'   => '',
				'sub'         => 'Participa por este Peugeot 208 Active PureTech 1.2 AT. Evento transmitido públicamente y proceso transparente conforme a las bases legales.',
				'specs'       => [
					['icon' => 'car', 'label' => 'Año 2017'],
					['icon' => 'car', 'label' => 'Automático'],
					['icon' => 'clock', 'label' => '89.500 km'],
				],
				'prizes'      => [],
				'price_label' => 'DigiTicket desde',
				'cta'         => 'Participar ahora',
				'img_pc'      => 'slide-5-pc.jpg',
				'img_mobile'  => 'slide-5-mobile.jpg',
				'chip_title'  => 'PEUGEOT 208',
				'chip_meta'   => 'Active PureTech 1.2',
				'chip_icon'   => 'car',
				'dot_label'   => 'Peugeot 208',
			],
		];

		foreach ($defs as $id => $def) {
			if (!isset($by_id[$id])) {
				continue;
			}
			$c = $by_id[$id];
			$def['product_id'] = $id;
			$def['url'] = (string) ($c['url'] ?? '#ss-home-concursos');
			$def['price'] = $fmt_price($c);
			$def['aria'] = (string) ($def['kicker'] !== '' ? $def['kicker'] : $def['title']);
			$def['show_jumps'] = false;
			if (!empty($def['prizes']) && is_array($def['prizes'])) {
				foreach ($def['prizes'] as $pi => $prize) {
					$img = $prize['image'] ?? null;
					$def['prizes'][$pi]['image'] = '';
					if ((is_int($img) || (is_string($img) && ctype_digit($img))) && function_exists('wp_get_attachment_image_url')) {
						$url = wp_get_attachment_image_url((int) $img, 'medium');
						$def['prizes'][$pi]['image'] = $url ? (string) $url : '';
					} elseif (is_string($img) && $img !== '') {
						if (preg_match('#^https?://#i', $img)) {
							$def['prizes'][$pi]['image'] = $img;
						} else {
							$def['prizes'][$pi]['image'] = self::hero_asset_url($img);
						}
					}
				}
			}
			$slides[] = $def;
		}

		return apply_filters('ss_home_hero_slides', $slides, $contests);
	}

	/**
	 * Cupón WooCommerce del banner promo (editable en wp-admin).
	 * Contador y % se leen del cupón; no hardcodear caducidad.
	 */
	public static function promo_coupon_id(): int {
		return (int) apply_filters('ss_home_promo_coupon_id', 75018);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function promo_config(): ?array {
		$coupon_id = self::promo_coupon_id();
		$code      = 'SSD30OFF';
		$discount  = '30%';
		$end       = '2026-08-31 23:59:59';
		$enabled   = true;

		if ($coupon_id > 0 && class_exists('WC_Coupon')) {
			$coupon = new WC_Coupon($coupon_id);
			$loaded_code = (string) $coupon->get_code();
			if ($loaded_code !== '') {
				$code = strtoupper($loaded_code);
				$status = get_post_status($coupon_id);
				if ($status && $status !== 'publish') {
					$enabled = false;
				}

				$type   = (string) $coupon->get_discount_type();
				$amount = (float) $coupon->get_amount();
				$desc   = (string) $coupon->get_description();
				if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $desc, $m)) {
					$num = str_replace(',', '.', $m[1]);
					if (strpos($num, '.') !== false) {
						$num = rtrim(rtrim($num, '0'), '.');
					}
					$discount = $num . '%';
				} elseif (in_array($type, ['percent', 'percent_product'], true) && $amount > 0) {
					$raw = number_format($amount, 2, '.', '');
					if (strpos($raw, '.') !== false) {
						$raw = rtrim(rtrim($raw, '0'), '.');
					}
					$discount = $raw . '%';
				} elseif ($amount > 0 && function_exists('wc_price')) {
					$discount = wp_strip_all_tags(html_entity_decode(wc_price($amount), ENT_QUOTES, 'UTF-8'));
				}

				$exp = $coupon->get_date_expires();
				if ($exp) {
					$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
					$exp->setTimezone($tz);
					// WC suele guardar 00:00:00 del día de caducidad: el banner cuenta hasta fin de ese día.
					if ($exp->format('H:i:s') === '00:00:00') {
						$exp->setTime(23, 59, 59);
					}
					$end = $exp->format('Y-m-d H:i:s');
					if ($exp->getTimestamp() < time()) {
						$enabled = false;
					}
				}
			}
		}

		$config = apply_filters('ss_home_promo_config', [
			'enabled'       => $enabled,
			'coupon_id'     => $coupon_id,
			'badge'         => 'Promoción por tiempo limitado',
			'title_before'  => 'Beneficio',
			'title_em'      => 'especial',
			'title_after'   => 'activo',
			'subtitle'      => 'Aprovecha este descuento exclusivo y participa hoy.',
			'discount'      => $discount,
			'discount_unit' => 'dcto.',
			'discount_note' => 'en DigiTickets seleccionados',
			'code'          => $code,
			'end'           => $end,
			'cta_label'     => 'Ver concursos',
			'cta_url'       => '#ss-home-concursos',
			'legal'         => 'Promoción válida por tiempo limitado. Revisa las bases legales en cada concurso.',
		]);

		if (empty($config) || empty($config['enabled'])) {
			return null;
		}
		return $config;
	}

	/**
	 * @return array<int, array{name:string,prize:string,date:string,quote:string}>
	 */
	public static function testimonials(): array {
		return [
			[
				'name'  => 'Roberto A.',
				'prize' => 'Casa Vista Mar Dunares',
				'date'  => 'Mayo 2024',
				'quote' => 'Nunca pensé que iba a ganar una casa. Sorteo Seguro realmente cambia vidas.',
			],
			[
				'name'  => 'Camila R.',
				'prize' => 'Jeep Avenger 2026',
				'date'  => 'Febrero 2025',
				'quote' => 'Nunca pensé que iba a ganar. El proceso fue súper claro y el sorteo en vivo me dio toda la confianza.',
			],
			[
				'name'  => 'Rodrigo M.',
				'prize' => 'Casa Vista Mar Dunares',
				'date'  => 'Enero 2025',
				'quote' => 'Todo transparente, con bases ante notario. Recibí mi DigiTicket al instante y seguí el sorteo sin dramas.',
			],
			[
				'name'  => 'Valentina P.',
				'prize' => 'Peugeot 208',
				'date'  => 'Diciembre 2024',
				'quote' => 'Fácil, rápido y seguro. Compré más DigiTickets con el pack y terminé llevándome el auto.',
			],
		];
	}

	/**
	 * @return array<int, array{name:string,image:string,alt:string}>
	 */
	public static function partners(): array {
		$mp = content_url('mu-plugins/sorteoseguro-chrome/assets/logo-mercado-pago.png');
		$wp = content_url('mu-plugins/sorteoseguro-chrome/assets/logo-web-pay-plus.png');
		return [
			[
				'name'  => 'Jeep',
				'image' => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/Jeep-300x169.png',
				'alt'   => 'Jeep',
			],
			[
				'name'  => 'Mercado Pago',
				'image' => $mp,
				'alt'   => 'Mercado Pago',
			],
			[
				'name'  => 'Notarías de Chile',
				'image' => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo-notaria.jpg',
				'alt'   => 'Notarías de Chile',
			],
			[
				'name'  => 'Webpay Plus',
				'image' => $wp,
				'alt'   => 'Webpay Plus',
			],
		];
	}

	/** Producto fuente de precios de packs en home (Jeep Avenger). */
	public static function pack_product_id(): int {
		return (int) apply_filters('ss_home_pack_product_id', 45941);
	}

	public static function pack_cta_url(): string {
		$id = self::pack_product_id();
		$url = $id > 0 ? (string) get_permalink($id) : '';
		if ($url === '') {
			$url = 'https://sorteoseguro.cl/producto/jeep-avenger/';
		}
		return (string) apply_filters('ss_home_pack_cta_url', $url);
	}

	/**
	 * Packs activos (misma lógica que ficha): 3x2, 5x3, 10x5, 20x8.
	 * Precio = packs del Jeep Avenger.
	 *
	 * @return array<int, array{
	 *   buy:int,pay:int,key:string,title:string,badge:string,badge_slug:string,
	 *   price_raw:float,price:string,theme:string
	 * }>
	 */
	public static function get_pack_cards(): array {
		$order = [
			['buy' => 3,  'pay' => 2, 'theme' => 'pink'],
			['buy' => 5,  'pay' => 3, 'theme' => 'purple'],
			['buy' => 10, 'pay' => 5, 'theme' => 'gold'],
			['buy' => 20, 'pay' => 8, 'theme' => 'dark'],
		];

		$mins = [];
		$pid = self::pack_product_id();
		if ($pid > 0) {
			$unit = 0.0;
			if (function_exists('wc_get_product')) {
				$product = wc_get_product($pid);
				if ($product) {
					$unit = (float) (function_exists('wc_get_price_to_display')
						? wc_get_price_to_display($product)
						: $product->get_price());
				}
			}

			$rows = [];
			if (class_exists('Promo_Engine_Stable') && method_exists('Promo_Engine_Stable', 'get_product_page_campaigns')) {
				$rows = Promo_Engine_Stable::get_product_page_campaigns($pid);
			}

			if (!$rows && $unit > 0) {
				foreach ($order as $o) {
					$rows[] = [
						'buy'        => $o['buy'],
						'pay'        => $o['pay'],
						'pack_price' => $unit * $o['pay'],
					];
				}
			}

			foreach ($rows as $c) {
				$buy = max(1, (int) ($c['buy'] ?? 1));
				$pay = max(1, (int) ($c['pay'] ?? 1));
				if ($buy <= 1) {
					continue;
				}
				$key = $buy . 'x' . $pay;
				$price = (float) ($c['pack_price'] ?? ($unit * $pay));
				if ($price <= 0) {
					continue;
				}
				$mins[$key] = $price;
			}
		}

		$out = [];
		foreach ($order as $o) {
			$key = $o['buy'] . 'x' . $o['pay'];
			$price = $mins[$key] ?? 0.0;
			if ($price <= 0) {
				continue;
			}
			$badge = '';
			$badge_slug = '';
			if ($o['buy'] === 5 && $o['pay'] === 3) {
				$badge = 'Más elegido';
				$badge_slug = 'elegido';
			} elseif ($o['buy'] === 20 && $o['pay'] === 8) {
				$badge = 'Más conveniente';
				$badge_slug = 'conveniente';
			}
			$out[] = [
				'buy'        => $o['buy'],
				'pay'        => $o['pay'],
				'key'        => $key,
				'title'      => 'PACK ' . $key,
				'badge'      => $badge,
				'badge_slug' => $badge_slug,
				'price_raw'  => $price,
				'price'      => '$' . number_format($price, 0, ',', '.'),
				'theme'      => $o['theme'],
			];
		}

		return apply_filters('ss_home_pack_cards', $out);
	}

	/** @return array<int, array{id:int,q:string,a:string}> */
	public static function faq_teaser(int $limit = 5): array {
		if (!class_exists('SorteoSeguro_FAQ')) {
			return [];
		}
		$all = SorteoSeguro_FAQ::get_faqs();
		$out = [];
		foreach (array_slice($all, 0, $limit) as $faq) {
			$out[] = [
				'id' => (int) ($faq['id'] ?? 0),
				'q'  => (string) ($faq['q'] ?? ''),
				'a'  => (string) ($faq['a'] ?? ''),
			];
		}
		return $out;
	}
}

SorteoSeguro_Home::init();
