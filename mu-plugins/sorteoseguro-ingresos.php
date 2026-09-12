<?php
/**
 * Plugin Name: Sorteo Seguro – Ingresos reales
 * Description: Panel wp-admin con lo que pagaron los clientes (DigiPack aplicado). Solo lectura; no toca pedidos ni pagos.
 * Author: Sorteo Seguro
 * Version: 1.2.0
 *
 * mu-plugin: borrar este archivo y la carpeta sorteoseguro-ingresos para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Ingresos {

	const VERSION   = '1.2.0';
	const PAGE      = 'ss-ingresos';
	const DIR       = __DIR__ . '/sorteoseguro-ingresos';
	const CACHE_TTL = 120;
	/** false = oculto en menú; acceso solo por URL directa. */
	const SHOW_IN_MENU = true;

	/** Orden fijo del ranking DigiPack. */
	const PACK_ORDER = array('Individual', '3x2', '5x3', '10x5', '20x8');

	/** ISO-8601: 1=Lunes … 7=Domingo. 0 = todos. */
	const DOW_LABELS = array(
		0 => 'Todos los días',
		1 => 'Lunes',
		2 => 'Martes',
		3 => 'Miércoles',
		4 => 'Jueves',
		5 => 'Viernes',
		6 => 'Sábado',
		7 => 'Domingo',
	);

	public static function init(): void {
		add_action('admin_menu', array(__CLASS__, 'register_menu'), 64);
		add_action('admin_menu', array(__CLASS__, 'promote_menu_to_top'), 999);
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
	}

	public static function register_menu(): void {
		if (!self::SHOW_IN_MENU) {
			add_submenu_page(
				null,
				'Centro de Inteligencia Vixor',
				'CIV',
				'manage_woocommerce',
				self::PAGE,
				array(__CLASS__, 'render')
			);
			return;
		}

		add_submenu_page(
			'woocommerce',
			'Centro de Inteligencia Vixor',
			'CIV',
			'manage_woocommerce',
			self::PAGE,
			array(__CLASS__, 'render')
		);
	}

	public static function promote_menu_to_top(): void {
		if (!self::SHOW_IN_MENU) {
			return;
		}

		global $submenu;
		if (!isset($submenu['woocommerce']) || !is_array($submenu['woocommerce'])) {
			return;
		}

		$target = null;
		$index  = null;
		foreach ($submenu['woocommerce'] as $i => $item) {
			if (isset($item[2]) && $item[2] === self::PAGE) {
				$target = $item;
				$index  = $i;
				break;
			}
		}
		if ($target === null || $index === null) {
			return;
		}

		unset($submenu['woocommerce'][ $index ]);
		array_unshift($submenu['woocommerce'], $target);
	}

	public static function assets(string $hook): void {
		$allowed = array(
			'woocommerce_page_' . self::PAGE,
			'admin_page_' . self::PAGE,
		);
		if (!in_array($hook, $allowed, true)) {
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
		echo '<h1>Centro de Inteligencia Vixor</h1>';
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
		self::render_weekdays($data);
		self::render_payment($data);
		self::render_packs($data);
		self::render_tables($data);
		self::render_customers($data);
		echo '</div>';
	}

	/**
	 * @param array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime,dow:int,compare_note?:string} $range
	 */
	private static function render_filters(array $range): void {
		$base = admin_url('admin.php');
		$presets = array(
			'today'      => 'Hoy',
			'7d'         => 'Últimos 7 días',
			'month'      => 'Este mes',
			'last_month' => 'Mes anterior',
			'year'       => 'Este año',
			'all'         => 'Todo el historial',
			'custom'     => 'Personalizado',
		);
		$dow = isset($range['dow']) ? (int) $range['dow'] : 0;

		echo '<form class="ss-ing__filters" method="get" action="' . esc_url($base) . '">';
		echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE) . '" />';
		echo '<label class="screen-reader-text" for="ss_range">Período</label>';
		echo '<select name="ss_range" id="ss_range">';
		foreach ($presets as $key => $label) {
			echo '<option value="' . esc_attr($key) . '"' . selected($range['preset'], $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '<label for="ss_dow">Día';
		echo '<select name="ss_dow" id="ss_dow">';
		foreach (self::DOW_LABELS as $key => $label) {
			$text = $key === 0 ? $label : ('Todos los ' . $label);
			echo '<option value="' . esc_attr((string) $key) . '"' . selected($dow, $key, false) . '>' . esc_html($text) . '</option>';
		}
		echo '</select></label>';
		echo '<label>Desde <input type="date" name="ss_start" value="' . esc_attr($range['start']->format('Y-m-d')) . '" /></label>';
		echo '<label>Hasta <input type="date" name="ss_end" value="' . esc_attr($range['end']->format('Y-m-d')) . '" /></label>';
		echo '<button type="submit" class="ss-ing__btn">Ver período</button>';
		echo '</form>';

		$tz_name = wp_timezone()->getName();
		echo '<p class="ss-ing__meta">Período: <strong>' . esc_html($range['start']->format('d-m-Y H:i')) . '</strong> → <strong>' . esc_html($range['end']->format('d-m-Y H:i')) . '</strong> (' . esc_html($tz_name) . '). Solo lectura.</p>';
		if ($dow > 0 && isset(self::DOW_LABELS[ $dow ])) {
			echo '<p class="ss-ing__meta">Filtro día: <strong>todos los ' . esc_html(self::DOW_LABELS[ $dow ]) . '</strong> del período (hora Chile).</p>';
		}
		if (!empty($range['compare_note'])) {
			echo '<p class="ss-ing__meta">' . esc_html((string) $range['compare_note']) . '</p>';
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_kpis(array $data): void {
		$orders = max(0, (int) $data['orders']);
		$prev_orders = max(0, (int) $data['prev_orders']);
		$avg_ticket = $orders > 0 ? (float) $data['paid'] / $orders : 0.0;
		$avg_items  = $orders > 0 ? (float) $data['items'] / $orders : 0.0;
		$prev_avg_ticket = $prev_orders > 0 ? (float) $data['prev_paid'] / $prev_orders : 0.0;
		$prev_avg_items  = $prev_orders > 0 ? (float) $data['prev_items'] / $prev_orders : 0.0;

		$cards = array(
			array(
				'label' => 'Ingresado',
				'hint'  => 'Lo pagado, con DigiPack',
				'value' => self::format_money((float) $data['paid']),
				'delta' => self::delta_label((float) $data['paid'], (float) $data['prev_paid']),
			),
			array(
				'label' => 'Ticket promedio',
				'hint'  => 'Ingresado ÷ pedidos pagados',
				'value' => self::format_money($avg_ticket),
				'delta' => self::delta_label($avg_ticket, $prev_avg_ticket),
			),
			array(
				'label' => 'DigiTickets / pedido',
				'hint'  => 'Promedio por pedido pagado',
				'value' => number_format($avg_items, 1, ',', '.'),
				'delta' => self::delta_label($avg_items, $prev_avg_items),
			),
			array(
				'label' => 'Pedidos pagados',
				'hint'  => 'Sin duplicar por pack',
				'value' => self::format_int($orders),
				'delta' => self::delta_label((float) $orders, (float) $prev_orders),
			),
			array(
				'label' => 'DigiTickets',
				'hint'  => 'Unidades vendidas',
				'value' => self::format_int((int) $data['items']),
				'delta' => self::delta_label((float) $data['items'], (float) $data['prev_items']),
			),
			array(
				'label' => 'Precio lista',
				'hint'  => 'Sin DigiPack',
				'value' => self::format_money((float) $data['list']),
				'delta' => '',
			),
			array(
				'label' => 'Descuento DigiPack',
				'hint'  => 'Lista − cobrado',
				'value' => self::format_money((float) $data['pack_discount']),
				'delta' => '',
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
			echo '<p class="ss-ing__empty">No hay pedidos pagados en este período.</p>';
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
		foreach ($hours as $row) {
			$max_orders = max($max_orders, (int) $row['orders']);
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
		$top = array();
		foreach ($ranked as $row) {
			if ((int) $row['orders'] <= 0) {
				continue;
			}
			$top[] = $row;
			if (count($top) >= 3) {
				break;
			}
		}
		$peak_hours = array();
		foreach ($top as $i => $row) {
			$peak_hours[ (int) $row['hour'] ] = $i + 1;
		}

		echo '<section class="ss-ing__hours">';
		echo '<article class="ss-ing__chart ss-ing__chart--hours">';
		echo '<h2>Concentración por hora del día</h2>';
		echo '<p class="ss-ing__section-lead">Pedidos pagados del período, agrupados por hora local (cuando se creó el pedido).</p>';

		if ($top) {
			echo '<div class="ss-ing__peaks">';
			$labels = array('1ª concentración', '2ª concentración', '3ª concentración');
			foreach ($top as $i => $peak) {
				$h = (int) $peak['hour'];
				$next = sprintf('%02d:00', ($h + 1) % 24);
				echo '<p class="ss-ing__peak">';
				echo esc_html($labels[ $i ] ?? ('#' . ($i + 1))) . ': <strong>' . esc_html(sprintf('%02d:00', $h) . '–' . $next) . '</strong> — ';
				echo esc_html(self::format_int((int) $peak['orders'])) . ' pedidos · ' . esc_html(self::format_money((float) $peak['paid']));
				echo '</p>';
			}
			echo '</div>';
		}

		echo '<div class="ss-ing__bars ss-ing__bars--hours" role="img" aria-label="Pedidos por hora">';
		foreach ($hours as $row) {
			$raw = (int) $row['orders'];
			$pct = $max_orders > 0 && $raw > 0 ? max(2, (int) round(100 * $raw / $max_orders)) : 0;
			$rank = isset($peak_hours[ (int) $row['hour'] ]) ? (int) $peak_hours[ (int) $row['hour'] ] : 0;
			$peak_class = $rank > 0 ? ' is-peak is-peak-' . $rank : '';
			$tip = $row['label'] . ': ' . self::format_int($raw) . ' pedidos · ' . self::format_money((float) $row['paid']);
			echo '<div class="ss-ing__bar-wrap' . esc_attr($peak_class) . '" title="' . esc_attr($tip) . '">';
			echo '<span class="ss-ing__bar" style="height:' . esc_attr((string) $pct) . '%"></span>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="ss-ing__bar-axis ss-ing__bar-axis--hours">';
		foreach ($hours as $i => $row) {
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
	 * @param array<string,mixed> $data
	 */
	private static function render_weekdays(array $data): void {
		$rows = is_array($data['weekdays'] ?? null) ? $data['weekdays'] : array();
		if (!$rows) {
			return;
		}

		$max_orders = 0;
		foreach ($rows as $row) {
			$max_orders = max($max_orders, (int) $row['orders']);
		}

		echo '<section class="ss-ing__weekdays">';
		echo '<article class="ss-ing__chart ss-ing__chart--wide">';
		echo '<h2>Ventas por día de la semana</h2>';
		echo '<p class="ss-ing__section-lead">Suma del período filtrado (Lunes a Domingo, hora Chile).</p>';

		echo '<div class="ss-ing__bars ss-ing__bars--weekdays" role="img" aria-label="Pedidos por día de semana">';
		foreach ($rows as $row) {
			$raw = (int) $row['orders'];
			$pct = $max_orders > 0 && $raw > 0 ? max(4, (int) round(100 * $raw / $max_orders)) : 0;
			$tip = $row['label'] . ': ' . self::format_int($raw) . ' pedidos · ' . self::format_money((float) $row['paid']);
			echo '<div class="ss-ing__bar-wrap" title="' . esc_attr($tip) . '">';
			echo '<span class="ss-ing__bar" style="height:' . esc_attr((string) $pct) . '%"></span>';
			echo '<span class="ss-ing__bar-label">' . esc_html($row['short']) . '</span>';
			echo '</div>';
		}
		echo '</div>';

		echo '<table class="ss-ing__table ss-ing__table--compact">';
		echo '<thead><tr><th>Día</th><th>Pedidos</th><th>Ingresado</th></tr></thead><tbody>';
		foreach ($rows as $row) {
			echo '<tr><td>' . esc_html($row['label']) . '</td>';
			echo '<td>' . esc_html(self::format_int((int) $row['orders'])) . '</td>';
			echo '<td><strong>' . esc_html(self::format_money((float) $row['paid'])) . '</strong></td></tr>';
		}
		echo '</tbody></table></article></section>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_payment(array $data): void {
		$pay = is_array($data['payment'] ?? null) ? $data['payment'] : array();
		$gateways = is_array($data['gateways'] ?? null) ? $data['gateways'] : array();
		if (!$pay) {
			return;
		}

		echo '<section class="ss-ing__payment">';
		echo '<article class="ss-ing__table-card ss-ing__chart--wide">';
		echo '<h2>Estado de pagos y conversión</h2>';
		echo '<p class="ss-ing__section-lead">Intentos = pedido con pasarela elegida (Mercado Pago / Webpay), sin borrador de checkout.</p>';

		$attempts = (int) ($pay['attempts'] ?? 0);
		$paid_n   = (int) ($pay['paid'] ?? 0);
		$conv     = (float) ($pay['conversion'] ?? 0);

		echo '<div class="ss-ing__pay-summary">';
		echo '<div class="ss-ing__pay-stat"><span>Intentos de pago</span><strong>' . esc_html(self::format_int($attempts)) . '</strong></div>';
		echo '<div class="ss-ing__pay-stat"><span>Pagados</span><strong>' . esc_html(self::format_int($paid_n)) . '</strong></div>';
		echo '<div class="ss-ing__pay-stat ss-ing__pay-stat--highlight"><span>Conversión</span><strong>' . esc_html(number_format($conv, 1, ',', '.')) . '%</strong></div>';
		echo '</div>';

		echo '<table class="ss-ing__table ss-ing__table--compact">';
		echo '<thead><tr><th>Estado</th><th>Pedidos</th><th>% intentos</th></tr></thead><tbody>';
		$states = array(
			array('label' => 'Pagados', 'key' => 'paid'),
			array('label' => 'Pendientes', 'key' => 'pending'),
			array('label' => 'Fallidos', 'key' => 'failed'),
			array('label' => 'Cancelados', 'key' => 'cancelled'),
			array('label' => 'Reembolsados', 'key' => 'refunded'),
		);
		foreach ($states as $st) {
			$n = (int) ($pay[ $st['key'] ] ?? 0);
			$pct = $attempts > 0 ? round(100 * $n / $attempts, 1) : 0;
			echo '<tr><td>' . esc_html($st['label']) . '</td>';
			echo '<td>' . esc_html(self::format_int($n)) . '</td>';
			echo '<td>' . esc_html(number_format($pct, 1, ',', '.')) . '%</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3 class="ss-ing__h3">Ingresos por pasarela</h3>';
		if (!$gateways) {
			echo '<p class="ss-ing__section-lead">Sin datos de pasarela en este período.</p>';
		} else {
			echo '<table class="ss-ing__table">';
			echo '<thead><tr><th>Medio de pago</th><th>Intentos</th><th>Pagados</th><th>Conversión</th><th>Ingresado</th><th>% ventas</th></tr></thead><tbody>';
			foreach ($gateways as $gw) {
				echo '<tr>';
				echo '<td>' . esc_html((string) $gw['label']) . '</td>';
				echo '<td>' . esc_html(self::format_int((int) $gw['attempts'])) . '</td>';
				echo '<td>' . esc_html(self::format_int((int) $gw['paid'])) . '</td>';
				echo '<td>' . esc_html(number_format((float) $gw['conversion'], 1, ',', '.')) . '%</td>';
				echo '<td><strong>' . esc_html(self::format_money((float) $gw['revenue'])) . '</strong></td>';
				echo '<td>' . esc_html(number_format((float) $gw['share'], 1, ',', '.')) . '%</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</article></section>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_packs(array $data): void {
		$packs = is_array($data['packs'] ?? null) ? $data['packs'] : array();
		$highlights = is_array($data['pack_highlights'] ?? null) ? $data['pack_highlights'] : array();
		if (!$packs) {
			return;
		}

		echo '<section class="ss-ing__packs">';
		echo '<article class="ss-ing__table-card ss-ing__chart--wide">';
		echo '<h2>Ranking DigiPacks</h2>';
		echo '<p class="ss-ing__section-lead">Un pedido con 2 packs distintos cuenta en ambas filas. Los totales de pedidos del resumen no se duplican.</p>';

		if ($highlights) {
			echo '<p class="ss-ing__peak">';
			if (!empty($highlights['top_orders'])) {
				echo '🏆 Pack más vendido (pedidos): <strong>' . esc_html((string) $highlights['top_orders']) . '</strong>';
			}
			if (!empty($highlights['top_revenue'])) {
				echo ' · 💰 Pack que más factura: <strong>' . esc_html((string) $highlights['top_revenue']) . '</strong>';
			}
			echo '</p>';
		}

		echo '<table class="ss-ing__table">';
		echo '<thead><tr><th>DigiPack</th><th>Pedidos</th><th>DigiTickets</th><th>Ingresado</th><th>% pedidos</th></tr></thead><tbody>';
		foreach ($packs as $row) {
			echo '<tr>';
			echo '<td>' . esc_html((string) $row['name']) . '</td>';
			echo '<td>' . esc_html(self::format_int((int) $row['orders'])) . '</td>';
			echo '<td>' . esc_html(self::format_int((int) $row['qty'])) . '</td>';
			echo '<td><strong>' . esc_html(self::format_money((float) $row['paid'])) . '</strong></td>';
			echo '<td>' . esc_html(number_format((float) $row['order_pct'], 1, ',', '.')) . '%</td>';
			echo '</tr>';
		}
		echo '</tbody></table></article></section>';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function render_customers(array $data): void {
		$c = is_array($data['customers'] ?? null) ? $data['customers'] : array();
		if (!$c) {
			return;
		}

		echo '<section class="ss-ing__customers">';
		echo '<article class="ss-ing__table-card ss-ing__chart--wide">';
		echo '<h2>Clientes nuevos vs recurrentes</h2>';
		echo '<p class="ss-ing__section-lead">Por email de facturación normalizado. Clasificación al momento de cada compra pagada (el histórico no cambia).</p>';

		echo '<div class="ss-ing__pay-summary">';
		echo '<div class="ss-ing__pay-stat"><span>👤 Pedidos cliente nuevo</span><strong>' . esc_html(self::format_int((int) ($c['orders_new'] ?? 0))) . '</strong></div>';
		echo '<div class="ss-ing__pay-stat"><span>🔄 Pedidos cliente recurrente</span><strong>' . esc_html(self::format_int((int) ($c['orders_returning'] ?? 0))) . '</strong></div>';
		echo '<div class="ss-ing__pay-stat ss-ing__pay-stat--highlight"><span>% recurrentes (pedidos)</span><strong>' . esc_html(number_format((float) ($c['pct_returning_orders'] ?? 0), 1, ',', '.')) . '%</strong></div>';
		echo '</div>';

		echo '<table class="ss-ing__table ss-ing__table--compact">';
		echo '<thead><tr><th>Métrica</th><th>Valor</th></tr></thead><tbody>';
		echo '<tr><td>Compradores únicos nuevos</td><td>' . esc_html(self::format_int((int) ($c['unique_new'] ?? 0))) . '</td></tr>';
		echo '<tr><td>Compradores únicos recurrentes</td><td>' . esc_html(self::format_int((int) ($c['unique_returning'] ?? 0))) . '</td></tr>';
		echo '<tr><td>% compradores que ya habían comprado</td><td><strong>' . esc_html(number_format((float) ($c['pct_unique_returning'] ?? 0), 1, ',', '.')) . '%</strong></td></tr>';
		echo '</tbody></table></article></section>';
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
			$pct = $max > 0 && $raw > 0 ? max(2, (int) round(100 * $raw / $max)) : 0;
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
	 * @return array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime,compare_note:string}
	 */
	private static function parse_range(): array {
		$tz     = wp_timezone();
		$now    = new DateTime('now', $tz);
		$preset = isset($_GET['ss_range']) ? sanitize_key((string) wp_unslash($_GET['ss_range'])) : 'month';
		$allowed = array('today', '7d', 'month', 'last_month', 'year', 'all', 'custom');
		if (!in_array($preset, $allowed, true)) {
			$preset = 'month';
		}

		$dow = isset($_GET['ss_dow']) ? (int) wp_unslash($_GET['ss_dow']) : 0;
		if ($dow < 0 || $dow > 7) {
			$dow = 0;
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
		} elseif ($preset === 'all') {
			$start = self::first_paid_local_datetime($tz);
		} else {
			$start_s = isset($_GET['ss_start']) ? sanitize_text_field((string) wp_unslash($_GET['ss_start'])) : '';
			$end_s   = isset($_GET['ss_end']) ? sanitize_text_field((string) wp_unslash($_GET['ss_end'])) : '';
			if ($start_s === '' && $end_s === '') {
				$preset = 'all';
				$start  = self::first_paid_local_datetime($tz);
			} else {
				$ds = DateTime::createFromFormat('Y-m-d H:i:s', $start_s . ' 00:00:00', $tz);
				$de = DateTime::createFromFormat('Y-m-d H:i:s', $end_s . ' 23:59:59', $tz);
				if ($ds instanceof DateTime && $de instanceof DateTime && $ds <= $de) {
					$start = $ds;
					$end   = $de;
				} elseif ($ds instanceof DateTime && $end_s === '') {
					$start = $ds;
					$end   = clone $now;
				} elseif ($de instanceof DateTime && $start_s === '') {
					$start = self::first_paid_local_datetime($tz);
					$end   = $de;
				} else {
					$preset = 'month';
					$start  = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
				}
			}
		}

		if ($preset !== 'last_month' && $preset !== 'custom') {
			$end = clone $now;
		}

		$compare_note = '';
		$single_day = $start->format('Y-m-d') === $end->format('Y-m-d');

		if ($preset === 'today') {
			$prev_start = (clone $start)->modify('-1 day');
			$prev_end   = (clone $end)->modify('-1 day');
			$compare_note = 'Comparado con: ' . $prev_start->format('d-m-Y H:i') . ' → ' . $prev_end->format('d-m-Y H:i') . ' (mismo horario, día anterior).';
		} elseif ($preset === 'custom' && $single_day) {
			$prev_end = (clone $start)->modify('-1 second');
			$prev_start = new DateTime($prev_end->format('Y-m-d') . ' 00:00:00', $tz);
			$compare_note = 'Comparado con: ' . $prev_start->format('d-m-Y H:i') . ' → ' . $prev_end->format('d-m-Y H:i') . ' (día anterior completo).';
		} else {
			$span = max(1, $end->getTimestamp() - $start->getTimestamp());
			$prev_end = (clone $start)->modify('-1 second');
			$prev_start = (clone $prev_end)->setTimestamp($prev_end->getTimestamp() - $span);
			$compare_note = 'Comparado con: ' . $prev_start->format('d-m-Y H:i') . ' → ' . $prev_end->format('d-m-Y H:i') . ' (período anterior de igual duración).';
		}

		return array(
			'preset'       => $preset,
			'start'        => $start,
			'end'          => $end,
			'prev_start'   => $prev_start,
			'prev_end'     => $prev_end,
			'dow'          => $dow,
			'compare_note' => $compare_note,
		);
	}

	/**
	 * Primer pedido pagado conocido (hora local Chile), o 2020-01-01 si no hay datos.
	 */
	private static function first_paid_local_datetime(DateTimeZone $tz): DateTime {
		global $wpdb;

		$stats = $wpdb->prefix . 'wc_order_stats';
		$fallback = new DateTime('2020-01-01 00:00:00', $tz);
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stats)) !== $stats) {
			return $fallback;
		}

		$min = $wpdb->get_var("SELECT MIN(date_created_gmt) FROM {$stats} WHERE parent_id = 0");
		if (!is_string($min) || $min === '') {
			return $fallback;
		}

		try {
			$utc = new DateTimeZone('UTC');
			$dt  = new DateTime($min, $utc);
			$dt->setTimezone($tz);
			$dt->setTime(0, 0, 0);
			return $dt;
		} catch (Exception $e) {
			return $fallback;
		}
	}

	/**
	 * @param array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime,dow:int} $range
	 * @return array<string,mixed>
	 */
	private static function get_report(array $range): array {
		$key = 'ss_ing_' . self::VERSION . '_' . md5(
			$range['preset']
			. '|' . (string) (int) $range['dow']
			. '|' . $range['start']->format('c')
			. '|' . $range['end']->format('c')
		);
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
	 * @param array{preset:string,start:DateTime,end:DateTime,prev_start:DateTime,prev_end:DateTime,dow:int} $range
	 * @return array<string,mixed>
	 */
	private static function build_report(array $range): array {
		global $wpdb;

		$stats = $wpdb->prefix . 'wc_order_stats';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stats)) !== $stats) {
			return array('error' => 'No está la tabla de Analytics de WooCommerce (wc_order_stats).');
		}

		$utc = new DateTimeZone('UTC');
		$dow = isset($range['dow']) ? (int) $range['dow'] : 0;
		$cur = self::query_order_stats($stats, $range['start'], $range['end'], $utc, $dow);
		$prev = self::query_order_stats($stats, $range['prev_start'], $range['prev_end'], $utc, $dow);
		$days = self::query_days($stats, $range['start'], $range['end'], $utc, $dow);
		$hours = self::query_hours($stats, $range['start'], $range['end'], $utc, $dow);
		$weekdays = self::query_weekdays($stats, $range['start'], $range['end'], $utc, $dow);
		$breakdown = self::query_breakdown($stats, $range['start'], $range['end'], $utc, $dow);
		$payment = self::query_payment_funnel($stats, $range['start'], $range['end'], $utc, $dow);
		$gateways = self::query_gateways($payment, (float) $cur['paid']);
		$customers = self::query_customers($stats, $range['start'], $range['end'], $utc, $dow);

		$list = (float) $breakdown['list'];
		$ingresado = (float) $cur['paid'];
		$pack = max(0.0, $list - $ingresado);

		return array(
			'paid'             => $ingresado,
			'list'             => $list,
			'pack_discount'    => $pack,
			'orders'           => (int) $cur['orders'],
			'items'            => (int) $cur['items'],
			'prev_paid'        => (float) $prev['paid'],
			'prev_orders'      => (int) $prev['orders'],
			'prev_items'       => (int) $prev['items'],
			'days'             => $days,
			'hours'            => $hours,
			'weekdays'         => $weekdays,
			'payment'          => $payment['summary'],
			'gateways'         => $gateways,
			'packs'            => $breakdown['packs'],
			'pack_highlights'  => $breakdown['pack_highlights'],
			'products'         => $breakdown['products'],
			'categories'       => $breakdown['categories'],
			'customers'        => $customers,
		);
	}

	private static function local_iso_dow(string $gmt, DateTimeZone $utc): int {
		$dt = new DateTime($gmt, $utc);
		$dt->setTimezone(wp_timezone());
		return (int) $dt->format('N');
	}

	/**
	 * @param array<int,object> $rows
	 * @return array<int,object>
	 */
	private static function filter_rows_by_dow(array $rows, int $dow, DateTimeZone $utc, string $prop = 'date_created_gmt'): array {
		if ($dow <= 0) {
			return $rows;
		}
		$out = array();
		foreach ($rows as $row) {
			if (!isset($row->{$prop})) {
				continue;
			}
			if (self::local_iso_dow((string) $row->{$prop}, $utc) === $dow) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @return array<int,true>|null null = sin filtro de día
	 */
	private static function paid_order_ids_by_dow(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow): ?array {
		if ($dow <= 0) {
			return null;
		}

		global $wpdb;
		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::paid_status_sql();

		$sql = "SELECT order_id, date_created_gmt
			FROM {$stats}
			WHERE date_created_gmt >= %s AND date_created_gmt <= %s
			AND parent_id = 0 AND status IN ({$st['in']})";
		$rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		$map  = array();
		if (!is_array($rows)) {
			return $map;
		}
		foreach ($rows as $row) {
			if (self::local_iso_dow((string) $row->date_created_gmt, $utc) === $dow) {
				$map[ (int) $row->order_id ] = true;
			}
		}
		return $map;
	}

	/**
	 * @return array{paid:float,orders:int,items:int}
	 */
	private static function query_order_stats(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::paid_status_sql();

		if ($dow <= 0) {
			$sql = "SELECT
				COALESCE(SUM(net_total), 0) AS paid,
				COALESCE(SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END), 0) AS orders,
				COALESCE(SUM(num_items_sold), 0) AS items
				FROM {$stats}
				WHERE date_created_gmt >= %s AND date_created_gmt <= %s
				AND parent_id = 0
				AND status IN ({$st['in']})";

			$row = $wpdb->get_row($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
			return array(
				'paid'   => $row ? (float) $row->paid : 0.0,
				'orders' => $row ? (int) $row->orders : 0,
				'items'  => $row ? (int) $row->items : 0,
			);
		}

		$sql = "SELECT date_created_gmt, net_total, num_items_sold
			FROM {$stats}
			WHERE date_created_gmt >= %s AND date_created_gmt <= %s
			AND parent_id = 0
			AND status IN ({$st['in']})";
		$rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		$rows = self::filter_rows_by_dow(is_array($rows) ? $rows : array(), $dow, $utc);

		$paid = 0.0;
		$orders = 0;
		$items = 0;
		foreach ($rows as $row) {
			$paid += (float) $row->net_total;
			$orders++;
			$items += (int) $row->num_items_sold;
		}

		return array(
			'paid'   => $paid,
			'orders' => $orders,
			'items'  => $items,
		);
	}

	/**
	 * @return array{summary:array<string,mixed>,rows:array<int,object>}
	 */
	private static function query_payment_funnel(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$src  = self::payment_source_sql($stats);

		$admin_join = $src['admin_filter'] !== '' ? self::attempt_join_sql() : '';

		$sql = "SELECT s.order_id, s.status, s.net_total, s.parent_id, s.date_created_gmt, {$src['method_expr']} AS payment_method
			FROM {$stats} s
			{$src['join']}
			{$admin_join}
			WHERE s.date_created_gmt >= %s AND s.date_created_gmt <= %s
			AND s.parent_id = 0
			AND s.status NOT IN ('wc-checkout-draft')
			AND {$src['method_expr']} IS NOT NULL AND {$src['method_expr']} != ''
			{$src['admin_filter']}";

		$rows = $wpdb->get_results($wpdb->prepare($sql, array($from, $to)));
		if (!is_array($rows)) {
			$rows = array();
		}
		$rows = self::filter_rows_by_dow($rows, $dow, $utc);

		$summary = array(
			'attempts'   => 0,
			'paid'       => 0,
			'pending'    => 0,
			'failed'     => 0,
			'cancelled'  => 0,
			'refunded'   => 0,
			'conversion' => 0.0,
		);

		foreach ($rows as $row) {
			$summary['attempts']++;
			$bucket = self::payment_status_bucket((string) $row->status);
			$summary[ $bucket ]++;
		}

		if ($summary['attempts'] > 0) {
			$summary['conversion'] = round(100 * $summary['paid'] / $summary['attempts'], 1);
		}

		return array('summary' => $summary, 'rows' => $rows);
	}

	/**
	 * @param array{summary:array<string,mixed>,rows:array<int,object>} $payment
	 * @return array<int,array<string,mixed>>
	 */
	private static function query_gateways(array $payment, float $total_paid): array {
		$rows = is_array($payment['rows'] ?? null) ? $payment['rows'] : array();
		$agg  = array();

		foreach ($rows as $row) {
			$method = (string) $row->payment_method;
			$key    = $method !== '' ? $method : '_none';
			if (!isset($agg[ $key ])) {
				$agg[ $key ] = array(
					'label'      => self::payment_label($method),
					'attempts'   => 0,
					'paid'       => 0,
					'revenue'    => 0.0,
				);
			}
			$agg[ $key ]['attempts']++;
			$bucket = self::payment_status_bucket((string) $row->status);
			if ($bucket === 'paid') {
				$agg[ $key ]['paid']++;
				$agg[ $key ]['revenue'] += (float) $row->net_total;
			}
		}

		$out = array();
		foreach ($agg as $row) {
			$row['conversion'] = $row['attempts'] > 0 ? round(100 * $row['paid'] / $row['attempts'], 1) : 0.0;
			$row['share']      = $total_paid > 0 ? round(100 * $row['revenue'] / $total_paid, 1) : 0.0;
			$out[] = $row;
		}

		usort($out, static function ($a, $b) {
			return $b['revenue'] <=> $a['revenue'];
		});

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function query_customers(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::paid_status_sql();
		$addresses = $wpdb->prefix . 'wc_order_addresses';
		$has_addresses = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $addresses)) === $addresses;

		if ($has_addresses) {
			$sql = "SELECT s.order_id, s.date_created_gmt, LOWER(TRIM(a.email)) AS email
				FROM {$stats} s
				INNER JOIN {$addresses} a ON a.order_id = s.order_id AND a.address_type = 'billing'
				WHERE s.date_created_gmt >= %s AND s.date_created_gmt <= %s
				AND s.parent_id = 0 AND s.status IN ({$st['in']})
				AND a.email IS NOT NULL AND TRIM(a.email) != ''";
			$period_rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		} else {
			$sql = "SELECT s.order_id, s.date_created_gmt, LOWER(TRIM(pm.meta_value)) AS email
				FROM {$stats} s
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = s.order_id AND pm.meta_key = '_billing_email'
				WHERE s.date_created_gmt >= %s AND s.date_created_gmt <= %s
				AND s.parent_id = 0 AND s.status IN ({$st['in']})
				AND pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) != ''";
			$period_rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		}

		$period_rows = self::filter_rows_by_dow(is_array($period_rows) ? $period_rows : array(), $dow, $utc);

		if (!$period_rows) {
			return array(
				'orders_new'           => 0,
				'orders_returning'     => 0,
				'pct_returning_orders' => 0.0,
				'unique_new'           => 0,
				'unique_returning'     => 0,
				'pct_unique_returning' => 0.0,
			);
		}

		$emails = array();
		foreach ($period_rows as $row) {
			$email = (string) $row->email;
			if ($email !== '') {
				$emails[ $email ] = true;
			}
		}
		$email_list = array_keys($emails);

		$first_paid = self::first_paid_orders_by_email($stats, $email_list, $st, $has_addresses, $addresses);

		$orders_new = 0;
		$orders_returning = 0;
		$unique_new = array();
		$unique_returning = array();

		foreach ($period_rows as $row) {
			$email = (string) $row->email;
			$oid   = (int) $row->order_id;
			if ($email === '') {
				continue;
			}
			$is_new = isset($first_paid[ $email ]) && (int) $first_paid[ $email ]['order_id'] === $oid;
			if ($is_new) {
				$orders_new++;
				$unique_new[ $email ] = true;
			} else {
				$orders_returning++;
				$unique_returning[ $email ] = true;
			}
		}

		$total_orders = $orders_new + $orders_returning;
		$unique_total = count($unique_new) + count($unique_returning);

		return array(
			'orders_new'           => $orders_new,
			'orders_returning'     => $orders_returning,
			'pct_returning_orders' => $total_orders > 0 ? round(100 * $orders_returning / $total_orders, 1) : 0.0,
			'unique_new'           => count($unique_new),
			'unique_returning'     => count($unique_returning),
			'pct_unique_returning' => $unique_total > 0 ? round(100 * count($unique_returning) / $unique_total, 1) : 0.0,
		);
	}

	/**
	 * @param string[] $emails
	 * @param array{in:string,args:array<int,string>} $st
	 * @return array<string,array{order_id:int,date:string}>
	 */
	private static function first_paid_orders_by_email(string $stats, array $emails, array $st, bool $has_addresses, string $addresses): array {
		global $wpdb;

		$emails = array_values(array_filter(array_map('strtolower', array_map('trim', $emails))));
		if (!$emails) {
			return array();
		}

		$out = array();
		$chunks = array_chunk($emails, 200);
		foreach ($chunks as $chunk) {
			$holders = implode(',', array_fill(0, count($chunk), '%s'));
			if ($has_addresses) {
				$sql = "SELECT LOWER(TRIM(a.email)) AS email, s.order_id, s.date_created_gmt
					FROM {$stats} s
					INNER JOIN {$addresses} a ON a.order_id = s.order_id AND a.address_type = 'billing'
					WHERE s.parent_id = 0 AND s.status IN ({$st['in']})
					AND LOWER(TRIM(a.email)) IN ({$holders})
					ORDER BY s.date_created_gmt ASC, s.order_id ASC";
			} else {
				$sql = "SELECT LOWER(TRIM(pm.meta_value)) AS email, s.order_id, s.date_created_gmt
					FROM {$stats} s
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = s.order_id AND pm.meta_key = '_billing_email'
					WHERE s.parent_id = 0 AND s.status IN ({$st['in']})
					AND LOWER(TRIM(pm.meta_value)) IN ({$holders})
					ORDER BY s.date_created_gmt ASC, s.order_id ASC";
			}
			$rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($st['args'], $chunk)));
			if (!is_array($rows)) {
				continue;
			}
			foreach ($rows as $row) {
				$email = (string) $row->email;
				if ($email === '' || isset($out[ $email ])) {
					continue;
				}
				$out[ $email ] = array(
					'order_id' => (int) $row->order_id,
					'date'     => (string) $row->date_created_gmt,
				);
			}
		}

		return $out;
	}

	/**
	 * @return array<int,array{hour:int,label:string,paid:float,orders:int}>
	 */
	private static function query_hours(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		$rows = self::fetch_paid_order_rows($stats, $start, $end, $utc, $dow);
		$tz   = wp_timezone();
		$bucket = array();
		for ($h = 0; $h < 24; $h++) {
			$bucket[ $h ] = array(
				'hour'   => $h,
				'label'  => sprintf('%02d:00', $h),
				'paid'   => 0.0,
				'orders' => 0,
			);
		}
		foreach ($rows as $row) {
			$dt = new DateTime((string) $row->date_created_gmt, $utc);
			$dt->setTimezone($tz);
			$h = (int) $dt->format('G');
			$bucket[ $h ]['paid'] += (float) $row->net_total;
			$bucket[ $h ]['orders']++;
		}
		return array_values($bucket);
	}

	/**
	 * @return array<int,array{label:string,short:string,paid:float,orders:int}>
	 */
	private static function query_weekdays(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		$rows = self::fetch_paid_order_rows($stats, $start, $end, $utc, $dow);
		$tz   = wp_timezone();
		$names = array(
			1 => array('Lunes', 'Lun'),
			2 => array('Martes', 'Mar'),
			3 => array('Miércoles', 'Mié'),
			4 => array('Jueves', 'Jue'),
			5 => array('Viernes', 'Vie'),
			6 => array('Sábado', 'Sáb'),
			7 => array('Domingo', 'Dom'),
		);
		$bucket = array();
		foreach ($names as $d => $labels) {
			$bucket[ $d ] = array(
				'label'  => $labels[0],
				'short'  => $labels[1],
				'paid'   => 0.0,
				'orders' => 0,
			);
		}
		foreach ($rows as $row) {
			$dt = new DateTime((string) $row->date_created_gmt, $utc);
			$dt->setTimezone($tz);
			$d = (int) $dt->format('N');
			if (!isset($bucket[ $d ])) {
				continue;
			}
			$bucket[ $d ]['paid'] += (float) $row->net_total;
			$bucket[ $d ]['orders']++;
		}
		return array_values($bucket);
	}

	/**
	 * @return array<int,array{label:string,paid:float,orders:int}>
	 */
	private static function query_days(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		$rows = self::fetch_paid_order_rows($stats, $start, $end, $utc, $dow);
		$tz   = wp_timezone();

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

		foreach ($rows as $row) {
			$dt = new DateTime((string) $row->date_created_gmt, $utc);
			$dt->setTimezone($tz);
			$key = $by_month ? $dt->format('Y-m') : $dt->format('Y-m-d');
			if (!isset($bucket[ $key ])) {
				continue;
			}
			$bucket[ $key ]['paid'] += (float) $row->net_total;
			$bucket[ $key ]['orders']++;
		}

		return array_values($bucket);
	}

	/**
	 * @return array<int,object>
	 */
	private static function fetch_paid_order_rows(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::paid_status_sql();

		$sql = "SELECT date_created_gmt, net_total
			FROM {$stats}
			WHERE date_created_gmt >= %s AND date_created_gmt <= %s
			AND parent_id = 0 AND status IN ({$st['in']})";
		$rows = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		return self::filter_rows_by_dow(is_array($rows) ? $rows : array(), $dow, $utc);
	}

	/**
	 * @return array{list:float,paid:float,discount:float,products:array,categories:array,packs:array,pack_highlights:array}
	 */
	private static function query_breakdown(string $stats, DateTime $start, DateTime $end, DateTimeZone $utc, int $dow = 0): array {
		global $wpdb;

		$from = (clone $start)->setTimezone($utc)->format('Y-m-d H:i:s');
		$to   = (clone $end)->setTimezone($utc)->format('Y-m-d H:i:s');
		$st   = self::paid_status_sql();
		$lookup = $wpdb->prefix . 'wc_order_product_lookup';
		$items_t = $wpdb->prefix . 'woocommerce_order_items';
		$meta_t  = $wpdb->prefix . 'woocommerce_order_itemmeta';

		$empty = array(
			'list'            => 0.0,
			'paid'            => 0.0,
			'discount'        => 0.0,
			'products'        => array(),
			'categories'      => array(),
			'packs'           => array(),
			'pack_highlights' => array(),
		);

		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup)) !== $lookup) {
			return $empty;
		}

		$allowed_ids = self::paid_order_ids_by_dow($stats, $start, $end, $utc, $dow);
		if (is_array($allowed_ids) && !$allowed_ids) {
			return $empty;
		}

		$sql = "SELECT p.order_id, p.product_id, p.product_qty, p.product_net_revenue, p.order_item_id
			FROM {$lookup} p
			INNER JOIN {$stats} s ON s.order_id = p.order_id
			WHERE s.date_created_gmt >= %s AND s.date_created_gmt <= %s
			AND s.parent_id = 0 AND s.status IN ({$st['in']})";
		$lines = $wpdb->get_results($wpdb->prepare($sql, array_merge(array($from, $to), $st['args'])));
		if (!is_array($lines) || !$lines) {
			return $empty;
		}

		if (is_array($allowed_ids)) {
			$lines = array_values(
				array_filter(
					$lines,
					static function ($line) use ($allowed_ids) {
						return isset($allowed_ids[ (int) $line->order_id ]);
					}
				)
			);
			if (!$lines) {
				return $empty;
			}
		}

		$product_titles = self::product_names(array_unique(array_map(static function ($l) {
			return (int) $l->product_id;
		}, $lines)));

		$fee_sql = "SELECT oi.order_id, oi.order_item_name, oi.order_item_id, m.meta_key, m.meta_value
			FROM {$items_t} oi
			INNER JOIN {$stats} s ON s.order_id = oi.order_id
			INNER JOIN {$meta_t} m ON m.order_item_id = oi.order_item_id AND m.meta_key IN ('_line_total', '_line_tax')
			WHERE oi.order_item_type = 'fee'
			AND s.date_created_gmt >= %s AND s.date_created_gmt <= %s
			AND s.parent_id = 0 AND s.status IN ({$st['in']})";
		$fee_rows = $wpdb->get_results($wpdb->prepare($fee_sql, array_merge(array($from, $to), $st['args'])));

		$fees_by_item = array();
		$promo_fees_by_order = array();
		if (is_array($fee_rows)) {
			foreach ($fee_rows as $fr) {
				$oid = (int) $fr->order_id;
				$iid = (int) $fr->order_item_id;
				if (!isset($fees_by_item[ $oid ][ $iid ])) {
					$fees_by_item[ $oid ][ $iid ] = array(
						'name'  => (string) $fr->order_item_name,
						'total' => 0.0,
						'tax'   => 0.0,
					);
				}
				if ($fr->meta_key === '_line_tax') {
					$fees_by_item[ $oid ][ $iid ]['tax'] += (float) $fr->meta_value;
				} else {
					$fees_by_item[ $oid ][ $iid ]['total'] += (float) $fr->meta_value;
				}
			}
		}

		foreach ($fees_by_item as $oid => $items) {
			foreach ($items as $fee) {
				if (strpos($fee['name'], '[PROMO]') === false) {
					continue;
				}
				$amt = (float) $fee['total'] + (float) $fee['tax'];
				if ($amt >= 0) {
					continue;
				}
				$promo_fees_by_order[ $oid ][] = array(
					'name'   => (string) $fee['name'],
					'amount' => $amt,
				);
			}
		}

		$promo_by_order = array();
		foreach ($promo_fees_by_order as $oid => $fees) {
			$sum = 0.0;
			foreach ($fees as $fee) {
				$sum += (float) $fee['amount'];
			}
			if ($sum < 0) {
				$promo_by_order[ $oid ] = $sum;
			}
		}

		$by_order = array();
		foreach ($lines as $line) {
			$by_order[ (int) $line->order_id ][] = $line;
		}

		$agg = array();
		$pack_agg = array();
		$pack_orders = array();

		foreach ($by_order as $oid => $order_lines) {
			$sum_list = 0.0;
			foreach ($order_lines as $line) {
				$sum_list += (float) $line->product_net_revenue;
			}
			$promo = isset($promo_by_order[ $oid ]) ? (float) $promo_by_order[ $oid ] : 0.0;
			$left = abs($promo);
			$n = count($order_lines);

			foreach ($order_lines as $i => $line) {
				$pid   = (int) $line->product_id;
				$list  = (float) $line->product_net_revenue;
				$qty   = (int) $line->product_qty;
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
					$agg[ $pid ] = array('qty' => 0, 'list' => 0.0, 'paid' => 0.0, 'discount' => 0.0);
				}
				$agg[ $pid ]['qty']      += $qty;
				$agg[ $pid ]['list']     += $list;
				$agg[ $pid ]['paid']     += $paid;
				$agg[ $pid ]['discount'] += $share;

				$title = isset($product_titles[ $pid ]) ? $product_titles[ $pid ] : '';
				$pack_slug = self::resolve_line_pack_slug($oid, $title, isset($promo_fees_by_order[ $oid ]) ? $promo_fees_by_order[ $oid ] : array());

				if (!isset($pack_agg[ $pack_slug ])) {
					$pack_agg[ $pack_slug ] = array('qty' => 0, 'paid' => 0.0, 'orders' => array());
				}
				$pack_agg[ $pack_slug ]['qty']  += $qty;
				$pack_agg[ $pack_slug ]['paid'] += $paid;
				$pack_agg[ $pack_slug ]['orders'][ $oid ] = true;
				$pack_orders[ $oid ][ $pack_slug ] = true;
			}
		}

		$total_paid_orders = count($by_order);
		$packs = array();
		foreach ($pack_agg as $slug => $row) {
			$order_count = count($row['orders']);
			$packs[] = array(
				'name'      => $slug,
				'orders'    => $order_count,
				'qty'       => (int) $row['qty'],
				'paid'      => (float) $row['paid'],
				'order_pct' => $total_paid_orders > 0 ? round(100 * $order_count / $total_paid_orders, 1) : 0.0,
			);
		}

		usort($packs, static function ($a, $b) {
			$oa = array_search($a['name'], self::PACK_ORDER, true);
			$ob = array_search($b['name'], self::PACK_ORDER, true);
			$oa = $oa === false ? 99 : $oa;
			$ob = $ob === false ? 99 : $ob;
			if ($oa !== $ob) {
				return $oa <=> $ob;
			}
			return $b['paid'] <=> $a['paid'];
		});

		$top_orders = '';
		$top_revenue = '';
		if ($packs) {
			$by_ord = $packs;
			usort($by_ord, static function ($a, $b) {
				return $b['orders'] <=> $a['orders'];
			});
			$top_orders = (string) $by_ord[0]['name'];
			$by_rev = $packs;
			usort($by_rev, static function ($a, $b) {
				return $b['paid'] <=> $a['paid'];
			});
			$top_revenue = (string) $by_rev[0]['name'];
		}

		$ids = array_keys($agg);
		$names = self::product_names($ids);
		$cats  = self::product_categories($ids);

		$products = array();
		$categories = array();
		$list_total = 0.0;

		foreach ($agg as $pid => $row) {
			$list_total += $row['list'];
			$products[] = array(
				'name'     => isset($names[ $pid ]) ? $names[ $pid ] : ('Producto #' . $pid),
				'qty'      => $row['qty'],
				'list'     => $row['list'],
				'paid'     => $row['paid'],
				'discount' => $row['discount'],
			);
			$cat = isset($cats[ $pid ]) ? $cats[ $pid ] : 'Sin categoría';
			if (!isset($categories[ $cat ])) {
				$categories[ $cat ] = array('name' => $cat, 'qty' => 0, 'list' => 0.0, 'paid' => 0.0, 'discount' => 0.0);
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

		$paid_total = 0.0;
		$disc_total = 0.0;
		foreach ($agg as $row) {
			$paid_total += $row['paid'];
			$disc_total += $row['discount'];
		}

		return array(
			'list'            => $list_total,
			'paid'            => $paid_total,
			'discount'        => $disc_total,
			'products'        => $products,
			'categories'      => $cat_rows,
			'packs'           => $packs,
			'pack_highlights' => array(
				'top_orders'  => $top_orders,
				'top_revenue' => $top_revenue,
			),
		);
	}

	/**
	 * @param array<int,array{name:string,amount:float}> $order_promo_fees
	 */
	private static function resolve_line_pack_slug(int $order_id, string $product_title, array $order_promo_fees): string {
		unset($order_id);
		if (!$order_promo_fees) {
			return 'Individual';
		}

		$needle = strtolower(trim($product_title));
		foreach ($order_promo_fees as $fee) {
			$name = (string) $fee['name'];
			if ($needle !== '' && stripos($name, $needle) !== false) {
				return self::normalize_pack_slug($name);
			}
		}

		if (count($order_promo_fees) === 1) {
			return self::normalize_pack_slug((string) $order_promo_fees[0]['name']);
		}

		return self::normalize_pack_slug((string) $order_promo_fees[0]['name']);
	}

	private static function normalize_pack_slug(string $fee_name): string {
		$base = preg_replace('/^\[PROMO\]\s*/i', '', $fee_name);
		if (!is_string($base)) {
			return 'Individual';
		}
		$parts = explode(' — ', $base);
		$base  = trim($parts[0]);

		if (preg_match('/(\d+)\s*[x×]\s*(\d+)/iu', $base, $m)) {
			return $m[1] . 'x' . $m[2];
		}

		$lower = strtolower($base);
		foreach (self::PACK_ORDER as $pack) {
			if ($pack !== 'Individual' && strpos($lower, strtolower($pack)) !== false) {
				return $pack;
			}
		}

		return $base !== '' ? $base : 'Individual';
	}

	private static function payment_label(string $method): string {
		if ($method === '') {
			return 'Sin pasarela';
		}
		if (strpos($method, 'woo-mercado-pago') === 0) {
			return 'Mercado Pago';
		}
		if ($method === 'wcplugingateway' || strpos($method, 'webpay') !== false || strpos($method, 'transbank') !== false) {
			return 'Webpay Plus';
		}
		return $method;
	}

	/**
	 * Fuente de payment_method: HPOS (wc_orders) → stats → postmeta legacy.
	 *
	 * @return array{join:string,method_expr:string,admin_filter:string}
	 */
	private static function payment_source_sql(string $stats): array {
		global $wpdb;

		$orders = self::wc_orders_table();
		if ($orders) {
			return array(
				'join'         => "INNER JOIN {$orders} o ON o.id = s.order_id",
				'method_expr'  => 'o.payment_method',
				'admin_filter' => '',
			);
		}

		if (self::table_has_column($stats, 'payment_method')) {
			return array(
				'join'         => '',
				'method_expr'  => 's.payment_method',
				'admin_filter' => "AND (pm_cv.meta_value IS NULL OR pm_cv.meta_value NOT IN ('admin'))",
			);
		}

		return array(
			'join'         => "INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = s.order_id AND pm.meta_key = '_payment_method'",
			'method_expr'  => 'pm.meta_value',
			'admin_filter' => "AND (pm_cv.meta_value IS NULL OR pm_cv.meta_value NOT IN ('admin'))",
		);
	}

	private static function wc_orders_table(): ?string {
		global $wpdb;

		static $resolved = null;
		if ($resolved !== null) {
			return $resolved !== '' ? $resolved : null;
		}

		$table = $wpdb->prefix . 'wc_orders';
		$resolved = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) ? $table : '';
		return $resolved !== '' ? $resolved : null;
	}

	private static function table_has_column(string $table, string $column): bool {
		global $wpdb;

		static $cache = array();
		$key = $table . '.' . $column;
		if (!isset($cache[ $key ])) {
			$cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
			$cache[ $key ] = is_array($cols) && in_array($column, $cols, true);
		}
		return $cache[ $key ];
	}

	private static function payment_status_bucket(string $status): string {
		$status = str_replace('wc-', '', $status);
		if (in_array($status, array('processing', 'completed'), true)) {
			return 'paid';
		}
		if ($status === 'pending') {
			return 'pending';
		}
		if ($status === 'failed') {
			return 'failed';
		}
		if ($status === 'cancelled') {
			return 'cancelled';
		}
		if ($status === 'refunded') {
			return 'refunded';
		}
		return 'cancelled';
	}

	private static function attempt_join_sql(): string {
		global $wpdb;
		return "LEFT JOIN {$wpdb->postmeta} pm_cv ON pm_cv.post_id = s.order_id AND pm_cv.meta_key = '_created_via'";
	}

	/**
	 * @return array{in:string,args:array<int,string>}
	 */
	private static function paid_status_sql(): array {
		$statuses = array('processing', 'completed');
		$args = array();
		foreach ($statuses as $status) {
			$args[] = 'wc-' . $status;
		}
		return array(
			'in'   => implode(',', array_fill(0, count($args), '%s')),
			'args' => $args,
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
