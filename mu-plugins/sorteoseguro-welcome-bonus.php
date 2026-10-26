<?php
/**
 * Plugin Name: Sorteo Seguro – Bono bienvenida (1ª → 2ª compra)
 * Description: Detecta la primera compra pagada de un cliente, le asigna un bono automático para su segunda compra (monto fijo o %, gasto mínimo, sorteos válidos y duración configurables), lo muestra en la billetera (/mi-cuenta/wallet/) y en el badge del header, y envía un correo configurable desde el admin.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: rollback = borrar este archivo. No modifica Promo Engine, Lottery, packs ni checkout.
 * El descuento se aplica como fee negativo (igual que Promo Engine); Mercado Pago lo absorbe vía sorteoseguro-mp-pack-preference.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Welcome_Bonus {

	const VERSION      = '1.0.0';
	const LOG_SOURCE   = 'sorteoseguro-welcome-bonus';
	const FEE_PREFIX   = 'Bono de bienvenida';
	const OPT_ENABLED  = 'ss_wb_enabled';
	const OPT_TYPE     = 'ss_wb_type';
	const OPT_AMOUNT   = 'ss_wb_amount';
	const OPT_MIN      = 'ss_wb_min_total';
	const OPT_HOURS    = 'ss_wb_hours';
	const OPT_PRODUCTS = 'ss_wb_products';
	const OPT_SCOPE    = 'ss_wb_scope';
	const OPT_CUTOFF   = 'ss_wb_cutoff';
	const OPT_MAP      = 'ss_wb_by_email';
	const OPT_LOG      = 'ss_wb_log';
	const OPT_TPL_SUBJECT   = 'ss_wb_tpl_subject';
	const OPT_TPL_PREHEADER = 'ss_wb_tpl_preheader';
	const OPT_TPL_HEADING   = 'ss_wb_tpl_heading';
	const OPT_TPL_BODY      = 'ss_wb_tpl_body';
	const ADMIN_TEST   = 'ss_wb_send_test';
	const LOG_MAX      = 500;
	const MAP_MAX      = 20000;
	const META_GRANTED = '_ss_wb_granted';
	const META_APPLIED = '_ss_wb_bonus_applied';
	const META_APPLIED_EMAIL = '_ss_wb_bonus_email';
	const META_CONSUMED = '_ss_wb_consumed';

	/** Evita reentrada en el cálculo de fees. */
	private static $applying = false;

	public static function init(): void {
		add_action('init', array(__CLASS__, 'ensure_cutoff'), 5);
		add_action('admin_init', array(__CLASS__, 'ensure_defaults'), 5);

		// Otorgamiento al pago efectivo.
		add_action('woocommerce_order_status_processing', array(__CLASS__, 'on_paid_order'), 30, 1);
		add_action('woocommerce_order_status_completed', array(__CLASS__, 'on_paid_order'), 30, 1);
		add_action('woocommerce_payment_complete', array(__CLASS__, 'on_paid_order'), 30, 1);

		// Registrar si un pedido usó el bono (fee presente).
		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'capture_applied'), 20, 3);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'capture_applied_api'), 20, 1);

		// Aplicar el bono en el carrito (después de Promo Engine = 20).
		add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'apply_bonus_fee'), 21, 1);

		// Billetera y badge.
		add_action('woocommerce_account_wallet_endpoint', array(__CLASS__, 'render_wallet_section'));
		add_action('wp_footer', array(__CLASS__, 'render_header_badge'), 20);

		// Migración de bono por correo → usuario al registrarse.
		add_action('user_register', array(__CLASS__, 'maybe_link_on_register'), 20, 1);

		// Admin.
		add_action('admin_menu', array(__CLASS__, 'register_admin_menu'), 62);
		add_action('admin_post_' . self::ADMIN_TEST, array(__CLASS__, 'handle_send_test'));
	}

	/* ---------------------------------------------------------------------
	 * Opciones y defaults
	 * ------------------------------------------------------------------- */

	public static function ensure_cutoff(): void {
		if (get_option(self::OPT_CUTOFF, '') === '') {
			update_option(self::OPT_CUTOFF, gmdate('Y-m-d H:i:s'), false);
		}
	}

	public static function ensure_defaults(): void {
		self::ensure_cutoff();
		if (get_option(self::OPT_ENABLED, null) === null) {
			update_option(self::OPT_ENABLED, 'no', false);
		}
		if (get_option(self::OPT_TYPE, null) === null) {
			update_option(self::OPT_TYPE, 'fixed', false);
		}
		if (get_option(self::OPT_AMOUNT, null) === null) {
			update_option(self::OPT_AMOUNT, 60000, false);
		}
		if (get_option(self::OPT_MIN, null) === null) {
			update_option(self::OPT_MIN, 80000, false);
		}
		if (get_option(self::OPT_HOURS, null) === null) {
			update_option(self::OPT_HOURS, 72, false);
		}
		if (get_option(self::OPT_PRODUCTS, null) === null) {
			update_option(self::OPT_PRODUCTS, array(), false);
		}
		if (get_option(self::OPT_SCOPE, null) === null) {
			update_option(self::OPT_SCOPE, 'all', false);
		}
		foreach (self::template_defaults() as $k => $v) {
			if (get_option($k, null) === null) {
				update_option($k, $v, false);
			}
		}
	}

	public static function is_enabled(): bool {
		return get_option(self::OPT_ENABLED, 'no') === 'yes';
	}

	public static function get_type(): string {
		$t = (string) get_option(self::OPT_TYPE, 'fixed');
		return in_array($t, array('fixed', 'percent'), true) ? $t : 'fixed';
	}

	public static function get_amount(): float {
		return max(0.0, (float) get_option(self::OPT_AMOUNT, 0));
	}

	public static function get_min_total(): float {
		return max(0.0, (float) get_option(self::OPT_MIN, 0));
	}

	public static function get_hours(): int {
		return max(1, min(8760, (int) get_option(self::OPT_HOURS, 72)));
	}

	/** @return int[] */
	public static function get_products(): array {
		$raw = get_option(self::OPT_PRODUCTS, array());
		if (!is_array($raw)) {
			return array();
		}
		return array_values(array_filter(array_map('absint', $raw)));
	}

	/**
	 * Alcance del bono:
	 *  - all          = liberado (cualquier sorteo)
	 *  - same_product = solo el mismo sorteo de la primera compra
	 *  - same_category= solo sorteos de la misma categoría de la primera compra
	 *  - custom       = sorteos específicos marcados en el admin
	 */
	public static function get_scope(): string {
		$s = (string) get_option(self::OPT_SCOPE, 'all');
		return in_array($s, array('all', 'same_product', 'same_category', 'custom'), true) ? $s : 'all';
	}

	public static function template_defaults(): array {
		$body = '<p style="margin:0 0 14px;font-size:16px;color:#1d2327;">Hola {nombre},</p>'
			. '<p style="margin:0 0 14px;font-size:15px;color:#3c434a;line-height:1.65;">¡Gracias por tu primera compra (pedido #{pedido})! Como regalo de bienvenida, te dejamos un bono de <strong>{monto}</strong> para tu próxima compra.</p>'
			. '<p style="margin:0 0 14px;font-size:15px;color:#3c434a;line-height:1.65;">Se aplicará <strong>automáticamente</strong> al pagar, siempre que el total a pagar sea de al menos <strong>{minimo}</strong>. Válido para {sorteos}.</p>'
			. '<p style="margin:0 0 14px;font-size:15px;color:#3c434a;line-height:1.65;">Tu bono estará disponible hasta el <strong>{vencimiento}</strong>. Lo encontrarás siempre en tu billetera de bonificaciones.</p>'
			. '<p style="text-align:center;margin:22px 0;"><a href="{url}" style="display:inline-block;background:#6b3bb8;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;">🎁 VER MI BONO EN LA BILLETERA</a></p>'
			. '<p style="margin:0 0 14px;font-size:14px;color:#6b7280;line-height:1.6;">Si tienes dudas, escríbenos a contacto@sorteoseguro.cl y te ayudamos.</p>'
			. '<p style="margin:0;font-size:13px;color:#8a8f98;line-height:1.6;">📧 contacto@sorteoseguro.cl<br />🌐 www.sorteoseguro.cl<br />Equipo Sorteo Seguro — Transparencia, seguridad y confianza en cada participación.</p>';

		return array(
			self::OPT_TPL_SUBJECT   => '🎁 Tu bono de bienvenida te espera — pedido #{pedido}',
			self::OPT_TPL_PREHEADER => 'Te regalamos {monto} para tu próxima compra. Válido hasta {vencimiento}.',
			self::OPT_TPL_HEADING   => '🎁 Tenemos un bono para tu próxima compra',
			self::OPT_TPL_BODY      => $body,
		);
	}

	public static function get_template(): array {
		$d = self::template_defaults();
		return array(
			'subject'   => (string) get_option(self::OPT_TPL_SUBJECT, $d[self::OPT_TPL_SUBJECT]),
			'preheader' => (string) get_option(self::OPT_TPL_PREHEADER, $d[self::OPT_TPL_PREHEADER]),
			'heading'   => (string) get_option(self::OPT_TPL_HEADING, $d[self::OPT_TPL_HEADING]),
			'body'      => (string) get_option(self::OPT_TPL_BODY, $d[self::OPT_TPL_BODY]),
		);
	}

	/* ---------------------------------------------------------------------
	 * Datos del bono (mapa por correo)
	 * ------------------------------------------------------------------- */

	private static function normalize_email(string $email): string {
		return strtolower(trim($email));
	}

	private static function email_key(string $email): string {
		return md5(self::normalize_email($email));
	}

	private static function get_map(): array {
		$map = get_option(self::OPT_MAP, array());
		return is_array($map) ? $map : array();
	}

	private static function save_map(array $map): void {
		if (count($map) > self::MAP_MAX) {
			// Conservar las entradas más recientes.
			$map = array_slice($map, -self::MAP_MAX, null, true);
		}
		update_option(self::OPT_MAP, $map, false);
	}

	/** Bono crudo (sin importar estado) para un correo. */
	public static function get_raw_bonus(string $email): ?array {
		$email = self::normalize_email($email);
		if ($email === '' || !is_email($email)) {
			return null;
		}
		$map = self::get_map();
		$key = self::email_key($email);
		return isset($map[$key]) && is_array($map[$key]) ? $map[$key] : null;
	}

	/** Bono vigente (no consumido y no expirado). */
	public static function get_active_bonus(string $email): ?array {
		$b = self::get_raw_bonus($email);
		if (!$b) {
			return null;
		}
		if (!empty($b['consumed_order'])) {
			return null;
		}
		if (!empty($b['expires_ts']) && time() > (int) $b['expires_ts']) {
			return null;
		}
		return $b;
	}

	private static function store_bonus(string $email, array $bonus): void {
		$email = self::normalize_email($email);
		$map   = self::get_map();
		$map[ self::email_key($email) ] = $bonus;
		self::save_map($map);
	}

	private static function mark_consumed(string $email, int $order_id): void {
		$email = self::normalize_email($email);
		if ($email === '') {
			return;
		}
		$map = self::get_map();
		$key = self::email_key($email);
		if (!isset($map[$key]) || !is_array($map[$key])) {
			return;
		}
		if (!empty($map[$key]['consumed_order'])) {
			return;
		}
		$map[$key]['consumed_order'] = $order_id;
		$map[$key]['consumed_at']    = gmdate('Y-m-d H:i:s');
		self::save_map($map);
		self::append_log(array('event' => 'consumed', 'email' => $email, 'order_id' => $order_id));
	}

	/* ---------------------------------------------------------------------
	 * Otorgamiento
	 * ------------------------------------------------------------------- */

	public static function on_paid_order($order_id): void {
		$order = wc_get_order(absint($order_id));
		if (!$order instanceof WC_Order) {
			return;
		}

		// Consumir el bono si este pedido lo usó.
		self::consume_if_applied($order);

		if (!self::is_enabled()) {
			return;
		}
		if (!in_array($order->get_status(), array('processing', 'completed'), true)) {
			return;
		}
		if ($order->get_meta(self::META_GRANTED) !== '') {
			return;
		}

		$created = $order->get_date_created();
		$cutoff  = (string) get_option(self::OPT_CUTOFF, '');
		if ($created && $cutoff !== '') {
			$cutoff_ts = strtotime($cutoff . ' UTC');
			if ($cutoff_ts && $created->getTimestamp() < $cutoff_ts) {
				return;
			}
		}

		$email = self::normalize_email((string) $order->get_billing_email());
		if ($email === '' || !is_email($email)) {
			$order->update_meta_data(self::META_GRANTED, 'no_email');
			$order->save();
			return;
		}

		// Una sola vez por cliente: si ya existe cualquier bono para este correo, no se otorga de nuevo.
		if (self::get_raw_bonus($email) !== null) {
			$order->update_meta_data(self::META_GRANTED, 'exists');
			$order->save();
			return;
		}

		if (!self::is_first_purchase($order, $email)) {
			$order->update_meta_data(self::META_GRANTED, 'not_first');
			$order->save();
			return;
		}

		$bonus = self::build_bonus($email, $order);
		self::store_bonus($email, $bonus);

		$order->update_meta_data(self::META_GRANTED, 'yes');
		$order->add_order_note(
			sprintf(
				'Sorteo Seguro: bono de bienvenida otorgado (%s; mínimo %s; válido hasta %s).',
				self::format_amount_text((float) $bonus['amount'], (string) $bonus['type']),
				function_exists('wc_price') ? wp_strip_all_tags(wc_price((float) $bonus['min_total'])) : ('$' . (float) $bonus['min_total']),
				wp_date('Y-m-d H:i', (int) $bonus['expires_ts'])
			)
		);
		$order->save();

		self::append_log(array('event' => 'granted', 'email' => $email, 'order_id' => (int) $order->get_id()));

		self::send_bonus_email($email, $bonus, $order);
	}

	/**
	 * ¿Es la primera compra pagada del cliente? (excluyendo el pedido actual).
	 */
	private static function is_first_purchase(WC_Order $order, string $email): bool {
		if (!function_exists('wc_get_orders')) {
			return false;
		}

		$args = array(
			'status'  => array('processing', 'completed'),
			'type'    => 'shop_order',
			'limit'   => 1,
			'return'  => 'ids',
			'exclude' => array((int) $order->get_id()),
		);

		$customer_id = (int) $order->get_customer_id();
		if ($customer_id > 0) {
			$found = wc_get_orders(array_merge($args, array('customer_id' => $customer_id)));
			if (!empty($found)) {
				return false;
			}
		}

		if ($email !== '' && is_email($email)) {
			$found = wc_get_orders(array_merge($args, array('billing_email' => $email)));
			if (!empty($found)) {
				return false;
			}
		}

		return true;
	}

	private static function build_bonus(string $email, WC_Order $order): array {
		$scope          = self::get_scope();
		$order_products = self::order_lottery_product_ids($order);
		$products       = array();
		$categories     = array();
		$category_names = array();

		if ($scope === 'custom') {
			$products = self::get_products();
		} elseif ($scope === 'same_product') {
			$products = $order_products;
			if (!$products) {
				$scope = 'all'; // Sin sorteos en el pedido: queda liberado.
			}
		} elseif ($scope === 'same_category') {
			$categories     = self::categories_for_products($order_products);
			$category_names = self::category_names($categories);
			if (!$categories) {
				$scope = 'all';
			}
		}

		return array(
			'email'          => $email,
			'type'           => self::get_type(),
			'amount'         => self::get_amount(),
			'min_total'      => self::get_min_total(),
			'scope'          => $scope,
			'products'       => $products,
			'categories'     => $categories,
			'category_names' => $category_names,
			'hours'          => self::get_hours(),
			'granted_order'  => (int) $order->get_id(),
			'granted_at'     => gmdate('Y-m-d H:i:s'),
			'expires_ts'     => time() + (self::get_hours() * HOUR_IN_SECONDS),
			'consumed_order' => 0,
			'consumed_at'    => '',
		);
	}

	/** Productos lottery (sorteos) de un pedido. @return int[] */
	private static function order_lottery_product_ids(WC_Order $order): array {
		$out = array();
		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product')) {
				continue;
			}
			$product = $item->get_product();
			if (function_exists('lty_is_lottery_product') && (!$product || !lty_is_lottery_product($product))) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ($pid > 0) {
				$out[ $pid ] = $pid;
			}
		}
		return array_values($out);
	}

	/** Categorías (product_cat) de un conjunto de productos. @return int[] */
	private static function categories_for_products(array $product_ids): array {
		if (!$product_ids || !function_exists('wc_get_product_term_ids')) {
			return array();
		}
		$out = array();
		foreach ($product_ids as $pid) {
			$terms = wc_get_product_term_ids((int) $pid, 'product_cat');
			foreach ((array) $terms as $tid) {
				$tid = absint($tid);
				if ($tid > 0) {
					$out[ $tid ] = $tid;
				}
			}
		}
		return array_values($out);
	}

	/** @param int[] $term_ids @return string[] */
	private static function category_names(array $term_ids): array {
		$out = array();
		foreach ($term_ids as $tid) {
			$term = get_term((int) $tid, 'product_cat');
			if ($term && !is_wp_error($term)) {
				$out[] = (string) $term->name;
			}
		}
		return $out;
	}

	private static function consume_if_applied(WC_Order $order): void {
		if ($order->get_meta(self::META_APPLIED) !== 'yes') {
			return;
		}
		if ($order->get_meta(self::META_CONSUMED) === 'yes') {
			return;
		}
		$email = self::normalize_email((string) $order->get_meta(self::META_APPLIED_EMAIL));
		if ($email === '') {
			$email = self::normalize_email((string) $order->get_billing_email());
		}
		if ($email !== '') {
			self::mark_consumed($email, (int) $order->get_id());
		}
		$order->update_meta_data(self::META_CONSUMED, 'yes');
		$order->save();
	}

	public static function capture_applied($order_id, $posted = null, $order = null): void {
		if (!$order instanceof WC_Order) {
			$order = wc_get_order(absint($order_id));
		}
		if (!$order instanceof WC_Order) {
			return;
		}
		self::capture_applied_order($order);
	}

	public static function capture_applied_api($order): void {
		if (is_numeric($order)) {
			$order = wc_get_order((int) $order);
		}
		if ($order instanceof WC_Order) {
			self::capture_applied_order($order);
		}
	}

	private static function capture_applied_order(WC_Order $order): void {
		if ($order->get_meta(self::META_APPLIED) === 'yes') {
			return;
		}
		foreach ($order->get_items('fee') as $fee) {
			if (!is_object($fee) || !method_exists($fee, 'get_name')) {
				continue;
			}
			if (strpos((string) $fee->get_name(), self::FEE_PREFIX) === 0) {
				$email = self::normalize_email((string) $order->get_billing_email());
				if ($email === '') {
					$email = self::current_customer_email();
				}
				$order->update_meta_data(self::META_APPLIED, 'yes');
				$order->update_meta_data(self::META_APPLIED_EMAIL, $email);
				$order->save();
				return;
			}
		}
	}

	public static function maybe_link_on_register($user_id): void {
		$user = get_userdata($user_id);
		if (!$user || !is_email($user->user_email)) {
			return;
		}
		$email = self::normalize_email((string) $user->user_email);
		$bonus = self::get_raw_bonus($email);
		if ($bonus) {
			update_user_meta($user_id, '_ss_wb_bonus', $bonus);
		}
	}

	/* ---------------------------------------------------------------------
	 * Aplicación del descuento (fee)
	 * ------------------------------------------------------------------- */

	public static function apply_bonus_fee($cart): void {
		if (!is_object($cart) || self::$applying) {
			return;
		}
		if (is_admin() && !wp_doing_ajax()) {
			return;
		}

		self::$applying = true;
		try {
			self::remove_our_fee($cart);

			if (!self::is_enabled()) {
				return;
			}

			$email = self::current_customer_email();
			if ($email === '') {
				return;
			}
			$bonus = self::get_active_bonus($email);
			if (!$bonus) {
				return;
			}

			$cart_subtotal     = 0.0;
			$eligible_subtotal = 0.0;
			foreach ($cart->get_cart() as $item) {
				if (!is_array($item)) {
					continue;
				}
				$line = 0.0;
				if (isset($item['line_subtotal'])) {
					$line = (float) $item['line_subtotal'];
				} elseif (isset($item['data']) && is_object($item['data'])) {
					$line = (float) $item['data']->get_price() * (int) ($item['quantity'] ?? 1);
				}
				$cart_subtotal += $line;

				$pid = (int) ($item['product_id'] ?? 0);
				if (self::product_is_eligible($pid, $bonus)) {
					$eligible_subtotal += $line;
				}
			}

			if ($eligible_subtotal <= 0 || $cart_subtotal <= 0) {
				return;
			}

			// Total a pagar antes del bono = subtotal líneas + fees ya aplicados (packs/promos).
			$fees_sum = 0.0;
			foreach ($cart->get_fees() as $fee) {
				if (is_object($fee) && isset($fee->amount)) {
					$fees_sum += (float) $fee->amount;
				}
			}
			$paid_before = $cart_subtotal + $fees_sum;

			$min_total = isset($bonus['min_total']) ? (float) $bonus['min_total'] : 0.0;
			if ($min_total > 0 && $paid_before < $min_total) {
				return;
			}

			$type   = (isset($bonus['type']) && $bonus['type'] === 'percent') ? 'percent' : 'fixed';
			$amount = isset($bonus['amount']) ? (float) $bonus['amount'] : 0.0;
			if ($amount <= 0) {
				return;
			}

			if ($type === 'percent') {
				$discount = $eligible_subtotal * $amount / 100;
			} else {
				$discount = min($amount, $eligible_subtotal);
			}

			// Nunca descontar más de lo que el cliente pagaría.
			$discount = min($discount, $paid_before);
			if ($discount <= 0) {
				return;
			}

			$decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 0;
			$label    = self::FEE_PREFIX . ($type === 'percent' ? ' (' . rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') . '%)' : '');

			$cart->add_fee($label, -1 * round($discount, $decimals), false);

			if (function_exists('WC') && WC()->session) {
				WC()->session->set('ss_wb_applied_email', $email);
			}
		} finally {
			self::$applying = false;
		}
	}

	/** ¿El producto del carrito califica para el bono según su alcance? */
	private static function product_is_eligible(int $product_id, array $bonus): bool {
		$products = (isset($bonus['products']) && is_array($bonus['products'])) ? array_map('absint', $bonus['products']) : array();
		$scope    = isset($bonus['scope']) ? (string) $bonus['scope'] : (!empty($products) ? 'custom' : 'all');

		if ($scope === 'same_category') {
			$cats = (isset($bonus['categories']) && is_array($bonus['categories'])) ? array_map('absint', $bonus['categories']) : array();
			if (!$cats || !function_exists('wc_get_product_term_ids')) {
				return true;
			}
			$pcats = array_map('absint', (array) wc_get_product_term_ids($product_id, 'product_cat'));
			return !empty(array_intersect($pcats, $cats));
		}

		if ($scope === 'same_product' || $scope === 'custom') {
			return empty($products) ? true : in_array($product_id, $products, true);
		}

		// all / desconocido.
		return true;
	}

	private static function remove_our_fee($cart): void {
		if (!is_object($cart) || !method_exists($cart, 'get_fees')) {
			return;
		}
		$fees = (array) $cart->get_fees();
		$keep = array();
		$had  = false;
		foreach ($fees as $fee) {
			$name = isset($fee->name) ? (string) $fee->name : '';
			if (strpos($name, self::FEE_PREFIX) === 0) {
				$had = true;
				continue;
			}
			$keep[] = array(
				'id'        => isset($fee->id) ? (string) $fee->id : '',
				'name'      => $name,
				'amount'    => isset($fee->amount) ? (float) $fee->amount : 0.0,
				'taxable'   => isset($fee->taxable) ? $fee->taxable : false,
				'tax_class' => isset($fee->tax_class) ? (string) $fee->tax_class : '',
			);
		}
		if ($had && method_exists($cart, 'fees_api')) {
			$cart->fees_api()->set_fees($keep);
		}
	}

	private static function current_customer_email(): string {
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			if ($user && is_email($user->user_email)) {
				return self::normalize_email((string) $user->user_email);
			}
		}
		if (function_exists('WC') && WC()->customer) {
			$email = self::normalize_email((string) WC()->customer->get_billing_email());
			if ($email !== '' && is_email($email)) {
				return $email;
			}
		}
		return '';
	}

	/* ---------------------------------------------------------------------
	 * Billetera
	 * ------------------------------------------------------------------- */

	public static function render_wallet_section(): void {
		if (!is_user_logged_in()) {
			return;
		}
		$user  = wp_get_current_user();
		$email = self::normalize_email((string) $user->user_email);
		$bonus = self::get_raw_bonus($email);
		if (!$bonus) {
			return;
		}

		$now       = time();
		$consumed  = !empty($bonus['consumed_order']);
		$expires   = (int) ($bonus['expires_ts'] ?? 0);
		$expired   = ($expires > 0 && $now > $expires);
		$active    = (!$consumed && !$expired);

		$amount_text = self::format_amount_text((float) ($bonus['amount'] ?? 0), (string) ($bonus['type'] ?? 'fixed'));
		$min_text    = self::format_money((float) ($bonus['min_total'] ?? 0));
		$sorteos_txt = self::scope_text($bonus);

		$estado = $active ? 'Activo' : ($consumed ? 'Usado' : 'Expirado');
		$class  = $active ? 'wallet-active' : 'wallet-expired';

		echo '<div class="wallet-section wallet-welcome-bonus">';
		echo '<h2>Bono de bienvenida</h2>';
		echo '<style>'
			. '.wallet-welcome-bonus .wl-th{font-weight:bold;}'
			. '.wallet-welcome-bonus .wallet-table{width:100%;border-collapse:collapse;margin-bottom:28px;}'
			. '.wallet-welcome-bonus .wallet-table th,.wallet-welcome-bonus .wallet-table td{border-bottom:1px solid #ccc;padding:12px 8px;text-align:left;vertical-align:top;}'
			. '.wallet-welcome-bonus .wallet-active{background-color:#d4edda;}'
			. '.wallet-welcome-bonus .wallet-expired{color:#999;text-decoration:line-through;}'
			. '</style>';
		echo '<table class="wallet-table"><thead><tr>'
			. '<th class="wl-th">Monto</th>'
			. '<th class="wl-th">Mínimo de compra</th>'
			. '<th class="wl-th">Válido para</th>'
			. '<th class="wl-th">Expira</th>'
			. '<th class="wl-th">Estado</th>'
			. '</tr></thead><tbody>';
		echo '<tr class="' . esc_attr($class) . '">';
		echo '<td>' . esc_html($amount_text) . '</td>';
		echo '<td>' . esc_html($min_text !== '' ? $min_text : 'Sin mínimo') . '</td>';
		echo '<td>' . esc_html($sorteos_txt) . '</td>';
		echo '<td>' . esc_html($expires > 0 ? wp_date('d/m/Y H:i', $expires) : '-') . '</td>';
		echo '<td>' . esc_html($estado) . '</td>';
		echo '</tr>';
		echo '</tbody></table>';
		echo '<p style="margin:-18px 0 28px;color:#666;font-size:13px;">El bono se aplica automáticamente al pagar, siempre que el total a pagar cumpla el mínimo.</p>';
		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Badge del header (se suma al del snippet existente)
	 * ------------------------------------------------------------------- */

	public static function render_header_badge(): void {
		if (!is_user_logged_in()) {
			return;
		}
		$user  = wp_get_current_user();
		$email = self::normalize_email((string) $user->user_email);
		$bonus = self::get_active_bonus($email);
		if (!$bonus) {
			return;
		}
		// El badge del header muestra montos fijos; el bono tipo % no suma un valor monetario.
		$amount = isset($bonus['amount']) ? (float) $bonus['amount'] : 0.0;
		$type   = (string) ($bonus['type'] ?? 'fixed');
		if ($type === 'percent' || $amount <= 0) {
			return;
		}

		$wallet_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('wallet') : home_url('/mi-cuenta/wallet/');

		echo '<style>.promo-valor-header{margin-left:8px;font-size:14px;background:#7321e1;color:#fff;padding:4px 9px;border-radius:3px;font-weight:bold;display:inline-flex;align-items:center;gap:4px;}</style>';
		echo '<script id="ss-wb-header-badge">(function(){';
		echo 'var add=' . (float) $amount . ';';
		echo 'var walletUrl=' . wp_json_encode($wallet_url) . ';';
		echo 'if(!add||add<=0)return;';
		echo 'function fmt(n){n=Math.round(n);var s=String(n),out="",c=0;for(var i=s.length-1;i>=0;i--){out=s[i]+out;c++;if(c%3===0&&i>0)out="."+out;}return out;}';
		echo 'function run(){var span=document.querySelector(".promo-valor-header");if(span){var a=span.querySelector("a")||span;var cur=parseInt((a.textContent||"").replace(/[^0-9]/g,""),10);if(isNaN(cur))cur=0;a.innerHTML=\'<i class="bx bx-gift"></i>$\'+fmt(cur+add);}else{var carrito=document.querySelector(\'.header-actions a[aria-label="Carrito de compras"]\');if(!carrito)return;var sp=document.createElement("span");sp.className="promo-valor-header";sp.innerHTML=\'<a href="\'+walletUrl+\'" style="color:#fff;text-decoration:none;"><i class="bx bx-gift"></i>$\'+fmt(add)+"</a>";carrito.insertAdjacentElement("afterend",sp);}}';
		echo 'document.addEventListener("DOMContentLoaded",function(){setTimeout(run,0);});';
		echo 'if(document.readyState!=="loading"){setTimeout(run,0);}';
		echo '})();</script>';
	}

	/* ---------------------------------------------------------------------
	 * Correo
	 * ------------------------------------------------------------------- */

	private static function send_bonus_email(string $email, array $bonus, WC_Order $order): void {
		if (!function_exists('WC') || !WC()->mailer() || !is_email($email)) {
			return;
		}

		$tpl   = self::get_template();
		$name  = trim((string) $order->get_billing_first_name());
		if ($name === '') {
			$name = 'hola';
		}
		$email_data = self::build_email($tpl, $name, (int) $order->get_id(), $bonus);
		$mailer     = WC()->mailer();
		$html       = $mailer->wrap_message($email_data['heading'], $email_data['body']);

		try {
			$ok = (bool) $mailer->send($email, $email_data['subject'], $html);
			self::append_log(array('event' => $ok ? 'email_sent' : 'email_failed', 'email' => $email, 'order_id' => (int) $order->get_id()));
		} catch (Throwable $e) {
			self::log('error', 'WB email exception: ' . $e->getMessage(), array('to' => $email));
		}
	}

	private static function build_email(array $tpl, string $name, int $order_id, array $bonus): array {
		$amount_text = self::format_amount_text((float) ($bonus['amount'] ?? 0), (string) ($bonus['type'] ?? 'fixed'));
		$min_text    = self::format_money((float) ($bonus['min_total'] ?? 0));
		$sorteos_txt = self::scope_text($bonus);
		$expires     = (int) ($bonus['expires_ts'] ?? 0);
		$venc_txt    = $expires > 0 ? wp_date('d/m/Y H:i', $expires) : '';
		$wallet_url  = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('wallet') : home_url('/mi-cuenta/wallet/');

		$replace = array(
			'{nombre}'      => esc_html($name),
			'{pedido}'      => esc_html((string) $order_id),
			'{monto}'       => esc_html($amount_text),
			'{bono}'        => esc_html($amount_text),
			'{minimo}'      => esc_html($min_text !== '' ? $min_text : 'sin mínimo'),
			'{sorteos}'     => esc_html($sorteos_txt),
			'{vencimiento}' => esc_html($venc_txt),
			'{url}'         => esc_url($wallet_url),
		);

		$body = strtr((string) $tpl['body'], $replace);
		if (trim((string) $tpl['preheader']) !== '') {
			$pre = esc_html(strtr((string) $tpl['preheader'], $replace));
			$body = '<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . $pre . '</div>' . $body;
		}

		return array(
			'subject' => strtr((string) $tpl['subject'], $replace),
			'heading' => strtr((string) $tpl['heading'], $replace),
			'body'    => $body,
		);
	}

	public static function handle_send_test(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('No autorizado.');
		}
		check_admin_referer(self::ADMIN_TEST);

		$to     = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
		$result = 'test_invalid';
		if (is_email($to)) {
			$ok     = self::send_test_email($to);
			$result = $ok ? 'test_sent' : 'test_failed';
		}

		wp_safe_redirect(add_query_arg('ss_wb_result', $result, admin_url('admin.php?page=ss-welcome-bonus')));
		exit;
	}

	private static function send_test_email(string $to): bool {
		if (!function_exists('WC') || !WC()->mailer() || !is_email($to)) {
			return false;
		}
		$sample = array(
			'type'        => self::get_type(),
			'amount'      => self::get_amount(),
			'min_total'   => self::get_min_total(),
			'products'    => self::get_products(),
			'expires_ts'  => time() + (self::get_hours() * HOUR_IN_SECONDS),
		);
		$data   = self::build_email(self::get_template(), 'equipo', 0, $sample);
		$mailer = WC()->mailer();
		$html   = $mailer->wrap_message($data['heading'], $data['body']);

		try {
			return (bool) $mailer->send($to, '[PRUEBA] ' . $data['subject'], $html);
		} catch (Throwable $e) {
			self::log('error', 'WB test email exception: ' . $e->getMessage(), array('to' => $to));
			return false;
		}
	}

	/* ---------------------------------------------------------------------
	 * Utilidades de formato
	 * ------------------------------------------------------------------- */

	private static function format_amount_text(float $amount, string $type): string {
		if ($type === 'percent') {
			$n = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
			return $n . '%';
		}
		return self::format_money($amount);
	}

	private static function format_money(float $amount): string {
		if ($amount <= 0) {
			return '';
		}
		if (function_exists('wc_price')) {
			return wp_strip_all_tags(html_entity_decode(wc_price($amount), ENT_QUOTES, 'UTF-8'));
		}
		return '$' . number_format($amount, 0, ',', '.');
	}

	/** Texto del alcance ("Válido para"). */
	private static function scope_text(array $bonus): string {
		$products = (isset($bonus['products']) && is_array($bonus['products'])) ? array_map('absint', $bonus['products']) : array();
		$scope    = isset($bonus['scope']) ? (string) $bonus['scope'] : (!empty($products) ? 'custom' : 'all');

		if ($scope === 'same_product') {
			return $products ? ('el mismo sorteo: ' . self::product_names($products)) : 'el mismo sorteo de tu primera compra';
		}
		if ($scope === 'same_category') {
			$names = (isset($bonus['category_names']) && is_array($bonus['category_names'])) ? array_filter(array_map('strval', $bonus['category_names'])) : array();
			if (!$names && isset($bonus['categories']) && is_array($bonus['categories'])) {
				$names = self::category_names(array_map('absint', $bonus['categories']));
			}
			return $names ? ('sorteos de la misma categoría (' . implode(', ', $names) . ')') : 'sorteos de la misma categoría de tu primera compra';
		}
		if ($scope === 'custom') {
			return $products ? self::product_names($products) : 'cualquier sorteo';
		}
		return 'cualquier sorteo';
	}

	/** @param int[] $product_ids */
	private static function product_names(array $product_ids): string {
		$product_ids = array_values(array_filter(array_map('absint', $product_ids)));
		if (!$product_ids) {
			return 'cualquier sorteo';
		}
		$names = array();
		foreach ($product_ids as $pid) {
			$title = get_the_title($pid);
			if ($title === '') {
				$title = 'Sorteo #' . $pid;
			}
			$names[] = $title;
		}
		return implode(', ', $names);
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	public static function register_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			'Bono bienvenida',
			'Bono bienvenida',
			'manage_woocommerce',
			'ss-welcome-bonus',
			array(__CLASS__, 'render_admin_page')
		);
	}

	public static function list_lottery_products(): array {
		if (!function_exists('wc_get_products')) {
			return array();
		}
		$out = array();
		$products = wc_get_products(array('limit' => -1, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
		foreach ((array) $products as $p) {
			if (!is_object($p) || !method_exists($p, 'get_id')) {
				continue;
			}
			if (function_exists('lty_is_lottery_product') && !lty_is_lottery_product($p)) {
				continue;
			}
			$out[ (int) $p->get_id() ] = (string) $p->get_name();
		}
		return $out;
	}

	public static function render_admin_page(): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}
		self::ensure_defaults();

		$saved = false;
		if (isset($_POST['ss_wb_save_settings']) && check_admin_referer('ss_wb_save_settings')) {
			update_option(self::OPT_ENABLED, (isset($_POST['ss_wb_enabled']) && $_POST['ss_wb_enabled'] === 'yes') ? 'yes' : 'no', false);
			$type = isset($_POST['ss_wb_type']) ? sanitize_key(wp_unslash($_POST['ss_wb_type'])) : 'fixed';
			update_option(self::OPT_TYPE, in_array($type, array('fixed', 'percent'), true) ? $type : 'fixed', false);
			update_option(self::OPT_AMOUNT, max(0, (float) wp_unslash($_POST['ss_wb_amount'] ?? 0)), false);
			update_option(self::OPT_MIN, max(0, (float) wp_unslash($_POST['ss_wb_min_total'] ?? 0)), false);
			update_option(self::OPT_HOURS, max(1, min(8760, (int) wp_unslash($_POST['ss_wb_hours'] ?? 72))), false);
			$products = isset($_POST['ss_wb_products']) ? array_map('absint', (array) wp_unslash($_POST['ss_wb_products'])) : array();
			update_option(self::OPT_PRODUCTS, array_values(array_filter($products)), false);
			$scope = isset($_POST['ss_wb_scope']) ? sanitize_key(wp_unslash($_POST['ss_wb_scope'])) : 'all';
			update_option(self::OPT_SCOPE, in_array($scope, array('all', 'same_product', 'same_category', 'custom'), true) ? $scope : 'all', false);
			$saved = true;
			self::log('info', 'WB settings updated', array());
		}

		if (isset($_POST['ss_wb_save_template']) && check_admin_referer('ss_wb_save_template')) {
			update_option(self::OPT_TPL_SUBJECT, sanitize_text_field(wp_unslash($_POST['ss_wb_tpl_subject'] ?? '')), false);
			update_option(self::OPT_TPL_PREHEADER, sanitize_text_field(wp_unslash($_POST['ss_wb_tpl_preheader'] ?? '')), false);
			update_option(self::OPT_TPL_HEADING, sanitize_text_field(wp_unslash($_POST['ss_wb_tpl_heading'] ?? '')), false);
			update_option(self::OPT_TPL_BODY, (string) wp_unslash($_POST['ss_wb_tpl_body'] ?? ''), false);
			$saved = true;
		}

		if (isset($_POST['ss_wb_restore_template']) && check_admin_referer('ss_wb_save_template')) {
			foreach (self::template_defaults() as $k => $v) {
				update_option($k, $v, false);
			}
			$saved = true;
		}

		$tpl      = self::get_template();
		$products = self::list_lottery_products();
		$selected = self::get_products();
		$cutoff   = (string) get_option(self::OPT_CUTOFF, '');

		echo '<div class="wrap">';
		echo '<h1>Bono de bienvenida (1ª → 2ª compra)</h1>';

		if ($saved) {
			echo '<div class="notice notice-success is-dismissible"><p>Cambios guardados.</p></div>';
		}
		if (isset($_GET['ss_wb_result'])) {
			$code = sanitize_key((string) wp_unslash($_GET['ss_wb_result']));
			$map  = array(
				'test_sent'    => array('success', 'Correo de prueba enviado.'),
				'test_invalid' => array('error', 'Ingresa un correo válido.'),
				'test_failed'  => array('error', 'No se pudo enviar la prueba.'),
			);
			if (isset($map[$code])) {
				echo '<div class="notice notice-' . esc_attr($map[$code][0]) . ' is-dismissible"><p>' . esc_html($map[$code][1]) . '</p></div>';
			}
		}

		echo '<div style="max-width:980px;background:#fff;border:1px solid #c3c4c7;border-left:3px solid #6b3bb8;padding:12px 16px;margin:10px 0 16px;">';
		echo '<p style="margin:0 0 6px;"><strong>¿Qué hace esta página?</strong></p>';
		echo '<p style="margin:0 0 8px;">Cuando un cliente paga su <strong>primera compra</strong>, se le asigna un bono para su <strong>segunda compra</strong>. El bono se aplica <strong>automáticamente</strong> al pagar (si cumple el gasto mínimo), aparece en <strong>Mi cuenta → Bonificaciones</strong> y en el badge del header, y se envía un correo.</p>';
		echo '<ul style="margin:6px 0;padding-left:20px;">';
		echo '<li>Solo se otorga a <strong>pedidos nuevos</strong> desde: <code>' . esc_html($cutoff) . ' UTC</code> (cutoff de activación).</li>';
		echo '<li>Una sola vez por cliente (por correo). No se reenvía si ya tuvo un bono.</li>';
		echo '<li>El gasto mínimo se mide sobre el <strong>total a pagar tras packs/promos</strong>.</li>';
		echo '</ul>';
		echo '</div>';

		self::render_kpis();

		echo '<form method="post" style="max-width:820px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 20px;">';
		wp_nonce_field('ss_wb_save_settings');
		echo '<h2 style="margin-top:0;">Configuración</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">Activo</th><td>';
		echo '<label><input type="checkbox" name="ss_wb_enabled" value="yes" ' . checked(self::is_enabled(), true, false) . ' /> Otorgar y aplicar el bono</label>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="ss_wb_type">Tipo de bono</label></th><td>';
		echo '<select name="ss_wb_type" id="ss_wb_type">';
		echo '<option value="fixed" ' . selected(self::get_type(), 'fixed', false) . '>Monto fijo ($)</option>';
		echo '<option value="percent" ' . selected(self::get_type(), 'percent', false) . '>Porcentaje (%)</option>';
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="ss_wb_amount">Monto / porcentaje</label></th><td>';
		echo '<input type="number" min="0" step="1" name="ss_wb_amount" id="ss_wb_amount" class="regular-text" value="' . esc_attr((string) self::get_amount()) . '" />';
		echo '<p class="description">Ej.: 60000 (monto fijo) o 30 (porcentaje).</p></td></tr>';

		echo '<tr><th scope="row"><label for="ss_wb_min_total">Gasto mínimo</label></th><td>';
		echo '<input type="number" min="0" step="1" name="ss_wb_min_total" id="ss_wb_min_total" class="regular-text" value="' . esc_attr((string) self::get_min_total()) . '" />';
		echo '<p class="description">Total a pagar (tras packs/promos) para que aplique. 0 = sin mínimo.</p></td></tr>';

		echo '<tr><th scope="row"><label for="ss_wb_hours">Duración (horas)</label></th><td>';
		echo '<input type="number" min="1" max="8760" step="1" name="ss_wb_hours" id="ss_wb_hours" class="small-text" value="' . esc_attr((string) self::get_hours()) . '" />';
		echo '<p class="description">Tiempo para usar el bono desde la primera compra. Ej.: 72.</p></td></tr>';

		echo '<tr><th scope="row"><label for="ss_wb_scope">Alcance del bono</label></th><td>';
		echo '<select name="ss_wb_scope" id="ss_wb_scope">';
		echo '<option value="all" ' . selected(self::get_scope(), 'all', false) . '>Liberado (cualquier sorteo)</option>';
		echo '<option value="same_product" ' . selected(self::get_scope(), 'same_product', false) . '>Solo el mismo sorteo de la 1ª compra</option>';
		echo '<option value="same_category" ' . selected(self::get_scope(), 'same_category', false) . '>Solo la misma categoría de la 1ª compra</option>';
		echo '<option value="custom" ' . selected(self::get_scope(), 'custom', false) . '>Sorteos específicos (elegir abajo)</option>';
		echo '</select>';
		echo '<p class="description">';
		echo '<strong>Liberado</strong>: sirve para cualquier sorteo. ';
		echo '<strong>Mismo sorteo</strong>: si compra Peugeot, el bono solo sirve para el Peugeot (y si compra Casa Mar, solo para esa casa). ';
		echo '<strong>Misma categoría</strong>: si compra Peugeot (automóvil), el bono sirve para Peugeot y Jeep Avenger; si compra Casa Mar (inmobiliaria), sirve para esa categoría. ';
		echo '<strong>Específicos</strong>: solo los sorteos que marques abajo.';
		echo '</p></td></tr>';

		$scope_now = self::get_scope();
		echo '<tr><th scope="row">Sorteos específicos</th><td>';
		echo '<p class="description" style="margin-top:0;">Solo se usan si el alcance es <strong>Sorteos específicos</strong>. Marca uno o varios para agrupar (ej. Jeep + Peugeot).</p>';
		echo '<div style="max-height:260px;overflow:auto;border:1px solid #ddd;padding:8px;background:#fafafa;' . ($scope_now === 'custom' ? '' : 'opacity:.6;') . '">';
		if (!$products) {
			echo '<em>No se encontraron sorteos (productos lottery publicados).</em>';
		} else {
			foreach ($products as $pid => $name) {
				echo '<label style="display:block;margin:2px 0;"><input type="checkbox" name="ss_wb_products[]" value="' . esc_attr((string) $pid) . '" ' . checked(in_array((int) $pid, $selected, true), true, false) . ' /> ' . esc_html($name) . ' <span style="color:#999;">#' . (int) $pid . '</span></label>';
			}
		}
		echo '</div></td></tr>';

		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" name="ss_wb_save_settings" class="button button-primary" value="1">Guardar configuración</button></p>';
		echo '</form>';

		// Editor de correo.
		echo '<form method="post" style="max-width:820px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 20px;">';
		wp_nonce_field('ss_wb_save_template');
		echo '<h2 style="margin-top:0;">Correo</h2>';
		echo '<p class="description">Etiquetas: <code>{nombre}</code>, <code>{pedido}</code>, <code>{monto}</code>, <code>{minimo}</code>, <code>{sorteos}</code>, <code>{vencimiento}</code>, <code>{url}</code>.</p>';
		echo '<p><label><strong>Asunto</strong></label><br /><input type="text" name="ss_wb_tpl_subject" value="' . esc_attr($tpl['subject']) . '" style="width:100%;" /></p>';
		echo '<p><label><strong>Preheader (vista previa)</strong></label><br /><input type="text" name="ss_wb_tpl_preheader" value="' . esc_attr($tpl['preheader']) . '" style="width:100%;" /></p>';
		echo '<p><label><strong>Encabezado</strong></label><br /><input type="text" name="ss_wb_tpl_heading" value="' . esc_attr($tpl['heading']) . '" style="width:100%;" /></p>';
		echo '<p><label><strong>Cuerpo (HTML)</strong></label><br /><textarea name="ss_wb_tpl_body" rows="14" style="width:100%;font-family:Consolas,monospace;font-size:12px;">' . esc_textarea($tpl['body']) . '</textarea></p>';
		echo '<p class="submit" style="display:flex;gap:10px;">';
		echo '<button type="submit" name="ss_wb_save_template" class="button button-primary" value="1">Guardar correo</button>';
		echo '<button type="submit" name="ss_wb_restore_template" class="button" value="1" onclick="return confirm(\'¿Restaurar el correo por defecto?\');">Restaurar por defecto</button>';
		echo '</p></form>';

		// Prueba.
		$user    = wp_get_current_user();
		$prefill = ($user && is_email($user->user_email)) ? $user->user_email : '';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:820px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 20px;">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_TEST) . '" />';
		wp_nonce_field(self::ADMIN_TEST);
		echo '<h2 style="margin-top:0;">Enviar correo de prueba</h2>';
		echo '<p><input type="email" name="test_email" class="regular-text" value="' . esc_attr($prefill) . '" required /> ';
		echo '<button type="submit" class="button">Enviar prueba</button></p>';
		echo '</form>';

		self::render_log();

		echo '</div>';
	}

	private static function render_kpis(): void {
		$map      = self::get_map();
		$granted  = count($map);
		$active   = 0;
		$consumed = 0;
		$expired  = 0;
		$now      = time();
		foreach ($map as $b) {
			if (!is_array($b)) {
				continue;
			}
			if (!empty($b['consumed_order'])) {
				$consumed++;
			} elseif (!empty($b['expires_ts']) && $now > (int) $b['expires_ts']) {
				$expired++;
			} else {
				$active++;
			}
		}

		$card = static function (string $label, string $value) {
			echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:12px 16px;min-width:140px;">';
			echo '<div style="color:#666;font-size:12px;">' . esc_html($label) . '</div>';
			echo '<div style="font-size:22px;font-weight:600;margin-top:4px;color:#1d2327;">' . esc_html($value) . '</div>';
			echo '</div>';
		};

		echo '<h2 style="margin-bottom:4px;">Resumen</h2>';
		echo '<div style="display:flex;gap:14px;flex-wrap:wrap;margin:0 0 14px;">';
		$card('Bonos otorgados', (string) $granted);
		$card('Activos', (string) $active);
		$card('Usados', (string) $consumed);
		$card('Expirados', (string) $expired);
		echo '</div>';
	}

	private static function render_log(): void {
		$log = get_option(self::OPT_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$log = array_reverse($log);

		echo '<h2>Registro</h2>';
		echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Fecha (UTC)</th><th>Evento</th><th>Email</th><th>Pedido</th></tr></thead><tbody>';
		$rows = 0;
		foreach ($log as $row) {
			if (!is_array($row)) {
				continue;
			}
			$rows++;
			if ($rows > 200) {
				break;
			}
			$oid  = isset($row['order_id']) ? (int) $row['order_id'] : 0;
			$link = $oid ? admin_url('post.php?post=' . $oid . '&action=edit') : '';
			echo '<tr>';
			echo '<td>' . esc_html((string) ($row['time'] ?? '')) . '</td>';
			echo '<td>' . esc_html((string) ($row['event'] ?? '')) . '</td>';
			echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
			echo '<td>' . ($oid ? '<a href="' . esc_url($link) . '">#' . esc_html((string) $oid) . '</a>' : '—') . '</td>';
			echo '</tr>';
		}
		if ($rows === 0) {
			echo '<tr><td colspan="4">Aún no hay eventos.</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ---------------------------------------------------------------------
	 * Log
	 * ------------------------------------------------------------------- */

	private static function append_log(array $entry): void {
		$log = get_option(self::OPT_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$entry['time'] = $entry['time'] ?? gmdate('c');
		$log[]         = $entry;
		if (count($log) > self::LOG_MAX) {
			$log = array_slice($log, -self::LOG_MAX);
		}
		update_option(self::OPT_LOG, $log, false);
	}

	private static function log(string $level, string $message, array $context = array()): void {
		if (!function_exists('wc_get_logger')) {
			return;
		}
		$logger  = wc_get_logger();
		$payload = $message;
		if ($context) {
			$payload .= ' | ' . wp_json_encode($context);
		}
		$valid = array('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug');
		if (in_array($level, $valid, true)) {
			$logger->{$level}($payload, array('source' => self::LOG_SOURCE));
		} else {
			$logger->info($payload, array('source' => self::LOG_SOURCE));
		}
	}
}

add_action(
	'plugins_loaded',
	static function () {
		if (!class_exists('WooCommerce')) {
			return;
		}
		SorteoSeguro_Welcome_Bonus::init();
	},
	25
);
