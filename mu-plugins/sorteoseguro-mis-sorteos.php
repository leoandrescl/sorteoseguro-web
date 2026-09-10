<?php
/**
 * Plugin Name: Sorteo Seguro — Mis Sorteos
 * Description: Endpoint Mi Cuenta → Mis Sorteos. Avance = DigiTickets vendidos / _lty_maximum_tickets.
 */

if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Mis_Sorteos {
	public static function init(): void {
		add_action('init', [__CLASS__, 'register_endpoint']);
		add_filter('woocommerce_account_menu_items', [__CLASS__, 'menu_items']);
		add_action('woocommerce_account_mis-sorteos_endpoint', [__CLASS__, 'render']);
	}

	public static function register_endpoint(): void {
		add_rewrite_endpoint('mis-sorteos', EP_PAGES);
	}

	/**
	 * @param array<string,string> $items
	 * @return array<string,string>
	 */
	public static function menu_items(array $items): array {
		if (!isset($items['customer-logout'])) {
			$items['mis-sorteos'] = 'Mis Sorteos';
			return $items;
		}
		$logout = $items['customer-logout'];
		unset($items['customer-logout']);
		$items['mis-sorteos'] = 'Mis Sorteos';
		$items['customer-logout'] = $logout;
		return $items;
	}

	public static function render(): void {
		global $wpdb;

		if (!current_user_can('administrator')) {
			echo '<div class="woocommerce-info">Esta sección está en mantenimiento programado. Vuelve pronto.</div>';
			return;
		}

		$current_user_id = get_current_user_id();
		$tabla_meta = $wpdb->prefix . 'postmeta';
		$tabla_posts = $wpdb->prefix . 'posts';

		$sql = "SELECT DISTINCT post_id
			FROM {$tabla_meta}
			WHERE meta_key = '_lty_minimum_tickets'
			AND post_id IN (SELECT ID FROM {$tabla_posts} WHERE post_status = 'publish')";
		$ids_sorteos = $wpdb->get_col($sql);

		if (empty($ids_sorteos)) {
			echo '<p class="woocommerce-info">No se encontraron sorteos activos.</p>';
			return;
		}

		echo '<h3 style="color: #2c3e50;">Sorteos en Curso</h3>';
		echo '<table class="shop_table shop_table_responsive" style="width:100%; margin-bottom: 40px;">';
		echo '<thead><tr>';
		echo '<th style="text-align:left;">Producto</th>';
		echo '<th style="text-align:center; width: 45%;">Estado / Participación</th>';
		echo '</tr></thead><tbody>';

		foreach ($ids_sorteos as $product_id) {
			$p = wc_get_product((int) $product_id);
			if (!$p) {
				continue;
			}

			$is_closed = $p->get_meta('_lottery_closed') === 'yes' || !$p->is_in_stock();
			if ($is_closed) {
				continue;
			}

			$ha_comprado = wc_customer_bought_product('', $current_user_id, (int) $product_id);

			echo '<tr>';
			echo '<td data-title="Producto"><strong>' . esc_html($p->get_name()) . '</strong></td>';
			echo '<td data-title="Estado" style="text-align:center;">';

			if ($ha_comprado) {
				$progress = self::progress($p);
				$pct = $progress['pct'];
				$pct_display = $progress['pct_display'];
				$color = $pct_display > 55 ? '#fff' : '#333';

				echo '<div style="background-color: #eee; border-radius: 20px; position: relative; height: 24px; width: 100%; overflow: hidden; border: 1px solid #ddd;">';
				echo '<div style="background-color: #4CAF50; width: ' . esc_attr((string) $pct_display) . '%; height: 100%;"></div>';
				echo '<span style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: bold; color: ' . esc_attr($color) . ';">';
				echo esc_html((string) $pct) . '%';
				echo '</span></div>';
			} else {
				$url_producto = get_permalink((int) $product_id);
				echo '<p style="margin:0; font-size: 13px; color: #666;">';
				echo 'No estás participando, si quieres participar ';
				echo '<a href="' . esc_url($url_producto) . '" style="color: #e67e22; font-weight: bold; text-decoration: underline;">compra aquí</a>.';
				echo '</p>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		echo '<h3 style="color: #7f8c8d; margin-top: 50px;">Tus Sorteos Finalizados</h3>';
		echo '<p style="color: #999; font-style: italic;">No tienes sorteos finalizados.</p>';
	}

	/**
	 * @return array{sold:int,max:int,pct:int,pct_display:int}
	 */
	public static function progress(WC_Product $product): array {
		$sold = 0;
		if (method_exists($product, 'get_purchased_ticket_count')) {
			$sold = (int) $product->get_purchased_ticket_count();
		}
		if ($sold <= 0) {
			$sold = (int) $product->get_total_sales();
		}
		$max = (int) get_post_meta((int) $product->get_id(), '_lty_maximum_tickets', true);
		if ($max <= 0) {
			$max = 1;
		}
		$pct = (int) round(($sold / $max) * 100);
		return [
			'sold' => $sold,
			'max' => $max,
			'pct' => $pct,
			'pct_display' => min(100, $pct),
		];
	}
}

SorteoSeguro_Mis_Sorteos::init();
