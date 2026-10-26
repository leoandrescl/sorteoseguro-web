<?php
/**
 * Plugin Name: Sorteo Seguro – Flow Auto Complete
 * Description: Si Flow (flowpayment) aprueba el pago, fuerza el pedido a Completado. El plugin oficial llama payment_complete() y deja el pedido en Procesando; este mu-plugin lo completa igual que MP/TUU/MACH.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: no se desactiva desde el admin; borrar este archivo para rollback.
 *
 * Cobertura:
 * 1) thank-you (si el cliente vuelve)
 * 2) woocommerce_order_status_processing / payment_complete (callback_return y callback_confirm de Flow)
 * 3) Reintentos Action Scheduler a 1/3/5/10/20 min tras crear el pedido
 * 4) Cron cada 2 min: red de seguridad para pedidos Flow pagados que quedaron sin completar
 *
 * Solo pedidos creados desde la activación (option ss_flow_ac_cutoff). No modifica el plugin oficial de Flow.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Flow_Auto_Complete {

	const VERSION       = '1.0.0';
	const LOG_SOURCE    = 'sorteoseguro-flow-auto-complete';
	const META_LOCK     = '_ss_flow_ac_done';
	const META_SCHEDULE = '_ss_flow_ac_scheduled';
	const TRANSIENT_PF  = 'ss_flow_ac_lock_';
	const OPTION_CUTOFF = 'ss_flow_ac_cutoff';
	const CRON_HOOK     = 'ss_flow_ac_scan_pending';
	const AS_HOOK       = 'ss_flow_ac_check_order';
	const SCAN_LIMIT    = 40;

	/** @var int[] minutos tras checkout */
	const RETRY_OFFSETS = array(1, 3, 5, 10, 20);

	public static function init(): void {
		add_filter('cron_schedules', array(__CLASS__, 'register_cron_schedule'));

		add_action('woocommerce_thankyou', array(__CLASS__, 'on_thankyou'), 30, 1);

		// Flow llama payment_complete() al confirmar el pago y deja el pedido Procesando → forzar Completado.
		add_action('woocommerce_order_status_processing', array(__CLASS__, 'on_processing'), 60, 2);
		add_action('woocommerce_payment_complete', array(__CLASS__, 'on_payment_complete'), 60, 1);

		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'schedule_retries_for_order'), 50, 1);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'schedule_retries_for_order'), 50, 1);

		add_action(self::AS_HOOK, array(__CLASS__, 'check_order'), 10, 1);

		add_action(self::CRON_HOOK, array(__CLASS__, 'scan_pending_orders'));
		add_action('init', array(__CLASS__, 'bootstrap_runtime'), 20);
	}

	public static function bootstrap_runtime(): void {
		self::ensure_cutoff();
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + 60, 'ss_flow_ac_every_two_minutes', self::CRON_HOOK);
		}
	}

	public static function register_cron_schedule(array $schedules): array {
		if (!isset($schedules['ss_flow_ac_every_two_minutes'])) {
			$schedules['ss_flow_ac_every_two_minutes'] = array(
				'interval' => 120,
				'display'  => 'Sorteo Seguro Flow Auto Complete (2 min)',
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
	 * @param int           $order_id
	 * @param WC_Order|null $order
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
		if (!self::is_flow_order($order)) {
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

		self::log('info', 'Scheduled Flow auto-complete retries', array(
			'order_id' => $order_id,
			'offsets'  => self::RETRY_OFFSETS,
		));
	}

	private static function enqueue_check(int $order_id, int $delay_seconds): void {
		$timestamp = time() + max(0, $delay_seconds);

		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, self::AS_HOOK, array($order_id), 'sorteoseguro-flow-auto-complete');
			return;
		}

		wp_schedule_single_event($timestamp, self::AS_HOOK, array($order_id));
	}

	/**
	 * Red de seguridad: pedidos Flow nuevos pagados que no llegaron a Completado.
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
				'status'         => array('pending', 'on-hold', 'processing'),
				'date_created'   => '>=' . $cutoff,
				'payment_method' => 'flowpayment',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
			)
		);

		if (!is_array($orders)) {
			return;
		}

		$checked = 0;
		foreach ($orders as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order instanceof WC_Order) {
				continue;
			}
			if (!self::is_flow_order($order)) {
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

		if (!self::is_flow_order($order)) {
			return;
		}

		if (!self::is_after_cutoff($order)) {
			return;
		}

		if ($order->get_meta(self::META_LOCK) === 'yes') {
			return;
		}

		$status = $order->get_status();
		if ($status === 'completed') {
			$order->update_meta_data(self::META_LOCK, 'yes');
			$order->save();
			return;
		}
		if (!in_array($status, array('pending', 'on-hold', 'processing'), true)) {
			return;
		}

		// Solo forzamos cuando Flow ya marcó el pedido como pagado.
		if (!$order->is_paid()) {
			return;
		}

		$lock_key = self::TRANSIENT_PF . $order_id;
		if (get_transient($lock_key)) {
			return;
		}
		set_transient($lock_key, 1, 90);

		self::force_to_completed($order, $source, $status);
		delete_transient($lock_key);
	}

	/**
	 * Fuerza Completado y deja nota visible en el pedido.
	 */
	private static function force_to_completed(WC_Order $order, string $source, string $prev_status): void {
		$order_id = $order->get_id();

		if ($order->get_status() !== 'completed') {
			$order->update_status(
				'completed',
				sprintf(
					'Sorteo Seguro Flow Auto Complete: pago Flow aprobado. Estado forzado a Completado (antes: %s). Origen: %s.',
					$prev_status,
					$source
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					'Sorteo Seguro Flow Auto Complete: pago Flow aprobado. Pedido Completado. Origen: %s.',
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

		self::log('info', 'Order auto-completed via Flow', array(
			'order_id'     => $order_id,
			'source'       => $source,
			'prev_status'  => $prev_status,
			'final_status' => $order->get_status(),
		));
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

	private static function is_flow_order(WC_Order $order): bool {
		return (string) $order->get_payment_method() === 'flowpayment';
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
		SorteoSeguro_Flow_Auto_Complete::init();
	},
	20
);
