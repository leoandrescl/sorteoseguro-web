<?php
/**
 * Plugin Name: Sorteo Seguro – Guest order link
 * Description: Vincula pedidos guest al usuario creado por el correo “Completa tu Registro”, para que aparezcan en Mi cuenta → Pedidos. No altera checkout ni pagos.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: no se desactiva desde el admin.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Guest_Order_Link {

	const META_LINKED = '_ss_guest_order_linked';
	const VERSION     = '1.0.0';

	public static function init(): void {
		// Tras el WPCode que crea el usuario e invita (prioridad 10).
		add_action('woocommerce_thankyou', [__CLASS__, 'link_order_on_thankyou'], 20, 1);
		add_action('woocommerce_payment_complete', [__CLASS__, 'link_order_on_thankyou'], 30, 1);
		add_action('woocommerce_order_status_processing', [__CLASS__, 'link_order_on_status'], 30, 2);
		add_action('woocommerce_order_status_completed', [__CLASS__, 'link_order_on_status'], 30, 2);

		// Login clásico.
		add_action('wp_login', [__CLASS__, 'link_orders_for_login'], 20, 2);

		// Tras activar cuenta en /finalizar-registro/ (usa wp_set_auth_cookie, no dispara wp_login).
		add_action('template_redirect', [__CLASS__, 'link_orders_on_account'], 5);
	}

	public static function link_order_on_status($order_id, $order = null): void {
		self::link_order_on_thankyou((int) $order_id);
	}

	public static function link_order_on_thankyou($order_id): void {
		$order_id = (int) $order_id;
		if ($order_id < 1) {
			return;
		}
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		self::attach_order_to_matching_user($order);
	}

	/**
	 * @param string  $user_login
	 * @param WP_User $user
	 */
	public static function link_orders_for_login($user_login, $user): void {
		if (!$user instanceof WP_User) {
			return;
		}
		self::attach_guest_orders_for_user((int) $user->ID);
	}

	public static function link_orders_on_account(): void {
		if (!is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
			return;
		}
		self::attach_guest_orders_for_user((int) get_current_user_id());
	}

	public static function attach_guest_orders_for_user(int $user_id): void {
		if ($user_id < 1 || !function_exists('wc_get_orders')) {
			return;
		}
		$user = get_userdata($user_id);
		if (!$user || empty($user->user_email)) {
			return;
		}
		$email = strtolower(trim((string) $user->user_email));
		if ($email === '' || !is_email($email)) {
			return;
		}

		$orders = wc_get_orders([
			'limit'         => 30,
			'billing_email' => $email,
			'return'        => 'objects',
			'orderby'       => 'date',
			'order'         => 'DESC',
		]);

		foreach ($orders as $order) {
			if ($order instanceof WC_Order && (int) $order->get_customer_id() === 0) {
				self::attach_order_to_user($order, $user_id);
			}
		}
	}

	public static function attach_order_to_matching_user(WC_Order $order): bool {
		if ((int) $order->get_customer_id() > 0) {
			return false;
		}
		$email = strtolower(trim((string) $order->get_billing_email()));
		if ($email === '' || !is_email($email)) {
			return false;
		}
		$user = get_user_by('email', $email);
		if (!$user) {
			return false;
		}
		return self::attach_order_to_user($order, (int) $user->ID);
	}

	public static function attach_order_to_user(WC_Order $order, int $user_id): bool {
		if ($user_id < 1) {
			return false;
		}
		$status = (string) $order->get_status();
		// No vincular borradores / checkout incompletos.
		if (in_array($status, ['checkout-draft', 'draft', 'auto-draft'], true)) {
			return false;
		}
		$current = (int) $order->get_customer_id();
		if ($current === $user_id) {
			return false;
		}
		// No robar pedidos ya asignados a otro cliente.
		if ($current > 0 && $current !== $user_id) {
			return false;
		}

		$order->set_customer_id($user_id);
		$order->update_meta_data(self::META_LINKED, gmdate('c'));
		$order->save();
		return true;
	}
}

SorteoSeguro_Guest_Order_Link::init();
