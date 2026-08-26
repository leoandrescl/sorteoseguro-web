<?php
/**
 * Plugin Name: Sorteo Seguro – Columna correo pedido
 * Description: Columnas en pedidos: correo al cliente y correo al admin. Reintenta cada uno por separado si no salió (no altera pagos).
 * Author: Sorteo Seguro
 * Version: 1.2.0
 *
 * mu-plugin: no se desactiva desde el admin; borrar este archivo para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Order_Email_Status {

	const VERSION              = '1.2.0';
	const COL_CUSTOMER         = 'ss_order_email_customer';
	const COL_ADMIN            = 'ss_order_email_admin';
	const LOG_SOURCE           = 'sorteoseguro-order-email-status';
	const OPTION_CUTOFF        = 'ss_oe_autoresend_cutoff';
	const OPTION_CUSTOMER_TRACK = 'ss_oe_customer_track_since';
	const META_CUSTOMER_SENT   = '_ss_customer_order_email_sent';
	const META_CUSTOMER_DONE   = '_ss_oe_customer_autoresend_done';
	const META_CUSTOMER_TRIES  = '_ss_oe_customer_autoresend_attempts';
	const META_ADMIN_DONE      = '_ss_oe_admin_autoresend_done';
	const META_ADMIN_TRIES     = '_ss_oe_admin_autoresend_attempts';
	const META_DONE_LEGACY     = '_ss_oe_autoresend_done';
	const CRON_HOOK            = 'ss_oe_scan_unsent';
	const CRON_SCHEDULE        = 'ss_oe_every_five_minutes';
	const SCAN_LIMIT           = 40;
	const MIN_AGE_SECONDS      = 300;
	const MAX_ATTEMPTS         = 1;
	const TRANSIENT_PF         = 'ss_oe_lock_';

	public static function init(): void {
		add_filter('cron_schedules', array(__CLASS__, 'register_cron_schedule'));
		add_action('init', array(__CLASS__, 'bootstrap_runtime'), 20);
		add_action(self::CRON_HOOK, array(__CLASS__, 'scan_unsent_orders'));
		add_action('woocommerce_email_sent', array(__CLASS__, 'on_email_sent'), 10, 3);

		if (!is_admin()) {
			return;
		}

		add_filter('woocommerce_shop_order_list_table_columns', array(__CLASS__, 'add_column'), 30);
		add_action('woocommerce_shop_order_list_table_custom_column', array(__CLASS__, 'render_hpos'), 10, 2);

		add_filter('manage_edit-shop_order_columns', array(__CLASS__, 'add_column'), 30);
		add_action('manage_shop_order_posts_custom_column', array(__CLASS__, 'render_legacy'), 10, 2);

		add_action('admin_head', array(__CLASS__, 'print_admin_css'));
	}

	public static function register_cron_schedule(array $schedules): array {
		if (!isset($schedules[self::CRON_SCHEDULE])) {
			$schedules[self::CRON_SCHEDULE] = array(
				'interval' => 300,
				'display'  => 'Sorteo Seguro correo pedido (5 min)',
			);
		}
		return $schedules;
	}

	public static function bootstrap_runtime(): void {
		self::ensure_cutoff();
		self::ensure_customer_track_since();
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + 60, self::CRON_SCHEDULE, self::CRON_HOOK);
		}
	}

	private static function ensure_cutoff(): void {
		$cutoff = get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '' || $cutoff === false) {
			update_option(self::OPTION_CUTOFF, gmdate('Y-m-d H:i:s'), false);
			self::log('info', 'Autoresend cutoff initialized', array('cutoff' => get_option(self::OPTION_CUTOFF)));
		}
	}

	private static function ensure_customer_track_since(): void {
		$since = get_option(self::OPTION_CUSTOMER_TRACK, '');
		if ($since === '' || $since === false) {
			update_option(self::OPTION_CUSTOMER_TRACK, gmdate('Y-m-d H:i:s'), false);
		}
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public static function add_column(array $columns): array {
		$out = array();
		foreach ($columns as $key => $label) {
			if ($key === self::COL_CUSTOMER || $key === self::COL_ADMIN || $key === 'ss_order_email') {
				continue;
			}
			$out[$key] = $label;
			if ($key === 'order_status') {
				$out[self::COL_CUSTOMER] = 'Cliente';
				$out[self::COL_ADMIN]    = 'Admin';
			}
		}
		if (!isset($out[self::COL_CUSTOMER])) {
			$out[self::COL_CUSTOMER] = 'Cliente';
		}
		if (!isset($out[self::COL_ADMIN])) {
			$out[self::COL_ADMIN] = 'Admin';
		}
		return $out;
	}

	/**
	 * @param string             $column
	 * @param WC_Order|int|mixed $order
	 */
	public static function render_hpos($column, $order): void {
		if (!$order instanceof WC_Order && function_exists('wc_get_order')) {
			$order = wc_get_order($order);
		}
		self::render_column((string) $column, $order instanceof WC_Order ? $order : null);
	}

	/**
	 * @param string $column
	 * @param int    $post_id
	 */
	public static function render_legacy($column, $post_id): void {
		$order = function_exists('wc_get_order') ? wc_get_order((int) $post_id) : null;
		self::render_column((string) $column, $order instanceof WC_Order ? $order : null);
	}

	private static function render_column(string $column, ?WC_Order $order): void {
		if ($column !== self::COL_CUSTOMER && $column !== self::COL_ADMIN) {
			return;
		}
		$which = $column === self::COL_ADMIN ? 'admin' : 'customer';
		self::echo_cell($order, $which);
	}

	/**
	 * @param 'customer'|'admin' $which
	 */
	private static function echo_cell(?WC_Order $order, string $which): void {
		if (!$order) {
			echo '—';
			return;
		}

		$status = $order->get_status();
		if (!in_array($status, array('processing', 'completed'), true)) {
			echo '<span class="ss-oe-dash" aria-hidden="true">—</span>';
			return;
		}

		$sent = $which === 'admin' ? self::is_admin_sent($order) : self::is_customer_sent($order);
		if ($sent) {
			echo '<span class="ss-oe-badge ss-oe-badge--yes">Enviado</span>';
			return;
		}

		$resent = $which === 'admin'
			? ((string) $order->get_meta(self::META_ADMIN_DONE) === 'yes')
			: ((string) $order->get_meta(self::META_CUSTOMER_DONE) === 'yes' || (string) $order->get_meta(self::META_DONE_LEGACY) === 'yes');

		if ($resent) {
			echo '<span class="ss-oe-badge ss-oe-badge--yes">Reenviado</span>';
			return;
		}

		echo '<span class="ss-oe-badge ss-oe-badge--no">No enviado</span>';
	}

	private static function is_admin_sent(WC_Order $order): bool {
		$value = method_exists($order, 'get_new_order_email_sent')
			? $order->get_new_order_email_sent()
			: $order->get_meta('_new_order_email_sent');

		return self::is_truthy_meta($value);
	}

	private static function is_customer_sent(WC_Order $order): bool {
		if (self::is_truthy_meta($order->get_meta(self::META_CUSTOMER_SENT))) {
			return true;
		}

		if (self::is_after_customer_track($order)) {
			return false;
		}

		return self::is_admin_sent($order);
	}

	private static function is_truthy_meta($value): bool {
		if ($value === true || $value === 1) {
			return true;
		}
		if (is_string($value) && in_array(strtolower($value), array('true', 'yes', '1'), true)) {
			return true;
		}
		return false;
	}

	/**
	 * @param mixed    $sent
	 * @param mixed    $email_id
	 * @param mixed    $email
	 */
	public static function on_email_sent($sent, $email_id = '', $email = null): void {
		if (!$sent) {
			return;
		}

		$id = is_string($email_id) ? $email_id : '';
		if ($id === '' && is_object($email) && isset($email->id)) {
			$id = (string) $email->id;
		}
		if (!in_array($id, array('customer_completed_order', 'customer_processing_order'), true)) {
			return;
		}

		$order = null;
		if (is_object($email) && isset($email->object) && $email->object instanceof WC_Order) {
			$order = $email->object;
		}
		if (!$order instanceof WC_Order) {
			return;
		}
		if (self::is_truthy_meta($order->get_meta(self::META_CUSTOMER_SENT))) {
			return;
		}

		$order->update_meta_data(self::META_CUSTOMER_SENT, 'true');
		if (method_exists($order, 'save_meta_data')) {
			$order->save_meta_data();
		} else {
			$order->save();
		}
	}

	public static function print_admin_css(): void {
		if (!self::is_orders_list_screen()) {
			return;
		}
		echo '<style id="ss-order-email-status">
.wp-list-table .column-ss_order_email_customer,
.wp-list-table .column-ss_order_email_admin{width:108px;}
.ss-oe-badge{display:inline-block;padding:3px 8px;border-radius:8px;font-size:12px;font-weight:600;line-height:1.3;white-space:nowrap;}
.ss-oe-badge--yes{background:#efebfa;color:#552c9a;}
.ss-oe-badge--no{background:#fef3c7;color:#92400e;}
.ss-oe-dash{color:#9ca3af;}
</style>';
	}

	private static function is_orders_list_screen(): bool {
		$page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
		if ($page === 'wc-orders') {
			return true;
		}
		if (!function_exists('get_current_screen')) {
			return false;
		}
		$screen = get_current_screen();
		if (!$screen) {
			return false;
		}
		return in_array($screen->id, array('woocommerce_page_wc-orders', 'edit-shop_order'), true);
	}

	public static function scan_unsent_orders(): void {
		if (!function_exists('wc_get_orders')) {
			return;
		}

		$cutoff = (string) get_option(self::OPTION_CUTOFF, '');
		if ($cutoff === '') {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'        => self::SCAN_LIMIT,
				'status'       => array('processing', 'completed'),
				'date_created' => '>=' . $cutoff,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'objects',
			)
		);

		if (!is_array($orders) || !$orders) {
			return;
		}

		$resent = 0;
		foreach ($orders as $order) {
			if (!$order instanceof WC_Order) {
				continue;
			}
			if (self::maybe_autoresend($order)) {
				$resent++;
			}
		}

		if ($resent > 0) {
			self::log('info', 'Autoresend scan finished', array('resent' => $resent));
		}
	}

	private static function maybe_autoresend(WC_Order $order): bool {
		if (!self::is_after_cutoff($order)) {
			return false;
		}
		if (!in_array($order->get_status(), array('processing', 'completed'), true)) {
			return false;
		}
		if (!self::is_old_enough($order)) {
			return false;
		}

		$need_customer = self::needs_customer_resend($order);
		$need_admin    = self::needs_admin_resend($order);
		if (!$need_customer && !$need_admin) {
			return false;
		}

		$order_id = $order->get_id();
		$lock_key = self::TRANSIENT_PF . $order_id;
		if (get_transient($lock_key)) {
			return false;
		}
		set_transient($lock_key, 1, 90);

		$did = false;
		if ($need_customer) {
			$did = self::autoresend_customer($order) || $did;
		}
		$order = wc_get_order($order_id);
		if ($order instanceof WC_Order && $need_admin) {
			$did = self::autoresend_admin($order) || $did;
		}

		delete_transient($lock_key);
		return $did;
	}

	private static function needs_customer_resend(WC_Order $order): bool {
		if (self::is_customer_sent($order)) {
			return false;
		}
		if ((string) $order->get_meta(self::META_CUSTOMER_DONE) === 'yes') {
			return false;
		}
		if ((int) $order->get_meta(self::META_CUSTOMER_TRIES) >= self::MAX_ATTEMPTS) {
			return false;
		}
		return true;
	}

	private static function needs_admin_resend(WC_Order $order): bool {
		if (self::is_admin_sent($order)) {
			return false;
		}
		if ((string) $order->get_meta(self::META_ADMIN_DONE) === 'yes') {
			return false;
		}
		if ((int) $order->get_meta(self::META_ADMIN_TRIES) >= self::MAX_ATTEMPTS) {
			return false;
		}
		return true;
	}

	private static function autoresend_customer(WC_Order $order): bool {
		$email_to = $order->get_billing_email();
		$ok       = ($email_to !== '' && is_email($email_to)) ? self::trigger_customer_email($order) : false;

		$attempts = (int) $order->get_meta(self::META_CUSTOMER_TRIES) + 1;
		$order->update_meta_data(self::META_CUSTOMER_TRIES, (string) $attempts);

		if ($ok) {
			$order->update_meta_data(self::META_CUSTOMER_DONE, 'yes');
			$order->add_order_note(
				sprintf(
					'Sorteo Seguro: correo al cliente reenviado automáticamente a %s (intento %d).',
					$email_to,
					$attempts
				)
			);
			self::log('info', 'Customer email autoresent', array('order_id' => $order->get_id(), 'email' => $email_to));
		} else {
			$order->add_order_note(
				sprintf('Sorteo Seguro: falló el reenvío automático del correo al cliente (intento %d).', $attempts)
			);
			self::log('error', 'Customer email autoresend failed', array('order_id' => $order->get_id()));
		}

		$order->save();
		return $ok;
	}

	private static function autoresend_admin(WC_Order $order): bool {
		$ok       = self::trigger_admin_email($order);
		$attempts = (int) $order->get_meta(self::META_ADMIN_TRIES) + 1;
		$order->update_meta_data(self::META_ADMIN_TRIES, (string) $attempts);

		if ($ok) {
			$order->update_meta_data(self::META_ADMIN_DONE, 'yes');
			$order->add_order_note(
				sprintf('Sorteo Seguro: correo de nuevo pedido al admin reenviado automáticamente (intento %d).', $attempts)
			);
			self::log('info', 'Admin email autoresent', array('order_id' => $order->get_id()));
		} else {
			$order->add_order_note(
				sprintf('Sorteo Seguro: falló el reenvío automático del correo al admin (intento %d).', $attempts)
			);
			self::log('error', 'Admin email autoresend failed', array('order_id' => $order->get_id()));
		}

		$order->save();
		return $ok;
	}

	private static function trigger_customer_email(WC_Order $order): bool {
		if (!function_exists('WC') || !WC()->mailer()) {
			return false;
		}
		$emails = WC()->mailer()->get_emails();
		if (!is_array($emails)) {
			return false;
		}

		try {
			$status = $order->get_status();
			if ($status === 'completed' && !empty($emails['WC_Email_Customer_Completed_Order'])) {
				$emails['WC_Email_Customer_Completed_Order']->trigger($order->get_id(), $order);
				return true;
			}
			if ($status === 'processing' && !empty($emails['WC_Email_Customer_Processing_Order'])) {
				$emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id(), $order);
				return true;
			}
		} catch (Throwable $e) {
			self::log('error', 'Customer autoresend exception: ' . $e->getMessage(), array('order_id' => $order->get_id()));
		}
		return false;
	}

	private static function trigger_admin_email(WC_Order $order): bool {
		if (!function_exists('WC') || !WC()->mailer()) {
			return false;
		}
		$emails = WC()->mailer()->get_emails();
		if (empty($emails['WC_Email_New_Order'])) {
			return false;
		}

		try {
			$emails['WC_Email_New_Order']->trigger($order->get_id(), $order);
			$fresh = wc_get_order($order->get_id());
			return $fresh instanceof WC_Order && self::is_admin_sent($fresh);
		} catch (Throwable $e) {
			self::log('error', 'Admin autoresend exception: ' . $e->getMessage(), array('order_id' => $order->get_id()));
			return false;
		}
	}

	private static function is_after_cutoff(WC_Order $order): bool {
		return self::created_after_option($order, self::OPTION_CUTOFF);
	}

	private static function is_after_customer_track(WC_Order $order): bool {
		return self::created_after_option($order, self::OPTION_CUSTOMER_TRACK);
	}

	private static function created_after_option(WC_Order $order, string $option): bool {
		$cutoff = (string) get_option($option, '');
		if ($cutoff === '') {
			return false;
		}
		$created = $order->get_date_created();
		if (!$created) {
			return false;
		}
		return $created->getTimestamp() >= strtotime($cutoff . ' UTC');
	}

	private static function is_old_enough(WC_Order $order): bool {
		$ref = $order->get_date_paid();
		if (!$ref) {
			$ref = $order->get_date_completed();
		}
		if (!$ref) {
			$ref = $order->get_date_created();
		}
		if (!$ref) {
			return false;
		}
		return (time() - $ref->getTimestamp()) >= self::MIN_AGE_SECONDS;
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
		if (in_array($level, array('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'), true)) {
			$logger->{$level}($payload, array('source' => self::LOG_SOURCE));
		} else {
			$logger->info($payload, array('source' => self::LOG_SOURCE));
		}
	}
}

SorteoSeguro_Order_Email_Status::init();
