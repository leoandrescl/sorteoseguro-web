<?php
/**
 * Plugin Name: Sorteo Seguro – Lottery ticket ensure
 * Description: Garantiza CPT de Lottery al completar/procesar pedido y al descargar DigiTickets (emails). Soporta guest checkout.
 * Author: Sorteo Seguro
 * Version: 1.0.1
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Lottery_Ticket_Ensure {

	const VERSION = '1.0.2';

	public static function init(): void {
		add_action('woocommerce_order_status_processing', [__CLASS__, 'ensure_for_order'], 5, 1);
		add_action('woocommerce_order_status_completed', [__CLASS__, 'ensure_for_order'], 5, 1);
		add_action('init', [__CLASS__, 'maybe_backfill_on_download'], 1);
	}

	public static function ensure_for_order($order_id): void {
		$order_id = (int) $order_id;
		if ($order_id <= 0 || !function_exists('lty_get_lottery_ticket_ids_by_order_id') || !function_exists('lty_create_new_lottery_ticket')) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof WC_Order) {
			return;
		}

		$created_any = false;

		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product')) {
				continue;
			}
			$product = $item->get_product();
			if (!$product || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($product)) {
				continue;
			}

			$numbers = $item->get_meta('_lty_lottery_tickets');
			if (!is_array($numbers) || empty($numbers)) {
				continue;
			}

			if (empty(self::missing_numbers_for_order($order_id, $numbers))) {
				continue;
			}

			$product_id = (int) $product->get_id();
			$existing   = lty_get_lottery_ticket_ids_by_order_id($order_id, $product_id);
			if (empty($existing) && class_exists('LTY_Order_Handler')) {
				LTY_Order_Handler::create_ticket_on_placing_order($order_id, $product_id, false, false);
			}

			if (!empty(self::missing_numbers_for_order($order_id, $numbers))) {
				$created = self::create_tickets_from_item_meta($order, $item, $product);
				if (!empty($created)) {
					$created_any = true;
				}
			}
		}

		if ($created_any) {
			$order = wc_get_order($order_id);
			if ($order instanceof WC_Order) {
				self::confirm_tickets_for_order($order);
			}
		}
	}

	public static function maybe_backfill_on_download(): void {
		if (is_admin() || !isset($_GET['action'], $_GET['ss_key'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = sanitize_text_field(wp_unslash((string) $_GET['action'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ($action !== 'ss-download') {
			return;
		}

		if (!function_exists('lty_decode') || !function_exists('lty_get_lottery_ticket_ids_by_order_id')) {
			return;
		}

		$key = sanitize_text_field(wp_unslash((string) $_GET['ss_key'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ($key === '') {
			return;
		}

		$decoded = json_decode(lty_decode($key));
		if (!is_object($decoded) || empty($decoded->lty_order_id)) {
			return;
		}

		self::ensure_for_order((int) $decoded->lty_order_id);
	}

	/** @return int[] */
	private static function create_tickets_from_item_meta(WC_Order $order, $item, $product): array {
		$numbers = $item->get_meta('_lty_lottery_tickets');
		if (!is_array($numbers) || empty($numbers)) {
			return [];
		}

		$order_id = (int) $order->get_id();
		$user_id  = (int) $order->get_user_id();
		$user     = $user_id > 0 ? get_user_by('ID', $user_id) : false;

		$args_base = [
			'lty_user_id'      => ($user instanceof WP_User) ? (int) $user->ID : 0,
			'lty_product_id'   => (int) $product->get_id(),
			'lty_amount'       => $product->get_price(),
			'lty_user_name'    => ($user instanceof WP_User) ? $user->user_login : __('Guest', 'lottery-for-woocommerce'),
			'lty_user_email'   => ($user instanceof WP_User) ? $user->user_email : $order->get_billing_email(),
			'lty_currency'     => $order->get_currency(),
			'lty_order_id'     => $order_id,
			'lty_answer'       => '',
			'lty_answers'      => [],
			'lty_valid_answer' => 'no',
			'lty_ip_address'   => $order->get_customer_ip_address() ?: (function_exists('lty_get_ip_address') ? lty_get_ip_address() : ''),
		];

		$parent_id  = self::lottery_parent_id($product);
		$ticket_ids = is_array($order->get_meta('lty_ticket_ids_in_order')) ? $order->get_meta('lty_ticket_ids_in_order') : [];

		foreach ($numbers as $ticket_number) {
			$ticket_number = (string) $ticket_number;
			if ($ticket_number === '') {
				continue;
			}

			$existing_post = get_posts([
				'post_type'      => 'lty_lottery_ticket',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => 'lty_order_id',
						'value' => (string) $order_id,
					],
					[
						'key'   => 'lty_ticket_number',
						'value' => $ticket_number,
					],
				],
			]);
			if (!empty($existing_post[0])) {
				$ticket_ids[] = (int) $existing_post[0];
				continue;
			}

			$args                      = $args_base;
			$args['lty_ticket_number'] = $ticket_number;
			$new_id                    = lty_create_new_lottery_ticket(
				$args,
				[
					'post_parent' => $parent_id,
					'post_status' => 'lty_ticket_pending',
				]
			);

			if ($new_id && !is_wp_error($new_id)) {
				$ticket_ids[] = (int) $new_id;
			}
		}

		$ticket_ids = array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));
		if (empty($ticket_ids)) {
			return [];
		}

		$order->update_meta_data('lty_ticket_ids_in_order', $ticket_ids);
		$order->update_meta_data('lty_lottery_ticket_created_once', '1');
		$order->save();

		return $ticket_ids;
	}

	/** @return string[] */
	private static function missing_numbers_for_order(int $order_id, array $numbers): array {
		$missing = [];
		foreach ($numbers as $ticket_number) {
			$ticket_number = (string) $ticket_number;
			if ($ticket_number === '') {
				continue;
			}
			$existing_post = get_posts([
				'post_type'      => 'lty_lottery_ticket',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => 'lty_order_id',
						'value' => (string) $order_id,
					],
					[
						'key'   => 'lty_ticket_number',
						'value' => $ticket_number,
					],
				],
			]);
			if (empty($existing_post)) {
				$missing[] = $ticket_number;
			}
		}
		return $missing;
	}

	private static function lottery_parent_id($product): int {
		if (is_object($product) && method_exists($product, 'get_lottery_id')) {
			$lottery_id = (int) $product->get_lottery_id();
			if ($lottery_id > 0) {
				return $lottery_id;
			}
		}
		return (int) $product->get_id();
	}

	private static function confirm_tickets_for_order(WC_Order $order): void {
		if (!in_array($order->get_status(), ['processing', 'completed'], true)) {
			return;
		}

		$ticket_ids = $order->get_meta('lty_ticket_ids_in_order');
		if (!is_array($ticket_ids) || empty($ticket_ids)) {
			return;
		}

		foreach ($ticket_ids as $ticket_id) {
			if (!function_exists('lty_get_lottery_ticket')) {
				continue;
			}
			$ticket = lty_get_lottery_ticket((int) $ticket_id);
			if (!is_object($ticket) || $ticket->has_status('lty_ticket_buyer')) {
				continue;
			}

			$product = $ticket->get_product();
			if (!$product || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($product)) {
				continue;
			}

			$ticket->update_status('lty_ticket_buyer');
			if (method_exists($product, 'get_lty_ticket_count') && method_exists($product, 'update_post_meta')) {
				$count = (int) $product->get_lty_ticket_count();
				$product->update_post_meta('lty_ticket_count', $count + 1);
			}
		}

		if (!$order->get_meta('lty_lottery_ticket_updated_once')) {
			$order->update_meta_data('lty_lottery_ticket_updated_once', '1');
			$order->save();
		}
	}
}

SorteoSeguro_Lottery_Ticket_Ensure::init();
