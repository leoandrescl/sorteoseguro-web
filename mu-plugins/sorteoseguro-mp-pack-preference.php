<?php
/**
 * Plugin Name: Sorteo Seguro – MP Pack Preference
 * Description: Mercado Pago rechaza preferencias con unit_price negativo (fees DigiPack / Promo Engine). Al crear la preferencia, absorbe esos fees en los ítems de producto (repartido, no solo el primero). No modifica el pedido en BD ni el checkout visible.
 * Author: Sorteo Seguro
 * Version: 1.0.2
 *
 * mu-plugin: borrar este archivo para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_MP_Pack_Preference {

	const VERSION = '1.0.2';
	const LOG_SOURCE = 'sorteoseguro-mp-pack-preference';

	/**
	 * @var array{order_id:int,by_item:array<int,float>}|null
	 * by_item[item_id] es negativo (porción del fee [PROMO] absorbida en esa línea).
	 */
	private static $fold = null;

	/** Evita reentrada en filtros WC. */
	private static $filtering = false;

	public static function init(): void {
		add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'arm_from_checkout'), 999999, 3);
		add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'arm_from_store_api'), 999999, 1);
		add_action('woocommerce_before_pay_action', array(__CLASS__, 'arm_from_order'), 5, 1);

		add_filter('woocommerce_order_get_items', array(__CLASS__, 'filter_order_items'), 10, 3);
		add_filter('woocommerce_order_item_get_total', array(__CLASS__, 'filter_item_total'), 10, 2);

		add_filter('woocommerce_payment_successful_result', array(__CLASS__, 'disarm_keep_result'), 1, 1);
		add_action('shutdown', array(__CLASS__, 'disarm'), 0);
	}

	public static function arm_from_checkout($order_id, $posted_data, $order): void {
		unset($posted_data);
		if (!$order instanceof WC_Order) {
			$order = wc_get_order($order_id);
		}
		self::arm_from_order($order);
	}

	public static function arm_from_store_api($order): void {
		if (is_numeric($order)) {
			$order = wc_get_order((int) $order);
		}
		self::arm_from_order($order);
	}

	/**
	 * @param WC_Order|false|null $order
	 */
	public static function arm_from_order($order): void {
		if (!$order instanceof WC_Order) {
			return;
		}
		if (!self::is_mp_gateway($order->get_payment_method())) {
			return;
		}

		$discount = self::sum_negative_fee_amounts($order);
		if ($discount >= 0) {
			return;
		}

		$by_item = self::allocate_discount_across_items($order, $discount);
		if (!$by_item) {
			return;
		}

		self::$fold = array(
			'order_id' => (int) $order->get_id(),
			'by_item'  => $by_item,
		);

		if (function_exists('wc_get_logger')) {
			wc_get_logger()->info(
				'Folding negative promo fees into MP preference line items',
				array(
					'source'    => self::LOG_SOURCE,
					'order_id'  => (int) $order->get_id(),
					'discount'  => (float) $discount,
					'by_item'   => $by_item,
				)
			);
		}
	}

	public static function disarm(): void {
		self::$fold = null;
		self::$filtering = false;
	}

	/**
	 * @param mixed $result
	 * @return mixed
	 */
	public static function disarm_keep_result($result) {
		self::disarm();
		return $result;
	}

	private static function is_mp_gateway(string $method): bool {
		return $method !== '' && strpos($method, 'woo-mercado-pago') === 0;
	}

	/**
	 * Reparte el fee negativo entre líneas para que ninguna quede con total < 0
	 * (N packs: 2× $50.000 con −$50.000 → $25.000 + $25.000, no $0 + $50.000).
	 *
	 * @return array<int,float> item_id => amount negativo
	 */
	private static function allocate_discount_across_items(WC_Order $order, float $discount): array {
		$need = abs($discount);
		if ($need <= 0) {
			return array();
		}

		$weights = array();
		$sum     = 0.0;
		foreach ($order->get_items('line_item') as $item) {
			if (!$item instanceof WC_Order_Item_Product) {
				continue;
			}
			$total = (float) $item->get_total() + (float) $item->get_total_tax();
			if ($total <= 0) {
				continue;
			}
			$id = (int) $item->get_id();
			$weights[ $id ] = $total;
			$sum += $total;
		}
		if ($sum <= 0 || !$weights) {
			return array();
		}

		$decimals = function_exists('wc_get_price_decimals') ? (int) wc_get_price_decimals() : 0;
		$ids      = array_keys($weights);
		$last     = (int) end($ids);
		$left     = $need;
		$alloc    = array();

		foreach ($weights as $id => $total) {
			$id = (int) $id;
			if ($id === $last) {
				$share = min($total, $left);
			} else {
				$share = min($total, (float) wc_format_decimal($need * ($total / $sum), $decimals));
				$share = min($share, $left);
			}
			if ($share > 0) {
				$alloc[ $id ] = -1 * $share;
				$left        -= $share;
			}
		}

		return $alloc;
	}

	private static function is_armed_for(WC_Order $order): bool {
		return is_array(self::$fold)
			&& (int) self::$fold['order_id'] === (int) $order->get_id();
	}

	private static function sum_negative_fee_amounts(WC_Order $order): float {
		$sum = 0.0;
		foreach ($order->get_items('fee') as $fee) {
			if (!$fee instanceof WC_Order_Item_Fee) {
				continue;
			}
			$amount = (float) $fee->get_total() + (float) $fee->get_total_tax();
			if ($amount < 0) {
				$sum += $amount;
			}
		}
		return $sum;
	}

	/**
	 * Oculta fees negativos solo mientras MP arma la preferencia.
	 *
	 * @param WC_Order_Item[] $items
	 * @param string[]        $types
	 * @return WC_Order_Item[]
	 */
	public static function filter_order_items($items, $order, $types) {
		if (self::$filtering || !$order instanceof WC_Order || !self::is_armed_for($order)) {
			return $items;
		}

		$types = array_filter((array) $types);
		if (!in_array('fee', $types, true)) {
			return $items;
		}

		self::$filtering = true;
		$kept = array();
		foreach ($items as $item_id => $item) {
			if (!$item instanceof WC_Order_Item_Fee) {
				$kept[ $item_id ] = $item;
				continue;
			}
			// Leer data cruda evita filtros/reentrada.
			$total = (float) $item->get_data()['total'];
			$total_tax = (float) $item->get_data()['total_tax'];
			if (($total + $total_tax) >= 0) {
				$kept[ $item_id ] = $item;
			}
		}
		self::$filtering = false;
		return $kept;
	}

	/**
	 * Absorbe el descuento del pack en cada ítem de producto (repartido).
	 *
	 * @param mixed         $total
	 * @param WC_Order_Item $item
	 * @return mixed
	 */
	public static function filter_item_total($total, $item) {
		if (self::$filtering || !is_array(self::$fold) || !$item instanceof WC_Order_Item_Product) {
			return $total;
		}
		$id = (int) $item->get_id();
		if (!isset(self::$fold['by_item'][ $id ])) {
			return $total;
		}
		if ((int) $item->get_order_id() !== (int) self::$fold['order_id']) {
			return $total;
		}
		return (float) $total + (float) self::$fold['by_item'][ $id ];
	}
}

SorteoSeguro_MP_Pack_Preference::init();
