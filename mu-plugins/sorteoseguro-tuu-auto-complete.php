<?php
/**
 * Plugin Name: Sorteo Seguro – TUU Auto Complete
 * Description: Procesa el callback POST de TUU/Webpay (wc-api=WCPluginGateway) y completa pedidos aunque el cliente no vuelva al thank-you. Complementa sorteoseguro-tuu-guard (redirect GET).
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: no se desactiva desde el admin; borrar este archivo para rollback.
 *
 * El plugin oficial TUU envía x_url_callback a WC()->api_request_url('WCPluginGateway') pero no registra
 * woocommerce_api_wcplugingateway. Sin este mu-plugin, un pago Webpay exitoso puede quedar en Pendiente.
 *
 * Cobertura:
 * 1) Callback server-to-server POST (fuente de verdad TUU)
 * 2) thank-you GET con x_result=completed (respaldo si el cliente vuelve)
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_TUU_Auto_Complete {

	const VERSION       = '1.0.0';
	const LOG_SOURCE    = 'sorteoseguro-tuu-auto-complete';
	const META_LOCK     = '_ss_tuu_ac_done';
	const TRANSIENT_PF  = 'ss_tuu_ac_lock_';
	const TRANSIENT_SK  = 'ss_tuu_ac_secret_key';
	const OPTION_CUTOFF = 'ss_tuu_ac_cutoff';

	const URL_PROD = 'https://core.payment.haulmer.com/api/v1/payment';
	const URL_DEV  = 'https://frontend-api.payment.haulmer.dev/v1/payment';

	public static function init(): void {
		add_action('init', array(__CLASS__, 'ensure_cutoff'), 15);

		// Handler que falta en el plugin TUU (POST x_url_callback).
		add_action('woocommerce_api_wcplugingateway', array(__CLASS__, 'handle_api_callback'), 5);

		// Respaldo browser redirect (ss_tuu-guard también cubre wcplugingateway en thank-you).
		add_action('woocommerce_thankyou', array(__CLASS__, 'on_thankyou'), 25, 1);
	}

	public static function ensure_cutoff(): void {
		$cutoff = get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '' || $cutoff === false) {
			update_option(self::OPTION_CUTOFF, gmdate('Y-m-d H:i:s'), false);
			self::log('info', 'Cutoff initialized', array('cutoff' => get_option(self::OPTION_CUTOFF)));
		}
	}

	/**
	 * Callback POST de TUU → wc-api=WCPluginGateway
	 */
	public static function handle_api_callback(): void {
		$payload = self::collect_x_payload();
		if ($payload === array()) {
			self::log('warning', 'Empty TUU callback payload');
			self::respond_callback(false);
			return;
		}

		self::log('info', 'TUU callback received', array(
			'x_reference' => isset($payload['x_reference']) ? (string) $payload['x_reference'] : '',
			'x_result'    => isset($payload['x_result']) ? (string) $payload['x_result'] : '',
			'method'      => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])) : '',
			'headers'     => self::request_headers(),
		));

		$processed = self::process_payload($payload, 'api_callback');
		self::respond_callback($processed);
	}

	public static function on_thankyou($order_id): void {
		if (!self::has_tuu_result_params()) {
			return;
		}
		$payload = self::collect_x_payload();
		if ($payload === array()) {
			return;
		}
		self::process_payload($payload, 'thankyou');
	}

	/**
	 * @param array<string, string> $payload
	 */
	private static function process_payload(array $payload, string $source): bool {
		$order_id = isset($payload['x_reference']) ? absint($payload['x_reference']) : 0;
		if (!$order_id || !function_exists('wc_get_order')) {
			self::log('warning', 'TUU payload missing x_reference', array('source' => $source));
			return false;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order || !self::is_tuu_order($order)) {
			self::log('warning', 'TUU callback for non-TUU order', array('order_id' => $order_id, 'source' => $source));
			return false;
		}

		if (!self::is_after_cutoff($order)) {
			return false;
		}

		if ($order->get_meta(self::META_LOCK) === 'yes' || $order->get_status() === 'completed') {
			return true;
		}

		$lock_key = self::TRANSIENT_PF . $order_id;
		if (get_transient($lock_key)) {
			return false;
		}
		set_transient($lock_key, 1, 90);

		$result = isset($payload['x_result']) ? strtolower((string) $payload['x_result']) : '';

		if ($result === 'failed' || $result === 'cancelled') {
			self::log('info', 'TUU payment not completed', array(
				'order_id'  => $order_id,
				'x_result'  => $result,
				'x_message' => isset($payload['x_message']) ? (string) $payload['x_message'] : '',
				'source'    => $source,
			));
			delete_transient($lock_key);
			return true;
		}

		if ($result !== 'completed') {
			delete_transient($lock_key);
			return false;
		}

		if (!self::validate_signature($payload, $source)) {
			self::log('error', 'TUU signature validation failed', array('order_id' => $order_id, 'source' => $source));
			delete_transient($lock_key);
			return false;
		}

		if (!self::amount_matches($order, $payload)) {
			self::log('error', 'TUU amount mismatch', array(
				'order_id' => $order_id,
				'expected' => (string) round((float) $order->get_total()),
				'got'      => isset($payload['x_amount']) ? (string) $payload['x_amount'] : '',
				'source'   => $source,
			));
			delete_transient($lock_key);
			return false;
		}

		$txn = isset($payload['x_timestamp']) ? (string) $payload['x_timestamp'] : 'tuu-webpay';
		self::complete_tuu_order($order, $txn, $source);
		delete_transient($lock_key);
		return true;
	}

	private static function complete_tuu_order(WC_Order $order, string $txn, string $source): void {
		$order_id    = $order->get_id();
		$prev_status = $order->get_status();

		if (in_array($prev_status, array('cancelled', 'failed'), true)) {
			$order->update_status(
				'pending',
				sprintf(
					'Sorteo Seguro TUU Auto Complete: pago confirmado; restaurando desde %s.',
					$prev_status
				)
			);
			$order = wc_get_order($order_id);
			if (!$order instanceof WC_Order) {
				return;
			}
		}

		if (!$order->is_paid()) {
			$order->payment_complete($txn);
			$order = wc_get_order($order_id);
			if (!$order instanceof WC_Order) {
				return;
			}
		}

		if ($order->get_status() !== 'completed') {
			$order->update_status(
				'completed',
				sprintf(
					'Sorteo Seguro TUU Auto Complete: Webpay/TUU confirmado. Estado Completado (antes: %s). Origen: %s.',
					$prev_status,
					$source
				)
			);
		} else {
			$order->add_order_note(
				sprintf(
					'Sorteo Seguro TUU Auto Complete: Webpay/TUU confirmado. Origen: %s.',
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

		self::log('info', 'Order auto-completed via TUU', array(
			'order_id'     => $order_id,
			'source'       => $source,
			'prev_status'  => $prev_status,
			'final_status' => $order->get_status(),
		));
	}

	/**
	 * @return array<string, string>
	 */
	private static function collect_x_payload(): array {
		$raw = array_merge($_GET, $_POST);
		$out = array();
		foreach ($raw as $key => $value) {
			if (!is_string($key) || strpos($key, 'x_') !== 0) {
				continue;
			}
			if (is_array($value)) {
				continue;
			}
			$out[$key] = sanitize_text_field(wp_unslash((string) $value));
		}
		return $out;
	}

	private static function has_tuu_result_params(): bool {
		return isset($_GET['x_result']) || isset($_POST['x_result']);
	}

	/**
	 * Headers HTTP recibidos en el callback (para diagnóstico).
	 * @return array<string, string>
	 */
	private static function request_headers(): array {
		$out = array();
		foreach ($_SERVER as $key => $value) {
			if (strpos($key, 'HTTP_') !== 0) {
				continue;
			}
			$name = str_replace('_', '-', substr($key, 5));
			$out[$name] = is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
		}
		if (isset($_SERVER['CONTENT_TYPE'])) {
			$out['CONTENT-TYPE'] = sanitize_text_field(wp_unslash((string) $_SERVER['CONTENT_TYPE']));
		}
		return $out;
	}

	/**
	 * @param array<string, string> $payload
	 */
	private static function validate_signature(array $payload, string $source): bool {
		$received = isset($payload['x_signature']) ? (string) $payload['x_signature'] : '';
		if ($received === '') {
			// Callback POST debe traer firma; thank-you GET puede no traerla.
			return $source !== 'api_callback';
		}

		$secret = self::get_merchant_secret_key();
		if ($secret === '') {
			self::log('error', 'Missing TUU secret_key for signature validation');
			return false;
		}

		$expected = self::build_signature($payload, $secret);
		return hash_equals(strtolower($expected), strtolower($received));
	}

	/**
	 * @param array<string, string> $payload
	 */
	private static function build_signature(array $payload, string $secret): string {
		$data = array();
		foreach ($payload as $key => $value) {
			if (strpos($key, 'x_') === 0 && $key !== 'x_signature') {
				$data[$key] = $value;
			}
		}
		ksort($data);
		$message = '';
		foreach ($data as $key => $value) {
			$message .= $key . $value;
		}
		return hash_hmac('sha256', $message, $secret);
	}

	/**
	 * @param array<string, string> $payload
	 */
	private static function amount_matches(WC_Order $order, array $payload): bool {
		if (!isset($payload['x_amount'])) {
			return true;
		}
		$expected = (int) round((float) $order->get_total());
		$got      = (int) round((float) $payload['x_amount']);
		return $expected === $got;
	}

	private static function respond_callback(bool $ok): void {
		self::log('info', 'TUU callback response sent', array('http' => $ok ? 200 : 400));
		status_header($ok ? 200 : 400);
		echo $ok ? 'OK' : 'ERROR';
		exit;
	}

	private static function get_merchant_secret_key(): string {
		$cached = get_transient(self::TRANSIENT_SK);
		if (is_string($cached) && $cached !== '') {
			return $cached;
		}

		$settings = get_option('woocommerce_wcplugingateway_settings', array());
		if (!is_array($settings)) {
			return '';
		}

		$rut      = isset($settings['rut']) ? trim((string) $settings['rut']) : '';
		$clave    = isset($settings['clave_secreta']) ? trim((string) $settings['clave_secreta']) : '';
		$ambiente = isset($settings['ambiente']) ? (string) $settings['ambiente'] : 'PRODUCCION';
		if ($rut === '' || $clave === '') {
			return '';
		}

		$base = ($ambiente === 'DESARROLLO') ? self::URL_DEV : self::URL_PROD;

		$token_resp = wp_remote_get(
			$base . '/token/' . rawurlencode($rut),
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $clave,
				),
			)
		);

		if (is_wp_error($token_resp) || (int) wp_remote_retrieve_response_code($token_resp) !== 200) {
			self::log('error', 'TUU token request failed');
			return '';
		}

		$token_body = json_decode((string) wp_remote_retrieve_body($token_resp), true);
		$token      = is_array($token_body) && !empty($token_body['token']) ? (string) $token_body['token'] : '';
		if ($token === '') {
			return '';
		}

		$validate_resp = wp_remote_post(
			$base . '/validatetoken',
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $clave,
				),
				'body'    => wp_json_encode(array('token' => $token)),
			)
		);

		if (is_wp_error($validate_resp) || (int) wp_remote_retrieve_response_code($validate_resp) !== 200) {
			self::log('error', 'TUU validateToken request failed');
			return '';
		}

		$validate_body = json_decode((string) wp_remote_retrieve_body($validate_resp), true);
		$secret        = is_array($validate_body) && !empty($validate_body['secret_key']) ? (string) $validate_body['secret_key'] : '';
		if ($secret !== '') {
			set_transient(self::TRANSIENT_SK, $secret, 50 * MINUTE_IN_SECONDS);
		}
		return $secret;
	}

	private static function is_after_cutoff(WC_Order $order): bool {
		$cutoff = (string) get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '') {
			return false;
		}
		$created = $order->get_date_created();
		return $created && $created->getTimestamp() >= strtotime($cutoff . ' UTC');
	}

	private static function is_tuu_order(WC_Order $order): bool {
		return (string) $order->get_payment_method() === 'wcplugingateway';
	}

	/**
	 * @param array<string, mixed> $context
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
		SorteoSeguro_TUU_Auto_Complete::init();
	},
	20
);
