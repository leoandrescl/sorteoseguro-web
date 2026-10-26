<?php
/**
 * Plugin Name: Sorteo Seguro – Gift Tickets (promo de regalo)
 * Description: Promo "Compra X → regala Y" por sorteo. Crea DigiTickets de regalo con prefijo propio (ej. J → JR) que NO descuentan el universo de disponibles (solo lo pagado X lo descuenta) y que SÍ participan del sorteo. Módulo aislado: no modifica Promo Engine ni el flujo de packs.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin. Rollback: borrar este archivo y restaurar los respaldos en bkp-gift-tickets-20261011/.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Gift_Tickets {

	const VERSION = '1.0.0';
	const CPT     = 'ss_gift_promo';

	// Promo meta.
	const META_PRODUCT = '_ssgift_product_id';
	const META_BUY     = '_ssgift_buy';
	const META_COUNT   = '_ssgift_count';
	const META_PREFIX  = '_ssgift_prefix';
	const META_START   = '_ssgift_start';
	const META_END     = '_ssgift_end';
	const META_ACTIVE  = '_ssgift_active';
	const META_BADGE   = '_ssgift_badge';
	const META_COLOR   = '_ssgift_color';

	/** IDs de campaña sintéticos para las tarjetas de regalo (evita choque con Promo Engine). */
	const CAMPAIGN_OFFSET = 900000000;

	// Order item meta / cart item data.
	const ITEM_GIFTS = '_ss_gift_tickets';
	const ITEM_PROMO = '_ss_gift_promo_id';
	const CART_GIFTS = 'ss_gift_tickets';
	const CART_PROMO = 'ss_gift_promo_id';

	// Ticket CPT markers.
	const TICKET_FLAG  = '_ss_gift';
	const TICKET_PROMO = '_ss_gift_promo_id';
	const META_ORDER   = 'lty_order_id';
	const META_NUMBER  = 'lty_ticket_number';

	const COUNTER_PFX = '_ssgift_next_';
	const LOCK_PFX    = 'ssgift_tickets_';
	const LOCK_WAIT   = 10;

	/** @var bool */
	private static $booted = false;

	public static function init(): void {
		if (self::$booted) {
			return;
		}
		self::$booted = true;

		add_action('init', array(__CLASS__, 'register_cpt'));
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
		add_action('save_post_' . self::CPT, array(__CLASS__, 'save_promo'), 10, 2);
		add_filter('manage_' . self::CPT . '_posts_columns', array(__CLASS__, 'admin_columns'));
		add_action('manage_' . self::CPT . '_posts_custom_column', array(__CLASS__, 'render_admin_column'), 10, 2);
		add_action('admin_head', array(__CLASS__, 'admin_head'));

		// Carrito: mintear regalos al agregar un pack (marcado por el flujo de packs).
		add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'attach_gift_to_cart_item'), 20, 4);
		// Mostrar regalos junto a los tickets en carrito/checkout (resumen).
		add_filter('woocommerce_get_item_data', array(__CLASS__, 'append_gift_to_item_data'), 30, 2);

		// Mostrar las tarjetas de regalo en el selector de packs (todas las superficies).
		add_filter('ss_packs_lottery_config', array(__CLASS__, 'filter_packs_config'), 99, 2);

		// Pedido: persistir regalos en el ítem y su etiqueta visible.
		add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'persist_gift_on_order_item'), 20, 4);

		// Crear CPT de regalo al colocar pedido (pending) y confirmar al pagar.
		add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'on_order_created'), 20, 1);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'on_store_api_order_processed'), 20, 1);
		add_action('woocommerce_order_status_processing', array(__CLASS__, 'on_order_paid'), 20, 1);
		add_action('woocommerce_order_status_completed', array(__CLASS__, 'on_order_paid'), 20, 1);
		add_action('woocommerce_payment_complete', array(__CLASS__, 'on_order_paid'), 20, 1);
		// Backfill: si el pedido ya estaba pagado antes del deploy, crear regalos al descargar.
		add_action('init', array(__CLASS__, 'maybe_backfill_on_download'), 1);

		// Anular regalos si el pedido se cancela/reembolsa/falla.
		add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'on_order_void'), 20, 1);
		add_action('woocommerce_order_status_refunded', array(__CLASS__, 'on_order_void'), 20, 1);
		add_action('woocommerce_order_status_failed', array(__CLASS__, 'on_order_void'), 20, 1);
	}

	/* ============================ CPT / Admin ============================ */

	public static function register_cpt(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => 'Promos de regalo',
					'singular_name' => 'Promo de regalo',
					'add_new'       => 'Añadir nueva',
					'add_new_item'  => 'Nueva promo de regalo',
					'edit_item'     => 'Editar promo de regalo',
					'new_item'      => 'Nueva promo de regalo',
					'view_item'     => 'Ver promo de regalo',
					'search_items'  => 'Buscar promos de regalo',
					'menu_name'     => 'Promos de regalo',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-tickets-alt',
				'supports'        => array('title'),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'show_in_rest'    => false,
			)
		);
	}

	public static function add_meta_boxes(): void {
		add_meta_box('ssgift_settings', 'Configuración de la Promo de Regalo', array(__CLASS__, 'render_settings_metabox'), self::CPT, 'normal', 'high');
	}

	public static function admin_head(): void {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->post_type !== self::CPT) {
			return;
		}
		echo '<style>.ssgift-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:920px}.ssgift-grid .full{grid-column:1 / -1}.ssgift-field label{font-weight:600;display:block;margin-bottom:6px}.ssgift-hint{color:#666;font-size:12px;margin-top:4px}.ssgift-box{background:#f0f6fb;border:1px solid #c3d4e3;border-radius:4px;padding:12px;grid-column:1 / -1}</style>';
	}

	public static function admin_columns(array $columns): array {
		$out = array();
		$out['cb']      = isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />';
		$out['title']   = 'Promo';
		$out['ssgift']  = 'Sorteo / Regla';
		$out['ssgift2'] = 'Vigencia';
		$out['ssgift3'] = 'Estado';
		return $out;
	}

	public static function render_admin_column($column, $post_id): void {
		if ($column === 'ssgift') {
			$product_id = (int) get_post_meta($post_id, self::META_PRODUCT, true);
			$buy        = (int) get_post_meta($post_id, self::META_BUY, true);
			$count      = (int) get_post_meta($post_id, self::META_COUNT, true);
			$prefix     = (string) get_post_meta($post_id, self::META_PREFIX, true);
			if ($prefix === '' && $product_id > 0) {
				$prefix = self::default_prefix($product_id) . ' (auto)';
			}
			echo esc_html(($product_id ? get_the_title($product_id) : '—') . ' · compra ' . $buy . ' → regala ' . $count . ' · ' . $prefix);
		} elseif ($column === 'ssgift2') {
			$start = (string) get_post_meta($post_id, self::META_START, true);
			$end   = (string) get_post_meta($post_id, self::META_END, true);
			echo esc_html(($start ?: '—') . ' → ' . ($end ?: '—'));
		} elseif ($column === 'ssgift3') {
			$active = get_post_meta($post_id, self::META_ACTIVE, true) === 'yes';
			echo $active ? '<span style="color:#2f6b1a;font-weight:600;">Activa</span>' : '<span style="color:#999;">Inactiva</span>';
		}
	}

	public static function render_settings_metabox($post): void {
		wp_nonce_field('ssgift_save', 'ssgift_nonce');
		$product_id = (int) get_post_meta($post->ID, self::META_PRODUCT, true);
		$buy        = (int) get_post_meta($post->ID, self::META_BUY, true);
		$count      = (int) get_post_meta($post->ID, self::META_COUNT, true);
		$prefix     = (string) get_post_meta($post->ID, self::META_PREFIX, true);
		$start      = (string) get_post_meta($post->ID, self::META_START, true);
		$end        = (string) get_post_meta($post->ID, self::META_END, true);
		$badge      = (string) get_post_meta($post->ID, self::META_BADGE, true);
		$color      = (string) get_post_meta($post->ID, self::META_COLOR, true);
		$active     = get_post_meta($post->ID, self::META_ACTIVE, true) === 'yes';
		$lotteries  = self::get_lottery_products();
		?>
		<div class="ssgift-grid">
			<div class="ssgift-box">
				<p style="margin:0;"><strong>Compra X → regala Y.</strong> Los X tickets vendidos SÍ descuentan el universo del sorteo; los Y de regalo NO lo descuentan y se identifican con prefijo propio (por defecto <code>{prefijo del sorteo}R</code>). Participan del sorteo igual que los comprados.</p>
			</div>

			<div class="ssgift-field">
				<label>Sorteo</label>
				<select name="ssgift_product_id" class="regular-text">
					<option value="0">— Selecciona un sorteo —</option>
					<?php foreach ($lotteries as $id => $title) : ?>
						<option value="<?php echo (int) $id; ?>" <?php selected($product_id, (int) $id); ?>><?php echo esc_html($title); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="ssgift-hint">Producto Lottery al que aplica la promo.</p>
			</div>

			<div class="ssgift-field">
				<label>Cantidad comprada (X)</label>
				<input type="number" name="ssgift_buy" min="1" step="1" value="<?php echo (int) ($buy > 0 ? $buy : 1); ?>" class="small-text" />
				<p class="ssgift-hint">Se aplica al pack cuyo "lleva" sea ≥ este valor (gana el mayor que calce).</p>
			</div>

			<div class="ssgift-field">
				<label>Cantidad regalada (Y)</label>
				<input type="number" name="ssgift_count" min="1" step="1" value="<?php echo (int) ($count > 0 ? $count : 1); ?>" class="small-text" />
				<p class="ssgift-hint">DigiTickets de regalo que recibe por calzar la regla.</p>
			</div>

			<div class="ssgift-field">
				<label>Prefijo de regalo</label>
				<input type="text" name="ssgift_prefix" value="<?php echo esc_attr($prefix); ?>" class="regular-text" placeholder="(automático: prefijo del sorteo + R)" />
				<p class="ssgift-hint">Vacío = automático. Ej. sorteo <code>J</code> → regalos <code>JR</code> desde el máximo+1.</p>
			</div>

			<div class="ssgift-field">
				<label>Etiqueta de la tarjeta (badge)</label>
				<input type="text" name="ssgift_badge" value="<?php echo esc_attr($badge); ?>" class="regular-text" placeholder="REGALO" />
				<p class="ssgift-hint">Texto del badge superior de la tarjeta. Vacío = sin badge.</p>
			</div>

			<div class="ssgift-field">
				<label>Color de la tarjeta (hex)</label>
				<input type="text" name="ssgift_color" value="<?php echo esc_attr($color); ?>" class="regular-text" placeholder="#e91e8c" />
				<p class="ssgift-hint">Color de acento (borde/icono). Vacío = usa el color por defecto.</p>
			</div>

			<div class="ssgift-field">
				<label>Desde (opcional)</label>
				<input type="datetime-local" name="ssgift_start" value="<?php echo esc_attr(self::to_input_datetime($start)); ?>" />
			</div>

			<div class="ssgift-field">
				<label>Hasta (opcional)</label>
				<input type="datetime-local" name="ssgift_end" value="<?php echo esc_attr(self::to_input_datetime($end)); ?>" />
			</div>

			<div class="ssgift-field full">
				<label style="display:flex;align-items:center;gap:8px;">
					<input type="checkbox" name="ssgift_active" value="yes" <?php checked($active); ?> />
					Promo activa
				</label>
			</div>
		</div>
		<?php
	}

	public static function save_promo($post_id, $post): void {
		if (!isset($_POST['ssgift_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ssgift_nonce'])), 'ssgift_save')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$product_id = isset($_POST['ssgift_product_id']) ? absint($_POST['ssgift_product_id']) : 0;
		$buy        = isset($_POST['ssgift_buy']) ? max(1, absint($_POST['ssgift_buy'])) : 1;
		$count      = isset($_POST['ssgift_count']) ? max(1, absint($_POST['ssgift_count'])) : 1;
		$prefix     = isset($_POST['ssgift_prefix']) ? sanitize_text_field(wp_unslash($_POST['ssgift_prefix'])) : '';
		$start      = isset($_POST['ssgift_start']) ? sanitize_text_field(wp_unslash($_POST['ssgift_start'])) : '';
		$end        = isset($_POST['ssgift_end']) ? sanitize_text_field(wp_unslash($_POST['ssgift_end'])) : '';
		$badge      = isset($_POST['ssgift_badge']) ? sanitize_text_field(wp_unslash($_POST['ssgift_badge'])) : '';
		$color      = isset($_POST['ssgift_color']) ? sanitize_text_field(wp_unslash($_POST['ssgift_color'])) : '';
		$active     = (isset($_POST['ssgift_active']) && $_POST['ssgift_active'] === 'yes') ? 'yes' : 'no';

		update_post_meta($post_id, self::META_PRODUCT, $product_id);
		update_post_meta($post_id, self::META_BUY, $buy);
		update_post_meta($post_id, self::META_COUNT, $count);
		update_post_meta($post_id, self::META_PREFIX, $prefix);
		update_post_meta($post_id, self::META_START, self::from_input_datetime($start));
		update_post_meta($post_id, self::META_END, self::from_input_datetime($end));
		update_post_meta($post_id, self::META_BADGE, $badge);
		update_post_meta($post_id, self::META_COLOR, $color);
		update_post_meta($post_id, self::META_ACTIVE, $active);
	}

	/* ============================ Carrito / Pedido ============================ */

	/**
	 * Mint ea los regalos al agregar un pack al carrito.
	 *
	 * @param array $cart_item_data
	 * @param int   $product_id
	 * @param int   $variation_id
	 * @param int   $quantity
	 * @return array
	 */
	public static function attach_gift_to_cart_item($cart_item_data, $product_id, $variation_id = 0, $quantity = 1) {
		if (!is_array($cart_item_data)) {
			$cart_item_data = array();
		}
		if (!function_exists('lty_is_lottery_product') || !function_exists('wc_get_product')) {
			return $cart_item_data;
		}

		// Solo mintea si el cliente eligió una tarjeta de REGALO (campaign id propio),
		// no cuando elige un pack normal de Promo Engine.
		$campaign_id = isset($cart_item_data['ss_pack_campaign_id']) ? (int) $cart_item_data['ss_pack_campaign_id'] : 0;
		$promo       = self::promo_from_campaign($campaign_id, (int) $product_id);
		if ($promo <= 0) {
			return $cart_item_data;
		}

		$count = (int) get_post_meta($promo, self::META_COUNT, true);
		if ($count < 1) {
			return $cart_item_data;
		}
		$prefix = (string) get_post_meta($promo, self::META_PREFIX, true);
		$gifts  = self::mint_gift_numbers((int) $product_id, $count, $prefix);
		if (empty($gifts)) {
			return $cart_item_data;
		}

		$cart_item_data[self::CART_GIFTS] = array_values(array_map('strval', $gifts));
		$cart_item_data[self::CART_PROMO] = (int) $promo;
		return $cart_item_data;
	}

	/** ID de campaña sintético para una promo de regalo. */
	public static function campaign_id_for_promo(int $promo_id): int {
		return self::CAMPAIGN_OFFSET + max(0, $promo_id);
	}

	/** Promo de regalo a partir de un campaign id sintético (0 si no es de regalo). */
	public static function promo_id_from_campaign(int $campaign_id): int {
		return $campaign_id > self::CAMPAIGN_OFFSET ? ($campaign_id - self::CAMPAIGN_OFFSET) : 0;
	}

	/**
	 * Promo válida (de este sorteo y activa) a partir de un campaign id de regalo.
	 */
	private static function promo_from_campaign(int $campaign_id, int $product_id): int {
		$promo_id = self::promo_id_from_campaign($campaign_id);
		if ($promo_id <= 0 || get_post_type($promo_id) !== self::CPT) {
			return 0;
		}
		if ((int) get_post_meta($promo_id, self::META_PRODUCT, true) !== $product_id) {
			return 0;
		}
		if (!self::promo_is_active($promo_id)) {
			return 0;
		}
		return $promo_id;
	}

	/**
	 * Cantidad a comprar (X) para una tarjeta de regalo. 0 si no aplica.
	 * Usado por el motor de packs para resolver el pack de una tarjeta de regalo.
	 */
	public static function pack_quantity_for_campaign(int $campaign_id, int $product_id): int {
		$promo_id = self::promo_from_campaign($campaign_id, $product_id);
		if ($promo_id <= 0) {
			return 0;
		}
		return max(1, (int) get_post_meta($promo_id, self::META_BUY, true));
	}

	/** @return int[] Promos de regalo activas de un sorteo. */
	private static function active_promos_for_product(int $product_id): array {
		if ($product_id <= 0) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_PRODUCT,
				'meta_value'     => $product_id,
			)
		);
		$out = array();
		foreach ($posts as $promo_id) {
			if (self::promo_is_active((int) $promo_id)) {
				$out[] = (int) $promo_id;
			}
		}
		return $out;
	}

	/**
	 * Inyecta las tarjetas de regalo en el selector de packs (misma estética).
	 * Corre al final (prioridad 99) para no ser pisado por otras superficies.
	 *
	 * @param array $config
	 * @param int   $product_id
	 * @return array
	 */
	public static function filter_packs_config($config, $product_id = 0) {
		if (!is_array($config)) {
			return $config;
		}
		$pid = isset($config['productId']) ? (int) $config['productId'] : (int) $product_id;
		if ($pid <= 0) {
			return $config;
		}
		$promos = self::active_promos_for_product($pid);
		if (empty($promos)) {
			return $config;
		}
		if (!isset($config['campaigns']) || !is_array($config['campaigns'])) {
			$config['campaigns'] = array();
		}

		$unit = 0.0;
		if (function_exists('wc_get_product')) {
			$product = wc_get_product($pid);
			if ($product) {
				$unit = (float) wc_get_price_to_display($product);
			}
		}

		$existing = array();
		foreach ($config['campaigns'] as $row) {
			if (isset($row['id'])) {
				$existing[(int) $row['id']] = true;
			}
		}

		foreach ($promos as $promo_id) {
			$cid = self::campaign_id_for_promo((int) $promo_id);
			if (isset($existing[$cid])) {
				continue;
			}
			$buy  = max(1, (int) get_post_meta($promo_id, self::META_BUY, true));
			$gift = max(1, (int) get_post_meta($promo_id, self::META_COUNT, true));
			$config['campaigns'][] = array(
				'id'         => $cid,
				'title'      => '',
				'buy'        => $buy,
				'pay'        => $buy,
				'badge'      => (string) get_post_meta($promo_id, self::META_BADGE, true),
				'color'      => (string) get_post_meta($promo_id, self::META_COLOR, true),
				'sort'       => 100,
				'unit_price' => $unit,
				'list_price' => $unit * $buy,
				'pack_price' => $unit * $buy,
				'savings'    => 0.0,
				'gift'       => $gift,
			);
		}

		usort(
			$config['campaigns'],
			static function ($a, $b) {
				$sa = isset($a['sort']) ? (int) $a['sort'] : 10;
				$sb = isset($b['sort']) ? (int) $b['sort'] : 10;
				if ($sa === $sb) {
					return ((int) (isset($a['buy']) ? $a['buy'] : 1)) <=> ((int) (isset($b['buy']) ? $b['buy'] : 1));
				}
				return $sa <=> $sb;
			}
		);

		return $config;
	}

	/**
	 * Añade los DigiTickets de regalo a la meta visible del carrito/checkout.
	 * Si ya existe una fila de tickets, le anexa los regalos; si no, crea una.
	 *
	 * @param array $item_data
	 * @param array $cart_item
	 * @return array
	 */
	public static function append_gift_to_item_data($item_data, $cart_item) {
		if (!is_array($item_data) || !is_array($cart_item)) {
			return $item_data;
		}
		if (empty($cart_item[self::CART_GIFTS]) || !is_array($cart_item[self::CART_GIFTS])) {
			return $item_data;
		}
		$gifts = array_values(array_filter(array_map('strval', $cart_item[self::CART_GIFTS])));
		if (empty($gifts)) {
			return $item_data;
		}
		$gift_text = implode(', ', $gifts);

		foreach ($item_data as $index => $row) {
			$key = isset($row['key']) ? (string) $row['key'] : '';
			if ($key !== '' && (stripos($key, 'ticket') !== false)) {
				$value = isset($row['value']) ? $row['value'] : (isset($row['display']) ? $row['display'] : '');
				if (is_array($value)) {
					$value = implode(', ', array_map('strval', $value));
				}
				$combined                 = trim((string) $value, " \t\n\r\0\x0B,");
				$combined                 = $combined !== '' ? ($combined . ', ' . $gift_text) : $gift_text;
				$item_data[$index]['value']   = $combined;
				$item_data[$index]['display'] = $combined;
				return $item_data;
			}
		}

		$item_data[] = array(
			'key'     => 'Tus DigiTickets',
			'value'   => $gift_text,
			'display' => $gift_text,
		);
		return $item_data;
	}

	/**
	 * Persiste los regalos en el ítem del pedido y su etiqueta visible.
	 */
	public static function persist_gift_on_order_item($item, $cart_item_key, $values, $order): void {
		if (!is_array($values) || empty($values[self::CART_GIFTS]) || !is_array($values[self::CART_GIFTS])) {
			return;
		}
		$gifts = array_values(array_filter(array_map('strval', $values[self::CART_GIFTS])));
		if (empty($gifts)) {
			return;
		}
		$item->add_meta_data(self::ITEM_GIFTS, $gifts);
		if (!empty($values[self::CART_PROMO])) {
			$item->add_meta_data(self::ITEM_PROMO, absint($values[self::CART_PROMO]));
		}
		// Si LTY ya decidió mostrar la etiqueta de tickets, la completamos incluyendo los regalos.
		self::sync_visible_label($item, true);
	}

	public static function on_order_created($order_id): void {
		self::ensure_gift_tickets((int) $order_id, false);
	}

	public static function on_store_api_order_processed($order): void {
		$order_id = is_object($order) && method_exists($order, 'get_id') ? (int) $order->get_id() : (int) $order;
		self::ensure_gift_tickets($order_id, false);
	}

	public static function on_order_paid($order_id): void {
		self::ensure_gift_tickets((int) $order_id, true);
	}

	public static function on_order_void($order_id): void {
		self::remove_gift_tickets((int) $order_id);
	}

	/**
	 * Si se descarga el DigiTicket de un pedido pagado cuyos regalos no se crearon
	 * (p. ej. pedido anterior al deploy), los crea/confirma en ese momento.
	 */
	public static function maybe_backfill_on_download(): void {
		if (is_admin() || !isset($_GET['action'], $_GET['ss_key'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if (sanitize_text_field(wp_unslash((string) $_GET['action'])) !== 'ss-download') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if (!function_exists('lty_decode') || !function_exists('wc_get_order')) {
			return;
		}
		$decoded = json_decode((string) lty_decode(sanitize_text_field(wp_unslash((string) $_GET['ss_key'])))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!is_object($decoded) || empty($decoded->lty_order_id)) {
			return;
		}
		$order = wc_get_order((int) $decoded->lty_order_id);
		if (!$order instanceof WC_Order) {
			return;
		}
		if (!$order->is_paid()) {
			return;
		}
		self::ensure_gift_tickets((int) $order->get_id(), true);
	}

	/* ============================ Motor de numeración ============================ */

	/**
	 * Devuelve el ID de la promo activa que calza para el producto y la cantidad comprada.
	 */
	private static function find_promo_for_product(int $product_id, int $qty): int {
		$posts = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_PRODUCT,
				'meta_value'     => $product_id,
			)
		);
		if (empty($posts)) {
			return 0;
		}

		$best      = 0;
		$best_buy  = -1;
		$any       = 0;
		foreach ($posts as $promo_id) {
			if (!self::promo_is_active((int) $promo_id)) {
				continue;
			}
			$buy = (int) get_post_meta($promo_id, self::META_BUY, true);
			if ($buy > 0 && $qty >= $buy) {
				if ($buy > $best_buy) {
					$best_buy = $buy;
					$best     = (int) $promo_id;
				}
			} elseif ($buy <= 0 && !$any) {
				$any = (int) $promo_id;
			}
		}

		return $best > 0 ? $best : $any;
	}

	private static function promo_is_active(int $promo_id): bool {
		if (get_post_status($promo_id) !== 'publish') {
			return false;
		}
		if (get_post_meta($promo_id, self::META_ACTIVE, true) !== 'yes') {
			return false;
		}
		$now   = current_time('timestamp');
		$start = strtotime((string) get_post_meta($promo_id, self::META_START, true));
		$end   = strtotime((string) get_post_meta($promo_id, self::META_END, true));
		if ($start && $now < $start) {
			return false;
		}
		if ($end && $now > $end) {
			return false;
		}
		return true;
	}

	/**
	 * Mintea N números de regalo únicos y globalmente no repetidos.
	 *
	 * @return string[]
	 */
	public static function mint_gift_numbers(int $product_id, int $count, string $prefix = ''): array {
		$count = (int) $count;
		if ($count < 1) {
			return array();
		}
		$prefix = trim($prefix);
		if ($prefix === '') {
			$prefix = self::default_prefix($product_id);
		}
		if ($prefix === '') {
			return array();
		}

		global $wpdb;
		$lock = self::LOCK_PFX . $product_id;
		$got  = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_WAIT));
		if ($got !== 1) {
			return array();
		}

		try {
			$key  = self::COUNTER_PFX . md5($prefix);
			$next = (int) get_post_meta($product_id, $key, true);
			if ($next <= 0) {
				$next = self::base_start_number($product_id);
			}

			$numbers = array();
			$guard   = 0;
			$limit   = ($count * 50) + 1000;
			while (count($numbers) < $count && $guard < $limit) {
				$guard++;
				$candidate = $prefix . $next;
				$next++;
				if ($next < 1) {
					$next = 1;
				}
				if (self::ticket_number_exists($candidate)) {
					continue;
				}
				$numbers[] = $candidate;
			}

			update_post_meta($product_id, $key, $next);
			return $numbers;
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}

	private static function base_start_number(int $product_id): int {
		$start = (int) get_post_meta($product_id, '_lty_ticket_start_number', true);
		if ($start <= 0) {
			$start = 1;
		}
		$max = (int) get_post_meta($product_id, '_lty_maximum_tickets', true);
		return $max > 0 ? ($start + $max) : $start;
	}

	private static function ticket_number_exists(string $number): bool {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::META_NUMBER,
				$number
			)
		);
		return !empty($found);
	}

	public static function default_prefix(int $product_id): string {
		$prefix = self::product_prefix($product_id);
		return $prefix !== '' ? ($prefix . 'R') : 'REG';
	}

	private static function product_prefix(int $product_id): string {
		$prefix = (string) get_post_meta($product_id, '_lty_ticket_prefix', true);
		if ($prefix === '') {
			$prefix = (string) get_post_meta($product_id, '_lty_tickets_prefix', true);
		}
		if ($prefix === '' && function_exists('wc_get_product')) {
			$product = wc_get_product($product_id);
			if ($product && method_exists($product, 'get_lty_ticket_prefix')) {
				$prefix = (string) $product->get_lty_ticket_prefix();
			}
		}
		return $prefix;
	}

	/* ============================ Creación / confirmación ============================ */

	public static function ensure_gift_tickets(int $order_id, bool $confirm): void {
		if ($order_id <= 0 || !function_exists('lty_create_new_lottery_ticket') || !function_exists('wc_get_order')) {
			return;
		}
		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}

		$did_work = false;
		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product')) {
				continue;
			}
			$product = $item->get_product();
			if (!$product || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($product)) {
				continue;
			}
			$gifts = $item->get_meta(self::ITEM_GIFTS);
			if (!is_array($gifts) || empty($gifts)) {
				continue;
			}
			$promo  = (int) $item->get_meta(self::ITEM_PROMO);
			$parent = self::lottery_parent_id($product);

			$ids = array();
			foreach (array_filter(array_map('strval', $gifts)) as $number) {
				$existing = self::find_ticket_post($order_id, $number);
				if ($existing) {
					$ids[] = $existing;
					continue;
				}
				$args = self::ticket_args($order, $product, $number);
				$new  = lty_create_new_lottery_ticket(
					$args,
					array(
						'post_parent' => $parent,
						'post_status' => $confirm ? 'lty_ticket_buyer' : 'lty_ticket_pending',
					)
				);
				if ($new && !is_wp_error($new)) {
					$new = (int) $new;
					$ids[] = $new;
					update_post_meta($new, self::TICKET_FLAG, '1');
					if ($promo > 0) {
						update_post_meta($new, self::TICKET_PROMO, $promo);
					}
				}
			}

			if ($confirm && !empty($ids)) {
				self::confirm_tickets($ids);
				self::sync_visible_label($item, false);
				$item->save();
				$did_work = true;
			}
		}

		if ($did_work) {
			$order->save();
		}
	}

	private static function remove_gift_tickets(int $order_id): void {
		$ids = self::gift_ticket_ids_for_order($order_id);
		if (empty($ids)) {
			return;
		}
		foreach ($ids as $ticket_id) {
			$ticket = function_exists('lty_get_lottery_ticket') ? lty_get_lottery_ticket($ticket_id) : null;
			if (is_object($ticket) && method_exists($ticket, 'has_status') && $ticket->has_status('lty_ticket_buyer')) {
				$product = method_exists($ticket, 'get_product') ? $ticket->get_product() : null;
				if ($product && method_exists($product, 'get_lty_ticket_count') && method_exists($product, 'update_post_meta')) {
					$current = (int) $product->get_lty_ticket_count();
					$product->update_post_meta('lty_ticket_count', max(0, $current - 1));
				}
			}
			wp_delete_post($ticket_id, true);
		}
	}

	/** @param int[] $ids */
	private static function confirm_tickets(array $ids): void {
		if (!function_exists('lty_get_lottery_ticket')) {
			return;
		}
		foreach ($ids as $ticket_id) {
			$ticket = lty_get_lottery_ticket((int) $ticket_id);
			if (!is_object($ticket) || !method_exists($ticket, 'has_status') || $ticket->has_status('lty_ticket_buyer')) {
				continue;
			}
			$ticket->update_status('lty_ticket_buyer');
			$product = method_exists($ticket, 'get_product') ? $ticket->get_product() : null;
			if ($product && method_exists($product, 'get_lty_ticket_count') && method_exists($product, 'update_post_meta')) {
				$current = (int) $product->get_lty_ticket_count();
				$product->update_post_meta('lty_ticket_count', $current + 1);
			}
		}
	}

	/** @return int[] */
	private static function gift_ticket_ids_for_order(int $order_id): array {
		if ($order_id <= 0) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => 'lty_lottery_ticket',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array('key' => self::TICKET_FLAG, 'value' => '1'),
					array('key' => self::META_ORDER, 'value' => (string) $order_id),
				),
			)
		);
		return array_map('intval', (array) $posts);
	}

	private static function find_ticket_post(int $order_id, string $number): int {
		if ($order_id <= 0 || $number === '') {
			return 0;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'lty_lottery_ticket',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array('key' => self::META_ORDER, 'value' => (string) $order_id),
					array('key' => self::META_NUMBER, 'value' => $number),
				),
			)
		);
		return !empty($posts[0]) ? (int) $posts[0] : 0;
	}

	/** @return array */
	private static function ticket_args(WC_Order $order, $product, string $number): array {
		$user_id = (int) $order->get_user_id();
		$user    = $user_id > 0 ? get_user_by('ID', $user_id) : false;
		return array(
			'lty_user_id'      => ($user instanceof WP_User) ? (int) $user->ID : 0,
			'lty_product_id'   => (int) $product->get_id(),
			'lty_amount'       => $product->get_price(),
			'lty_user_name'    => ($user instanceof WP_User) ? $user->user_login : __('Guest', 'lottery-for-woocommerce'),
			'lty_user_email'   => ($user instanceof WP_User) ? $user->user_email : $order->get_billing_email(),
			'lty_currency'     => $order->get_currency(),
			'lty_order_id'     => (int) $order->get_id(),
			'lty_answer'       => '',
			'lty_answers'      => array(),
			'lty_valid_answer' => 'no',
			'lty_ip_address'   => $order->get_customer_ip_address() ?: (function_exists('lty_get_ip_address') ? lty_get_ip_address() : ''),
			'lty_ticket_number' => $number,
		);
	}

	private static function lottery_parent_id($product): int {
		if (is_object($product) && method_exists($product, 'get_lottery_id')) {
			$lottery_id = (int) $product->get_lottery_id();
			if ($lottery_id > 0) {
				return $lottery_id;
			}
		}
		return is_object($product) && method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
	}

	/**
	 * Etiqueta visible del ítem = pagados + regalos.
	 */
	private static function sync_visible_label($item, bool $only_if_exists): void {
		if (!is_object($item) || !method_exists($item, 'meta_exists')) {
			return;
		}
		$label = function_exists('lty_get_order_item_ticket_number_name') ? lty_get_order_item_ticket_number_name() : 'Ticket Number( s )';
		if ($only_if_exists && !$item->meta_exists($label)) {
			return;
		}
		$paid  = $item->get_meta('_lty_lottery_tickets');
		$gifts = $item->get_meta(self::ITEM_GIFTS);
		$paid  = is_array($paid) ? array_map('strval', $paid) : array();
		$gifts = is_array($gifts) ? array_map('strval', $gifts) : array();
		$all   = array_values(array_unique(array_merge($paid, $gifts)));
		if (empty($all)) {
			return;
		}
		$item->update_meta_data($label, '<span class="notranslate">' . implode(', ', array_map('esc_html', $all)) . '</span>');
	}

	/* ============================ API pública (reportes) ============================ */

	/**
	 * Cuenta DigiTickets de regalo de un sorteo (por defecto solo confirmados/pagados).
	 */
	public static function count_gift_tickets_for_product(int $product_id, bool $buyer_only = true): int {
		if ($product_id <= 0) {
			return 0;
		}
		$parent = self::lottery_parent_id(function_exists('wc_get_product') ? wc_get_product($product_id) : null);
		if ($parent <= 0) {
			$parent = $product_id;
		}
		$args = array(
			'post_type'      => 'lty_lottery_ticket',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_parent'    => $parent,
			'meta_key'       => self::TICKET_FLAG,
			'meta_value'     => '1',
		);
		$args['post_status'] = $buyer_only ? array('lty_ticket_buyer', 'lty_ticket_winner') : 'any';
		return count(get_posts($args));
	}

	/**
	 * Filas de regalos por sorteo y período (para el CIV).
	 *
	 * @return array<int, array{product_id:int,title:string,gifts:int}>
	 */
	public static function gift_rows_for_range(DateTime $start, DateTime $end): array {
		global $wpdb;
		$utc  = new DateTimeZone('UTC');
		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');

		$posts  = $wpdb->posts;
		$pm     = $wpdb->postmeta;
		$sql    = "SELECT p.post_parent AS product_id, COUNT(*) AS gifts
			FROM {$posts} p
			INNER JOIN {$pm} flag ON flag.post_id = p.ID AND flag.meta_key = %s AND flag.meta_value = '1'
			WHERE p.post_type = 'lty_lottery_ticket'
			AND p.post_status IN ('lty_ticket_buyer','lty_ticket_winner')
			AND p.post_date_gmt >= %s AND p.post_date_gmt <= %s
			GROUP BY p.post_parent
			ORDER BY gifts DESC";
		$rows   = $wpdb->get_results($wpdb->prepare($sql, self::TICKET_FLAG, $from, $to));
		$out    = array();
		foreach ((array) $rows as $row) {
			$pid   = (int) $row->product_id;
			$out[] = array(
				'product_id' => $pid,
				'title'      => $pid ? get_the_title($pid) : '—',
				'gifts'      => (int) $row->gifts,
			);
		}
		return $out;
	}

	/* ============================ Utilidades ============================ */

	/** @return array<int,string> */
	private static function get_lottery_products(): array {
		$out = array();
		if (function_exists('wc_get_products')) {
			$ids = wc_get_products(
				array(
					'type'   => 'lottery',
					'status' => array('publish', 'draft'),
					'limit'  => -1,
					'return' => 'ids',
				)
			);
			foreach ((array) $ids as $id) {
				$out[(int) $id] = get_the_title((int) $id);
			}
		}
		if (empty($out)) {
			global $wpdb;
			$ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lty_maximum_tickets'");
			foreach ((array) $ids as $id) {
				$out[(int) $id] = get_the_title((int) $id);
			}
		}
		asort($out);
		return $out;
	}

	private static function to_input_datetime(string $value): string {
		$ts = strtotime($value);
		return $ts ? gmdate('Y-m-d\TH:i', $ts) : '';
	}

	private static function from_input_datetime(string $value): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		$ts = strtotime($value);
		return $ts ? date('Y-m-d H:i:s', $ts) : '';
	}
}

SorteoSeguro_Gift_Tickets::init();
