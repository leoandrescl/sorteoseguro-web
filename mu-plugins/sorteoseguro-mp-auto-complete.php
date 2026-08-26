<?php
/**
 * Plugin Name: Sorteo Seguro – MP Auto Complete
 * Description: Si Mercado Pago aprueba el pago, fuerza el pedido a Completado. No depende solo de thank-you ni del IPN: reintentos + cron + búsqueda por external_reference.
 * Author: Sorteo Seguro
 * Version: 1.2.0
 *
 * mu-plugin: no se desactiva desde el admin; borrar este archivo para rollback.
 *
 * Cobertura:
 * 1) thank-you (si el cliente vuelve)
 * 2) Action Scheduler: reintentos a 1/3/5/10/20 min tras crear el pedido MP
 * 3) Cron cada 2 min: red de seguridad (pending/on-hold/cancelled/failed/processing)
 * 4) Sin payment_id en meta: busca en API MP por external_reference (WC-{id})
 * 5) Si el IPN deja el pedido en Procesando, igual se fuerza a Completado
 *
 * Solo pedidos creados desde la activación (option ss_mp_ac_cutoff). No modifica el plugin oficial de MP.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_MP_Auto_Complete {

	const VERSION       = '1.2.0';
	const LOG_SOURCE    = 'sorteoseguro-mp-auto-complete';
	const META_LOCK     = '_ss_mp_auto_complete_done';
	const META_SCHEDULE = '_ss_mp_auto_complete_scheduled';
	const TRANSIENT_PF  = 'ss_mp_ac_lock_';
	const OPTION_CUTOFF = 'ss_mp_ac_cutoff';
	const CRON_HOOK     = 'ss_mp_ac_scan_pending';
	const AS_HOOK       = 'ss_mp_ac_check_order';
	const SCAN_LIMIT    = 40;

	/** @var int[] minutos tras checkout */
	const RETRY_OFFSETS = array(1, 3, 5, 10, 20);

	public static function init(): void {
		add_filter('cron_schedules', array(__CLASS__, 'register_cron_schedule'));

		// Rápido: cliente vuelve a thank-you (página nativa WC order-received; no la creamos nosotros).
		add_action('woocommerce_thankyou', array(__CLASS__, 'on_thankyou'), 30, 1);

		// Si el IPN de MP deja el pedido en Procesando, forzar Completado de inmediato.
		add_action('woocommerce_order_status_processing', array(__CLASS__, 'on_processing'), 60, 2);
		add_action('woocommerce_payment_complete', array(__CLASS__, 'on_payment_complete'), 60, 1);

		// Programa reintentos apenas se crea el pedido con gateway MP.
		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'schedule_retries_for_order'), 50, 1);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'schedule_retries_for_order'), 50, 1);

		// Worker Action Scheduler / WP-Cron single.
		add_action(self::AS_HOOK, array(__CLASS__, 'check_order'), 10, 1);

		// Red de seguridad periódica (registrar cron en init, cuando ya existen schedules).
		add_action(self::CRON_HOOK, array(__CLASS__, 'scan_pending_orders'));
		add_action('init', array(__CLASS__, 'bootstrap_runtime'), 20);
	}

	public static function bootstrap_runtime(): void {
		self::ensure_cutoff();
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + 60, 'ss_mp_ac_every_two_minutes', self::CRON_HOOK);
		}
	}

	public static function register_cron_schedule(array $schedules): array {
		if (!isset($schedules['ss_mp_ac_every_two_minutes'])) {
			$schedules['ss_mp_ac_every_two_minutes'] = array(
				'interval' => 120,
				'display'  => 'Sorteo Seguro MP Auto Complete (2 min)',
			);
		}
		return $schedules;
	}

	private static function ensure_cutoff(): void {
		$cutoff = get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '' || $cutoff === false) {
			// Pedidos anteriores al deploy no se tocan.
			update_option(self::OPTION_CUTOFF, gmdate('Y-m-d H:i:s'), false);
			self::log('info', 'Cutoff initialized', array('cutoff' => get_option(self::OPTION_CUTOFF)));
		}
	}

	public static function on_thankyou($order_id): void {
		self::check_order(absint($order_id), 'thankyou');
	}

	/**
	 * @param int            $order_id
	 * @param WC_Order|null  $order
	 */
	public static function on_processing($order_id, $order = null): void {
		self::check_order(absint($order_id), 'status_processing');
	}

	/**
	 * @param int $order_id
	 */
	public static function on_payment_complete($order_id): void {
		self::check_order(absint($order_id), 'payment_complete');
	}

	/**
	 * @param int|WC_Order $order
	 */
	public static function schedule_retries_for_order($order): void {
		$order = self::as_order($order);
		if (!$order) {
			return;
		}
		if (!self::is_mercadopago_order($order)) {
			return;
		}
		if (!self::is_after_cutoff($order)) {
			return;
		}
		if ($order->get_meta(self::META_SCHEDULE) === 'yes') {
			return;
		}

		$order_id = $order->get_id();
		foreach (self::RETRY_OFFSETS as $minutes) {
			self::enqueue_check($order_id, (int) $minutes * MINUTE_IN_SECONDS);
		}

		$order->update_meta_data(self::META_SCHEDULE, 'yes');
		$order->save();

		self::log(
			'info',
			'Scheduled MP auto-complete retries',
			array(
				'order_id' => $order_id,
				'offsets'  => self::RETRY_OFFSETS,
			)
		);
	}

	private static function enqueue_check(int $order_id, int $delay_seconds): void {
		$timestamp = time() + max(0, $delay_seconds);

		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, self::AS_HOOK, array($order_id), 'sorteoseguro-mp-auto-complete');
			return;
		}

		wp_schedule_single_event($timestamp, self::AS_HOOK, array($order_id));
	}

	/**
	 * Red de seguridad: pedidos MP nuevos aún no pagados / cancelados por timeout.
	 */
	public static function scan_pending_orders(): void {
		if (!function_exists('wc_get_orders')) {
			return;
		}

		$cutoff = (string) get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '') {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'          => self::SCAN_LIMIT,
				'status'         => array('pending', 'on-hold', 'cancelled', 'failed', 'processing'),
				'date_created'   => '>=' . $cutoff,
				'payment_method' => array(
					'woo-mercado-pago-basic',
					'woo-mercado-pago-custom',
					'woo-mercado-pago-ticket',
					'woo-mercado-pago-pix',
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
			)
		);

		// WC a veces no filtra bien payment_method como array; fallback amplio.
		if (!is_array($orders) || !$orders) {
			$orders = wc_get_orders(
				array(
					'limit'        => self::SCAN_LIMIT,
					'status'       => array('pending', 'on-hold', 'cancelled', 'failed', 'processing'),
					'date_created' => '>=' . $cutoff,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'return'       => 'ids',
				)
			);
		}

		if (!is_array($orders)) {
			return;
		}

		$checked = 0;
		foreach ($orders as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order instanceof WC_Order) {
				continue;
			}
			if (!self::is_mercadopago_order($order)) {
				continue;
			}
			if ($order->get_meta(self::META_LOCK) === 'yes') {
				continue;
			}

			self::check_order((int) $order_id, 'cron_scan');
			$checked++;
		}

		if ($checked > 0) {
			self::log('info', 'Cron scan finished', array('checked' => $checked));
		}
	}

	/**
	 * @param int|string $order_id
	 */
	public static function check_order($order_id, string $source = 'retry'): void {
		$order_id = absint($order_id);
		if (!$order_id || !function_exists('wc_get_order')) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}

		if (!self::is_mercadopago_order($order)) {
			return;
		}

		if (!self::is_after_cutoff($order)) {
			return;
		}

		if ($order->get_meta(self::META_LOCK) === 'yes') {
			return;
		}

		$status = $order->get_status();
		if (in_array($status, array('completed', 'refunded'), true)) {
			if ($status === 'completed') {
				$order->update_meta_data(self::META_LOCK, 'yes');
				$order->save();
			}
			return;
		}

		// pending / on-hold / cancelled / failed / processing: si MP aprobó → Completado.
		if (!in_array($status, array('pending', 'on-hold', 'cancelled', 'failed', 'processing'), true)) {
			return;
		}

		$lock_key = self::TRANSIENT_PF . $order_id;
		if (get_transient($lock_key)) {
			return;
		}
		set_transient($lock_key, 1, 90);

		// Atajo: IPN ya dejó Procesando + pagado → forzar Completado sin esperar API.
		if ($status === 'processing' && $order->is_paid()) {
			$payment_id = self::resolve_payment_id($order);
			if ($payment_id === '') {
				$payment_id = (string) $order->get_transaction_id();
			}
			self::force_to_completed($order, $payment_id, 'accredited', $source, 'processing');
			delete_transient($lock_key);
			return;
		}

		$token = self::get_access_token($order);
		if ($token === '') {
			self::log('error', 'Missing Mercado Pago access token', array('order_id' => $order_id, 'source' => $source));
			delete_transient($lock_key);
			return;
		}

		$payment_id = self::resolve_payment_id($order);
		$payment    = null;

		if ($payment_id !== '') {
			$payment = self::fetch_payment($payment_id, $token);
		} else {
			$payment = self::find_approved_payment_by_external_reference($order, $token);
			if (is_array($payment) && !empty($payment['id'])) {
				$payment_id = (string) $payment['id'];
				self::persist_payment_id($order, $payment_id);
			}
		}

		if ($payment === null || $payment_id === '') {
			self::log(
				'info',
				'No approved payment found yet',
				array(
					'order_id'   => $order_id,
					'source'     => $source,
					'payment_id' => $payment_id,
					'wc_status'  => $status,
				)
			);
			delete_transient($lock_key);
			return;
		}

		$mp_status = isset($payment['status']) ? (string) $payment['status'] : '';
		$mp_detail = isset($payment['status_detail']) ? (string) $payment['status_detail'] : '';
		$ext_ref   = isset($payment['external_reference']) ? (string) $payment['external_reference'] : '';

		self::log(
			'info',
			'MP payment checked',
			array(
				'order_id'           => $order_id,
				'source'             => $source,
				'payment_id'         => $payment_id,
				'mp_status'          => $mp_status,
				'mp_status_detail'   => $mp_detail,
				'external_reference' => $ext_ref,
				'wc_status'          => $status,
			)
		);

		if (!self::external_reference_matches($order, $ext_ref)) {
			self::log(
				'warning',
				'external_reference mismatch; skipping',
				array(
					'order_id'           => $order_id,
					'external_reference' => $ext_ref,
					'source'             => $source,
				)
			);
			delete_transient($lock_key);
			return;
		}

		if ($mp_status !== 'approved') {
			delete_transient($lock_key);
			return;
		}

		// Re-load por carrera con IPN.
		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			delete_transient($lock_key);
			return;
		}
		if ($order->get_status() === 'completed') {
			$order->update_meta_data(self::META_LOCK, 'yes');
			$order->save();
			delete_transient($lock_key);
			return;
		}

		try {
			if (!$order->get_transaction_id()) {
				$order->set_transaction_id($payment_id);
			}

			$prev_status = $order->get_status();

			// Si estaba cancelled/failed por hold-stock, payment_complete puede fallar o no aplicar.
			// Primero volver a pending si hace falta, luego payment_complete + completed.
			if (in_array($prev_status, array('cancelled', 'failed'), true)) {
				$order->update_status(
					'pending',
					sprintf(
						'Sorteo Seguro MP Auto Complete: pago MP %s aprobado; restaurando pedido desde %s.',
						$payment_id,
						$prev_status
					)
				);
				$order = wc_get_order($order_id);
				if (!$order instanceof WC_Order) {
					delete_transient($lock_key);
					return;
				}
			}

			if ($prev_status !== 'processing') {
				$order->payment_complete($payment_id);
				$order = wc_get_order($order_id);
				if (!$order instanceof WC_Order) {
					delete_transient($lock_key);
					return;
				}
			}

			self::force_to_completed($order, $payment_id, $mp_detail !== '' ? $mp_detail : 'approved', $source, $prev_status);
		} catch (Throwable $e) {
			self::log(
				'error',
				'Exception while completing order: ' . $e->getMessage(),
				array(
					'order_id'   => $order_id,
					'payment_id' => $payment_id,
					'source'     => $source,
				)
			);
		}

		delete_transient($lock_key);
	}

	/**
	 * Fuerza Completado y deja nota visible en el pedido.
	 */
	private static function force_to_completed(WC_Order $order, string $payment_id, string $mp_detail, string $source, string $prev_status): void {
		$order_id = $order->get_id();

		if ($order->get_status() !== 'completed') {
			$order->update_status(
				'completed',
				sprintf(
					'Sorteo Seguro MP Auto Complete: pago MP %s aprobado (%s). Estado forzado a Completado (antes: %s). Origen: %s.',
					$payment_id !== '' ? $payment_id : 'n/d',
					$mp_detail !== '' ? $mp_detail : 'approved',
					$prev_status,
					$source
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					'Sorteo Seguro MP Auto Complete: pago MP %s aprobado (%s). Pedido Completado. Origen: %s.',
					$payment_id !== '' ? $payment_id : 'n/d',
					$mp_detail !== '' ? $mp_detail : 'approved',
					$source
				)
			);
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}

		$order->update_meta_data(self::META_LOCK, 'yes');
		$order->save();

		self::log(
			'info',
			'Order auto-completed',
			array(
				'order_id'     => $order_id,
				'payment_id'   => $payment_id,
				'source'       => $source,
				'prev_status'  => $prev_status,
				'final_status' => $order->get_status(),
			)
		);
	}

	/**
	 * @param int|WC_Order $order
	 */
	private static function as_order($order): ?WC_Order {
		if ($order instanceof WC_Order) {
			return $order;
		}
		if (!function_exists('wc_get_order')) {
			return null;
		}
		$o = wc_get_order(absint($order));
		return $o instanceof WC_Order ? $o : null;
	}

	private static function is_after_cutoff(WC_Order $order): bool {
		$cutoff = (string) get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '') {
			return false;
		}
		$created = $order->get_date_created();
		if (!$created) {
			return false;
		}
		return $created->getTimestamp() >= strtotime($cutoff . ' UTC');
	}

	private static function is_mercadopago_order(WC_Order $order): bool {
		$method = (string) $order->get_payment_method();
		return strpos($method, 'woo-mercado-pago') === 0;
	}

	private static function resolve_payment_id(WC_Order $order): string {
		$from_get = isset($_GET['payment_id']) ? preg_replace('/\D+/', '', wp_unslash((string) $_GET['payment_id'])) : '';
		if ($from_get !== '') {
			return $from_get;
		}

		$meta = (string) $order->get_meta('_Mercado_Pago_Payment_IDs');
		if ($meta === '') {
			$tx = (string) $order->get_transaction_id();
			return preg_replace('/\D+/', '', $tx);
		}

		$parts = array_filter(array_map('trim', explode(',', $meta)));
		$last  = (string) end($parts);
		return preg_replace('/\D+/', '', $last);
	}

	private static function persist_payment_id(WC_Order $order, string $payment_id): void {
		$existing = (string) $order->get_meta('_Mercado_Pago_Payment_IDs');
		if ($existing !== '' && strpos($existing, $payment_id) !== false) {
			return;
		}
		$new = $existing === '' ? $payment_id : ($existing . ',' . $payment_id);
		$order->update_meta_data('_Mercado_Pago_Payment_IDs', $new);
		if (!$order->get_transaction_id()) {
			$order->set_transaction_id($payment_id);
		}
		$order->save();
	}

	private static function get_access_token(WC_Order $order): string {
		$is_prod = $order->get_meta('is_production_mode');
		if ($is_prod === '' || $is_prod === null) {
			$is_prod = 'yes';
		}

		if (in_array((string) $is_prod, array('yes', '1'), true)) {
			$token = (string) get_option('_mp_access_token_prod', '');
		} else {
			$token = (string) get_option('_mp_access_token_test', '');
		}

		if ($token === '') {
			$token = (string) get_option('_mp_access_token_prod', '');
		}

		return trim($token);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function fetch_payment(string $payment_id, string $token): ?array {
		$url  = 'https://api.mercadopago.com/v1/payments/' . rawurlencode($payment_id);
		$body = self::mp_get_json($url, $token, array('payment_id' => $payment_id));
		return is_array($body) ? $body : null;
	}

	/**
	 * Cliente cerró el browser antes de thank-you: no hay payment_id en el pedido.
	 * Buscamos por external_reference = WC-{order_id}.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function find_approved_payment_by_external_reference(WC_Order $order, string $token): ?array {
		$prefix   = (string) get_option('_mp_store_identificator', 'WC-');
		$expected = $prefix . $order->get_id();

		$url = add_query_arg(
			array(
				'external_reference' => $expected,
				'sort'               => 'date_created',
				'criteria'           => 'desc',
				'range'              => 'date_created',
				'begin_date'         => 'NOW-7DAYS',
				'end_date'           => 'NOW',
			),
			'https://api.mercadopago.com/v1/payments/search'
		);

		$body = self::mp_get_json(
			$url,
			$token,
			array(
				'order_id'           => $order->get_id(),
				'external_reference' => $expected,
			)
		);

		if (!is_array($body) || empty($body['results']) || !is_array($body['results'])) {
			return null;
		}

		foreach ($body['results'] as $row) {
			if (!is_array($row)) {
				continue;
			}
			$status = isset($row['status']) ? (string) $row['status'] : '';
			if ($status === 'approved') {
				return $row;
			}
		}

		// Devolver el más reciente aunque no esté approved (para log/diagnóstico).
		$first = $body['results'][0];
		return is_array($first) ? $first : null;
	}

	/**
	 * @param array<string,mixed> $ctx
	 * @return array<string,mixed>|null
	 */
	private static function mp_get_json(string $url, string $token, array $ctx = array()): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if (is_wp_error($response)) {
			self::log('error', 'wp_remote_get error: ' . $response->get_error_message(), $ctx);
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode((string) wp_remote_retrieve_body($response), true);
		if ($code < 200 || $code >= 300 || !is_array($body)) {
			self::log('error', 'MP API bad response', array_merge($ctx, array('http' => $code)));
			return null;
		}

		return $body;
	}

	private static function external_reference_matches(WC_Order $order, string $ext_ref): bool {
		if ($ext_ref === '') {
			$stored = (string) $order->get_meta('_Mercado_Pago_Payment_IDs');
			return $stored !== '';
		}

		$order_id = (string) $order->get_id();
		$prefix   = (string) get_option('_mp_store_identificator', 'WC-');
		$expected = $prefix . $order_id;

		if ($ext_ref === $expected) {
			return true;
		}

		return (bool) preg_match('/(?:^|[^0-9])' . preg_quote($order_id, '/') . '$/', $ext_ref);
	}

	/**
	 * @param array<string,mixed> $context
	 */
	private static function log(string $level, string $message, array $context = array()): void {
		if (!function_exists('wc_get_logger')) {
			return;
		}
		$logger  = wc_get_logger();
		$payload = $message;
		if ($context) {
			$payload .= ' | ' . wp_json_encode($context);
		}
		if (in_array($level, array('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'), true)) {
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
		SorteoSeguro_MP_Auto_Complete::init();
	},
	20
);
