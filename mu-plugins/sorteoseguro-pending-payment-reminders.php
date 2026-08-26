<?php
/**
 * Plugin Name: Sorteo Seguro – Recordatorios pago pendiente
 * Description: Envía recordatorios automáticos a pedidos en Pendiente de pago (30 min y +2 h). Solo pedidos nuevos. No modifica gateways ni el auto-complete de MP. Incluye menú aparte para invitar a armar el carrito.
 * Author: Sorteo Seguro
 * Version: 1.2.5
 *
 * mu-plugin: borrar este archivo para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Pending_Payment_Reminders {

	const VERSION           = '1.2.5';
	const LOG_SOURCE        = 'sorteoseguro-pending-reminders';
	const OPTION_CUTOFF     = 'ss_ppr_cutoff';
	const OPTION_LOG        = 'ss_ppr_send_log';
	const OPTION_DELAY_1    = 'ss_ppr_delay_1_minutes';
	const OPTION_DELAY_2    = 'ss_ppr_delay_2_minutes';
	const META_SCHEDULED    = '_ss_ppr_scheduled';
	const META_R1           = '_ss_ppr_reminder_1_at';
	const META_R2           = '_ss_ppr_reminder_2_at';
	const META_SKIP         = '_ss_ppr_skip';
	const META_PAID_LOG     = '_ss_ppr_paid_logged';
	const META_REBUY        = '_ss_ppr_rebuy_at';
	const AS_HOOK           = 'ss_ppr_send_reminder';
	const ADMIN_REBUY       = 'ss_ppr_send_rebuy';
	const ADMIN_REBUY_TEST  = 'ss_ppr_send_rebuy_test';
	const LOG_MAX           = 400;
	const DEFAULT_DELAY_1   = 30;  // minutos tras crear el pedido
	const DEFAULT_DELAY_2   = 120; // minutos tras el 1.er correo

	/** Evita tratar nuestro envío automático como “manual”. */
	private static $sending_internally = false;

	public static function init(): void {
		add_action('init', array(__CLASS__, 'ensure_cutoff'), 5);
		add_action('admin_init', array(__CLASS__, 'ensure_default_delays'));

		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'schedule_for_order'), 60, 1);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'schedule_for_order'), 60, 1);

		add_action(self::AS_HOOK, array(__CLASS__, 'send_reminder'), 10, 2);

		// Envío manual desde Acciones del pedido → cuenta como 1.er recordatorio.
		add_action('woocommerce_after_resend_order_email', array(__CLASS__, 'on_customer_invoice_resent'), 10, 2);

		// Si paga después de recordatorio(s), dejarlo en el registro.
		add_action('woocommerce_order_status_changed', array(__CLASS__, 'on_order_status_changed'), 40, 4);
		add_action('woocommerce_payment_complete', array(__CLASS__, 'on_payment_complete'), 40, 1);

		add_action('add_meta_boxes', array(__CLASS__, 'register_order_metabox'), 40);
		add_action('add_meta_boxes', array(__CLASS__, 'register_rebuy_metabox'), 41);
		add_action('admin_menu', array(__CLASS__, 'register_admin_menu'), 60);
		add_action('admin_post_' . self::ADMIN_REBUY, array(__CLASS__, 'handle_send_rebuy'));
		add_action('admin_post_' . self::ADMIN_REBUY_TEST, array(__CLASS__, 'handle_send_rebuy_test'));
	}

	public static function ensure_cutoff(): void {
		if (get_option(self::OPTION_CUTOFF, '') === '') {
			update_option(self::OPTION_CUTOFF, gmdate('Y-m-d H:i:s'), false);
			self::log('info', 'Cutoff initialized', array('cutoff' => get_option(self::OPTION_CUTOFF)));
		}
	}

	public static function ensure_default_delays(): void {
		if (get_option(self::OPTION_DELAY_1, null) === null) {
			update_option(self::OPTION_DELAY_1, self::DEFAULT_DELAY_1, false);
		}
		if (get_option(self::OPTION_DELAY_2, null) === null) {
			update_option(self::OPTION_DELAY_2, self::DEFAULT_DELAY_2, false);
		}
	}

	public static function get_delay_1_minutes(): int {
		$m = (int) get_option(self::OPTION_DELAY_1, self::DEFAULT_DELAY_1);
		return max(1, min(10080, $m)); // 1 min … 7 días
	}

	public static function get_delay_2_minutes(): int {
		$m = (int) get_option(self::OPTION_DELAY_2, self::DEFAULT_DELAY_2);
		return max(1, min(10080, $m));
	}

	public static function get_delay_1_seconds(): int {
		return self::get_delay_1_minutes() * MINUTE_IN_SECONDS;
	}

	public static function get_delay_2_seconds(): int {
		return self::get_delay_2_minutes() * MINUTE_IN_SECONDS;
	}

	/**
	 * @param int|WC_Order $order
	 */
	public static function schedule_for_order($order): void {
		$order = self::as_order($order);
		if (!$order) {
			return;
		}
		if (!self::is_after_cutoff($order)) {
			return;
		}
		if ($order->get_meta(self::META_SCHEDULED) === 'yes') {
			return;
		}
		if ($order->get_status() !== 'pending') {
			return;
		}
		if ((float) $order->get_total() <= 0) {
			return;
		}

		$order_id = $order->get_id();
		$d1       = self::get_delay_1_minutes();
		$d2       = self::get_delay_2_minutes();
		self::enqueue($order_id, 1, time() + self::get_delay_1_seconds());

		$order->update_meta_data(self::META_SCHEDULED, 'yes');
		$order->save();

		$order->add_order_note(
			sprintf(
				'Sorteo Seguro: programados recordatorios de pago pendiente (1.º a los %d min; 2.º %d min después del primero si sigue pendiente).',
				$d1,
				$d2
			)
		);

		self::log('info', 'Reminders scheduled', array('order_id' => $order_id));
	}

	private static function enqueue(int $order_id, int $which, int $timestamp): void {
		$args = array($order_id, $which);
		if (function_exists('as_schedule_single_action')) {
			as_schedule_single_action($timestamp, self::AS_HOOK, $args, 'sorteoseguro-pending-reminders');
			return;
		}
		wp_schedule_single_event($timestamp, self::AS_HOOK, $args);
	}

	private static function unschedule(int $order_id, int $which): void {
		$args = array($order_id, $which);
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions(self::AS_HOOK, $args, 'sorteoseguro-pending-reminders');
			return;
		}
		wp_clear_scheduled_hook(self::AS_HOOK, $args);
	}

	/**
	 * Si staff envía “detalles del pedido” a mano, cuenta como recordatorio #1
	 * y el automático de los 30 min no se vuelve a mandar.
	 *
	 * @param WC_Order $order
	 * @param string   $email_type
	 */
	public static function on_customer_invoice_resent($order, $email_type): void {
		if (self::$sending_internally) {
			return;
		}
		if ((string) $email_type !== 'customer_invoice') {
			return;
		}

		$order = self::as_order($order);
		if (!$order) {
			return;
		}
		if (!self::is_after_cutoff($order)) {
			return;
		}
		// Ya hubo 1.er recordatorio (auto o manual previo): no reiniciar ciclo.
		if ($order->get_meta(self::META_R1)) {
			return;
		}

		$order_id = $order->get_id();
		$email_to = $order->get_billing_email();
		$now_gmt  = gmdate('Y-m-d H:i:s');

		self::unschedule($order_id, 1);

		$order->update_meta_data(self::META_R1, $now_gmt);
		if ($order->get_meta(self::META_SCHEDULED) !== 'yes') {
			$order->update_meta_data(self::META_SCHEDULED, 'yes');
		}
		$order->add_order_note(
				sprintf(
				'Sorteo Seguro: el envío manual de detalles del pedido cuenta como recordatorio #1 (%s). El automático del 1.º no se enviará. 2.º recordatorio en %d min si sigue pendiente.',
				$email_to !== '' ? $email_to : 'sin email',
				self::get_delay_2_minutes()
			)
		);
		$order->save();

		self::append_log(
			array(
				'time'     => gmdate('c'),
				'order_id' => $order_id,
				'which'    => 1,
				'email'    => $email_to,
				'result'   => 'manual_counts_as_r1',
			)
		);

		if (!$order->get_meta(self::META_R2) && $order->get_status() === 'pending') {
			self::unschedule($order_id, 2);
			self::enqueue($order_id, 2, time() + self::get_delay_2_seconds());
		}

		self::log(
			'info',
			'Manual invoice counted as reminder #1',
			array(
				'order_id' => $order_id,
				'email'    => $email_to,
			)
		);
	}

	/**
	 * @param int      $order_id
	 * @param string   $status_from
	 * @param string   $status_to
	 * @param WC_Order $order
	 */
	public static function on_order_status_changed($order_id, $status_from, $status_to, $order): void {
		if (!in_array((string) $status_to, array('processing', 'completed'), true)) {
			return;
		}
		self::maybe_log_paid_after_reminders($order, (string) $status_from, (string) $status_to);
	}

	/**
	 * @param int $order_id
	 */
	public static function on_payment_complete($order_id): void {
		$order = wc_get_order(absint($order_id));
		if (!$order instanceof WC_Order) {
			return;
		}
		self::maybe_log_paid_after_reminders($order, 'pending', $order->get_status());
	}

	/**
	 * @param WC_Order|mixed $order
	 */
	private static function maybe_log_paid_after_reminders($order, string $from, string $to): void {
		$order = self::as_order($order);
		if (!$order) {
			return;
		}
		if (!self::is_after_cutoff($order)) {
			return;
		}
		if ($order->get_meta(self::META_PAID_LOG) === 'yes') {
			return;
		}

		$r1 = (string) $order->get_meta(self::META_R1);
		$r2 = (string) $order->get_meta(self::META_R2);
		if ($r1 === '' && $r2 === '') {
			// Sin recordatorios enviados: solo cancelar pendientes programados.
			self::unschedule($order->get_id(), 1);
			self::unschedule($order->get_id(), 2);
			return;
		}

		$order_id = $order->get_id();
		self::unschedule($order_id, 1);
		self::unschedule($order_id, 2);

		$result = ($r2 !== '') ? 'paid_after_r2' : 'paid_after_r1';

		self::append_log(
			array(
				'time'     => gmdate('c'),
				'order_id' => $order_id,
				'which'    => 'pago',
				'email'    => $order->get_billing_email(),
				'result'   => $result,
				'from'     => $from,
				'to'       => $to,
				'r1_at'    => $r1,
				'r2_at'    => $r2,
				'total'    => $order->get_total(),
			)
		);

		$order->update_meta_data(self::META_PAID_LOG, 'yes');
		$order->add_order_note(
			sprintf(
				'Sorteo Seguro: pago registrado en el log de recordatorios (%s). Recordatorio(s) previo(s): %s.',
				$result,
				$r2 !== '' ? '#1 y #2' : '#1'
			)
		);
		$order->save();

		self::log(
			'info',
			'Paid after reminder(s)',
			array(
				'order_id' => $order_id,
				'result'   => $result,
				'to'       => $to,
			)
		);
	}

	/**
	 * @param int|string $order_id
	 * @param int|string $which 1|2
	 */
	public static function send_reminder($order_id, $which = 1): void {
		$order_id = absint($order_id);
		$which    = (int) $which;
		if (!$order_id || !in_array($which, array(1, 2), true)) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}
		if (!self::is_after_cutoff($order)) {
			return;
		}
		if ($order->get_meta(self::META_SKIP) === 'yes') {
			return;
		}

		$meta_key = $which === 1 ? self::META_R1 : self::META_R2;
		if ($order->get_meta($meta_key)) {
			return;
		}

		if ($order->get_status() !== 'pending') {
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => $which,
					'email'    => $order->get_billing_email(),
					'result'   => 'skipped_not_pending',
					'status'   => $order->get_status(),
				)
			);
			self::log(
				'info',
				'Reminder skipped; order not pending',
				array(
					'order_id' => $order_id,
					'which'    => $which,
					'status'   => $order->get_status(),
				)
			);
			return;
		}

		$email_to = $order->get_billing_email();
		if ($email_to === '') {
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => $which,
					'email'    => '',
					'result'   => 'skipped_no_email',
				)
			);
			return;
		}

		$ok = self::send_customer_invoice($order);
		$now_gmt = gmdate('Y-m-d H:i:s');

		if ($ok) {
			$order->update_meta_data($meta_key, $now_gmt);
			$order->add_order_note(
				sprintf(
					'Sorteo Seguro: recordatorio de pago pendiente #%d enviado a %s.',
					$which,
					$email_to
				)
			);
			$order->save();

			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => $which,
					'email'    => $email_to,
					'result'   => 'sent',
					'total'    => $order->get_total(),
				)
			);

			self::log(
				'info',
				'Reminder sent',
				array(
					'order_id' => $order_id,
					'which'    => $which,
					'email'    => $email_to,
				)
			);

			if ($which === 1) {
				self::enqueue($order_id, 2, time() + self::get_delay_2_seconds());
			}
		} else {
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => $which,
					'email'    => $email_to,
					'result'   => 'send_failed',
				)
			);
			self::log(
				'error',
				'Reminder send failed',
				array(
					'order_id' => $order_id,
					'which'    => $which,
				)
			);
		}
	}

	private static function send_customer_invoice(WC_Order $order): bool {
		if (!function_exists('WC') || !WC()->mailer()) {
			return false;
		}

		self::$sending_internally = true;
		try {
			do_action('woocommerce_before_resend_order_emails', $order, 'customer_invoice');

			$mailer = WC()->mailer();
			if (method_exists($mailer, 'customer_invoice')) {
				$mailer->customer_invoice($order);
			} else {
				$emails = $mailer->get_emails();
				if (empty($emails['WC_Email_Customer_Invoice'])) {
					return false;
				}
				$emails['WC_Email_Customer_Invoice']->trigger($order->get_id());
			}

			do_action('woocommerce_after_resend_order_email', $order, 'customer_invoice');
			return true;
		} catch (Throwable $e) {
			self::log('error', 'Exception sending invoice: ' . $e->getMessage(), array('order_id' => $order->get_id()));
			return false;
		} finally {
			self::$sending_internally = false;
		}
	}

	public static function register_order_metabox(): void {
		$screens = array('shop_order');
		if (function_exists('wc_get_page_screen_id')) {
			$screens[] = wc_get_page_screen_id('shop-order');
		}
		foreach (array_unique($screens) as $screen) {
			add_meta_box(
				'ss_ppr_reminders',
				'Recordatorios de pago',
				array(__CLASS__, 'render_order_metabox'),
				$screen,
				'side',
				'default'
			);
		}
	}

	public static function render_order_metabox($post_or_order): void {
		$order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);
		if (!$order instanceof WC_Order) {
			echo '<p>Pedido no disponible.</p>';
			return;
		}

		$r1 = (string) $order->get_meta(self::META_R1);
		$r2 = (string) $order->get_meta(self::META_R2);
		$sch = (string) $order->get_meta(self::META_SCHEDULED);

		echo '<p><strong>Programado:</strong> ' . esc_html($sch === 'yes' ? 'Sí' : 'No') . '</p>';
		echo '<p><strong>1.er recordatorio:</strong> ' . esc_html($r1 !== '' ? self::format_local($r1) : 'Pendiente / no enviado') . '</p>';
		echo '<p><strong>2.º recordatorio:</strong> ' . esc_html($r2 !== '' ? self::format_local($r2) : 'Pendiente / no enviado') . '</p>';
		$paid = (string) $order->get_meta(self::META_PAID_LOG);
		if ($paid === 'yes') {
			echo '<p><strong>Pago tras recordatorio:</strong> sí (en el registro del menú)</p>';
		}
		echo '<p style="color:#666;font-size:12px;margin:0;">Si envías el correo a mano, cuenta como el 1.º. Los tiempos se configuran en WooCommerce → Recordatorios pago.</p>';
	}

	public static function register_rebuy_metabox(): void {
		$screens = array('shop_order');
		if (function_exists('wc_get_page_screen_id')) {
			$screens[] = wc_get_page_screen_id('shop-order');
		}
		foreach (array_unique($screens) as $screen) {
			add_meta_box(
				'ss_ppr_rebuy',
				'Invitar a armar carrito',
				array(__CLASS__, 'render_rebuy_metabox'),
				$screen,
				'side',
				'default'
			);
		}
	}

	public static function render_rebuy_metabox($post_or_order): void {
		$order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);
		if (!$order instanceof WC_Order) {
			echo '<p>Pedido no disponible.</p>';
			return;
		}

		$rebuy = (string) $order->get_meta(self::META_REBUY);
		if ($rebuy !== '') {
			echo '<p>Último envío: ' . esc_html(self::format_local($rebuy)) . '</p>';
		}
		if (self::is_rebuy_eligible($order)) {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
			echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_REBUY) . '" />';
			echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $order->get_id()) . '" />';
			wp_nonce_field('ss_ppr_send_rebuy');
			echo '<p style="color:#666;font-size:12px;">No usa el enlace de pago de este pedido. Invita a comprar de nuevo en la ficha.</p>';
			echo '<p><button type="submit" class="button">Enviar invitación</button></p>';
			echo '</form>';
		} else {
			echo '<p style="color:#666;font-size:12px;margin:0;">Disponible si el pedido está cancelado, fallido o pendiente con reserva vencida.</p>';
		}
		echo '<p style="color:#666;font-size:12px;margin:8px 0 0;"><a href="' . esc_url(admin_url('admin.php?page=ss-rebuy-invitation')) . '">Abrir listado</a></p>';
	}

	public static function register_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			'Recordatorios de pago',
			'Recordatorios pago',
			'manage_woocommerce',
			'ss-pending-payment-reminders',
			array(__CLASS__, 'render_admin_page')
		);
		add_submenu_page(
			'woocommerce',
			'Invitar a armar carrito',
			'Armar carrito',
			'manage_woocommerce',
			'ss-rebuy-invitation',
			array(__CLASS__, 'render_rebuy_admin_page')
		);
	}

	public static function render_admin_page(): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		self::ensure_default_delays();

		$saved = false;
		$error = '';
		if (isset($_POST['ss_ppr_save_settings']) && check_admin_referer('ss_ppr_save_settings')) {
			$d1 = isset($_POST['ss_ppr_delay_1']) ? (int) wp_unslash($_POST['ss_ppr_delay_1']) : self::DEFAULT_DELAY_1;
			$d2 = isset($_POST['ss_ppr_delay_2']) ? (int) wp_unslash($_POST['ss_ppr_delay_2']) : self::DEFAULT_DELAY_2;
			$d1 = max(1, min(10080, $d1));
			$d2 = max(1, min(10080, $d2));
			update_option(self::OPTION_DELAY_1, $d1, false);
			update_option(self::OPTION_DELAY_2, $d2, false);
			$saved = true;
			self::log(
				'info',
				'Reminder delays updated from admin',
				array(
					'delay_1_min' => $d1,
					'delay_2_min' => $d2,
				)
			);
		}

		$d1     = self::get_delay_1_minutes();
		$d2     = self::get_delay_2_minutes();
		$cutoff = (string) get_option(self::OPTION_CUTOFF, '');
		$log    = get_option(self::OPTION_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$log = array_reverse($log);

		echo '<div class="wrap">';
		echo '<h1>Recordatorios de pago pendiente</h1>';

		if ($saved) {
			echo '<div class="notice notice-success is-dismissible"><p>Tiempos guardados. Aplican a <strong>pedidos nuevos</strong> (los ya programados conservan su horario).</p></div>';
		}
		if ($error !== '') {
			echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
		}

		echo '<h2>Configuración de tiempos</h2>';
		echo '<form method="post" style="max-width:640px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 24px;">';
		wp_nonce_field('ss_ppr_save_settings');
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="ss_ppr_delay_1">1.er recordatorio (minutos)</label></th>';
		echo '<td><input name="ss_ppr_delay_1" id="ss_ppr_delay_1" type="number" min="1" max="10080" step="1" value="' . esc_attr((string) $d1) . '" class="small-text" /> ';
		echo '<p class="description">Minutos después de crear el pedido en Pendiente de pago. Actual: <strong>' . esc_html((string) $d1) . '</strong>.</p></td></tr>';
		echo '<tr><th scope="row"><label for="ss_ppr_delay_2">2.º recordatorio (minutos)</label></th>';
		echo '<td><input name="ss_ppr_delay_2" id="ss_ppr_delay_2" type="number" min="1" max="10080" step="1" value="' . esc_attr((string) $d2) . '" class="small-text" /> ';
		echo '<p class="description">Minutos después del 1.er correo (auto o manual). Actual: <strong>' . esc_html((string) $d2) . '</strong> (' . esc_html(self::human_minutes($d2)) . ').</p></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" name="ss_ppr_save_settings" class="button button-primary" value="1">Guardar tiempos</button></p>';
		echo '</form>';

		echo '<p>Envía automáticamente el correo de detalles del pedido (enlace para pagar) a pedidos en <strong>Pendiente de pago</strong>.</p>';
		echo '<ul>';
		echo '<li>1.er correo: <strong>' . esc_html((string) $d1) . ' min</strong> después de crear el pedido.</li>';
		echo '<li>2.º correo: <strong>' . esc_html((string) $d2) . ' min</strong> después del primero (' . esc_html(self::human_minutes($d2)) . '), si sigue pendiente.</li>';
		echo '<li>Si el staff envía el correo a mano antes, cuenta como el 1.º (no se duplica).</li>';
		echo '<li>Solo pedidos creados desde: <code>' . esc_html($cutoff) . ' UTC</code>.</li>';
		echo '</ul>';

		echo '<h2>Registro de envíos y pagos</h2>';
		echo '<p style="color:#666;max-width:900px;">Significado de <strong>Resultado</strong>:</p>';
		echo '<ul style="color:#666;max-width:900px;">';
		echo '<li><strong>Correo enviado</strong> — se mandó el recordatorio; el pedido seguía pendiente.</li>';
		echo '<li><strong>Envío manual (cuenta como 1.º)</strong> — el equipo envió el correo a mano; no se reenvía el automático de 30 min.</li>';
		echo '<li><strong>Pagó después del 1.er recordatorio</strong> / <strong>del 2.º</strong> — el cliente completó el pago tras ese aviso.</li>';
		echo '<li><strong>No enviado: ya no estaba pendiente</strong> — a la hora del envío el pedido ya no estaba pendiente. La columna <strong>¿Compró?</strong> dice si pagó, se canceló o falló.</li>';
		echo '<li><strong>No enviado: sin email</strong> / <strong>Error al enviar</strong> — faltaba correo o falló el envío.</li>';
		echo '</ul>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>Fecha (UTC)</th><th>Pedido</th><th>Evento</th><th>Email</th><th>Resultado</th><th>¿Compró?</th>';
		echo '</tr></thead><tbody>';

		if (!$log) {
			echo '<tr><td colspan="6">Aún no hay envíos registrados.</td></tr>';
		} else {
			$order_cache = array();
			foreach (array_slice($log, 0, 200) as $row) {
				if (!is_array($row)) {
					continue;
				}
				if ((string) ($row['which'] ?? '') === 'rebuy') {
					continue;
				}
				$oid  = isset($row['order_id']) ? (int) $row['order_id'] : 0;
				$link = $oid ? admin_url('post.php?post=' . $oid . '&action=edit') : '#';
				$order = null;
				if ($oid && function_exists('wc_get_order')) {
					if (!array_key_exists($oid, $order_cache)) {
						$got = wc_get_order($oid);
						$order_cache[$oid] = $got instanceof WC_Order ? $got : null;
					}
					$order = $order_cache[$oid];
					if ($order) {
						$link = $order->get_edit_order_url();
					}
				}
				echo '<tr>';
				echo '<td>' . esc_html((string) ($row['time'] ?? '')) . '</td>';
				echo '<td>' . ($oid ? '<a href="' . esc_url($link) . '">#' . esc_html((string) $oid) . '</a>' : '—') . '</td>';
				echo '<td>' . esc_html(self::label_event($row['which'] ?? '')) . '</td>';
				echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
				echo '<td>' . esc_html(self::label_result((string) ($row['result'] ?? ''))) . '</td>';
				echo '<td>' . esc_html(self::label_purchase_outcome($row, $order)) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * @param mixed $which
	 */
	private static function label_event($which): string {
		$w = is_string($which) || is_int($which) || is_float($which) ? (string) $which : '';
		$map = array(
			'1'    => '1.er recordatorio',
			'2'    => '2.º recordatorio',
			'pago'  => 'Pago del cliente',
			'rebuy' => 'Invitación a armar carrito',
		);
		return $map[$w] ?? ($w !== '' ? $w : '—');
	}

	private static function label_result(string $result): string {
		$map = array(
			'sent'                => 'Correo enviado',
			'manual_counts_as_r1' => 'Envío manual (cuenta como 1.º)',
			'paid_after_r1'       => 'Pagó después del 1.er recordatorio',
			'paid_after_r2'       => 'Pagó después del 2.º recordatorio',
			'skipped_not_pending' => 'No enviado: ya no estaba pendiente',
			'skipped_no_email'    => 'No enviado: sin email en el pedido',
			'send_failed'         => 'Error al enviar el correo',
			'rebuy_sent'          => 'Invitación enviada',
			'rebuy_test'          => 'Prueba enviada',
			'rebuy_not_eligible'  => 'No aplica invitación',
			'rebuy_no_email'      => 'Invitación: sin email',
			'rebuy_send_failed'   => 'Invitación: error al enviar',
		);
		return $map[$result] ?? ($result !== '' ? $result : '—');
	}

	/**
	 * @param array<string,mixed> $row
	 */
	private static function label_purchase_outcome(array $row, $order): string {
		$result = (string) ($row['result'] ?? '');
		if (in_array($result, array('paid_after_r1', 'paid_after_r2'), true)) {
			return 'Sí, compró';
		}

		$status = (string) ($row['status'] ?? '');
		if ($status === '' && $order instanceof WC_Order) {
			$status = $order->get_status();
		}
		$status = str_replace('wc-', '', $status);

		if ($result === 'skipped_not_pending') {
			return self::status_to_purchase_label($status, true);
		}

		if (in_array($result, array('sent', 'manual_counts_as_r1'), true)) {
			return self::status_to_purchase_label($status, false);
		}

		if ($status !== '') {
			return self::status_to_purchase_label($status, false);
		}

		return '—';
	}

	private static function status_to_purchase_label(string $status, bool $skipped_send): string {
		if ($status === '') {
			return $skipped_send ? 'No se envió: el pedido ya no estaba pendiente' : '—';
		}
		if (in_array($status, array('processing', 'completed'), true)) {
			return $skipped_send ? 'Sí, compró (por eso no se reenvió)' : 'Sí, compró';
		}
		if ($status === 'cancelled') {
			return $skipped_send ? 'No: se canceló (por eso no se envió)' : 'No: pedido cancelado';
		}
		if ($status === 'failed') {
			return $skipped_send ? 'No: pago fallido (por eso no se envió)' : 'No: pago fallido';
		}
		if ($status === 'refunded') {
			return 'No: reembolsado';
		}
		if ($status === 'pending' || $status === 'on-hold') {
			return 'Aún no';
		}
		$name = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : $status;
		if ($skipped_send) {
			return 'No se envió: quedó en ' . $name;
		}
		return 'No: ' . $name;
	}

	private static function human_minutes(int $minutes): string {
		if ($minutes < 60) {
			return $minutes . ' min';
		}
		$h = intdiv($minutes, 60);
		$m = $minutes % 60;
		if ($m === 0) {
			return $h . ' h';
		}
		return $h . ' h ' . $m . ' min';
	}

	/**
	 * @param array<string,mixed> $entry
	 */
	private static function append_log(array $entry): void {
		$log = get_option(self::OPTION_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$log[] = $entry;
		if (count($log) > self::LOG_MAX) {
			$log = array_slice($log, -self::LOG_MAX);
		}
		update_option(self::OPTION_LOG, $log, false);
	}

	private static function format_local(string $gmt): string {
		$ts = strtotime($gmt . ' UTC');
		if (!$ts) {
			return $gmt . ' UTC';
		}
		return wp_date('Y-m-d H:i:s', $ts) . ' (sitio)';
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

	public static function handle_send_rebuy(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('No autorizado.');
		}
		check_admin_referer('ss_ppr_send_rebuy');

		$order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
		$result   = self::send_rebuy_invitation($order_id);

		$redirect = wp_get_referer();
		if (!is_string($redirect) || $redirect === '') {
			$redirect = admin_url('admin.php?page=ss-rebuy-invitation');
		}
		if (strpos($redirect, 'ss-pending-payment-reminders') !== false) {
			$redirect = admin_url('admin.php?page=ss-rebuy-invitation');
		}
		$redirect = add_query_arg(
			array(
				'ss_ppr_rebuy' => $result,
				'ss_ppr_oid'   => $order_id,
			),
			$redirect
		);
		wp_safe_redirect($redirect);
		exit;
	}

	public static function handle_send_rebuy_test(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('No autorizado.');
		}
		check_admin_referer('ss_ppr_send_rebuy_test');

		$raw = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
		$result = 'test_invalid';
		if (is_email($raw)) {
			$ok = self::send_rebuy_test_email($raw);
			$result = $ok ? 'test_sent' : 'test_failed';
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => 0,
					'which'    => 'rebuy',
					'email'    => $raw,
					'result'   => $ok ? 'rebuy_test' : 'rebuy_send_failed',
				)
			);
		}

		$redirect = admin_url('admin.php?page=ss-rebuy-invitation');
		$redirect = add_query_arg(
			array(
				'ss_ppr_rebuy' => $result,
				'ss_ppr_to'    => $raw,
			),
			$redirect
		);
		wp_safe_redirect($redirect);
		exit;
	}

	/**
	 * @return string Resultado para el aviso admin.
	 */
	public static function send_rebuy_invitation(int $order_id): string {
		$order = self::as_order($order_id);
		if (!$order) {
			return 'missing';
		}

		$email = $order->get_billing_email();
		if ($email === '') {
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => 'rebuy',
					'email'    => '',
					'result'   => 'rebuy_no_email',
					'status'   => $order->get_status(),
				)
			);
			return 'no_email';
		}

		if (!self::is_rebuy_eligible($order)) {
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => 'rebuy',
					'email'    => $email,
					'result'   => 'rebuy_not_eligible',
					'status'   => $order->get_status(),
				)
			);
			return 'not_eligible';
		}

		$ok = self::send_rebuy_email($order);
		if (!$ok) {
			self::append_log(
				array(
					'time'     => gmdate('c'),
					'order_id' => $order_id,
					'which'    => 'rebuy',
					'email'    => $email,
					'result'   => 'rebuy_send_failed',
					'status'   => $order->get_status(),
				)
			);
			self::log('error', 'Rebuy invitation send failed', array('order_id' => $order_id));
			return 'send_failed';
		}

		$now_gmt = gmdate('Y-m-d H:i:s');
		$order->update_meta_data(self::META_REBUY, $now_gmt);
		$order->add_order_note(
			sprintf(
				'Sorteo Seguro: invitación a armar el carrito de nuevo enviada a %s. No usa el enlace de pago de este pedido.',
				$email
			)
		);
		$order->save();

		self::append_log(
			array(
				'time'     => gmdate('c'),
				'order_id' => $order_id,
				'which'    => 'rebuy',
				'email'    => $email,
				'result'   => 'rebuy_sent',
				'status'   => $order->get_status(),
			)
		);
		self::log('info', 'Rebuy invitation sent', array('order_id' => $order_id, 'email' => $email));
		return 'sent';
	}

	public static function is_rebuy_eligible(WC_Order $order): bool {
		if ($order->get_billing_email() === '') {
			return false;
		}
		$status = $order->get_status();
		if (in_array($status, array('cancelled', 'failed'), true)) {
			return true;
		}
		if ($status === 'pending' && self::is_hold_expired($order)) {
			return true;
		}
		return false;
	}

	private static function is_hold_expired(WC_Order $order): bool {
		$created = $order->get_date_created();
		if (!$created) {
			return false;
		}
		$minutes = (int) get_option('lty_settings_reserve_ticket_time_in_min', 30);
		$minutes = max(1, $minutes);
		return (time() - $created->getTimestamp()) > ($minutes * MINUTE_IN_SECONDS);
	}

	private static function send_rebuy_email(WC_Order $order): bool {
		if (!function_exists('WC') || !WC()->mailer()) {
			return false;
		}

		$to = $order->get_billing_email();
		if ($to === '') {
			return false;
		}

		$name = trim($order->get_billing_first_name());
		if ($name === '') {
			$name = 'hola';
		}

		$links = self::product_links_from_order($order);
		return self::dispatch_rebuy_email($to, $name, (string) $order->get_id(), $links, false);
	}

	private static function send_rebuy_test_email(string $to): bool {
		$home = self::front_home_url();
		$links = array(
			array(
				'title' => 'sorteos',
				'url'   => $home,
			),
		);
		return self::dispatch_rebuy_email($to, 'equipo', 'PRUEBA', $links, true);
	}

	private static function front_home_url(): string {
		return home_url('/');
	}

	/**
	 * @param array<int, array{title:string,url:string}> $links
	 */
	private static function dispatch_rebuy_email(string $to, string $name, string $order_ref, array $links, bool $is_test): bool {
		if (!function_exists('WC') || !WC()->mailer() || !is_email($to)) {
			return false;
		}

		$items_html = '';
		foreach ($links as $row) {
			$items_html .= '<p style="margin:12px 0 0;"><a href="' . esc_url($row['url']) . '" style="display:inline-block;background:#6b3bb8;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Ver ' . esc_html($row['title']) . '</a></p>';
		}

		$heading = 'Todavía puedes participar';
		$body    = '<p>Hola ' . esc_html($name) . ',</p>';
		if ($is_test) {
			$body .= '<p><strong>Esto es un correo de prueba.</strong> Así se ve la invitación a armar el carrito.</p>';
		}
		$body   .= '<p>Tu pedido #' . esc_html($order_ref) . ' no se completó, así que los <strong>DigiTickets</strong> de esa compra ya no están reservados.</p>';
		$body   .= '<p>Puedes armar tu carrito de nuevo cuando quieras. Si eliges un pack, el descuento se aplica al pagar.</p>';
		$body   .= $items_html !== '' ? $items_html : '<p style="margin:12px 0 0;"><a href="' . esc_url(self::front_home_url()) . '" style="display:inline-block;background:#6b3bb8;color:#fff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Ver sorteos</a></p>';
		$body   .= '<p style="margin-top:20px;color:#666;font-size:13px;">Este correo no reutiliza el pago del pedido anterior. Es una invitación para comprar de nuevo.</p>';

		$subject = 'Todavía estás a tiempo de participar — Sorteo Seguro';
		if ($is_test) {
			$subject = '[PRUEBA] ' . $subject;
		}
		$mailer = WC()->mailer();
		$html   = $mailer->wrap_message($heading, $body);

		try {
			return (bool) $mailer->send($to, $subject, $html);
		} catch (Throwable $e) {
			self::log('error', 'Rebuy email exception: ' . $e->getMessage(), array('to' => $to, 'test' => $is_test));
			return false;
		}
	}

	/**
	 * @return array<int, array{title:string,url:string}>
	 */
	private static function product_links_from_order(WC_Order $order): array {
		$out = array();
		$seen = array();
		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product_id')) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ($pid < 1 || isset($seen[$pid])) {
				continue;
			}
			$seen[$pid] = true;
			$product = $item->get_product();
			$url = $product ? $product->get_permalink() : get_permalink($pid);
			if (!is_string($url) || $url === '') {
				continue;
			}
			$title = $product ? $product->get_name() : $item->get_name();
			$title = trim(preg_replace('/^DigiTicket\s*\|\s*/iu', '', wp_strip_all_tags((string) $title)) ?? (string) $title);
			$out[] = array(
				'title' => $title !== '' ? $title : ('Sorteo #' . $pid),
				'url'   => $url,
			);
		}
		return $out;
	}

	private static function render_rebuy_notices(): void {
		if (!isset($_GET['ss_ppr_rebuy'])) {
			return;
		}
		$code = sanitize_key((string) wp_unslash($_GET['ss_ppr_rebuy']));
		$oid  = isset($_GET['ss_ppr_oid']) ? absint($_GET['ss_ppr_oid']) : 0;
		$to   = isset($_GET['ss_ppr_to']) ? sanitize_email(wp_unslash($_GET['ss_ppr_to'])) : '';
		$ref  = $oid > 0 ? ' pedido #' . $oid : '';
		$map  = array(
			'sent'          => array('success', 'Invitación a armar el carrito enviada' . $ref . '.'),
			'test_sent'     => array('success', 'Correo de prueba enviado' . ($to !== '' ? ' a ' . $to : '') . '.'),
			'test_invalid'  => array('error', 'Ingresa un correo válido para la prueba.'),
			'test_failed'   => array('error', 'No se pudo enviar el correo de prueba.'),
			'no_email'      => array('error', 'Ese pedido no tiene email de facturación.'),
			'not_eligible'  => array('error', 'Ese pedido no aplica (solo cancelado, fallido o pendiente con reserva vencida).'),
			'send_failed'   => array('error', 'No se pudo enviar la invitación' . $ref . '.'),
			'missing'       => array('error', 'No se encontró el pedido.'),
		);
		if (!isset($map[$code])) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr($map[$code][0]) . ' is-dismissible"><p>' . esc_html($map[$code][1]) . '</p></div>';
	}

	public static function render_rebuy_admin_page(): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>Invitar a armar el carrito</h1>';
		self::render_rebuy_notices();
		self::render_rebuy_test_box();
		self::render_rebuy_admin_section();
		self::render_rebuy_log();
		echo '</div>';
	}

	private static function render_rebuy_test_box(): void {
		$user  = wp_get_current_user();
		$prefill = ($user && is_email($user->user_email)) ? $user->user_email : '';
		echo '<h2>Enviar correo de prueba</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:640px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 24px;">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_REBUY_TEST) . '" />';
		wp_nonce_field('ss_ppr_send_rebuy_test');
		echo '<p>Manda el mismo correo de invitación a un email tuyo, sin tocar un pedido real. El asunto lleva <code>[PRUEBA]</code>.</p>';
		echo '<p><label for="ss_ppr_test_email"><strong>Correo</strong></label><br />';
		echo '<input type="email" name="test_email" id="ss_ppr_test_email" class="regular-text" value="' . esc_attr($prefill) . '" required /></p>';
		echo '<p class="submit" style="margin:0;"><button type="submit" class="button">Enviar prueba</button></p>';
		echo '</form>';
	}

	private static function render_rebuy_admin_section(): void {
		$candidates = self::list_rebuy_candidates();

		echo '<h2>Invitar a armar el carrito de nuevo</h2>';
		echo '<p style="max-width:900px;">Para pedidos <strong>cancelados</strong>, <strong>fallidos</strong> o <strong>pendiente de pago con números ya vencidos</strong>. El correo invita a comprar otra vez en la ficha; <strong>no</strong> manda el enlace de pago del pedido viejo.</p>';

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:640px;background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:12px 0 16px;">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_REBUY) . '" />';
		wp_nonce_field('ss_ppr_send_rebuy');
		echo '<p><label for="ss_ppr_rebuy_oid"><strong>Nº de pedido</strong></label><br />';
		echo '<input type="number" min="1" name="order_id" id="ss_ppr_rebuy_oid" class="regular-text" required /></p>';
		echo '<p class="submit" style="margin:0;"><button type="submit" class="button button-primary">Enviar invitación</button></p>';
		echo '</form>';

		echo '<table class="widefat striped" style="max-width:1100px;"><thead><tr>';
		echo '<th>Pedido</th><th>Estado</th><th>Email</th><th>Creado</th><th>Última invitación</th><th></th>';
		echo '</tr></thead><tbody>';
		if (!$candidates) {
			echo '<tr><td colspan="6">No hay pedidos elegibles en los últimos 14 días.</td></tr>';
		} else {
			foreach ($candidates as $order) {
				$oid = $order->get_id();
				$rebuy = (string) $order->get_meta(self::META_REBUY);
				$created = $order->get_date_created();
				echo '<tr>';
				echo '<td><a href="' . esc_url($order->get_edit_order_url()) . '">#' . esc_html((string) $oid) . '</a></td>';
				echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
				echo '<td>' . esc_html($order->get_billing_email()) . '</td>';
				echo '<td>' . esc_html($created ? $created->date_i18n('Y-m-d H:i') : '—') . '</td>';
				echo '<td>' . esc_html($rebuy !== '' ? self::format_local($rebuy) : '—') . '</td>';
				echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0;">';
				echo '<input type="hidden" name="action" value="' . esc_attr(self::ADMIN_REBUY) . '" />';
				echo '<input type="hidden" name="order_id" value="' . esc_attr((string) $oid) . '" />';
				wp_nonce_field('ss_ppr_send_rebuy');
				echo '<button type="submit" class="button">Enviar</button></form></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	private static function render_rebuy_log(): void {
		$log = get_option(self::OPTION_LOG, array());
		if (!is_array($log)) {
			$log = array();
		}
		$log = array_reverse($log);

		echo '<h2>Registro de invitaciones</h2>';
		echo '<table class="widefat striped" style="max-width:1100px;margin-top:12px;"><thead><tr>';
		echo '<th>Fecha (UTC)</th><th>Pedido</th><th>Email</th><th>Resultado</th>';
		echo '</tr></thead><tbody>';
		$rows = 0;
		foreach ($log as $row) {
			if (!is_array($row) || (string) ($row['which'] ?? '') !== 'rebuy') {
				continue;
			}
			$rows++;
			if ($rows > 200) {
				break;
			}
			$oid  = isset($row['order_id']) ? (int) $row['order_id'] : 0;
			$link = $oid ? admin_url('post.php?post=' . $oid . '&action=edit') : '#';
			if ($oid && function_exists('wc_get_order')) {
				$o = wc_get_order($oid);
				if ($o) {
					$link = $o->get_edit_order_url();
				}
			}
			echo '<tr>';
			echo '<td>' . esc_html((string) ($row['time'] ?? '')) . '</td>';
			echo '<td>' . ($oid ? '<a href="' . esc_url($link) . '">#' . esc_html((string) $oid) . '</a>' : '—') . '</td>';
			echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
			echo '<td>' . esc_html(self::label_result((string) ($row['result'] ?? ''))) . '</td>';
			echo '</tr>';
		}
		if ($rows === 0) {
			echo '<tr><td colspan="4">Aún no hay invitaciones registradas.</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @return WC_Order[]
	 */
	private static function list_rebuy_candidates(): array {
		if (!function_exists('wc_get_orders')) {
			return array();
		}
		$found = wc_get_orders(
			array(
				'status'       => array('pending', 'cancelled', 'failed'),
				'limit'        => 80,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>=' . (time() - (14 * DAY_IN_SECONDS)),
				'return'       => 'objects',
			)
		);
		$out = array();
		foreach ((array) $found as $order) {
			if ($order instanceof WC_Order && self::is_rebuy_eligible($order)) {
				$out[] = $order;
			}
		}
		return $out;
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
		SorteoSeguro_Pending_Payment_Reminders::init();
	},
	25
);
