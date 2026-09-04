<?php
/**
 * Plugin Name: Sorteo Seguro – Packs Lottery Auto
 * Description: Oculta selección manual de DigiTickets; AJAX pack → reserva N números al azar → carrito → checkout. No modifica lottery-for-woocommerce.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: rollback = renombrar/borrar este archivo + restaurar opciones documentadas en docs/fase-b-lottery-metas-rollback.md
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Packs_Lottery {

	const VERSION        = '1.3.29';
	const AJAX_ACTION    = 'ss_packs_select';
	const NONCE_ACTION   = 'ss_packs_lottery';
	const LOCK_PREFIX    = 'ss_pack_lock_';
	const MYSQL_LOCK_PFX = 'ss_pack_tickets_';
	const LOCK_TTL       = 20;
	const MYSQL_LOCK_WAIT = 15;
	const MAX_PICK_TRIES = 40;
	const OPTION_RESERVE_PREV = 'ss_packs_prev_reserve_enabled';
	const OPTION_RESERVE_TIME_PREV = 'ss_packs_prev_reserve_minutes';
	const OPTION_BOOTSTRAPPED = 'ss_packs_lottery_bootstrapped';

	/** @var int|null */
	private static $detected_product_id = null;

	public static function init(): void {
		add_action('plugins_loaded', array(__CLASS__, 'bootstrap_reserve_setting'), 20);

		add_action('wp', array(__CLASS__, 'detect_context'), 5);
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_front'), 5);
		add_action('wp_head', array(__CLASS__, 'print_head_assets'), 99);
		add_action('wp_footer', array(__CLASS__, 'print_footer_assets'), 5);
		add_action('wp_footer', array(__CLASS__, 'print_checkout_assets'), 999);

		add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'ajax_select_pack'));
		add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array(__CLASS__, 'ajax_select_pack'));

		// DigiTicket: nomenclatura en carrito/checkout (sin gettext global).
		add_filter('woocommerce_cart_item_name', array(__CLASS__, 'filter_cart_item_name_digiticket'), 20, 3);
		add_filter('woocommerce_order_item_name', array(__CLASS__, 'filter_order_item_name_digiticket'), 20, 2);
		add_filter('woocommerce_get_item_data', array(__CLASS__, 'filter_cart_item_data_digiticket'), 20, 2);
		add_filter('woocommerce_cart_item_product_title', array(__CLASS__, 'filter_cart_item_name_digiticket'), 20, 3);

		// Neutralizar shortcode WPCode de grilla (sin borrar el snippet).
		add_filter('do_shortcode_tag', array(__CLASS__, 'neutralize_ticket_wpcode'), 10, 4);
		add_filter('do_shortcode_tag', array(__CLASS__, 'neutralize_lty_chooser_shortcode'), 10, 4);
		add_filter('elementor/widget/render_content', array(__CLASS__, 'filter_elementor_widget_content'), 20, 2);

		// Blindaje anti-duplicados en carrito/checkout/pedido.
		add_action('woocommerce_check_cart_items', array(__CLASS__, 'validate_cart_tickets_unique'), 20);
		add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'validate_checkout_tickets_unique'), 20, 2);
		add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'guard_order_tickets_before_lottery'), 1, 2);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'guard_store_api_order_tickets'), 1, 1);
		add_filter('woocommerce_add_to_cart_sold_individually_found_in_cart', array(__CLASS__, 'allow_multiple_lottery_lines'), 20, 5);
	}

	/**
	 * Activa reserva nativa de Lottery (documentar rollback en OPTION_*_PREV).
	 */
	public static function bootstrap_reserve_setting(): void {
		if (get_option(self::OPTION_BOOTSTRAPPED) === 'yes') {
			return;
		}

		$prev_enabled = get_option('lty_settings_enable_reserve_ticket_manual_selection_type', 'no');
		$prev_minutes = get_option('lty_settings_reserve_ticket_time_in_min', '5');

		update_option(self::OPTION_RESERVE_PREV, $prev_enabled, false);
		update_option(self::OPTION_RESERVE_TIME_PREV, $prev_minutes, false);

		update_option('lty_settings_enable_reserve_ticket_manual_selection_type', 'yes');
		update_option('lty_settings_reserve_ticket_time_in_min', '30');
		update_option(self::OPTION_BOOTSTRAPPED, 'yes', false);
	}

	/** Restaura opciones de reserve (llamar en rollback manual o vía WP-CLI). */
	public static function restore_reserve_setting(): void {
		$prev_enabled = get_option(self::OPTION_RESERVE_PREV, 'no');
		$prev_minutes = get_option(self::OPTION_RESERVE_TIME_PREV, '5');
		update_option('lty_settings_enable_reserve_ticket_manual_selection_type', $prev_enabled);
		update_option('lty_settings_reserve_ticket_time_in_min', $prev_minutes);
		delete_option(self::OPTION_BOOTSTRAPPED);
	}

	public static function detect_context(): void {
		self::$detected_product_id = self::resolve_lottery_product_id();
	}

	private static function resolve_lottery_product_id(): int {
		$forced = (int) apply_filters('ss_packs_lottery_product_id', 0);
		if ($forced > 0) {
			return $forced;
		}

		if (!function_exists('wc_get_product') || !function_exists('lty_is_lottery_product')) {
			return 0;
		}

		$candidates = array();
		if (function_exists('is_product') && is_product()) {
			$candidates[] = (int) get_queried_object_id();
		}
		$qo = get_queried_object();
		if ($qo && isset($qo->ID) && isset($qo->post_type) && $qo->post_type === 'product') {
			$candidates[] = (int) $qo->ID;
		}
		global $post;
		if ($post && isset($post->ID) && isset($post->post_type) && $post->post_type === 'product') {
			$candidates[] = (int) $post->ID;
		}

		foreach (array_unique(array_filter($candidates)) as $id) {
			$product = wc_get_product($id);
			if ($product && lty_is_lottery_product($product)) {
				return (int) $id;
			}
		}
		return 0;
	}

	private static function current_product_id(): int {
		if (self::$detected_product_id === null) {
			self::$detected_product_id = self::resolve_lottery_product_id();
		}
		return (int) self::$detected_product_id;
	}

	private static function is_lottery_product_context(): bool {
		return self::current_product_id() > 0;
	}

	public static function neutralize_ticket_wpcode($output, $tag, $attr, $m) {
		if (is_admin() && !wp_doing_ajax()) {
			return $output;
		}
		$tag = (string) $tag;
		if ($tag !== 'wpcode' && $tag !== 'wpcode_snippet') {
			return $output;
		}
		$id = 0;
		if (is_array($attr) && isset($attr['id'])) {
			$id = absint($attr['id']);
		} elseif (is_string($m) && preg_match('/id=["\']?(\d+)/', $m, $mm)) {
			$id = absint($mm[1]);
		}
		// 12313 = UI selección manual; 628 = botón "Asegurar mis DigiTickets"
		if (in_array($id, array(12313, 628), true)) {
			return '<!-- ss-packs: wpcode ' . $id . ' neutralizado -->';
		}
		return $output;
	}

	public static function neutralize_lty_chooser_shortcode($output, $tag, $attr, $m) {
		if (is_admin() && !wp_doing_ajax()) {
			return $output;
		}
		if ((string) $tag !== 'lty_user_chooses_ticket') {
			return $output;
		}
		return '<!-- ss-packs: lty_user_chooses_ticket neutralizado -->';
	}

	public static function filter_elementor_widget_content($content, $widget = null) {
		if (!is_string($content) || $content === '') {
			return $content;
		}
		if (!self::is_lottery_product_context()) {
			// Si aún no detectamos, igual limpiar shortcodes de tickets en HTML crudo de Elementor.
			if (strpos($content, 'wpcode') === false && strpos($content, 'lty-lottery-ticket') === false) {
				return $content;
			}
		}
		// Forzar resolución de shortcodes WPCode bajo nuestro filtro.
		$content = preg_replace_callback(
			'/\[wpcode([^\]]*)\]/i',
			static function ($m) {
				$attrs = $m[1];
				$id = 0;
				if (preg_match('/id=["\']?(\d+)/i', $attrs, $mm)) {
					$id = absint($mm[1]);
				}
				if (in_array($id, array(12313, 628), true)) {
					return '<!-- ss-packs: wpcode ' . $id . ' neutralizado -->';
				}
				return $m[0];
			},
			$content
		);
		return $content;
	}

	public static function enqueue_front(): void {
		if (!self::is_lottery_product_context()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		// Asegura jQuery disponible para el footer inline.
		wp_enqueue_script('jquery');
	}

	public static function print_head_assets(): void {
		if (!self::is_lottery_product_context()) {
			return;
		}
		$product_id = self::current_product_id();
		$campaigns  = self::get_pack_campaigns_for_product($product_id);
		$config     = array(
			'ajaxUrl'   => admin_url('admin-ajax.php'),
			'action'    => self::AJAX_ACTION,
			'nonce'     => wp_create_nonce(self::NONCE_ACTION),
			'productId' => (int) $product_id,
			'checkout'  => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '/checkout/',
			'embeddedCheckout' => false,
			'checkoutAnchor'   => '',
			'campaigns' => $campaigns,
			'i18n'      => array(
				'heading'     => 'Elige cuántos DigiTickets quieres',
				'subheading'  => 'Mientras más DigiTickets compres, mayor será tu ahorro',
				'oneTicket'   => '1 DigiTicket',
				'nTickets'    => '{n} DigiTickets',
				'packBasic'   => 'PACK BÁSICO',
				'packName'    => 'PACK {buy}x{pay}',
				'promoTag'    => "Pagas {pay} y\nrecibes {buy}",
				'priceNormal' => 'PRECIO NORMAL',
				'saveLabel'   => 'AHORRAS',
				'perPack'     => 'por {n} DigiTickets',
				'select'      => 'SELECCIONAR',
				'selectBest'  => 'Elegir este pack',
				'selected'    => 'SELECCIONADO',
				'loading'     => 'Asignando DigiTickets…',
				'error'       => 'No se pudieron asignar DigiTickets. Intenta de nuevo.',
				'save'        => 'Ahorras {amount}',
				'bestBadge'   => 'Pack más elegido',
				'valueBadge'  => 'Pack más conveniente',
				'trustSecure' => 'COMPRA SEGURA',
				'trustSecureD'=> 'Tus datos están 100% protegidos',
				'trustShip'   => 'ENVÍO INMEDIATO',
				'trustShipD'  => 'Recibe tus DigiTickets por email al instante',
				'trustDigital'=> '100% DIGITALES',
				'trustDigitalD'=> 'Tus DigiTickets siempre disponibles en tu perfil',
				'trustCert'   => 'CONCURSO CERTIFICADO',
				'trustCertD'  => 'Bases legales certificadas ante Notario',
				'trustFoot1'  => 'Al continuar podrás revisar tus datos antes de realizar el pago.',
				'trustFoot2'  => 'Proceso rápido, transparente y seguro.',
			),
		);
		$config = apply_filters('ss_packs_lottery_config', $config, $product_id);

		echo "\n<!-- ss-packs-lottery v" . esc_html(self::VERSION) . " product=" . (int) $product_id . " -->\n";
		echo '<style id="ss-packs-lottery-css">' . self::front_css() . "</style>\n";
		echo '<script id="ss-packs-lottery-config" data-no-optimize="1">window.ssPacksLottery = ' . wp_json_encode($config) . ';</script>' . "\n";
	}

	public static function print_footer_assets(): void {
		if (!self::is_lottery_product_context()) {
			return;
		}
		echo '<script id="ss-packs-lottery-js" data-no-optimize="1">' . self::front_js() . "</script>\n";
	}

	public static function print_checkout_assets(): void {
		if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_order_received_page') && is_order_received_page())) {
			return;
		}
		// El diseño vive en sorteoseguro-checkout; no inyectar polish viejo (bordes/padding).
		if (class_exists('SorteoSeguro_Checkout')) {
			echo "\n<!-- ss-packs-lottery: checkout polish skipped (ss-checkout active) -->\n";
			echo '<script id="ss-checkout-digiticket-js">' . self::checkout_digiticket_js() . "</script>\n";
			return;
		}
		echo "\n<!-- ss-packs-lottery checkout polish v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-checkout-polish-css">' . self::checkout_css() . "</style>\n";
		echo '<script id="ss-checkout-digiticket-js">' . self::checkout_digiticket_js() . "</script>\n";
	}

	/** Reemplaza “ticket(s)” por DigiTicket(s) en el nombre visible del ítem. */
	public static function filter_cart_item_name_digiticket($name, $cart_item = null, $cart_item_key = null) {
		return self::digiticketize_label((string) $name);
	}

	public static function filter_order_item_name_digiticket($name, $item = null) {
		return self::digiticketize_label((string) $name);
	}

	/**
	 * @param array<int, array<string, mixed>> $item_data
	 * @param array<string, mixed>             $cart_item
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_cart_item_data_digiticket($item_data, $cart_item = null) {
		if (!is_array($item_data)) {
			return $item_data;
		}
		foreach ($item_data as &$row) {
			if (!empty($row['key'])) {
				$row['key'] = self::digiticketize_label((string) $row['key']);
			}
			if (!empty($row['name'])) {
				$row['name'] = self::digiticketize_label((string) $row['name']);
			}
			if (!empty($row['display'])) {
				$row['display'] = self::digiticketize_label((string) $row['display']);
			}
		}
		unset($row);
		return $item_data;
	}

	private static function digiticketize_label(string $name): string {
		$name = preg_replace('/\bTickets\b/i', 'DigiTickets', $name) ?? $name;
		$name = preg_replace('/\bTicket\b/i', 'DigiTicket', $name) ?? $name;
		return str_replace(array('DigiDigiTicket', 'DigiDigiTickets'), array('DigiTicket', 'DigiTickets'), $name);
	}

	private static function checkout_digiticket_js(): string {
		return <<<'JS'
(function () {
	function digiticketize(text) {
		if (!text || text.indexOf('icket') === -1) return text;
		return String(text)
			.replace(/\bTickets\b/gi, 'DigiTickets')
			.replace(/\bTicket\b/gi, 'DigiTicket')
			.replace(/DigiDigiTickets/g, 'DigiTickets')
			.replace(/DigiDigiTicket/g, 'DigiTicket');
	}
	function walk(root) {
		if (!root || root.nodeType !== 1) return;
		var tw = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
		var node;
		while ((node = tw.nextNode())) {
			var next = digiticketize(node.nodeValue);
			if (next !== node.nodeValue) node.nodeValue = next;
		}
	}
	function colorPackLabels(root) {
		if (!root) return;
		root.querySelectorAll('.checkout-totals-section > div, .checkout-totals-section span').forEach(function (el) {
			var t = (el.textContent || '').toUpperCase();
			var color = null;
			if (t.indexOf('3×2') !== -1 || t.indexOf('3X2') !== -1 || t.indexOf('3 X 2') !== -1) color = '#e91e8c';
			else if (t.indexOf('5×3') !== -1 || t.indexOf('5X3') !== -1 || t.indexOf('5 X 3') !== -1) color = '#9b7ede';
			else if (t.indexOf('10×5') !== -1 || t.indexOf('10X5') !== -1 || t.indexOf('10 X 5') !== -1) color = '#c9a227';
			else if (t.indexOf('20×8') !== -1 || t.indexOf('20X8') !== -1 || t.indexOf('20 X 8') !== -1) color = '#141414';
			if (color && /PROMO|PACK/i.test(t)) {
				el.style.color = color;
				el.style.fontWeight = '700';
			}
		});
	}
	function run() {
		var roots = document.querySelectorAll(
			'.custom-checkout-form, .checkout-main-wrapper, .checkout-right-sidebar, .order-review-card, .woocommerce-checkout-review-order'
		);
		if (!roots.length && document.body) roots = [document.body];
		roots.forEach(function (root) {
			walk(root);
			colorPackLabels(root);
		});
	}
	run();
	var obs = new MutationObserver(function () { run(); });
	if (document.body) obs.observe(document.body, { childList: true, subtree: true, characterData: true });
	document.addEventListener('DOMContentLoaded', run);
	jQuery && jQuery(document.body).on('updated_checkout', run);
})();
JS;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_pack_campaigns_for_product(int $product_id): array {
		$rows = array();

		// Opción fija: 1 DigiTicket (sin campaña Promo Engine).
		$unit = 0.0;
		$product = wc_get_product($product_id);
		if ($product) {
			$unit = (float) wc_get_price_to_display($product);
		}
		$rows[] = array(
			'id'         => 0,
			'title'      => '1 DigiTicket',
			'buy'        => 1,
			'pay'        => 1,
			'badge'      => self::default_pack_badge(1, 1, ''),
			'sort'       => 0,
			'unit_price' => $unit,
			'list_price' => $unit,
			'pack_price' => $unit,
			'savings'    => 0.0,
		);

		if (class_exists('Promo_Engine_Stable') && method_exists('Promo_Engine_Stable', 'get_product_page_campaigns')) {
			$from_pe = Promo_Engine_Stable::get_product_page_campaigns($product_id);
			foreach ($from_pe as $c) {
				$buy = max(1, (int) ($c['buy'] ?? 1));
				$pay = max(1, (int) ($c['pay'] ?? 1));
				$rows[] = array(
					'id'         => (int) ($c['id'] ?? 0),
					'title'      => (string) ($c['title'] ?? ''),
					'buy'        => $buy,
					'pay'        => $pay,
					'badge'      => self::default_pack_badge($buy, $pay, (string) ($c['badge'] ?? '')),
					'sort'       => (int) ($c['sort'] ?? 10),
					'unit_price' => (float) ($c['unit_price'] ?? $unit),
					'list_price' => (float) ($c['list_price'] ?? ($unit * $buy)),
					'pack_price' => (float) ($c['pack_price'] ?? ($unit * $pay)),
					'savings'    => (float) ($c['savings'] ?? 0),
				);
			}
		}

		usort(
			$rows,
			static function ($a, $b) {
				if ($a['sort'] === $b['sort']) {
					return $a['buy'] <=> $b['buy'];
				}
				return $a['sort'] <=> $b['sort'];
			}
		);

		return $rows;
	}

	/**
	 * Badge de marketing en ficha. Si Promo Engine trae texto, gana;
	 * si no, defaults por pack 5x3 / 20x8.
	 */
	private static function default_pack_badge(int $buy, int $pay, string $existing): string {
		$existing = trim($existing);
		if ($existing !== '') {
			return $existing;
		}
		if ($buy === 5 && $pay === 3) {
			return 'Pack más elegido';
		}
		if ($buy === 20 && $pay === 8) {
			return 'Pack más conveniente';
		}
		return '';
	}

	public static function ajax_select_pack(): void {
		if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
			wp_send_json_error(array('message' => 'Sesión inválida. Recarga la página.'), 403);
		}

		if (!function_exists('WC')) {
			wp_send_json_error(array('message' => 'WooCommerce no disponible.'), 500);
		}

		self::ensure_wc_cart();

		$product_id  = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$campaign_id = isset($_POST['campaign_id']) ? absint($_POST['campaign_id']) : 0;

		$product = wc_get_product($product_id);
		if (!$product || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($product)) {
			wp_send_json_error(array('message' => 'Producto inválido.'), 400);
		}

		if (method_exists($product, 'is_closed') && $product->is_closed()) {
			wp_send_json_error(array('message' => 'Este sorteo ya no acepta DigiTickets.'), 400);
		}

		$qty = self::resolve_pack_quantity($campaign_id, $product_id);
		if ($qty < 1) {
			wp_send_json_error(array('message' => 'Pack inválido o no vigente.'), 400);
		}

		if (!self::acquire_product_lock($product_id)) {
			wp_send_json_error(array('message' => 'Hay mucha demanda. Espera un momento e intenta de nuevo.'), 429);
		}

		$claimed = array();
		try {
			// No vaciar el carrito: DigiTickets de otros sorteos (y del mismo) deben permanecer.
			clean_post_cache($product_id);
			wc_delete_product_transients($product_id);

			$customer_id = self::get_customer_lock_id();
			$tickets     = self::pick_and_claim_tickets($product_id, $qty, $customer_id);
			if (count($tickets) !== $qty) {
				wp_send_json_error(array('message' => 'No hay suficientes DigiTickets disponibles.'), 409);
			}
			$claimed = $tickets;

			$ticket_csv = implode(',', $tickets);
			$_REQUEST['lty_lottery_ticket_numbers'] = $ticket_csv;
			$_POST['lty_lottery_ticket_numbers']    = $ticket_csv;

			$cart_item_data = array(
				'lty_lottery' => array(
					'tickets' => array_values($tickets),
				),
				'ss_pack_campaign_id' => $campaign_id,
				'ss_pack_claimed_at'  => time(),
				'ss_pack_line_uid'    => uniqid('sspack', true),
			);

			$cart_key = WC()->cart->add_to_cart($product_id, $qty, 0, array(), $cart_item_data);
			if (!$cart_key) {
				self::release_claimed_tickets($product_id, $claimed, $customer_id);
				$notices = wc_get_notices('error');
				$msg     = 'No se pudo agregar al carrito.';
				if (!empty($notices[0]['notice'])) {
					$msg = wp_strip_all_tags($notices[0]['notice']);
				}
				wc_clear_notices();
				wp_send_json_error(array('message' => $msg), 400);
			}

			if (!self::cart_tickets_are_exclusive($product_id, $tickets, $customer_id)) {
				WC()->cart->remove_cart_item($cart_key);
				self::release_claimed_tickets($product_id, $claimed, $customer_id);
				wp_send_json_error(array('message' => 'Esos DigiTickets acabaron de tomarse. Intenta de nuevo.'), 409);
			}

			if (WC()->session) {
				WC()->session->set('ss_pack_campaign_id', $campaign_id);
				WC()->session->set('ss_pack_tickets', $tickets);
				WC()->session->set('ss_pack_lock_id', $customer_id);
			}

			if (WC()->cart) {
				WC()->cart->calculate_totals();
			}
			if (WC()->session && method_exists(WC()->session, 'save_data')) {
				WC()->session->save_data();
			}

			$redirect = (string) apply_filters(
				'ss_packs_select_redirect',
				wc_get_checkout_url(),
				$product_id,
				$campaign_id
			);

			wp_send_json_success(
				array(
					'redirect'    => $redirect,
					'embedded'    => (bool) apply_filters('ss_packs_select_embedded', false, $product_id, $campaign_id),
					'tickets'     => $tickets,
					'quantity'    => $qty,
					'campaign_id' => $campaign_id,
				)
			);
		} finally {
			self::release_product_lock($product_id);
		}
	}

	private static function resolve_pack_quantity(int $campaign_id, int $product_id): int {
		if ($campaign_id === 0) {
			return 1;
		}

		if (!class_exists('Promo_Engine_Stable')) {
			return 0;
		}

		$show = get_post_meta($campaign_id, '_promo_show_on_product', true);
		if ($show !== 'yes' || get_post_status($campaign_id) !== 'publish') {
			return 0;
		}

		if (method_exists('Promo_Engine_Stable', 'campaign_targets_product')
			&& !Promo_Engine_Stable::campaign_targets_product($campaign_id, $product_id)) {
			return 0;
		}

		$campaigns = Promo_Engine_Stable::get_product_page_campaigns($product_id);
		foreach ($campaigns as $c) {
			if ((int) $c['id'] === $campaign_id) {
				return max(1, (int) $c['buy']);
			}
		}
		return 0;
	}

	/**
	 * Sortea y reclama tickets de forma exclusiva (debe llamarse bajo GET_LOCK).
	 *
	 * @return string[]
	 */
	private static function pick_and_claim_tickets(int $product_id, int $qty, string $customer_id): array {
		self::purge_orphan_hold_tickets($product_id);

		for ($try = 0; $try < self::MAX_PICK_TRIES; $try++) {
			clean_post_cache($product_id);
			$fresh = wc_get_product($product_id);
			if (!$fresh || !function_exists('lty_get_random_user_chooses_ticket_numbers_by_quantity')) {
				return array();
			}

			$remaining = method_exists($fresh, 'get_remaining_tickets') ? $fresh->get_remaining_tickets() : array();
			if (!is_array($remaining) || count($remaining) < $qty) {
				return array();
			}

			$blocked    = self::get_blocked_ticket_map($product_id, $customer_id);
			$free_pool  = array();
			foreach ($remaining as $ticket) {
				$ticket = (string) $ticket;
				if ($ticket === '' || isset($blocked[$ticket])) {
					continue;
				}
				$free_pool[] = $ticket;
			}
			if (count($free_pool) < $qty) {
				return array();
			}

			// Preferir el random nativo Lottery, filtrando holds/reservas ajenas.
			$candidates = lty_get_random_user_chooses_ticket_numbers_by_quantity($fresh, $qty);
			$candidates = array_values(array_unique(array_filter(array_map('strval', (array) $candidates))));
			$candidates = array_values(array_filter(
				$candidates,
				static function ($t) use ($blocked) {
					return $t !== '' && !isset($blocked[$t]);
				}
			));

			// Si el random nativo chocó con holds huérfanos, completar desde el pool libre.
			if (count($candidates) < $qty) {
				$need = $qty - count($candidates);
				$have = array_fill_keys($candidates, true);
				shuffle($free_pool);
				foreach ($free_pool as $ticket) {
					if (isset($have[$ticket])) {
						continue;
					}
					$candidates[] = $ticket;
					$have[$ticket] = true;
					$need--;
					if ($need <= 0) {
						break;
					}
				}
			}

			$candidates = array_values(array_unique($candidates));
			if (count($candidates) !== $qty) {
				continue;
			}

			if (!self::tickets_are_free($product_id, $candidates, $customer_id)) {
				continue;
			}

			if (!self::claim_tickets_in_reserve($product_id, $candidates, $customer_id)) {
				continue;
			}

			// Releer: si otro proceso (no debería bajo lock) contaminó, soltar y reintentar.
			if (!self::tickets_are_free($product_id, $candidates, $customer_id, true)) {
				self::release_claimed_tickets($product_id, $candidates, $customer_id);
				continue;
			}

			return $candidates;
		}

		return array();
	}

	/**
	 * Tickets bloqueados: hold nativo + reservas ajenas vigentes.
	 *
	 * @return array<string,true>
	 */
	private static function get_blocked_ticket_map(int $product_id, string $customer_id): array {
		$blocked = array();

		$hold = array_filter(array_map('strval', (array) get_post_meta($product_id, '_lty_hold_tickets', true)));
		foreach ($hold as $ticket) {
			if ($ticket !== '') {
				$blocked[$ticket] = true;
			}
		}

		$product = wc_get_product($product_id);
		if ($product && method_exists($product, 'get_reserved_tickets_data')) {
			$reserve_minutes = max(1, (int) get_option('lty_settings_reserve_ticket_time_in_min', 30));
			$now             = time();
			$data            = $product->get_reserved_tickets_data();
			if (is_array($data)) {
				foreach ($data as $ticket => $rows) {
					if (!is_array($rows)) {
						continue;
					}
					foreach ($rows as $cid => $ts) {
						if (absint($ts) + (60 * $reserve_minutes) < $now) {
							continue;
						}
						if ((string) $cid === (string) $customer_id) {
							continue; // nuestras reservas no bloquean el re-claim
						}
						$blocked[(string) $ticket] = true;
						break;
					}
				}
			}
		}

		return $blocked;
	}

	/**
	 * Limpia holds nativos sin reserva vigente (pedidos abandonados dejan basura
	 * en _lty_hold_tickets y el pack 20x8 falla por colisión aleatoria).
	 */
	private static function purge_orphan_hold_tickets(int $product_id): void {
		$hold = array_values(array_filter(array_map('strval', (array) get_post_meta($product_id, '_lty_hold_tickets', true))));
		if (count($hold) < 50) {
			// Poca basura: no tocar en caliente.
			return;
		}

		$keep = array();
		$product = wc_get_product($product_id);
		if ($product && method_exists($product, 'get_reserved_tickets_data')) {
			$reserve_minutes = max(1, (int) get_option('lty_settings_reserve_ticket_time_in_min', 30));
			$now             = time();
			$data            = $product->get_reserved_tickets_data();
			$active          = array();
			if (is_array($data)) {
				foreach ($data as $ticket => $rows) {
					if (!is_array($rows)) {
						continue;
					}
					foreach ($rows as $cid => $ts) {
						unset($cid);
						if (absint($ts) + (60 * $reserve_minutes) >= $now) {
							$active[(string) $ticket] = true;
							break;
						}
					}
				}
			}
			foreach ($hold as $ticket) {
				if (isset($active[$ticket])) {
					$keep[] = $ticket;
				}
			}
		}

		// También conservar holds ligados a pedidos pending/on-hold recientes.
		if (function_exists('wc_get_orders')) {
			$orders = wc_get_orders(
				array(
					'status'       => array('pending', 'on-hold', 'checkout-draft'),
					'limit'        => 80,
					'return'       => 'objects',
					'date_created' => '>' . (time() - 2 * DAY_IN_SECONDS),
				)
			);
			$pending_tickets = array();
			foreach ($orders as $order) {
				if (!is_a($order, 'WC_Order')) {
					continue;
				}
				foreach ($order->get_items() as $item) {
					if ((int) $item->get_product_id() !== $product_id) {
						continue;
					}
					$raw = $item->get_meta('lty_lottery_ticket_numbers');
					if ($raw === '' || $raw === null) {
						$raw = $item->get_meta('_lty_ticket_numbers');
					}
					if (is_array($raw)) {
						foreach ($raw as $t) {
							$pending_tickets[(string) $t] = true;
						}
					} elseif (is_string($raw) && $raw !== '') {
						foreach (preg_split('/\s*,\s*/', $raw) as $t) {
							if ($t !== '') {
								$pending_tickets[(string) $t] = true;
							}
						}
					}
					if (!empty($item['lty_lottery']['tickets']) && is_array($item['lty_lottery']['tickets'])) {
						foreach ($item['lty_lottery']['tickets'] as $t) {
							$pending_tickets[(string) $t] = true;
						}
					}
				}
			}
			foreach ($hold as $ticket) {
				if (isset($pending_tickets[$ticket]) && !in_array($ticket, $keep, true)) {
					$keep[] = $ticket;
				}
			}
		}

		$keep = array_values(array_unique($keep));
		if (count($keep) >= count($hold)) {
			return;
		}

		update_post_meta($product_id, '_lty_hold_tickets', $keep);
		clean_post_cache($product_id);
	}

	/**
	 * @param string[] $tickets
	 */
	private static function tickets_are_free(int $product_id, array $tickets, string $customer_id, bool $allow_own_reserve = false): bool {
		$tickets = array_values(array_unique(array_map('strval', $tickets)));
		if (count($tickets) === 0) {
			return false;
		}

		if (function_exists('lty_check_is_ticket_number_exists')) {
			$existing = lty_check_is_ticket_number_exists($tickets, $product_id);
			if (is_array($existing) && !empty($existing)) {
				return false;
			}
		}

		$product = wc_get_product($product_id);
		if (!$product || !method_exists($product, 'get_reserved_tickets_data')) {
			return true;
		}

		$reserve_minutes = max(1, (int) get_option('lty_settings_reserve_ticket_time_in_min', 30));
		$now             = time();
		$data            = $product->get_reserved_tickets_data();
		if (!is_array($data)) {
			$data = array();
		}

		foreach ($tickets as $ticket) {
			if (empty($data[$ticket]) || !is_array($data[$ticket])) {
				continue;
			}
			foreach ($data[$ticket] as $cid => $ts) {
				$ts = absint($ts);
				if ($ts + (60 * $reserve_minutes) < $now) {
					continue; // expirada
				}
				if ((string) $cid === (string) $customer_id && $allow_own_reserve) {
					continue;
				}
				if ((string) $cid !== (string) $customer_id) {
					return false;
				}
				if ((string) $cid === (string) $customer_id && !$allow_own_reserve) {
					// Ya nuestra: ok si allow_own; si no, aún es "libre" para nosotros al reclamar de nuevo.
					continue;
				}
			}
		}

		// Hold nativo Lottery (durante creación de pedido).
		$hold = array_filter(array_map('strval', (array) get_post_meta($product_id, '_lty_hold_tickets', true)));
		$held = array_values(array_intersect($tickets, $hold));
		if ($held) {
			// Si el hold es nuestro (reserva vigente), no forzar reasignación.
			if (!$allow_own_reserve || !self::tickets_reserved_by_customer($product_id, $held, $customer_id, $reserve_minutes)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string[] $tickets
	 */
	private static function tickets_reserved_by_customer(int $product_id, array $tickets, string $customer_id, int $reserve_minutes = 0): bool {
		$product = wc_get_product($product_id);
		if (!$product || !method_exists($product, 'get_reserved_tickets_data')) {
			return false;
		}
		if ($reserve_minutes < 1) {
			$reserve_minutes = max(1, (int) get_option('lty_settings_reserve_ticket_time_in_min', 30));
		}
		$now  = time();
		$data = $product->get_reserved_tickets_data();
		if (!is_array($data)) {
			return false;
		}
		foreach ($tickets as $ticket) {
			$ticket = (string) $ticket;
			if (empty($data[$ticket]) || !is_array($data[$ticket])) {
				return false;
			}
			$ours = false;
			foreach ($data[$ticket] as $cid => $ts) {
				if ((string) $cid === (string) $customer_id && absint($ts) + (60 * $reserve_minutes) >= $now) {
					$ours = true;
					break;
				}
			}
			if (!$ours) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Escribe reserva Lottery de inmediato (bajo GET_LOCK).
	 *
	 * @param string[] $tickets
	 */
	private static function claim_tickets_in_reserve(int $product_id, array $tickets, string $customer_id): bool {
		$product = wc_get_product($product_id);
		if (!$product || !method_exists($product, 'update_post_meta')) {
			return false;
		}

		$data = method_exists($product, 'get_reserved_tickets_data') ? $product->get_reserved_tickets_data() : array();
		if (!is_array($data)) {
			$data = array();
		}

		$now = time();
		foreach ($tickets as $ticket) {
			$ticket = (string) $ticket;
			$data[$ticket] = array($customer_id => $now);
		}

		$product->update_post_meta('lty_manual_reserved_tickets', $data);

		if (WC()->session) {
			$session_reserved = function_exists('lty_get_session_reserved_tickets')
				? (array) lty_get_session_reserved_tickets()
				: (array) WC()->session->get('lty_reserved_tickets', array());
			if (!isset($session_reserved[$product_id]) || !is_array($session_reserved[$product_id])) {
				$session_reserved[$product_id] = array();
			}
			foreach ($tickets as $ticket) {
				$session_reserved[$product_id][(string) $ticket] = $customer_id;
			}
			WC()->session->set('lty_reserved_tickets', $session_reserved);
		}

		clean_post_cache($product_id);
		return true;
	}

	/**
	 * @param string[] $tickets
	 */
	private static function release_claimed_tickets(int $product_id, array $tickets, string $customer_id): void {
		if (empty($tickets)) {
			return;
		}
		$product = wc_get_product($product_id);
		if (!$product || !method_exists($product, 'get_reserved_tickets_data')) {
			return;
		}

		$data = $product->get_reserved_tickets_data();
		if (!is_array($data)) {
			return;
		}

		foreach ($tickets as $ticket) {
			$ticket = (string) $ticket;
			if (empty($data[$ticket]) || !is_array($data[$ticket])) {
				continue;
			}
			unset($data[$ticket][$customer_id]);
			if (empty($data[$ticket])) {
				unset($data[$ticket]);
			}
		}
		$product->update_post_meta('lty_manual_reserved_tickets', $data);

		if (WC()->session) {
			$session_reserved = (array) WC()->session->get('lty_reserved_tickets', array());
			if (isset($session_reserved[$product_id]) && is_array($session_reserved[$product_id])) {
				foreach ($tickets as $ticket) {
					unset($session_reserved[$product_id][(string) $ticket]);
				}
				WC()->session->set('lty_reserved_tickets', $session_reserved);
			}
		}
		clean_post_cache($product_id);
	}

	/**
	 * Permite varias líneas del mismo sorteo (otro pack / más DigiTickets).
	 *
	 * @param bool  $found
	 * @param mixed $product_id
	 */
	public static function allow_multiple_lottery_lines($found, $product_id = 0, $variation_id = 0, $cart_item_data = array(), $cart_id = '') {
		$product = wc_get_product((int) $product_id);
		if ($product && function_exists('lty_is_lottery_product') && lty_is_lottery_product($product)) {
			return false;
		}
		return $found;
	}

	/**
	 * @param string[] $tickets
	 */
	private static function cart_tickets_are_exclusive(int $product_id, array $tickets, string $customer_id): bool {
		if (!self::tickets_are_free($product_id, $tickets, $customer_id, true)) {
			return false;
		}
		if (!WC()->cart) {
			return false;
		}
		$found = array();
		foreach (WC()->cart->get_cart() as $item) {
			if ((int) $item['product_id'] !== $product_id) {
				continue;
			}
			if (!empty($item['lty_lottery']['tickets']) && is_array($item['lty_lottery']['tickets'])) {
				$found = array_merge($found, array_map('strval', $item['lty_lottery']['tickets']));
			}
		}
		foreach ($tickets as $ticket) {
			if (!in_array((string) $ticket, $found, true)) {
				return false;
			}
		}
		return true;
	}

	public static function validate_cart_tickets_unique(): void {
		if (!WC()->cart) {
			return;
		}
		foreach (self::collect_cart_ticket_map() as $product_id => $tickets) {
			$customer_id = self::get_customer_lock_id();
			if (!self::tickets_are_free($product_id, $tickets, $customer_id, true)) {
				wc_add_notice('Uno o más DigiTickets de tu carrito ya no están disponibles. Vuelve a la ficha y elige el pack otra vez.', 'error');
				return;
			}
		}
	}

	public static function validate_checkout_tickets_unique($data = null, $errors = null): void {
		self::validate_cart_tickets_unique();
	}

	/**
	 * Antes de que Lottery cree CPT: hold atómico + verificación final.
	 */
	public static function guard_order_tickets_before_lottery($order_id, $data = null): void {
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		self::harden_order_tickets($order);
	}

	public static function guard_store_api_order_tickets($order): void {
		if ($order instanceof WC_Order) {
			self::harden_order_tickets($order);
		}
	}

	private static function harden_order_tickets(WC_Order $order): void {
		$by_product = array();
		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			if (!$product || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($product)) {
				continue;
			}
			$tickets = $item->get_meta('_lty_lottery_tickets');
			if (!is_array($tickets) || empty($tickets)) {
				continue;
			}
			$pid = (int) $product->get_id();
			if (!isset($by_product[$pid])) {
				$by_product[$pid] = array();
			}
			$by_product[$pid][] = array(
				'item_id' => $item_id,
				'item'    => $item,
				'tickets' => array_values(array_map('strval', $tickets)),
			);
		}

		foreach ($by_product as $product_id => $groups) {
			if (!self::acquire_product_lock($product_id)) {
				$order->add_order_note('SorteoSeguro: no se obtuvo lock de exclusividad de DigiTickets.');
				throw new Exception('No pudimos validar DigiTickets exclusivos. Reintenta el pago en unos segundos.');
			}
			try {
				foreach ($groups as $group) {
					$tickets     = $group['tickets'];
					$customer_id = self::get_customer_lock_id();
					$final       = $tickets;

					$needs_new = !self::tickets_are_free($product_id, $tickets, $customer_id, true);
					if ($needs_new) {
						$final = self::pick_and_claim_tickets($product_id, count($tickets), $customer_id);
						if (count($final) !== count($tickets)) {
							throw new Exception('DigiTickets ya no disponibles. Vuelve a la ficha y selecciona el pack otra vez.');
						}
					}

					if (!self::atomic_hold_tickets($product_id, $final, $customer_id)) {
						// Hold perdido: nuevo intento exclusivo.
						$final = self::pick_and_claim_tickets($product_id, count($tickets), $customer_id);
						if (count($final) !== count($tickets) || !self::atomic_hold_tickets($product_id, $final, $customer_id)) {
							throw new Exception('Conflicto al reservar DigiTickets. Reintenta el checkout.');
						}
						$needs_new = true;
					}

					if ($needs_new || self::ticket_lists_differ($final, $tickets)) {
						self::sync_order_item_tickets($group['item'], $final);
						$order->add_order_note('SorteoSeguro: DigiTickets reasignados por exclusividad: ' . implode(', ', $final));
					}

					self::claim_tickets_in_reserve($product_id, $final, $customer_id);
				}
			} finally {
				self::release_product_lock($product_id);
			}
		}
	}

	/**
	 * Una sola etiqueta visible (= Lottery). Nunca crear "DigiTicket(s)" paralelo.
	 *
	 * @param string[] $tickets
	 */
	private static function sync_order_item_tickets($item, array $tickets): void {
		$tickets = array_values(array_map('strval', $tickets));
		$html    = '<span class="notranslate">' . esc_html(implode(', ', $tickets)) . '</span>';
		$label   = function_exists('lty_get_order_item_ticket_number_name')
			? lty_get_order_item_ticket_number_name()
			: 'Tus DigiTickets';

		$item->update_meta_data('_lty_lottery_tickets', $tickets);
		$item->update_meta_data($label, $html);
		// Evitar segunda línea en correos (bug previo).
		$item->delete_meta_data('DigiTicket(s)');
		$item->save();
	}

	/**
	 * @param string[] $a
	 * @param string[] $b
	 */
	private static function ticket_lists_differ(array $a, array $b): bool {
		$a = array_values(array_map('strval', $a));
		$b = array_values(array_map('strval', $b));
		sort($a);
		sort($b);
		return $a !== $b;
	}

	/**
	 * Hold atómico estilo Lottery (UPDATE … NOT REGEXP). Evita dos pedidos con el mismo hold.
	 *
	 * @param string[] $tickets
	 */
	private static function atomic_hold_tickets(int $product_id, array $tickets, string $customer_id = ''): bool {
		global $wpdb;

		$tickets = array_values(array_unique(array_map('strval', $tickets)));
		if (empty($tickets)) {
			return false;
		}

		if (function_exists('lty_check_is_ticket_number_exists')) {
			$existing = lty_check_is_ticket_number_exists($tickets, $product_id);
			if (is_array($existing) && !empty($existing)) {
				return false;
			}
		}

		if (!metadata_exists('post', $product_id, '_lty_hold_tickets')) {
			update_post_meta($product_id, '_lty_hold_tickets', array());
		}

		$hold = array_filter(array_map('strval', (array) get_post_meta($product_id, '_lty_hold_tickets', true)));
		if (!is_array($hold)) {
			$hold = array();
		}
		$overlap = array_values(array_intersect($tickets, $hold));
		if ($overlap) {
			// Idempotente: ya están en hold y reservados por este comprador.
			if (count($overlap) === count($tickets)
				&& $customer_id !== ''
				&& self::tickets_reserved_by_customer($product_id, $tickets, $customer_id)) {
				return true;
			}
			return false;
		}

		$merged = array_values(array_unique(array_merge($hold, $tickets)));
		$regexp = '"' . implode('"|"', $tickets) . '"';
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} AS meta
				 SET meta.meta_value = %s
				 WHERE meta.post_id = %d
				   AND meta.meta_key = '_lty_hold_tickets'
				   AND meta.meta_value NOT REGEXP %s",
				maybe_serialize($merged),
				$product_id,
				$regexp
			)
		);

		clean_post_cache($product_id);
		return (false !== $result && (int) $result > 0);
	}

	/**
	 * @return array<int, string[]>
	 */
	private static function collect_cart_ticket_map(): array {
		$map = array();
		if (!WC()->cart) {
			return $map;
		}
		foreach (WC()->cart->get_cart() as $item) {
			$pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
			if (!$pid || empty($item['lty_lottery']['tickets']) || !is_array($item['lty_lottery']['tickets'])) {
				continue;
			}
			if (!isset($map[$pid])) {
				$map[$pid] = array();
			}
			$map[$pid] = array_merge($map[$pid], array_map('strval', $item['lty_lottery']['tickets']));
			$map[$pid] = array_values(array_unique($map[$pid]));
		}
		return $map;
	}

	private static function get_customer_lock_id(): string {
		if (WC()->session) {
			$persisted = (string) WC()->session->get('ss_pack_lock_id');
			if ($persisted !== '') {
				return $persisted;
			}
		}
		if (function_exists('lty_get_current_user_cart_session_value')) {
			$id = (string) lty_get_current_user_cart_session_value();
			if ($id !== '') {
				if (WC()->session) {
					WC()->session->set('ss_pack_lock_id', $id);
				}
				return $id;
			}
		}
		if (WC()->session) {
			$id = 't_' . substr(md5((string) WC()->session->get_customer_id()), 0, 24);
			WC()->session->set('ss_pack_lock_id', $id);
			return $id;
		}
		return 't_' . substr(md5(uniqid('ss', true)), 0, 24);
	}

	private static function acquire_product_lock(int $product_id): bool {
		global $wpdb;
		$name = self::MYSQL_LOCK_PFX . $product_id;
		$got  = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, self::MYSQL_LOCK_WAIT));
		if ($got !== 1) {
			return false;
		}
		// Transient extra (observabilidad / doble red).
		set_transient(self::LOCK_PREFIX . $product_id, (string) time(), self::LOCK_TTL);
		return true;
	}

	private static function release_product_lock(int $product_id): void {
		global $wpdb;
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::MYSQL_LOCK_PFX . $product_id));
		delete_transient(self::LOCK_PREFIX . $product_id);
	}

	private static function ensure_wc_cart(): void {
		if (is_null(WC()->cart) || !WC()->session) {
			if (function_exists('wc_load_cart')) {
				wc_load_cart();
			}
		}
		if (WC()->session && !WC()->session->has_session()) {
			WC()->session->set_customer_session_cookie(true);
		}
	}

	private static function checkout_css(): string {
		return <<<'CSS'
/* Checkout polish v1.3.4 — gana a custom-css-js (padding 5px / sidebar 380px) */
body.woocommerce-checkout {
	--ss-co-ink: #111827;
	--ss-co-muted: #6b7280;
	--ss-co-line: #e5e7eb;
	--ss-co-soft: #f9fafb;
	--ss-co-radius: 16px;
	--ss-co-cta: #16a34a;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-main-wrapper,
body.woocommerce-checkout .checkout-main-wrapper {
	display: flex !important;
	flex-wrap: nowrap !important;
	align-items: flex-start !important;
	gap: 36px !important;
	max-width: 1280px !important;
	width: 100% !important;
	margin: 0 auto !important;
	padding: 16px 20px 40px !important;
	box-sizing: border-box !important;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-left-column,
body.woocommerce-checkout .checkout-left-column {
	flex: 1 1 54% !important;
	width: 54% !important;
	max-width: 54% !important;
	min-width: 0 !important;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-right-sidebar,
body.woocommerce-checkout .checkout-right-sidebar {
	flex: 0 0 42% !important;
	width: 42% !important;
	max-width: 42% !important;
	min-width: 420px !important;
	position: sticky !important;
	top: 24px !important;
}

@media screen and (max-width: 1100px) {
	body.woocommerce-checkout form.custom-checkout-form .checkout-right-sidebar,
	body.woocommerce-checkout .checkout-right-sidebar {
		min-width: 360px !important;
		flex: 0 0 40% !important;
		width: 40% !important;
		max-width: 40% !important;
	}
}

@media screen and (max-width: 991px) {
	body.woocommerce-checkout form.custom-checkout-form .checkout-main-wrapper,
	body.woocommerce-checkout .checkout-main-wrapper {
		flex-wrap: wrap !important;
		gap: 20px !important;
		padding: 12px 14px 28px !important;
	}
	body.woocommerce-checkout form.custom-checkout-form .checkout-left-column,
	body.woocommerce-checkout .checkout-left-column,
	body.woocommerce-checkout form.custom-checkout-form .checkout-right-sidebar,
	body.woocommerce-checkout .checkout-right-sidebar {
		flex: 1 1 100% !important;
		width: 100% !important;
		max-width: 100% !important;
		min-width: 0 !important;
		position: static !important;
	}
}

/* Cards: el sitio usa padding:5px !important — forzar aire real */
body.woocommerce-checkout form.custom-checkout-form .checkout-step,
body.woocommerce-checkout form.custom-checkout-form .checkout-step-notes,
body.woocommerce-checkout form.custom-checkout-form .order-review-card,
body.woocommerce-checkout .checkout-step,
body.woocommerce-checkout .checkout-step-notes,
body.woocommerce-checkout .order-review-card {
	background: #fff !important;
	border: 1px solid var(--ss-co-line) !important;
	border-radius: var(--ss-co-radius) !important;
	padding: 28px 28px 24px !important;
	margin-bottom: 20px !important;
	box-shadow: 0 4px 18px rgba(16, 24, 40, 0.045) !important;
	box-sizing: border-box !important;
	width: 100% !important;
}

body.woocommerce-checkout form.custom-checkout-form .order-review-card,
body.woocommerce-checkout .order-review-card {
	padding: 26px 24px 20px !important;
	background: linear-gradient(180deg, #fff 0%, #fafafa 100%) !important;
	box-shadow: 0 10px 30px rgba(16, 24, 40, 0.06) !important;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-step h3,
body.woocommerce-checkout form.custom-checkout-form .order-review-card > h3,
body.woocommerce-checkout form.custom-checkout-form .checkout-accordion-header h3,
body.woocommerce-checkout .checkout-step h3,
body.woocommerce-checkout .order-review-card > h3 {
	margin: 0 0 8px !important;
	padding-bottom: 12px !important;
	border-bottom: 1px solid #f0f1f3 !important;
	font-size: 1.15rem !important;
	font-weight: 700 !important;
	color: var(--ss-co-ink) !important;
	letter-spacing: -0.015em !important;
}

body.woocommerce-checkout form.custom-checkout-form .step-description,
body.woocommerce-checkout .step-description {
	color: var(--ss-co-muted) !important;
	font-size: 0.9rem !important;
	line-height: 1.5 !important;
	margin: 0 0 22px !important;
}

/* Labels estáticos (ya no flotantes encima del valor) */
body.woocommerce-checkout form.custom-checkout-form .floating-label-style .form-row,
body.woocommerce-checkout .floating-label-style .form-row {
	position: relative !important;
	padding-top: 0 !important;
	margin: 0 0 16px !important;
	width: 100% !important;
}
body.woocommerce-checkout form.custom-checkout-form .floating-label-style .form-row-first,
body.woocommerce-checkout form.custom-checkout-form .floating-label-style .form-row-last,
body.woocommerce-checkout .floating-label-style .form-row-first,
body.woocommerce-checkout .floating-label-style .form-row-last {
	width: 48.5% !important;
	float: left !important;
}
body.woocommerce-checkout form.custom-checkout-form .floating-label-style .form-row-first,
body.woocommerce-checkout .floating-label-style .form-row-first {
	margin-right: 3% !important;
}
body.woocommerce-checkout form.custom-checkout-form .floating-label-style .form-row-wide,
body.woocommerce-checkout .floating-label-style .form-row-wide {
	clear: both !important;
	float: none !important;
	width: 100% !important;
}

body.woocommerce-checkout form.custom-checkout-form .floating-label-style label,
body.woocommerce-checkout .floating-label-style label,
body.woocommerce-checkout form.custom-checkout-form .floating-label-style .form-row > label,
body.woocommerce-checkout .floating-label-style .form-row > label {
	position: static !important;
	display: block !important;
	top: auto !important;
	left: auto !important;
	transform: none !important;
	background: transparent !important;
	padding: 0 0 8px !important;
	margin: 0 !important;
	font-size: 0.8125rem !important;
	font-weight: 650 !important;
	line-height: 1.2 !important;
	color: #374151 !important;
	z-index: auto !important;
	pointer-events: auto !important;
}

body.woocommerce-checkout form.custom-checkout-form .floating-label-style input.input-text,
body.woocommerce-checkout form.custom-checkout-form .floating-label-style input[type="text"],
body.woocommerce-checkout form.custom-checkout-form .floating-label-style input[type="email"],
body.woocommerce-checkout form.custom-checkout-form .floating-label-style input[type="tel"],
body.woocommerce-checkout form.custom-checkout-form .floating-label-style textarea,
body.woocommerce-checkout form.custom-checkout-form .woocommerce-input-wrapper input.input-text,
body.woocommerce-checkout form.custom-checkout-form input.input-text,
body.woocommerce-checkout form.custom-checkout-form textarea.input-text,
body.woocommerce-checkout .floating-label-style input.input-text,
body.woocommerce-checkout .floating-label-style input[type="text"],
body.woocommerce-checkout .floating-label-style input[type="email"],
body.woocommerce-checkout .floating-label-style input[type="tel"],
body.woocommerce-checkout .floating-label-style textarea,
body.woocommerce-checkout input.input-text,
body.woocommerce-checkout textarea.input-text {
	display: block !important;
	width: 100% !important;
	min-height: 52px !important;
	padding: 14px 16px !important;
	border: 1px solid #d1d5db !important;
	border-radius: 12px !important;
	background: #fff !important;
	box-shadow: none !important;
	font-size: 1rem !important;
	font-weight: 500 !important;
	line-height: 1.35 !important;
	color: var(--ss-co-ink) !important;
	box-sizing: border-box !important;
	transition: border-color .15s ease, box-shadow .15s ease;
}

body.woocommerce-checkout form.custom-checkout-form input.input-text:focus,
body.woocommerce-checkout form.custom-checkout-form textarea.input-text:focus,
body.woocommerce-checkout input.input-text:focus,
body.woocommerce-checkout textarea.input-text:focus {
	border-color: #111827 !important;
	box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08) !important;
	outline: none !important;
	background: #fff !important;
}

/* Resumen */
body.woocommerce-checkout form.custom-checkout-form .checkout-product-item,
body.woocommerce-checkout .checkout-product-item {
	padding: 18px 0 !important;
	border-bottom: 1px solid var(--ss-co-line) !important;
	gap: 16px !important;
}
body.woocommerce-checkout form.custom-checkout-form .product-thumbnail-wrapper img,
body.woocommerce-checkout .product-thumbnail-wrapper img {
	border-radius: 12px !important;
	border: 1px solid var(--ss-co-line) !important;
	box-shadow: 0 2px 8px rgba(0,0,0,.06) !important;
}
body.woocommerce-checkout form.custom-checkout-form .product-quantity-badge,
body.woocommerce-checkout .product-quantity-badge {
	background: #374151 !important;
	border: 2px solid #fff !important;
	font-weight: 700 !important;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-coupon-accordion,
body.woocommerce-checkout .checkout-coupon-accordion {
	border-bottom: 1px solid var(--ss-co-line) !important;
}
body.woocommerce-checkout form.custom-checkout-form #toggle-coupon,
body.woocommerce-checkout #toggle-coupon {
	padding: 18px 0 !important;
	font-weight: 600 !important;
	color: var(--ss-co-ink) !important;
}
body.woocommerce-checkout form.custom-checkout-form #coupon_code_custom,
body.woocommerce-checkout #coupon_code_custom {
	border-radius: 12px !important;
	border: 1px solid #d1d5db !important;
	min-height: 46px !important;
	padding: 10px 14px !important;
}
body.woocommerce-checkout form.custom-checkout-form #apply_coupon_custom,
body.woocommerce-checkout #apply_coupon_custom {
	background: var(--ss-co-cta) !important;
	border-radius: 12px !important;
	font-weight: 700 !important;
	padding: 10px 16px !important;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-totals-section,
body.woocommerce-checkout .checkout-totals-section {
	margin-top: 10px !important;
}
body.woocommerce-checkout form.custom-checkout-form .order-total,
body.woocommerce-checkout .order-total {
	padding-top: 18px !important;
	margin-top: 10px !important;
	border-top: 1px solid var(--ss-co-line) !important;
}
body.woocommerce-checkout form.custom-checkout-form .order-total strong,
body.woocommerce-checkout form.custom-checkout-form .total-price-container,
body.woocommerce-checkout .order-total strong,
body.woocommerce-checkout .total-price-container {
	font-size: 1.4rem !important;
	letter-spacing: -0.02em !important;
}

body.woocommerce-checkout form.custom-checkout-form .payment-method-wrap,
body.woocommerce-checkout form.custom-checkout-form .mp-checkout-pro-container,
body.woocommerce-checkout .payment-method-wrap,
body.woocommerce-checkout .mp-checkout-pro-container {
	border-radius: 14px !important;
	overflow: hidden;
}

body.woocommerce-checkout form.custom-checkout-form .checkout-actions #place_order,
body.woocommerce-checkout form.custom-checkout-form .checkout-actions button[name="woocommerce_checkout_place_order"],
body.woocommerce-checkout .checkout-actions #place_order {
	background: var(--ss-co-cta) !important;
	border: none !important;
	border-radius: 999px !important;
	min-height: 52px !important;
	font-weight: 700 !important;
	color: #fff !important;
	padding: 14px 28px !important;
	box-shadow: 0 8px 20px rgba(22, 163, 74, 0.25) !important;
}

body.woocommerce-checkout form.custom-checkout-form .notes-checkbox-container,
body.woocommerce-checkout .notes-checkbox-container {
	display: flex !important;
	align-items: center !important;
	gap: 10px !important;
	padding: 4px 0 !important;
}
body.woocommerce-checkout form.custom-checkout-form .notes-checkbox-container .notas,
body.woocommerce-checkout .notes-checkbox-container .notas {
	margin: 0 !important;
	color: var(--ss-co-muted) !important;
	font-size: 0.92rem !important;
}

body.woocommerce-checkout form.custom-checkout-form .field-container.floating-label-style::after,
body.woocommerce-checkout .field-container.floating-label-style::after {
	content: "";
	display: table;
	clear: both;
}
CSS;
	}

	private static function front_css(): string {
		return <<<'CSS'
/* Packs Lottery UI v1.3.1 — estructura ss2 + iconos + trust bar */
:root {
	--ss-pack-ink: #141414;
	--ss-pack-muted: #6b7280;
	--ss-pack-line: #e5e7eb;
	--ss-pack-accent: #7f54b3;
	--ss-pack-accent-soft: rgba(127, 84, 179, 0.12);
	--ss-pack-glow: rgba(127, 84, 179, 0.16);
	--ss-pack-cta: #16a34a;
	--ss-pack-cta-dark: #15803d;
	--ss-pack-save: #16a34a;
	--ss-trust: #7c3aed;
	--ss-font-family: "Montserrat", "Poppins", "Segoe UI", sans-serif;
}

.lty-lottery-ticket-container,
.lty-lottery-ticket-header,
.lty-lottery-ticket-panel,
.lty-lottery-ticket-lucky-dip-container,
.lty-lottery-ticket-wrapper,
.lty-ticket-number-wrapper,
.quantity-selector,
.buy-section .single_add_to_cart_button,
.buy-section .lty_manual_add_to_cart,
.purchase-action .buy-section,
.purchase-action .price-info,
form.form-buy-ticket > img,
.purchase-panel form > img,
aside.purchase-panel form > img,
.purchase-panel img[src*="WhatsApp-Image-2026-03-03"],
.purchase-panel img[data-src*="WhatsApp-Image-2026-03-03"],
img[src*="WhatsApp-Image-2026-03-03-at-20.03.45"],
img[data-src*="WhatsApp-Image-2026-03-03-at-20.03.45"],
.banner_packs,
#lottery-ticket-random,
input[name="search-ticket"] {
	display: none !important;
	visibility: hidden !important;
	height: 0 !important;
	overflow: hidden !important;
	margin: 0 !important;
	padding: 0 !important;
	border: 0 !important;
}

.ss-packs-wrap {
	display: block !important;
	margin: 4px 0 20px;
	padding: 0;
	border: 0;
	background: transparent;
	font-family: var(--ss-font-family, "Montserrat", "Poppins", "Segoe UI", sans-serif) !important;
}
.ss-packs-wrap,
.ss-packs-wrap *:not(svg):not(svg *) {
	font-family: var(--ss-font-family, "Montserrat", "Poppins", "Segoe UI", sans-serif) !important;
}
.ss-packs-wrap .ss-packs-title {
	margin: 0 0 6px;
	text-align: left;
	font-size: clamp(1.05rem, 2.4vw, 1.28rem);
	font-weight: 800;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: var(--ss-pack-ink);
	line-height: 1.25;
}
.ss-packs-title-accent { color: var(--ss-trust); }
.ss-packs-sub {
	margin: 0 0 16px;
	font-size: 0.9rem;
	color: var(--ss-pack-muted);
	line-height: 1.4;
}
.ss-packs-grid {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.ss-pack-card.ss-pack--1x1 {
	--ss-pack-accent: #374151;
	--ss-pack-accent-soft: rgba(55, 65, 81, 0.12);
	--ss-pack-glow: rgba(55, 65, 81, 0.14);
}
.ss-pack-card.ss-pack--3x2 {
	--ss-pack-accent: #e91e8c;
	--ss-pack-accent-soft: rgba(233, 30, 140, 0.12);
	--ss-pack-glow: rgba(233, 30, 140, 0.18);
}
.ss-pack-card.ss-pack--5x3 {
	--ss-pack-accent: #9b7ede;
	--ss-pack-accent-soft: rgba(155, 126, 222, 0.14);
	--ss-pack-glow: rgba(155, 126, 222, 0.2);
}
.ss-pack-card.ss-pack--10x5 {
	--ss-pack-accent: #c9a227;
	--ss-pack-accent-soft: rgba(201, 162, 39, 0.16);
	--ss-pack-glow: rgba(201, 162, 39, 0.22);
}
.ss-pack-card.ss-pack--20x8 {
	--ss-pack-accent: #141414;
	--ss-pack-accent-soft: rgba(20, 20, 20, 0.1);
	--ss-pack-glow: rgba(0, 0, 0, 0.18);
}
.ss-pack-card.ss-pack--30x9 {
	--ss-pack-accent: #0d9488;
	--ss-pack-accent-soft: rgba(13, 148, 136, 0.14);
	--ss-pack-glow: rgba(13, 148, 136, 0.2);
}
.ss-pack-card.ss-pack--40x10 {
	--ss-pack-accent: #ea580c;
	--ss-pack-accent-soft: rgba(234, 88, 12, 0.14);
	--ss-pack-glow: rgba(234, 88, 12, 0.22);
}

.ss-pack-card {
	position: relative;
	display: grid;
	grid-template-columns: auto auto minmax(0, 1.35fr) auto auto;
	align-items: center;
	gap: 12px 16px;
	width: 100%;
	padding: 16px 16px 16px 14px;
	border-radius: 16px;
	border: 1.5px solid var(--ss-pack-line) !important;
	background: #fff !important;
	box-shadow: 0 2px 8px rgba(16, 24, 40, 0.04);
	cursor: pointer;
	color: var(--ss-pack-ink) !important;
	text-align: left;
	transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
	box-sizing: border-box;
	outline: none;
	-webkit-tap-highlight-color: transparent;
}
.ss-pack-card:hover,
.ss-pack-card:focus,
.ss-pack-card:focus-visible {
	border-color: var(--ss-pack-accent) !important;
	box-shadow: 0 10px 24px var(--ss-pack-glow) !important;
	transform: translateY(-1px);
	background: #fff !important;
	color: var(--ss-pack-ink) !important;
}
.ss-pack-card.is-selected,
.ss-pack-card[aria-pressed="true"],
.ss-pack-card.is-best {
	border-color: var(--ss-pack-accent) !important;
	box-shadow: 0 0 0 2px var(--ss-pack-glow), 0 10px 24px var(--ss-pack-glow) !important;
}
.ss-pack-card:disabled,
.ss-pack-card[aria-disabled="true"] {
	opacity: 0.72;
	cursor: wait;
}

.ss-pack-badge {
	position: absolute;
	top: -10px;
	left: 18px;
	z-index: 2;
	padding: 4px 10px;
	border-radius: 999px;
	background: var(--ss-pack-accent) !important;
	color: #fff !important;
	font-size: 0.68rem;
	font-weight: 600;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	white-space: nowrap;
	box-shadow: 0 4px 10px var(--ss-pack-glow);
}
.ss-pack-card.has-badge { margin-top: 8px; }
.ss-pack-card.ss-pack--20x8 .ss-pack-badge { font-size: 0.62rem; }

.ss-pack-check {
	width: 22px;
	height: 22px;
	border-radius: 50%;
	border: 2px solid #c5cad3;
	background: #fff;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.ss-pack-check::after {
	content: "";
	width: 10px;
	height: 10px;
	border-radius: 50%;
	background: transparent;
}
.ss-pack-card.is-selected .ss-pack-check,
.ss-pack-card[aria-pressed="true"] .ss-pack-check {
	border-color: var(--ss-pack-accent);
}
.ss-pack-card.is-selected .ss-pack-check::after,
.ss-pack-card[aria-pressed="true"] .ss-pack-check::after {
	background: var(--ss-pack-accent);
}

.ss-pack-icon {
	width: 52px;
	height: 52px;
	border-radius: 50%;
	background: var(--ss-pack-accent-soft);
	color: var(--ss-pack-accent);
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.ss-pack-icon svg { width: 30px; height: 30px; display: block; }

.ss-pack-info { min-width: 0; }
.ss-pack-label {
	font-size: 1.02rem;
	font-weight: 600;
	line-height: 1.2;
	color: var(--ss-pack-ink) !important;
	margin: 0 0 2px;
}
.ss-pack-packname {
	font-size: 0.72rem;
	font-weight: 600;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: var(--ss-pack-muted);
	margin: 0 0 7px;
}
.ss-pack-tag {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	width: 90px;
	max-width: 90px;
	box-sizing: border-box;
	padding: 4px 6px;
	border-radius: 999px;
	background: var(--ss-pack-accent-soft);
	color: var(--ss-pack-accent);
	font-size: 0.62rem;
	font-weight: 600;
	line-height: 1.15;
	white-space: normal;
}
.ss-pack-tag svg { width: 11px; height: 11px; flex-shrink: 0; }
.ss-pack-tag-text {
	min-width: 0;
	white-space: pre-line;
	line-height: 1.15;
}
.ss-pack-tag[hidden] { display: none !important; }

.ss-pack-metrics {
	display: flex;
	flex-direction: column;
	gap: 10px;
	min-width: 96px;
}
.ss-pack-metric-label {
	font-size: 0.62rem;
	font-weight: 600;
	letter-spacing: 0.05em;
	text-transform: uppercase;
	color: var(--ss-pack-muted);
	margin: 0 0 2px;
}
.ss-pack-metric--save .ss-pack-metric-label { color: var(--ss-pack-save); }
.ss-pack-list {
	font-size: 0.95rem;
	color: #9ca3af;
	text-decoration: line-through;
	font-weight: 400;
}
.ss-pack-list[hidden] { display: none !important; }
.ss-pack-save {
	font-size: 1rem;
	font-weight: 600;
	color: var(--ss-pack-save) !important;
}
.ss-pack-save[hidden] { display: none !important; }

.ss-pack-pricebox {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	justify-content: center;
	gap: 8px;
	min-width: 132px;
	text-align: right;
}
.ss-pack-price {
	font-size: clamp(1.25rem, 2.6vw, 1.55rem);
	font-weight: 600;
	color: var(--ss-pack-accent) !important;
	line-height: 1.05;
}
.ss-pack-per {
	font-size: 0.72rem;
	color: var(--ss-pack-muted);
	margin-top: -4px;
}

.ss-pack-cta {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	min-width: 120px;
	padding: 10px 14px;
	border-radius: 999px;
	border: none !important;
	background: var(--ss-pack-cta) !important;
	color: #fff !important;
	font-size: 0.7rem;
	font-weight: 600;
	letter-spacing: 0;
	text-transform: uppercase;
	box-shadow: 0 6px 14px rgba(22, 163, 74, 0.28);
	white-space: nowrap;
}
.ss-pack-card:hover .ss-pack-cta,
.ss-pack-card.is-selected .ss-pack-cta,
.ss-pack-card[aria-pressed="true"] .ss-pack-cta,
.ss-pack-card.is-loading .ss-pack-cta {
	background: var(--ss-pack-cta-dark) !important;
	color: #fff !important;
}

.ss-packs-trust {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 0;
	margin: 18px 0 0;
	border: 1px solid var(--ss-pack-line);
	border-radius: 14px;
	overflow: hidden;
	background: #fff;
}
.ss-packs-trust-item {
	padding: 16px 12px;
	text-align: center;
	border-right: 1px solid var(--ss-pack-line);
}
.ss-packs-trust-item:last-child { border-right: 0; }
.ss-packs-trust-icon {
	width: 28px;
	height: 28px;
	margin: 0 auto 8px;
	color: var(--ss-trust);
}
.ss-packs-trust-icon svg { width: 28px; height: 28px; display: block; margin: 0 auto; }
.ss-packs-trust-title {
	margin: 0 0 4px;
	font-size: 0.68rem;
	font-weight: 800;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	color: var(--ss-trust);
	line-height: 1.25;
}
.ss-packs-trust-desc {
	margin: 0;
	font-size: 0.75rem;
	color: var(--ss-pack-muted);
	line-height: 1.35;
}

.ss-packs-secure {
	display: flex;
	align-items: flex-start;
	justify-content: center;
	gap: 8px;
	margin: 14px 0 0;
	text-align: center;
}
.ss-packs-secure-icon {
	width: 16px;
	height: 16px;
	color: var(--ss-trust);
	flex-shrink: 0;
	margin-top: 2px;
}
.ss-packs-secure-icon svg { width: 16px; height: 16px; display: block; }
.ss-packs-secure-text { margin: 0; }
.ss-packs-secure-text strong {
	display: block;
	font-size: 0.82rem;
	font-weight: 700;
	color: var(--ss-trust);
	line-height: 1.35;
}
.ss-packs-secure-text span {
	display: block;
	font-size: 0.82rem;
	color: var(--ss-pack-ink);
	line-height: 1.35;
}

.ss-packs-hint {
	display: flex;
	align-items: flex-start;
	gap: 8px;
	margin: 14px 0 0;
	font-size: 0.82rem;
	color: var(--ss-pack-muted);
	line-height: 1.4;
}
.ss-packs-hint-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	background: #9b7ede;
	color: #fff;
	font-size: 0.7rem;
	font-weight: 800;
	font-style: normal;
	flex-shrink: 0;
	margin-top: 1px;
}
.ss-packs-status {
	margin-top: 10px;
	font-size: 0.88rem;
	font-weight: 600;
	color: var(--ss-pack-ink);
	min-height: 1.2em;
}
.ss-packs-status.is-error { color: #b91c1c; }

@media (max-width: 820px) {
	.ss-packs-trust { grid-template-columns: repeat(4, 1fr); }
	.ss-packs-trust-item { padding: 10px 6px; }
	.ss-packs-trust-title { font-size: 0.55rem; }
	.ss-packs-trust-desc { font-size: 0.62rem; }
	.ss-packs-trust-icon { width: 22px; height: 22px; margin-bottom: 6px; }
	.ss-packs-trust-icon svg { width: 22px; height: 22px; }
}

/* Mobile = misma estructura que desktop (icono visible), solo compacto */
@media (max-width: 900px) {
	.purchase-panel,
	aside.purchase-panel {
		padding: 1rem !important;
	}
	.product-section-ss .container {
		gap: 1rem !important;
	}
	.container {
		padding-left: 1rem !important;
		padding-right: 1rem !important;
	}

	.ss-packs-wrap .ss-packs-title {
		font-size: 0.95rem !important;
		margin-bottom: 4px !important;
	}
	.ss-packs-sub {
		font-size: 0.75rem !important;
		margin-bottom: 10px !important;
	}
	.ss-packs-grid { gap: 8px !important; }

	.ss-pack-card {
		grid-template-columns: auto 80px 80px auto auto !important;
		align-items: center !important;
		gap: 6px 6px !important;
		padding: 10px 8px !important;
		border-radius: 12px !important;
	}
	.ss-pack-card.is-best { margin-top: 10px !important; }

	.ss-pack-check {
		display: none !important;
	}

	.ss-pack-icon {
		display: inline-flex !important;
		width: 34px !important;
		height: 34px !important;
		flex-shrink: 0 !important;
	}
	.ss-pack-icon svg {
		width: 20px !important;
		height: 20px !important;
	}

	.ss-pack-info { min-width: 0; }
	.ss-pack-label {
		font-size: 0.78rem !important;
		line-height: 1.15 !important;
		margin-bottom: 1px !important;
	}
	.ss-pack-packname {
		font-size: 0.55rem !important;
		margin-bottom: 3px !important;
		letter-spacing: 0.04em !important;
	}
	.ss-pack-tag {
		display: inline-flex !important;
		width: 70px !important;
		max-width: 70px !important;
		font-size: 0.55rem !important;
		padding: 2px 5px !important;
		gap: 3px !important;
		white-space: normal !important;
	}
	.ss-pack-tag svg { width: 9px !important; height: 9px !important; flex-shrink: 0 !important; }
	.ss-pack-tag-text {
		white-space: pre-line !important;
		line-height: 1.15 !important;
	}

	.ss-pack-metrics {
		grid-column: auto !important;
		flex-direction: column !important;
		justify-content: center !important;
		gap: 3px !important;
		min-width: 58px !important;
		padding-top: 0 !important;
		border-top: 0 !important;
		position: relative !important;
		left: 10px !important;
	}
	.ss-pack-metric-label { font-size: 0.48rem !important; margin-bottom: 0 !important; }
	.ss-pack-list,
	.ss-pack-save { font-size: 0.7rem !important; }

	.ss-pack-pricebox {
		grid-column: auto !important;
		display: flex !important;
		flex-direction: column !important;
		align-items: flex-end !important;
		text-align: right !important;
		min-width: 88px !important;
		gap: 4px !important;
	}
	.ss-pack-price {
		font-size: 0.95rem !important;
		text-align: right !important;
		width: auto !important;
		line-height: 1.05 !important;
	}
	.ss-pack-per {
		display: block !important;
		font-size: 0.55rem !important;
		text-align: right !important;
		width: auto !important;
		margin-top: 0 !important;
	}
	.ss-pack-cta {
		display: inline-flex !important;
		width: 100% !important;
		min-width: 0 !important;
		padding: 7px 8px !important;
		font-size: 0.55rem !important;
		letter-spacing: 0 !important;
		font-weight: 600 !important;
	}
	.ss-pack-badge {
		font-size: 0.52rem !important;
		left: 10px !important;
		top: -8px !important;
		padding: 2px 7px !important;
	}

	.ss-packs-secure {
		margin-top: 10px !important;
		gap: 6px !important;
	}
	.ss-packs-secure-text strong,
	.ss-packs-secure-text span {
		font-size: 0.72rem !important;
	}
}

@media (max-width: 400px) {
	.ss-pack-card {
		gap: 4px 4px !important;
		padding: 8px 6px !important;
	}
	.ss-pack-icon {
		width: 28px !important;
		height: 28px !important;
	}
	.ss-pack-icon svg {
		width: 16px !important;
		height: 16px !important;
	}
	.ss-pack-label { font-size: 0.7rem !important; }
	.ss-pack-price { font-size: 0.88rem !important; }
	.ss-pack-metrics { min-width: 52px !important; }
	.ss-pack-pricebox { min-width: 78px !important; }
	.ss-packs-trust-item { padding: 8px 4px; }
}
CSS;
	}

	private static function front_js(): string {
		return <<<'JS'
(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	function formatMoney(n) {
		try {
			return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n || 0);
		} catch (e) {
			return '$' + Math.round(n || 0);
		}
	}

	/* Ticket de admisión limpio (muescas laterales), apilados según cantidad */
	function ticketGlyph(ox, oy, scale, opacity) {
		var s = scale || 1;
		var o = (typeof opacity === 'number') ? opacity : 1;
		var t = 'translate(' + ox + ' ' + oy + ') scale(' + s + ')';
		return '<g transform="' + t + '" opacity="' + o + '">' +
			'<path fill="currentColor" fill-rule="evenodd" d="' +
			'M4 7.2c0-1.2.98-2.2 2.2-2.2h15.6c1.22 0 2.2.98 2.2 2.2v1.05a2.35 2.35 0 0 0 0 4.5V14.8c0 1.22-.98 2.2-2.2 2.2H6.2C4.98 17 4 16.02 4 14.8v-1.05a2.35 2.35 0 0 0 0-4.5V7.2z' +
			'M10.2 8.1h1.15v7.8H10.2V8.1zm2.5 1.55h6.2a.55.55 0 0 1 0 1.1h-6.2a.55.55 0 0 1 0-1.1zm0 2.4h4.6a.55.55 0 0 1 0 1.1h-4.6a.55.55 0 0 1 0-1.1z' +
			'"/>' +
			'</g>';
	}

	function ticketIconSvg(count) {
		var n = Math.max(1, Math.min(4, Number(count) || 1));
		var parts = [];
		if (n >= 4) parts.push(ticketGlyph(7.2, 0.2, 0.86, 0.28));
		if (n >= 3) parts.push(ticketGlyph(4.6, 1.4, 0.9, 0.42));
		if (n >= 2) parts.push(ticketGlyph(2.2, 2.8, 0.94, 0.62));
		parts.push(ticketGlyph(n === 1 ? 4 : 0, n === 1 ? 4 : 4.4, n === 1 ? 1.05 : 1, 1));
		return '<svg viewBox="0 0 32 32" aria-hidden="true">' + parts.join('') + '</svg>';
	}

	function iconCountForBuy(buy) {
		var b = Number(buy) || 1;
		if (b <= 1) return 1;
		if (b <= 3) return 2;
		if (b <= 5) return 3;
		return 4;
	}

	function tagIconSvg() {
		return '<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2.2 2.2h5.1c.3 0 .6.12.82.34l5.54 5.54a1.16 1.16 0 0 1 0 1.64l-4.1 4.1a1.16 1.16 0 0 1-1.64 0L2.38 8.28A1.16 1.16 0 0 1 2.04 7.46V2.2zm3.1 3.35a1.05 1.05 0 1 0 0-2.1 1.05 1.05 0 0 0 0 2.1z"/></svg>';
	}

	function trustIcons() {
		return {
			secure: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5.2c0 4.4-2.9 8.4-7 9.8-4.1-1.4-7-5.4-7-9.8V6l7-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9.2 12.1l1.8 1.8 3.8-3.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			ship: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="6" width="17" height="12" rx="2.2" stroke="currentColor" stroke-width="1.7"/><path d="M3.8 8.2L12 13.2l8.2-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			digital: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="7.5" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 7.5V6.2A2.2 2.2 0 0 1 10.2 4h3.6A2.2 2.2 0 0 1 16 6.2v1.3" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="13" r="1.4" fill="currentColor"/></svg>',
			cert: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="10" r="5.2" stroke="currentColor" stroke-width="1.7"/><path d="M9.8 10.1l1.5 1.5 3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.2 14.6L8 20l4-1.6L16 20l-1.2-5.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			lock: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8.2 11V8.4a3.8 3.8 0 0 1 7.6 0V11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'
		};
	}

	function hideManualUi() {
		var selectors = [
			'.lty-lottery-ticket-container',
			'.lty-lottery-ticket-header',
			'.lty-lottery-ticket-panel',
			'.quantity-selector',
			'.purchase-action .buy-section',
			'.purchase-action .price-info',
			'form.form-buy-ticket > img',
			'.purchase-panel form > img',
			'aside.purchase-panel form > img',
			'.purchase-panel img[src*="WhatsApp-Image-2026-03-03"]',
			'.purchase-panel img[data-src*="WhatsApp-Image-2026-03-03"]',
			'img[src*="WhatsApp-Image-2026-03-03-at-20.03.45"]',
			'img[data-src*="WhatsApp-Image-2026-03-03-at-20.03.45"]',
			'.banner_packs',
			'#lottery-ticket-random'
		];
		selectors.forEach(function (sel) {
			document.querySelectorAll(sel).forEach(function (el) {
				el.style.setProperty('display', 'none', 'important');
				el.style.setProperty('visibility', 'hidden', 'important');
				el.style.setProperty('height', '0', 'important');
				el.style.setProperty('margin', '0', 'important');
				el.style.setProperty('padding', '0', 'important');
			});
		});
	}

	function packThemeClass(c) {
		var buy = Number(c.buy || 0);
		var pay = Number(c.pay || 0);
		if (buy === 1 && pay === 1) return 'ss-pack--1x1';
		if (buy === 3 && pay === 2) return 'ss-pack--3x2';
		if (buy === 5 && pay === 3) return 'ss-pack--5x3';
		if (buy === 10 && pay === 5) return 'ss-pack--10x5';
		if (buy === 20 && pay === 8) return 'ss-pack--20x8';
		if (buy === 30 && pay === 9) return 'ss-pack--30x9';
		if (buy === 40 && pay === 10) return 'ss-pack--40x10';
		return 'ss-pack--1x1';
	}

	function isMasElegido(c) {
		return Number(c.buy) === 5 && Number(c.pay) === 3;
	}

	function isMasConveniente(c) {
		return Number(c.buy) === 20 && Number(c.pay) === 8;
	}

	function titleFor(c, i18n) {
		if (Number(c.buy) === 1) return i18n.oneTicket;
		return (i18n.nTickets || '{n} DigiTickets').replace('{n}', c.buy);
	}

	function packNameFor(c, i18n) {
		if (Number(c.buy) === 1 && Number(c.pay) === 1) return i18n.packBasic || 'PACK BÁSICO';
		return (i18n.packName || 'PACK {buy}x{pay}').replace('{buy}', c.buy).replace('{pay}', c.pay);
	}

	function renderHeading(el, text) {
		var raw = String(text || '');
		var parts = raw.split(/(DigiTickets?)/i);
		el.textContent = '';
		parts.forEach(function (p) {
			if (!p) return;
			if (/^DigiTickets?$/i.test(p)) {
				var span = document.createElement('span');
				span.className = 'ss-packs-title-accent';
				span.textContent = p.toUpperCase();
				el.appendChild(span);
			} else {
				el.appendChild(document.createTextNode(p.toUpperCase()));
			}
		});
	}

	function getPackMountPoint() {
		var panel = document.querySelector('aside.purchase-panel') || document.querySelector('.purchase-panel');
		if (panel) {
			var slot = panel.querySelector('.ss-pdp-packs-slot');
			if (slot) {
				return { parent: slot, before: null, panel: panel };
			}
			var details = panel.querySelector('.ss-pdp-details');
			if (details && details.parentNode) {
				return { parent: details.parentNode, before: details.nextSibling, panel: panel };
			}
			var countdown = panel.querySelector('.countdown-container');
			if (countdown && countdown.parentNode) {
				return { parent: countdown.parentNode, before: countdown.nextSibling, panel: panel };
			}
			var action = panel.querySelector('.purchase-action');
			if (action && action.parentNode) {
				return { parent: action.parentNode, before: action, panel: panel };
			}
			var legal = panel.querySelector('.legal-info');
			if (legal && legal.parentNode) {
				return { parent: legal.parentNode, before: legal, panel: panel };
			}
		}
		var form = document.querySelector('aside.purchase-panel form.form-buy-ticket') ||
			document.querySelector('form.form-buy-ticket');
		if (form) {
			return { parent: form, before: form.firstChild, panel: panel || form };
		}
		return null;
	}

	function mountPacks(wrap) {
		var mount = getPackMountPoint();
		if (!mount || !mount.parent) return false;
		mount.parent.insertBefore(wrap, mount.before);
		return true;
	}

	function renderPacks() {
		var cfg = window.ssPacksLottery;
		if (!cfg || !cfg.campaigns) return;

		var existing = document.querySelector('.ss-packs-wrap');
		if (existing) {
			// Reubicar si quedó arriba del título (bug fichas sin form.form-buy-ticket).
			var mount = getPackMountPoint();
			if (mount && mount.parent && existing.parentNode !== mount.parent) {
				mount.parent.insertBefore(existing, mount.before);
			} else if (mount && mount.panel) {
				var title = mount.panel.querySelector('h1.pdp-title, .pdp-title');
				if (title) {
					var posWrap = existing.compareDocumentPosition(title);
					// Si el wrap está antes del título, moverlo al ancla correcta.
					if (posWrap & Node.DOCUMENT_POSITION_FOLLOWING) {
						mount.parent.insertBefore(existing, mount.before);
					}
				}
			}
			return;
		}

		var icons = trustIcons();
		var wrap = document.createElement('div');
		wrap.className = 'ss-packs-wrap';
		wrap.innerHTML =
			'<h3 class="ss-packs-title"></h3>' +
			'<p class="ss-packs-sub"></p>' +
			'<div class="ss-packs-grid"></div>' +
			'<div class="ss-packs-trust" aria-label="Beneficios">' +
				'<div class="ss-packs-trust-item"><div class="ss-packs-trust-icon">' + icons.secure + '</div><p class="ss-packs-trust-title"></p><p class="ss-packs-trust-desc"></p></div>' +
				'<div class="ss-packs-trust-item"><div class="ss-packs-trust-icon">' + icons.ship + '</div><p class="ss-packs-trust-title"></p><p class="ss-packs-trust-desc"></p></div>' +
				'<div class="ss-packs-trust-item"><div class="ss-packs-trust-icon">' + icons.digital + '</div><p class="ss-packs-trust-title"></p><p class="ss-packs-trust-desc"></p></div>' +
				'<div class="ss-packs-trust-item"><div class="ss-packs-trust-icon">' + icons.cert + '</div><p class="ss-packs-trust-title"></p><p class="ss-packs-trust-desc"></p></div>' +
			'</div>' +
			'<div class="ss-packs-secure"><span class="ss-packs-secure-icon">' + icons.lock + '</span><p class="ss-packs-secure-text"><strong></strong><span></span></p></div>' +
			'<div class="ss-packs-status" aria-live="polite"></div>';

		renderHeading(wrap.querySelector('.ss-packs-title'), cfg.i18n.heading);
		wrap.querySelector('.ss-packs-sub').textContent = cfg.i18n.subheading || '';

		var trustItems = wrap.querySelectorAll('.ss-packs-trust-item');
		var trustData = [
			[cfg.i18n.trustSecure, cfg.i18n.trustSecureD],
			[cfg.i18n.trustShip, cfg.i18n.trustShipD],
			[cfg.i18n.trustDigital, cfg.i18n.trustDigitalD],
			[cfg.i18n.trustCert, cfg.i18n.trustCertD]
		];
		trustItems.forEach(function (item, idx) {
			item.querySelector('.ss-packs-trust-title').textContent = trustData[idx][0] || '';
			item.querySelector('.ss-packs-trust-desc').textContent = trustData[idx][1] || '';
		});
		wrap.querySelector('.ss-packs-secure-text strong').textContent = cfg.i18n.trustFoot1 || '';
		wrap.querySelector('.ss-packs-secure-text span').textContent = cfg.i18n.trustFoot2 || '';

		var grid = wrap.querySelector('.ss-packs-grid');

		cfg.campaigns.forEach(function (c) {
			var btn = document.createElement('div');
			btn.setAttribute('role', 'button');
			btn.setAttribute('tabindex', '0');
			btn.className = 'ss-pack-card ' + packThemeClass(c);
			btn.setAttribute('data-campaign-id', c.id);
			btn.setAttribute('data-buy', c.buy);
			btn.setAttribute('data-pay', c.pay);
			btn.setAttribute('aria-pressed', 'false');

			var badgeText = '';
			if (c.badge && String(c.badge).trim()) {
				badgeText = String(c.badge).trim();
			} else if (isMasElegido(c)) {
				badgeText = cfg.i18n.bestBadge;
			} else if (isMasConveniente(c)) {
				badgeText = cfg.i18n.valueBadge;
			}
			if (badgeText) btn.classList.add('has-badge');
			if (isMasElegido(c)) btn.classList.add('is-best');

			var showPromo = Number(c.buy) > 1;
			var ctaLabel = isMasElegido(c) && cfg.i18n.selectBest ? cfg.i18n.selectBest : cfg.i18n.select;

			btn.innerHTML =
				(badgeText ? '<span class="ss-pack-badge"></span>' : '') +
				'<span class="ss-pack-check" aria-hidden="true"></span>' +
				'<span class="ss-pack-icon">' + ticketIconSvg(iconCountForBuy(c.buy)) + '</span>' +
				'<div class="ss-pack-info">' +
					'<div class="ss-pack-label"></div>' +
					'<div class="ss-pack-packname"></div>' +
					'<span class="ss-pack-tag"' + (showPromo ? '' : ' hidden') + '>' + tagIconSvg() + '<span class="ss-pack-tag-text"></span></span>' +
				'</div>' +
				'<div class="ss-pack-metrics">' +
					'<div class="ss-pack-metric"><div class="ss-pack-metric-label">' + (cfg.i18n.priceNormal || 'PRECIO NORMAL') + '</div><div class="ss-pack-list" hidden></div></div>' +
					'<div class="ss-pack-metric ss-pack-metric--save"><div class="ss-pack-metric-label">' + (cfg.i18n.saveLabel || 'AHORRAS') + '</div><div class="ss-pack-save" hidden></div></div>' +
				'</div>' +
				'<div class="ss-pack-pricebox">' +
					'<div class="ss-pack-price"></div>' +
					'<div class="ss-pack-per"></div>' +
					'<div class="ss-pack-cta"></div>' +
				'</div>';

			if (badgeText) btn.querySelector('.ss-pack-badge').textContent = badgeText;
			btn.querySelector('.ss-pack-label').textContent = titleFor(c, cfg.i18n);
			btn.querySelector('.ss-pack-packname').textContent = packNameFor(c, cfg.i18n);

			if (showPromo) {
				btn.querySelector('.ss-pack-tag-text').textContent = (cfg.i18n.promoTag || "Pagas {pay} y\nrecibes {buy}")
					.replace('{pay}', c.pay)
					.replace('{buy}', c.buy);
			}

			var listEl = btn.querySelector('.ss-pack-list');
			var saveEl = btn.querySelector('.ss-pack-save');
			if (c.buy > 1 && c.list_price > c.pack_price) {
				listEl.hidden = false;
				listEl.textContent = formatMoney(c.list_price);
			} else {
				listEl.closest('.ss-pack-metric').style.visibility = 'hidden';
			}
			if (c.buy > 1 && c.savings > 0) {
				saveEl.hidden = false;
				saveEl.textContent = formatMoney(c.savings);
			} else {
				saveEl.closest('.ss-pack-metric').style.visibility = 'hidden';
			}

			btn.querySelector('.ss-pack-price').textContent = formatMoney(c.pack_price);
			if (Number(c.buy) === 1) {
				btn.querySelector('.ss-pack-per').textContent = 'por 1 DigiTicket';
			} else {
				btn.querySelector('.ss-pack-per').textContent = (cfg.i18n.perPack || 'por {n} DigiTickets').replace('{n}', c.buy);
			}
			btn.querySelector('.ss-pack-cta').textContent = ctaLabel;
			btn.setAttribute('data-cta', ctaLabel);
			grid.appendChild(btn);
		});

		if (!mountPacks(wrap)) {
			return;
		}
	}

	function setStatus(msg, isError) {
		var s = document.querySelector('.ss-packs-status');
		if (!s) return;
		s.classList.toggle('is-error', !!isError);
		s.textContent = msg || '';
	}

	function defaultCta(el) {
		return el.getAttribute('data-cta') || (window.ssPacksLottery && window.ssPacksLottery.i18n.select) || 'SELECCIONAR';
	}

	function markSelected(btn) {
		document.querySelectorAll('.ss-pack-card').forEach(function (el) {
			el.classList.remove('is-selected', 'is-loading');
			el.setAttribute('aria-pressed', 'false');
			var cta = el.querySelector('.ss-pack-cta');
			if (cta) cta.textContent = defaultCta(el);
		});
		btn.classList.add('is-selected', 'is-loading');
		btn.setAttribute('aria-pressed', 'true');
		var cta = btn.querySelector('.ss-pack-cta');
		if (cta && window.ssPacksLottery) cta.textContent = '✓ ' + window.ssPacksLottery.i18n.selected;
	}

	function selectPack(campaignId, btn) {
		var cfg = window.ssPacksLottery;
		if (btn.getAttribute('aria-disabled') === 'true') return;
		markSelected(btn);
		document.querySelectorAll('.ss-pack-card').forEach(function (el) {
			el.setAttribute('aria-disabled', 'true');
			el.style.pointerEvents = 'none';
		});
		setStatus(cfg.i18n.loading, false);

		var body = new FormData();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('product_id', cfg.productId);
		body.append('campaign_id', campaignId);

		fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success && res.data) {
					if (cfg.embeddedCheckout || res.data.embedded) {
						var target = res.data.redirect || (window.location.pathname + (cfg.checkoutAnchor || '#ss-comprar-checkout'));
						var urlObj;
						try {
							urlObj = new URL(target, window.location.origin);
						} catch (e) {
							urlObj = new URL(window.location.href);
						}
						urlObj.searchParams.set('ss_pack', String(res.data.campaign_id || '1'));
						if (!urlObj.hash && cfg.checkoutAnchor) {
							urlObj.hash = cfg.checkoutAnchor.replace(/^#/, '');
						}
						window.location.assign(urlObj.toString());
						return;
					}
					if (res.data.redirect) {
						window.location.href = res.data.redirect;
						return;
					}
				}
				var msg = (res && res.data && res.data.message) ? res.data.message : cfg.i18n.error;
				setStatus(msg, true);
				document.querySelectorAll('.ss-pack-card').forEach(function (el) {
					el.removeAttribute('aria-disabled');
					el.style.pointerEvents = '';
					el.classList.remove('is-selected', 'is-loading');
					el.setAttribute('aria-pressed', 'false');
					var cta = el.querySelector('.ss-pack-cta');
					if (cta) cta.textContent = defaultCta(el);
				});
			})
			.catch(function () {
				setStatus(cfg.i18n.error, true);
				document.querySelectorAll('.ss-pack-card').forEach(function (el) {
					el.removeAttribute('aria-disabled');
					el.style.pointerEvents = '';
					el.classList.remove('is-selected', 'is-loading');
					el.setAttribute('aria-pressed', 'false');
					var cta = el.querySelector('.ss-pack-cta');
					if (cta) cta.textContent = defaultCta(el);
				});
			});
	}

	function findCampaignByPackParam(raw) {
		var cfg = window.ssPacksLottery;
		if (!cfg || !cfg.campaigns || raw === null || raw === '') return null;
		var key = String(raw).trim();
		for (var i = 0; i < cfg.campaigns.length; i++) {
			var c = cfg.campaigns[i];
			if (String(c.id) === key || String(c.buy) === key) return c;
		}
		return null;
	}

	function maybePreselectPackFromQuery() {
		var cfg = window.ssPacksLottery;
		if (!cfg) return;
		var params = new URLSearchParams(window.location.search);
		var raw = params.get('pack');
		if (!raw) return;
		var campaign = findCampaignByPackParam(raw);
		if (!campaign) return;
		var tries = 0;
		var timer = setInterval(function () {
			tries++;
			var card = document.querySelector('.ss-pack-card[data-campaign-id="' + campaign.id + '"]');
			if (card) {
				clearInterval(timer);
				selectPack(String(campaign.id), card);
			} else if (tries > 40) {
				clearInterval(timer);
			}
		}, 150);
	}

	ready(function () {
		hideManualUi();
		renderPacks();
		hideManualUi();
		setTimeout(function () { hideManualUi(); renderPacks(); maybePreselectPackFromQuery(); }, 500);
		setTimeout(function () { hideManualUi(); renderPacks(); }, 2000);

		document.addEventListener('submit', function (e) {
			var form = e.target;
			if (form && form.classList && form.classList.contains('form-buy-ticket')) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true);

		document.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('.ss-pack-card') : null;
			if (!btn || btn.getAttribute('aria-disabled') === 'true') return;
			e.preventDefault();
			selectPack(btn.getAttribute('data-campaign-id') || '0', btn);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') return;
			var btn = e.target.closest ? e.target.closest('.ss-pack-card') : null;
			if (!btn || btn.getAttribute('aria-disabled') === 'true') return;
			e.preventDefault();
			selectPack(btn.getAttribute('data-campaign-id') || '0', btn);
		});
	});
})();
JS;
	}
}

SorteoSeguro_Packs_Lottery::init();
