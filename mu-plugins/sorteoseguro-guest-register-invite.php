<?php
/**
 * Plugin Name: Sorteo Seguro – Invitación registro guest
 * Description: El correo “Completa tu Registro” (WPCode) solo debe salir en compra invitada sin cuenta activa. No altera pagos.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: no se desactiva desde el admin.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Guest_Register_Invite {

	const VERSION     = '1.0.0';
	const META_SENT   = '_gs_invitacion_enviada_v3';
	const META_SKIP   = '_ss_guest_invite_skipped';
	const USER_TOKEN  = 'registro_incompleto_token';

	public static function init(): void {
		// Antes del WPCode gs_envio_invitacion_final (prioridad 10).
		add_action('woocommerce_thankyou', [__CLASS__, 'suppress_if_not_guest'], 1, 1);
	}

	public static function suppress_if_not_guest($order_id): void {
		$order_id = (int) $order_id;
		if ($order_id < 1 || !function_exists('wc_get_order')) {
			return;
		}
		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}
		if ((string) $order->get_meta(self::META_SKIP) === 'yes') {
			return;
		}
		if (!self::should_suppress($order)) {
			return;
		}

		update_post_meta($order_id, self::META_SENT, 'yes');
		$order->update_meta_data(self::META_SENT, 'yes');
		$order->update_meta_data(self::META_SKIP, 'yes');
		$order->add_order_note('Sorteo Seguro: no se envió «Completa tu Registro» (compra con cuenta existente o perfil ya completo).');
		$order->save();
	}

	private static function should_suppress(WC_Order $order): bool {
		$email = strtolower(trim((string) $order->get_billing_email()));
		if ($email === '' || !is_email($email)) {
			return true;
		}

		$user = get_user_by('email', $email);
		if (!$user instanceof WP_User) {
			return false;
		}

		if (self::has_complete_profile((int) $user->ID)) {
			return true;
		}

		$order_ts      = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time();
		$registered_ts = strtotime((string) $user->user_registered);
		$just_created  = $registered_ts !== false && $registered_ts >= ($order_ts - 600);
		if ($just_created) {
			return false;
		}

		$customer_id = (int) $order->get_customer_id();
		if ($customer_id > 0 && $customer_id === (int) $user->ID) {
			return true;
		}

		$token = trim((string) get_user_meta((int) $user->ID, self::USER_TOKEN, true));
		if ($token === '') {
			return true;
		}

		return false;
	}

	private static function has_complete_profile(int $user_id): bool {
		$rut = trim((string) get_user_meta($user_id, 'billing_rut', true));
		if ($rut !== '') {
			return true;
		}
		$rut_form = trim((string) get_user_meta($user_id, 'rut_form', true));
		return $rut_form !== '';
	}
}

SorteoSeguro_Guest_Register_Invite::init();
