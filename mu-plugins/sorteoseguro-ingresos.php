<?php
/**
 * Plugin Name: Sorteo Seguro – Ingresos reales
 * Description: Panel wp-admin con lo que pagaron los clientes (DigiPack aplicado). Solo lectura; no toca pedidos ni pagos.
 * Author: Sorteo Seguro
 * Version: 1.0.3
 *
 * mu-plugin: borrar este archivo y la carpeta sorteoseguro-ingresos para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Ingresos {

	const VERSION = '1.0.3';
	const PAGE    = 'ss-ingresos';
	const DIR     = __DIR__ . '/sorteoseguro-ingresos';
	const CACHE_TTL = 120;

	public static function init(): void {
		add_action('admin_menu', array(__CLASS__, 'register_menu'), 64);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
	}

	public static function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			'Ingresos reales',
			'Ingresos reales',
			'manage_woocommerce',
			self::PAGE,
			array(__CLASS__, 'render')
		);
	}

	public static function assets(string $hook): void {
		if ($hook !== 'woocommerce_page_' . self::PAGE) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		} else {
			wp_enqueue_style(
				'ss-fonts',
				'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap',
				array(),
				null
			);
		}
		$path = self::DIR . '/assets/ingresos.css';
		if (is_readable($path)) {
			wp_enqueue_style(
				'ss-ingresos',
				content_url('mu-plugins/sorteoseguro-ingresos/assets/ingresos.css'),
				array('ss-fonts'),
				self::VERSION
			);
		}
	}

	public static function render(): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$range = self::parse_range();
		$data  = self::get_report($range);

		echo '<div class="wrap ss-ing">';
		echo '<h1>Ingresos reales</h1>';
		echo '<p class="ss-ing__lead">Lo que <strong>pagaron los clientes</strong> (total del pedido, con descuento DigiPack). El ranking de WooCommerce Analytics usa precio de lista y no resta el pack.</p>';

		self::render_filters($range);

		if (!empty($data['error'])) {
			echo '<div class="notice notice-warning"><p>' . esc_html((string) $data['error']) . '</p></div>';
			echo '</div>';
			return;
		}

		self::render_kpis($data);
		self::render_charts($data);
		self::render_hours($data);
		self::render_tables($data);
		echo '</div>';
	}

	/**
	 * @param array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime} $range
	 */
	private static function render_filters(array $range): void {
		$base = admin_url('admin.php');
		$presets = array(
			'today'      => 'Hoy',
			'7d'         => 'Últimos 7 días',
			'month'      => 'Este mes',
			'last_month' => 'Mes anterior',
			'year'       => 'Este año',
			'custom'     => 'Personalizado',
		);

		echo '<form class="ss-ing__filters" method="get" action="' . esc_url($base) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '" />';
		echo '<label class="screen-reader-text" for="ss_range">Período</label>';
		echo '<select name="ss_range" id="ss_range">';
		foreach ($presets as $key => $label) {
			echo '<option value="' . esc_attr($key) . '"' . selected($range['preset'], $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '<label>Desde <input type="date" name="ss_start" value="' . esc_attr($range['start']->format('Y-m-d')) . '" /></label>';
		echo '<label>Hasta <input type="date" name="ss_end" value="' . esc_attr($range['end']->format('Y-m-d')) . '" /></label>';
		echo '<button type="submit" class="ss-ing__btn">Ver período</button>';
		echo '</form>';

		$tz_name = wp_timezone()->getName();
		echo '<p class="ss-ing__meta">Período: <strong>' . esc_html($range['start']->format('d-m-Y H:i')) . '</strong> → <strong>' . esc_html($range['end']->format('d-m-Y H:i')) . '</strong> (' . esc_html($tz_name) . '). Solo lectura. Para fechas exactas elige Personalizado.</p>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_kpis(array $data): void {
		$cards = array(
			array(
				'label' => 'Ingresado',
				'hint'  => 'Lo pagado, con DigiPack',
				'value' => self::format_money((float) $data['paid']),
				'delta' => self::delta_label((float) $data['paid'], (float) $data['prev_paid']),
			),
			array(
				'label' => 'Precio lista',
				'hint'  => 'DigiTickets sin pack',
				'value' => self::format_money((float) $data['list']),
				'delta' => '',
			),
			array(
				'label' => 'Descuento DigiPack',
				'hint'  => 'Diferencia lista − cobrado',
				'value' => self::format_money((float) $data['pack_discount']),
				'delta' => '',
			),
			array(
				'label' => 'Pedidos',
				'hint'  => 'Pedidos (sin contar reembolsos como pedido extra)',
				'value' => self::format_int((int) $data['orders']),
				'delta' => self::delta_label((float) $data['orders'], (float) $data['prev_orders']),
			),
			array(
				'label' => 'DigiTickets',
				'hint'  => 'Unidades vendidas',
				'value' => self::format_int((int) $data['items']),
				'delta' => self::delta_label((float) $data['items'], (float) $data['prev_items']),
			),
		);

		echo '<section class="ss-ing__kpis" aria-label="Resumen">';
		foreach ($cards as $card) {
			echo '<article class="ss-ing__kpi">';
			echo '<p class="ss-ing__kpi-label">' . esc_html($card['label']) . '</p>';
			echo '<p class="ss-ing__kpi-value">' . esc_html($card['value']) . '</p>';
			if ($card['delta'] !== '') {
				$up = strpos($card['delta'], '+') === 0;
				echo '<p class="ss-ing__kpi-delta' . ($up ? ' is-up' : ' is-down') . '">' . esc_html($card['delta']) . ' vs período anterior</p>';
			} else {
				echo '<p class="ss-ing__kpi-hint">' . esc_html($card['hint']) . '</p>';
			}
			echo '</article>';
		}
		echo '</section>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_charts(array $data): void {
		$days = is_array($data['days'] ?? null) ? $data['days'] : array();
		if (!$days) {
			echo '<p class="ss-ing__empty">No hay pedidos completados en este período.</p>';
			return;
		}

		$max_paid = 0.0;
		$max_orders = 0;
		foreach ($days as $row) {
			$max_paid = max($max_paid, (float) $row['paid']);
			$max_orders = max($max_orders, (int) $row['orders']);
		}

		echo '<section class="ss-ing__charts">';
		self::render_bar_chart('Ingresado por día', $days, 'paid', $max_paid, true);
		self::render_bar_chart('Pedidos por día', $days, 'orders', (float) $max_orders, false);
		echo '</section>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_hours(array $data): void {
		$hours = is_array($data['hours'] ?? null) ? $data['hours'] : array();
		if (!$hours) {
			return;
		}

		$max_orders = 0;
		$max_paid   = 0.0;
		foreach ($hours as $row) {
			$max_orders = max($max_orders, (int) $row['orders']);
			$max_paid   = max($max_paid, (float) $row['paid']);
		}

		$ranked = $hours;
		usort(
			$ranked,
			static function ($a, $b) {
				if ((int) $b['orders'] !== (int) $a['orders']) {
					return (int) $b['orders'] <=> (int) $a['orders'];
				}
				return (float) $b['paid'] <=> (float) $a['paid'];
			}
		);
		$top = array_slice($ranked, 0, 3);
		$peak_hour = isset($top[0]) ? (int) $top[0]['hour'] : -1;

		echo '<section class="ss-ing__hours">';
		echo '<article class="ss-ing__chart ss-ing__chart--hours">';
		echo '<h2>Concentración por hora del día</h2>';
		echo '<p class="ss-ing__hours-lead">Suma del período filtrado, agrupada por hora local del sitio (cuando se creó el pedido).</p>';

		if ($peak_hour >= 0 && $max_orders > 0) {
			$peak = $top[0];
			$next = sprintf('%02d:00', ($peak_hour + 1) % 24);
			echo '<p class="ss-ing__peak">Mayor concentración: <strong>' . esc_html(sprintf('%02d:00', $peak_hour) . '–' . $next) . '</strong> — ';
			echo esc_html(self::format_int((int) $peak['orders'])) . ' pedidos · ' . esc_html(self::format_money((float) $peak['paid'])) . '</p>';
			if (count($top) > 1) {
				echo '<p class="ss-ing__peak-more">Otras horas fuertes: ';
				$bits = array();
				foreach (array_slice($top, 1) as $row) {
					if ((int) $row['orders'] <= 0) {
						continue;
					}
					$h = (int) $row['hour'];
					$bits[] = sprintf('%02d:00–%02d:00 (%s pedidos)', $h, ($h + 1) % 24, self::format_int((int) $row['orders']));
				}
				echo esc_html(implode(' · ', $bits));
				echo '</p>';
			}
		}

		echo '<div class="ss-ing__bars ss-ing__bars--hours" role="img" aria-label="Pedidos por hora">';
		foreach ($hours as $row) {
			$raw = (int) $row['orders'];
			$pct = $max_orders > 0 ? max(2, (int) round(100 * $raw / $max_orders)) : 0;
			if ($raw <= 0) {
				$pct = 0;
			}
			$is_peak = ((int) $row['hour'] === $peak_hour && $raw > 0);
			$tip = $row['label'] . ': ' . self::format_int($raw) . ' pedidos · ' . self::format_money((float) $row['paid']);
			echo '<div class="ss-ing__bar-wrap' . ($is_peak ? ' is-peak' : '') . '" title="' . esc_attr($tip) . '">';
			echo '<span class="ss-ing__bar" style="height:' . esc_attr((string) $pct) . '%"></span>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="ss-ing__bar-axis ss-ing__bar-axis--hours">';
		foreach ($hours as $i => $row) {
			// Etiquetas cada 3 h para no saturar.
			if ($i % 3 !== 0) {
				echo '<span></span>';
				continue;
			}
			echo '<span>' . esc_html($row['label']) . '</span>';
		}
		echo '</div>';
		echo '</article>';
		echo '</section>';
	}

	/**
	 * @param array<int,array{label:string,paid:float,orders:int}> $days
	 */
	private static function render_bar_chart(string $title, array $days, string $key, float $max, bool $money): void {
		echo '<article class="ss-ing__chart">';
		echo '<h2>' . esc_html($title) . '</h2>';
		echo '<div class="ss-ing__bars" role="img" aria-label="' . esc_attr($title) . '">';
		foreach ($days as $row) {
			$raw = $key === 'paid' ? (float) $row['paid'] : (float) $row['orders'];
			$pct = $max > 0 ? max(2, (int) round(100 * $raw / $max)) : 0;
			if ($raw <= 0) {
				$pct = 0;
			}
			$tip = $row['label'] . ': ' . ($money ? self::format_money($raw) : self::format_int((int) $raw));
			echo '<div class="ss-ing__bar-wrap" title="' . esc_attr($tip) . '">';
			echo '<span class="ss-ing__bar" style="height:' . esc_attr((string) $pct) . '%"></span>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="ss-ing__bar-axis">';
		$n = count($days);
		$show = array(0, (int) floor(($n - 1) / 2), $n - 1);
		foreach ($days as $i => $row) {
			if (!in_array($i, $show, true)) {
				echo '<span></span>';
				continue;
			}
			echo '<span>' . esc_html($row['label']) . '</span>';
		}
		echo '</div>';
		echo '</article>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_tables(array $data): void {
		echo '<section class="ss-ing__tables">';
		self::render_product_table('Por sorteo (lo cobrado)', is_array($data['products'] ?? null) ? $data['products'] : array());
		self::render_product_table('Por categoría (lo cobrado)', is_array($data['categories'] ?? null) ? $data['categories'] : array(), true);
		echo '</section>';
	}

	/**
	 * @param array<int,array{name:string,qty:int,list:float,paid:float,discount:float}> $rows
	 */
	private static function render_product_table(string $title, array $rows, bool $is_cat = false): void {
		echo '<article class="ss-ing__table-card">';
		echo '<h2>' . esc_html($title) . '</h2>';
		echo '<table class="ss-ing__table">';
		echo '<thead><tr>';
		echo '<th>' . ($is_cat ? 'Categoría' : 'Producto') . '</th>';
		echo '<th>DigiTickets</th><th>Precio lista</th><th>Descuento pack</th><th>Ingresado</th>';
		echo '</tr></thead><tbody>';
		if (!$rows) {
			echo '<tr><td colspan="5">Sin datos en este período.</td></tr>';
		} else {
			foreach ($rows as $row) {
				echo '<tr>';
				echo '<td>' . esc_html($row['name']) . '</td>';
				echo '<td>' . esc_html(self::format_int((int) $row['qty'])) . '</td>';
				echo '<td>' . esc_html(self::format_money((float) $row['list'])) . '</td>';
				echo '<td>' . esc_html(self::format_money((float) $row['discount'])) . '</td>';
				echo '<td><strong>' . esc_html(self::format_money((float) $row['paid'])) . '</strong></td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table></article>';
	}

	/**
	 * @return array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime}
	 */
	private static function parse_range(): array {
		$tz     = wp_timezone();
		$now    = new DateTime('now', $tz);
		$preset = isset($_GET['ss_range']) ? sanitize_key((string) wp_unslash($_GET['ss_range'])) : 'month';
		$allowed = array('today', '7d', 'month', 'last_month', 'year', 'custom');
		if (!in_array($preset, $allowed, true)) {
			$preset = 'month';
		}

		$start = clone $now;
		$end   = clone $now;

		if ($preset === 'today') {
			$start->setTime(0, 0, 0);
		} elseif ($preset === '7d') {
			$start->modify('-6 days')->setTime(0, 0, 0);
		} elseif ($preset === 'month') {
			$start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
		} elseif ($preset === 'last_month') {
			$start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
			$start->modify('-1 month');
			$end = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
			$end->modify('-1 second');
		} elseif ($preset === 'year') {
			$start = new DateTime($now->format('Y-01-01 00:00:00'), $tz);
		} else {
			$start_s = isset($_GET['ss_start']) ? sanitize_text_field((string) wp_unslash($_GET['ss_start'])) : '';
			$end_s   = isset($_GET['ss_end']) ? sanitize_text_field((string) wp_unslash($_GET['ss_end'])) : '';
			$ds = DateTime::createFromFormat('Y-m-d H:i:s', $start_s . ' 00:00:00', $tz);
			$de = DateTime::createFromFormat('Y-m-d H:i:s', $end_s . ' 23:59:59', $tz);
			if ($ds instanceof DateTime && $de instanceof DateTime && $ds <= $de) {
				$start = $ds;
				$end   = $de;
			} else {
				$preset = 'month';
				$start  = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
			}
		}

		if ($preset !== 'last_month' && $preset !== 'custom') {
			$end = clone $now;
		}

		$span = max(1, $end->getTimestamp() - $start->getTimestamp());
		$prev_end = (clone $start)->modify('-1 second');
		$prev_start = (clone $prev_end)->setTimestamp($prev_end->getTimestamp() - $span);

		return array(
			'preset'     => $preset,
			'start'      => $start,
			'end'        => $end,
			'prev_start' => $prev_start,
			'prev_end'   => $prev_end,
		);
	}

	/**
	 * @param array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime} $range
	 * @return array<string,mixed>
	 */
	private static function get_report(array $range): array {
		$key = 'ss_ing_' . self::VERSION . '_' . md5($range['preset'] . $range['start']->format('c') . $range['end']->format('c'));
		$cached = get_transient($key);
		if (is_array($cached) && isset($cached['paid'])) {
			return $cached;
		}

		$report = self::build_report($range);
		if (empty($report['error'])) {
			set_transient($key, $report, self::CACHE_TTL);
		}
		return $report;
	}

	/**
	 * @param array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime} $range
	 * @return array<string,mixed>
	 */
	private static function build_report(array $range): array {
		global $wpdb;

		$stats = $wpdb->prefix . 'wc_order_stats';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stats)) !== $stats) {
			return array('error' => 'No está la tabla de Analytics de WooCommerce (wc_order_stats).');
		}

		$utc = new DateTimeZone('UTC');
		$cur = self::query_order_stats($stats, $range['start'], $range['end'], $utc);
		$prev = self::query_order_stats($stats, $range['prev_start'], $range['prev_end'], $utc);
		$days = self::query_days($stats, $range['start'], $range['end'], $utc);
		$hours = self::query_hours($stats, $range['start'], $range['end'], $utc);
		$breakdown = self::query_breakdown($stats, $range['start'], $range['end'], $utc);

		$list = (float) $breakdown['list'];
		$ingresado = (float) $cur['paid'];
		$pack = max(0.0, $list - $ingresado);

		return array(
			'paid'          => $ingresado,
			'list'          => $list,
			'pack_discount' => $pack,
			'orders'        => (int) $cur['orders'],
			'items'         => (int) $cur['items'],
			'prev_paid'     => (float) $prev['paid'],
			'prev_orders'   => (int) $prev['orders'],
			'prev_items'    => (int) $prev['items'],
			'days'          => $days,
			'hours'         => $hours,
			'products'      => $breakdown['products'],
			'categories'    => $breakdown['categories'],
		);
	}

	/**
	 * @return array{paid:float,orders:int,items:int}
	 */
	private static function query_order_stats(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::status_sql();

		$sql = "SELECT
			COALESCE(SUM(net_total), 0) AS paid,
			COALESCE(SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END), 0) AS orders,
			COALESCE(SUM(num_items_sold), 0) AS items
			FROM {$stats}
			WHERE date_created_gmt >= %s AND date_created_gmt <= %s
			AND status NOT IN ({$st['in']})";

		$row = $wpdb->get_row($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		return array(
			'paid'   => $row ? (float) $row->paid : 0.0,
			'orders' => $row ? (int) $row->orders : 0,
			'items'  => $row ? (int) $row->items : 0,
		);
	}

	/**
	 * Pedidos e ingresado agrupados por hora local (0–23) del período.
	 *
	 * @return array<int,array{hour:int,label:string,paid:float,orders:int}>
	 */
	private static function query_hours(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::status_sql();
		$tz   = wp_timezone();

		$sql = "SELECT date_created_gmt, net_total, parent_id
			FROM {$stats}
			WHERE date_created_gmt >= %s AND date_created_gmt <= %s
			AND status NOT IN ({$st['in']})";
		$rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));

		$bucket = array();
		for ($h = 0; $h < 24; $h++) {
			$bucket[ $h ] = array(
				'hour'   => $h,
				'label'  => sprintf('%02d:00', $h),
				'paid'   => 0.0,
				'orders' => 0,
			);
		}

		if (is_array($rows)) {
			foreach ($rows as $row) {
				$dt = new DateTime((string) $row->date_created_gmt, $utc);
				$dt->setTimezone($tz);
				$h = (int) $dt->format('G');
				$bucket[ $h ]['paid'] += (float) $row->net_total;
				if ((int) $row->parent_id === 0) {
					$bucket[ $h ]['orders']++;
				}
			}
		}

		return array_values($bucket);
	}

	/**
	 * @return array<int,array{label:string,paid:float,orders:int}>
	 */
	private static function query_days(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::status_sql();
		$tz   = wp_timezone();

		$sql = "SELECT date_created_gmt, net_total, parent_id
			FROM {$stats}
			WHERE date_created_gmt >= %s AND date_created_gmt <= %s
			AND status NOT IN ({$st['in']})";
		$rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));

		$bucket = array();
		$cursor = (clone $start)->setTime(0, 0, 0);
		$last   = (clone $end)->setTime(0, 0, 0);
		while ($cursor <= $last) {
			$key = $cursor->format('Y-m-d');
			$bucket[ $key ] = array(
				'label'  => $cursor->format('j M'),
				'paid'   => 0.0,
				'orders' => 0,
			);
			$cursor->modify('+1 day');
		}

		$span_days = count($bucket);
		$by_month = $span_days > 92;

		if ($by_month) {
			$bucket = array();
			$cursor = (clone $start)->modify('first day of this month')->setTime(0, 0, 0);
			$last_m = (clone $end)->modify('first day of this month')->setTime(0, 0, 0);
			while ($cursor <= $last_m) {
				$key = $cursor->format('Y-m');
				$bucket[ $key ] = array(
					'label'  => $cursor->format('M Y'),
					'paid'   => 0.0,
					'orders' => 0,
				);
				$cursor->modify('+1 month');
			}
		}

		if (is_array($rows)) {
			foreach ($rows as $row) {
				$dt = new DateTime((string) $row->date_created_gmt, $utc);
				$dt->setTimezone($tz);
				$key = $by_month ? $dt->format('Y-m') : $dt->format('Y-m-d');
				if (!isset($bucket[ $key ])) {
					continue;
				}
				$bucket[ $key ]['paid'] += (float) $row->net_total;
				if ((int) $row->parent_id === 0) {
					$bucket[ $key ]['orders']++;
				}
			}
		}

		return array_values($bucket);
	}

	/**
	 * @return array{list:float,paid:float,discount:float,products:array<int,array<string,mixed>>,categories:array<int,array<string,mixed>>}
	 */
	private static function query_breakdown(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::status_sql();
		$lookup = $wpdb->prefix . 'wc_order_product_lookup';
		$items_t = $wpdb->prefix . 'woocommerce_order_items';
		$meta_t  = $wpdb->prefix . 'woocommerce_order_itemmeta';

		$empty = array(
			'list'       => 0.0,
			'paid'       => 0.0,
			'discount'   => 0.0,
			'products'   => array(),
			'categories' => array(),
		);

		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup)) !== $lookup) {
			return $empty;
		}

		$sql = "SELECT p.order_id, p.product_id, p.product_qty, p.product_net_revenue
			FROM {$lookup} p
			INNER JOIN {$stats} s ON s.order_id = p.order_id
			WHERE s.date_created_gmt >= %s AND s.date_created_gmt <= %s
			AND s.status NOT IN ({$st['in']})";
		$lines = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		if (!is_array($lines) || !$lines) {
			return $empty;
		}

		$fee_sql = "SELECT oi.order_id, oi.order_item_name, oi.order_item_id, m.meta_key, m.meta_value
			FROM {$items_t} oi
			INNER JOIN {$stats} s ON s.order_id = oi.order_id
			INNER JOIN {$meta_t} m ON m.order_item_id = oi.order_item_id AND m.meta_key IN ('_line_total', '_line_tax')
			WHERE oi.order_item_type = 'fee'
			AND s.date_created_gmt >= %s AND s.date_created_gmt <= %s
			AND s.status NOT IN ({$st['in']})";
		$fee_rows = $wpdb->get_results($wpdb->prepare($fee_sql, array_merge(array($from, $to), $st['args'])));

		$fees_by_item = array();
		if (is_array($fee_rows)) {
			foreach ($fee_rows as $fr) {
				$oid = (int) $fr->order_id;
				$iid = (int) $fr->order_item_id;
				if (!isset($fees_by_item[ $oid ][ $iid ])) {
					$fees_by_item[ $oid ][ $iid ] = array(
						'name' => (string) $fr->order_item_name,
						'total' => 0.0,
						'tax' => 0.0,
					);
				}
				if ($fr->meta_key === '_line_tax') {
					$fees_by_item[ $oid ][ $iid ]['tax'] += (float) $fr->meta_value;
				} else {
					$fees_by_item[ $oid ][ $iid ]['total'] += (float) $fr->meta_value;
				}
			}
		}

		$promo_by_order = array();
		foreach ($fees_by_item as $oid => $items) {
			$sum = 0.0;
			foreach ($items as $fee) {
				if (strpos($fee['name'], '[PROMO]') === false) {
					continue;
				}
				$amt = (float) $fee['total'] + (float) $fee['tax'];
				if ($amt < 0) {
					$sum += $amt;
				}
			}
			if ($sum < 0) {
				$promo_by_order[ $oid ] = $sum;
			}
		}

		$by_order = array();
		foreach ($lines as $line) {
			$oid = (int) $line->order_id;
			$by_order[ $oid ][] = $line;
		}

		$agg = array();
		foreach ($by_order as $oid => $order_lines) {
			$sum_list = 0.0;
			foreach ($order_lines as $line) {
				$sum_list += (float) $line->product_net_revenue;
			}
			$promo = isset($promo_by_order[ $oid ]) ? (float) $promo_by_order[ $oid ] : 0.0;
			$left = abs($promo);
			$n = count($order_lines);
			foreach ($order_lines as $i => $line) {
				$pid  = (int) $line->product_id;
				$list = (float) $line->product_net_revenue;
				$share = 0.0;
				if ($promo < 0 && $sum_list > 0 && $list > 0) {
					if ($i === $n - 1) {
						$share = min($list, $left);
					} else {
						$share = min($list, $left * ($list / $sum_list));
						$share = (float) round($share, 0);
						$share = min($share, $left);
					}
					$left -= $share;
				}
				$paid = $list - $share;
				if (!isset($agg[ $pid ])) {
					$agg[ $pid ] = array(
						'qty'      => 0,
						'list'     => 0.0,
						'paid'     => 0.0,
						'discount' => 0.0,
					);
				}
				$agg[ $pid ]['qty']      += (int) $line->product_qty;
				$agg[ $pid ]['list']     += $list;
				$agg[ $pid ]['paid']     += $paid;
				$agg[ $pid ]['discount'] += $share;
			}
		}

		$ids = array_keys($agg);
		$names = self::product_names($ids);
		$cats  = self::product_categories($ids);

		$products = array();
		$categories = array();
		$list_total = 0.0;
		$paid_total = 0.0;
		$disc_total = 0.0;

		foreach ($agg as $pid => $row) {
			$list_total += $row['list'];
			$paid_total += $row['paid'];
			$disc_total += $row['discount'];
			$products[] = array(
				'name'     => isset($names[ $pid ]) ? $names[ $pid ] : ('Producto #' . $pid),
				'qty'      => $row['qty'],
				'list'     => $row['list'],
				'paid'     => $row['paid'],
				'discount' => $row['discount'],
			);
			$cat = isset($cats[ $pid ]) ? $cats[ $pid ] : 'Sin categoría';
			if (!isset($categories[ $cat ])) {
				$categories[ $cat ] = array(
					'name'     => $cat,
					'qty'      => 0,
					'list'     => 0.0,
					'paid'     => 0.0,
					'discount' => 0.0,
				);
			}
			$categories[ $cat ]['qty']      += $row['qty'];
			$categories[ $cat ]['list']     += $row['list'];
			$categories[ $cat ]['paid']     += $row['paid'];
			$categories[ $cat ]['discount'] += $row['discount'];
		}

		usort($products, static function ($a, $b) {
			return $b['paid'] <=> $a['paid'];
		});
		$cat_rows = array_values($categories);
		usort($cat_rows, static function ($a, $b) {
			return $b['paid'] <=> $a['paid'];
		});

		return array(
			'list'       => $list_total,
			'paid'       => $paid_total,
			'discount'   => $disc_total,
			'products'   => $products,
			'categories' => $cat_rows,
		);
	}

	/**
	 * @param int[] $ids
	 * @return array<int,string>
	 */
	private static function product_names(array $ids): array {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (!$ids) {
			return array();
		}
		$in = implode(',', $ids);
		$rows = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$in})");
		$out = array();
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$out[ (int) $row->ID ] = (string) $row->post_title;
			}
		}
		return $out;
	}

	/**
	 * @param int[] $ids
	 * @return array<int,string>
	 */
	private static function product_categories(array $ids): array {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (!$ids) {
			return array();
		}
		$in = implode(',', $ids);
		$sql = "SELECT tr.object_id, t.name
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE tr.object_id IN ({$in})
			ORDER BY t.name ASC";
		$rows = $wpdb->get_results($sql);
		$out = array();
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$oid = (int) $row->object_id;
				if (isset($out[ $oid ])) {
					continue;
				}
				$name = (string) $row->name;
				if (strtolower($name) === 'uncategorized' || strtolower($name) === 'sin categorizar') {
					continue;
				}
				$out[ $oid ] = $name;
			}
		}
		return $out;
	}

	/**
	 * @return array{in:string,args:array<int,string>}
	 */
	private static function status_sql(): array {
		$excluded = array('pending', 'failed', 'cancelled', 'checkout-draft');
		if (has_filter('woocommerce_analytics_excluded_order_statuses')) {
			$filtered = apply_filters('woocommerce_analytics_excluded_order_statuses', $excluded);
			if (is_array($filtered) && $filtered) {
				$excluded = $filtered;
			}
		}
		$args = array();
		foreach ($excluded as $status) {
			$status = str_replace('wc-', '', (string) $status);
			$args[] = 'wc-' . $status;
		}
		$in = implode(',', array_fill(0, count($args), '%s'));
		return array('in' => $in, 'args' => $args);
	}

	private static function format_money(float $n): string {
		if (function_exists('wc_price')) {
			return html_entity_decode(wp_strip_all_tags(wc_price($n)), ENT_QUOTES, 'UTF-8');
		}
		return '$' . number_format($n, 0, ',', '.');
	}

	private static function format_int(int $n): string {
		return number_format($n, 0, ',', '.');
	}

	private static function delta_label(float $current, float $previous): string {
		if ($previous == 0.0) {
			return $current > 0 ? '+100%' : '0%';
		}
		$pct = (($current - $previous) / $previous) * 100;
		$sign = $pct >= 0 ? '+' : '';
		return $sign . number_format($pct, 0, ',', '.') . '%';
	}
}

SorteoSeguro_Ingresos::init();
