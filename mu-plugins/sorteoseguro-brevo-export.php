<?php
/**
 * Plugin Name: Sorteo Seguro – Exportar contactos Brevo
 * Description: Panel wp-admin para descargar CSV (EMAIL, FIRSTNAME, LASTNAME) listo para importar en Brevo.
 * Author: Sorteo Seguro
 * Version: 1.1.0
 *
 * mu-plugin: borrar este archivo para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Brevo_Export {

	const VERSION = '1.1.0';
	const PAGE    = 'ss-brevo-export';
	const ACTION  = 'ss_brevo_export';
	const NONCE   = 'ss_brevo_export';

	const SKIP_ROLES = array('administrator', 'shop_manager', 'editor', 'author');

	const PAID_STATUSES = array('completed', 'processing');

	public static function init(): void {
		add_action('admin_menu', array(__CLASS__, 'register_menu'));
		add_action('admin_post_' . self::ACTION, array(__CLASS__, 'handle_export'));
		add_action('admin_head', array(__CLASS__, 'print_admin_css'));
	}

	public static function conditions(): array {
		return array(
			'registrados' => array(
				'label' => 'Registrados en el período',
				'help'  => 'Cuentas creadas entre esas fechas, hayan comprado o no. Si marcas sorteos, solo quienes además compraron esos sorteos.',
			),
			'compradores' => array(
				'label' => 'Compradores en el período',
				'help'  => 'Quienes pagaron un pedido (Completado o Procesando) en esas fechas, con cuenta o guest.',
			),
			'registrados_y_compraron' => array(
				'label' => 'Registrados que compraron',
				'help'  => 'Cuentas creadas en el período y que además compraron en el mismo período.',
			),
			'registrados_mas_compradores' => array(
				'label' => 'Registrados + compradores',
				'help'  => 'Unión: cuentas nuevas en el período y quienes compraron en el período (aunque la cuenta sea anterior o sea guest).',
			),
			'registrados_sin_compra' => array(
				'label' => 'Registrados que no compraron',
				'help'  => 'Cuentas creadas en el período sin pedido pagado (de los sorteos marcados, si eliges alguno).',
			),
		);
	}

	public static function register_menu(): void {
		add_menu_page(
			'Exportar contactos Brevo',
			'Exportar Brevo',
			'manage_woocommerce',
			self::PAGE,
			array(__CLASS__, 'render'),
			'dashicons-media-spreadsheet',
			58
		);
	}

	public static function print_admin_css(): void {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->id !== 'toplevel_page_' . self::PAGE) {
			return;
		}
		echo '<style id="ss-brevo-export">
.ss-brevo,
.ss-brevo p,
.ss-brevo h1,
.ss-brevo h2,
.ss-brevo label,
.ss-brevo input,
.ss-brevo button,
.ss-brevo strong,
.ss-brevo li,
.ss-brevo a {
	font-family: var(--ss-font-family, "Montserrat", "Poppins", "Segoe UI", sans-serif) !important;
}
.ss-brevo { max-width: 820px; color: #1f2430; }
.ss-brevo h1 { margin: 0 0 8px; font-size: 24px; font-weight: 800; }
.ss-brevo__lead { margin: 0 0 18px; color: #6b7280; font-size: 14px; line-height: 1.45; }
.ss-brevo__card {
	background: #fff;
	border: 1px solid #e6e8ef;
	border-radius: 14px;
	padding: 20px 22px 18px;
	margin: 0 0 18px;
}
.ss-brevo__row { display: flex; gap: 16px; flex-wrap: wrap; margin: 0 0 16px; }
.ss-brevo__field { display: flex; flex-direction: column; gap: 6px; min-width: 180px; }
.ss-brevo__field span,
.ss-brevo__label {
	font-size: 12px;
	font-weight: 700;
	color: #6b7280;
	margin: 0 0 8px;
}
.ss-brevo__hint { margin: 0 0 10px; color: #6b7280; font-size: 12px; line-height: 1.4; }
.ss-brevo input[type="date"] {
	border: 1px solid #e6e8ef;
	border-radius: 8px;
	padding: 8px 10px;
	font-size: 14px;
}
.ss-brevo__opts { display: flex; flex-direction: column; gap: 10px; margin: 4px 0 18px; }
.ss-brevo__opt,
.ss-brevo__check {
	display: block;
	border: 1px solid #e6e8ef;
	border-radius: 8px;
	padding: 12px 14px;
	background: #fff;
	cursor: pointer;
}
.ss-brevo__opt:hover,
.ss-brevo__check:hover { border-color: #6b3bb8; }
.ss-brevo__opt.is-on,
.ss-brevo__check.is-on { border-color: #6b3bb8; background: #f3ecff; }
.ss-brevo__opt strong { display: block; font-size: 14px; margin: 0 0 2px; }
.ss-brevo__opt small,
.ss-brevo__check small { color: #6b7280; font-size: 12px; line-height: 1.4; }
.ss-brevo__sorteos {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: 280px;
	overflow: auto;
	margin: 0 0 16px;
	padding: 2px;
}
.ss-brevo__sorteos-bar { margin: 0 0 8px; font-size: 12px; }
.ss-brevo__sorteos-bar a {
	color: #6b3bb8 !important;
	font-weight: 700;
	text-decoration: none;
}
.ss-brevo__sorteos-bar a:hover,
.ss-brevo__sorteos-bar a:focus { color: #552c9a !important; }
.ss-brevo .button-primary {
	background: #6b3bb8 !important;
	border-color: #6b3bb8 !important;
	color: #fff !important;
	border-radius: 8px !important;
	padding: 6px 16px !important;
	height: auto !important;
}
.ss-brevo .button-primary:hover,
.ss-brevo .button-primary:focus,
.ss-brevo .button-primary:active {
	background: #552c9a !important;
	border-color: #552c9a !important;
	color: #fff !important;
}
.ss-brevo__steps { color: #6b7280; font-size: 13px; line-height: 1.5; }
.ss-brevo__steps li { margin: 0 0 6px; }
</style>';
	}

	public static function render(): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$today = wp_date('Y-m-d');
		$from  = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : wp_date('Y-m-d', strtotime('-90 days'));
		$to    = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : $today;
		$cond  = isset($_GET['condicion']) ? sanitize_key(wp_unslash($_GET['condicion'])) : 'registrados_mas_compradores';
		if (!isset(self::conditions()[ $cond ])) {
			$cond = 'registrados_mas_compradores';
		}
		$selected = self::parse_product_ids(isset($_GET['sorteos']) ? wp_unslash($_GET['sorteos']) : array());
		$sorteos  = self::lottery_products();
		$notice   = isset($_GET['ss_brevo']) ? sanitize_key(wp_unslash($_GET['ss_brevo'])) : '';

		echo '<div class="wrap ss-brevo">';
		echo '<h1>Exportar contactos para Brevo</h1>';
		echo '<p class="ss-brevo__lead">Descarga un CSV con las columnas <strong>EMAIL, FIRSTNAME, LASTNAME</strong> listo para importar. Un email = una fila. No incluye staff.</p>';

		if ($notice === 'empty') {
			echo '<div class="notice notice-warning is-dismissible"><p>No hay contactos para esos filtros.</p></div>';
		} elseif ($notice === 'dates') {
			echo '<div class="notice notice-error is-dismissible"><p>Revisa las fechas: completa desde y hasta, y “desde” no puede ser posterior a “hasta”.</p></div>';
		} elseif ($notice === 'need_filter') {
			echo '<div class="notice notice-error is-dismissible"><p>Indica un rango de fechas o marca al menos un sorteo.</p></div>';
		} elseif ($notice === 'wc') {
			echo '<div class="notice notice-error is-dismissible"><p>WooCommerce tiene que estar activo para exportar compradores.</p></div>';
		}

		echo '<form class="ss-brevo__card" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '" />';
		wp_nonce_field(self::NONCE);

		echo '<div class="ss-brevo__row">';
		echo '<label class="ss-brevo__field"><span>Desde (opcional)</span>';
		echo '<input type="date" name="from" value="' . esc_attr($from) . '" /></label>';
		echo '<label class="ss-brevo__field"><span>Hasta (opcional)</span>';
		echo '<input type="date" name="to" value="' . esc_attr($to) . '" /></label>';
		echo '</div>';
		echo '<p class="ss-brevo__hint">Vacío = sin filtro de fecha. Si marcas sorteos y dejas las fechas vacías, exporta todos los compradores de esos sorteos.</p>';

		echo '<p class="ss-brevo__label">Sorteos (opcional, uno o más)</p>';
		echo '<p class="ss-brevo__hint">Complementa la condición y las fechas. Sin sorteos marcados, cuenta cualquier compra.</p>';
		if ($sorteos) {
			echo '<p class="ss-brevo__sorteos-bar"><a href="#" class="ss-brevo-all">Marcar todos</a> · <a href="#" class="ss-brevo-none">Ninguno</a></p>';
			echo '<div class="ss-brevo__sorteos">';
			foreach ($sorteos as $item) {
				$id  = (int) $item['id'];
				$on  = in_array($id, $selected, true) ? ' is-on' : '';
				$tag = $item['closed'] ? ' · finalizado' : '';
				echo '<label class="ss-brevo__check' . esc_attr($on) . '">';
				echo '<input type="checkbox" name="sorteos[]" value="' . esc_attr((string) $id) . '"' . checked(in_array($id, $selected, true), true, false) . ' /> ';
				echo esc_html($item['title']);
				if ($tag !== '') {
					echo '<small>' . esc_html($tag) . '</small>';
				}
				echo '</label>';
			}
			echo '</div>';
		} else {
			echo '<p class="ss-brevo__hint">No se encontraron sorteos (productos Lottery).</p>';
		}

		echo '<p class="ss-brevo__label">Condición</p>';
		echo '<div class="ss-brevo__opts">';
		foreach (self::conditions() as $key => $item) {
			$on = $key === $cond ? ' is-on' : '';
			echo '<label class="ss-brevo__opt' . esc_attr($on) . '">';
			echo '<input type="radio" name="condicion" value="' . esc_attr($key) . '"' . checked($cond, $key, false) . ' /> ';
			echo '<strong>' . esc_html($item['label']) . '</strong>';
			echo '<small>' . esc_html($item['help']) . '</small>';
			echo '</label>';
		}
		echo '</div>';

		echo '<button type="submit" class="button button-primary">Descargar CSV para Brevo</button>';
		echo '</form>';

		echo '<div class="ss-brevo__card">';
		echo '<h2 style="margin:0 0 8px;font-size:16px;">Cómo subirlo a Brevo</h2>';
		echo '<ol class="ss-brevo__steps">';
		echo '<li>En Brevo: <strong>Contactos → Añadir contactos → Importar contactos</strong>.</li>';
		echo '<li>Sube este CSV. Si aparecen FIELD1 / FIELD2, marca que la primera fila son encabezados.</li>';
		echo '<li>Mapea EMAIL → Email, FIRSTNAME → Nombre, LASTNAME → Apellidos.</li>';
		echo '<li>Elige la lista y confirma.</li>';
		echo '</ol>';
		echo '</div>';
		echo '</div>';

		echo '<script>
		(function(){
			var form = document.querySelector("form.ss-brevo__card");
			if (!form) return;
			form.addEventListener("change", function(e){
				if (!e.target) return;
				if (e.target.name === "condicion") {
					form.querySelectorAll(".ss-brevo__opt").forEach(function(el){ el.classList.remove("is-on"); });
					if (e.target.closest) e.target.closest(".ss-brevo__opt").classList.add("is-on");
				}
				if (e.target.name === "sorteos[]") {
					var lab = e.target.closest && e.target.closest(".ss-brevo__check");
					if (lab) lab.classList.toggle("is-on", e.target.checked);
				}
			});
			var all = form.querySelector(".ss-brevo-all");
			var none = form.querySelector(".ss-brevo-none");
			function setAll(on){
				form.querySelectorAll("input[name=\\"sorteos[]\\"]").forEach(function(cb){
					cb.checked = on;
					var lab = cb.closest && cb.closest(".ss-brevo__check");
					if (lab) lab.classList.toggle("is-on", on);
				});
			}
			if (all) all.addEventListener("click", function(e){ e.preventDefault(); setAll(true); });
			if (none) none.addEventListener("click", function(e){ e.preventDefault(); setAll(false); });
		})();
		</script>';
	}

	public static function handle_export(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die('No autorizado.', 403);
		}
		check_admin_referer(self::NONCE);

		$from = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
		$to   = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
		$cond = isset($_POST['condicion']) ? sanitize_key(wp_unslash($_POST['condicion'])) : '';
		$pids = self::parse_product_ids(isset($_POST['sorteos']) ? wp_unslash($_POST['sorteos']) : array());

		$back = admin_url('admin.php?page=' . self::PAGE);
		$q    = array(
			'from'      => $from,
			'to'        => $to,
			'condicion' => $cond,
			'sorteos'   => implode(',', $pids),
		);

		$has_from = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
		$has_to   = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
		if ($has_from !== $has_to || ($has_from && $from > $to)) {
			$q['ss_brevo'] = 'dates';
			wp_safe_redirect(add_query_arg($q, $back));
			exit;
		}
		if (!$has_from && !$pids) {
			$q['ss_brevo'] = 'need_filter';
			wp_safe_redirect(add_query_arg($q, $back));
			exit;
		}
		if (!isset(self::conditions()[ $cond ])) {
			$q['ss_brevo'] = 'dates';
			wp_safe_redirect(add_query_arg($q, $back));
			exit;
		}

		$needs_orders = ($cond !== 'registrados') || ($pids !== array());
		if ($needs_orders && !function_exists('wc_get_orders')) {
			$q['ss_brevo'] = 'wc';
			wp_safe_redirect(add_query_arg($q, $back));
			exit;
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(180);
		}

		$contacts = self::collect($has_from ? $from : '', $has_to ? $to : '', $cond, $pids);
		if ($contacts === array()) {
			$q['ss_brevo'] = 'empty';
			wp_safe_redirect(add_query_arg($q, $back));
			exit;
		}

		$slug = $cond;
		if ($has_from) {
			$slug .= '-' . $from . '-' . $to;
		}
		if ($pids) {
			$slug .= '-s' . implode('-', array_slice($pids, 0, 6));
		}
		$filename = 'brevo-contactos-' . $slug . '.csv';

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('X-Content-Type-Options: nosniff');

		$out = fopen('php://output', 'w');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, array('EMAIL', 'FIRSTNAME', 'LASTNAME'));
		foreach ($contacts as $row) {
			fputcsv($out, array($row['EMAIL'], $row['FIRSTNAME'], $row['LASTNAME']));
		}
		fclose($out);
		exit;
	}

	/**
	 * @param int[] $product_ids
	 * @return array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}>
	 */
	public static function collect(string $from, string $to, string $cond, array $product_ids = array()): array {
		$has_dates = ($from !== '' && $to !== '');
		$use_buyers = ($cond !== 'registrados') || ($product_ids !== array());
		$use_registered = $has_dates && in_array(
			$cond,
			array('registrados', 'registrados_y_compraron', 'registrados_mas_compradores', 'registrados_sin_compra'),
			true
		);

		if (!$has_dates && $product_ids !== array()) {
			$use_registered = false;
			$use_buyers     = true;
		}

		$registered = $use_registered ? self::registered_contacts($from, $to) : array();
		$buyers     = $use_buyers ? self::buyer_contacts($from, $to, $product_ids) : array();

		if (!$has_dates && $product_ids !== array()) {
			return $buyers;
		}

		switch ($cond) {
			case 'registrados':
				return $product_ids ? array_intersect_key($registered, $buyers) : $registered;
			case 'compradores':
				return $buyers;
			case 'registrados_y_compraron':
				return array_intersect_key($registered, $buyers);
			case 'registrados_sin_compra':
				return array_diff_key($registered, $buyers);
			case 'registrados_mas_compradores':
			default:
				return self::merge_contacts($registered, $buyers);
		}
	}

	/**
	 * @return array<int, array{id:int,title:string,closed:bool}>
	 */
	public static function lottery_products(): array {
		static $cached = null;
		if (is_array($cached)) {
			return $cached;
		}
		global $wpdb;
		$ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_lty_maximum_tickets'");
		if (!is_array($ids) || !$ids) {
			$cached = array();
			return $cached;
		}

		$out = array();
		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id <= 0 || !function_exists('wc_get_product')) {
				continue;
			}
			$product = wc_get_product($id);
			if (!$product) {
				continue;
			}
			$status = $product->get_status();
			if (!in_array($status, array('publish', 'private', 'draft'), true)) {
				continue;
			}
			$title = preg_replace('/^DigiTicket\s*\|\s*/iu', '', $product->get_name()) ?? $product->get_name();
			$title = trim(preg_replace('/\s+/u', ' ', (string) $title) ?? (string) $title);
			$closed = ($product->get_meta('_lottery_closed') === 'yes') || !$product->is_in_stock();
			$out[] = array(
				'id'     => $id,
				'title'  => $title !== '' ? $title : ('Sorteo #' . $id),
				'closed' => $closed,
			);
		}

		usort(
			$out,
			static function ($a, $b) {
				if ($a['closed'] !== $b['closed']) {
					return $a['closed'] ? 1 : -1;
				}
				return strcasecmp($a['title'], $b['title']);
			}
		);
		$cached = $out;
		return $cached;
	}

	/**
	 * @param mixed $raw
	 * @return int[]
	 */
	private static function parse_product_ids($raw): array {
		if (is_string($raw) && $raw !== '') {
			$raw = explode(',', $raw);
		}
		if (!is_array($raw)) {
			return array();
		}
		$allowed = array();
		foreach (self::lottery_products() as $item) {
			$allowed[ (int) $item['id'] ] = true;
		}
		$out = array();
		foreach ($raw as $id) {
			$id = (int) $id;
			if ($id > 0 && isset($allowed[ $id ])) {
				$out[] = $id;
			}
		}
		return array_values(array_unique($out));
	}

	/**
	 * @return array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}>
	 */
	private static function registered_contacts(string $from, string $to): array {
		$after  = get_gmt_from_date($from . ' 00:00:00');
		$before = get_gmt_from_date($to . ' 23:59:59');
		$users  = get_users(
			array(
				'number'       => -1,
				'fields'       => 'all',
				'role__not_in' => self::SKIP_ROLES,
				'date_query'   => array(
					array(
						'after'     => $after,
						'before'    => $before,
						'inclusive' => true,
						'column'    => 'user_registered',
					),
				),
			)
		);

		$out = array();
		foreach ($users as $user) {
			$row = self::contact_from_user($user);
			if ($row === null) {
				continue;
			}
			$out[ $row['EMAIL'] ] = $row;
		}
		ksort($out);
		return $out;
	}

	/**
	 * @param int[] $product_ids
	 * @return array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}>
	 */
	private static function buyer_contacts(string $from, string $to, array $product_ids = array()): array {
		$staff = self::staff_emails();
		$out   = array();

		$order_ids = self::paid_order_ids($from, $to, $product_ids);
		if (is_array($order_ids)) {
			foreach (array_chunk($order_ids, 100) as $chunk) {
				$orders = wc_get_orders(
					array(
						'limit'   => count($chunk),
						'include' => $chunk,
						'type'    => 'shop_order',
						'return'  => 'objects',
						'status'  => self::PAID_STATUSES,
					)
				);
				if (!is_array($orders)) {
					continue;
				}
				foreach ($orders as $order) {
					self::add_buyer_from_order($out, $order, $staff, $product_ids);
				}
			}
		} else {
			$page = 1;
			$args = array(
				'limit'  => 200,
				'status' => self::PAID_STATUSES,
				'type'   => 'shop_order',
				'return' => 'objects',
			);
			if ($from !== '' && $to !== '') {
				$args['date_created'] = $from . '...' . $to;
			}
			do {
				$args['page'] = $page;
				$batch = wc_get_orders($args);
				if (!is_array($batch)) {
					$batch = array();
				}
				foreach ($batch as $order) {
					self::add_buyer_from_order($out, $order, $staff, $product_ids);
				}
				$page++;
			} while (count($batch) === 200);
		}

		foreach ($out as $email => $row) {
			$user = get_user_by('email', $email);
			if (!$user instanceof WP_User) {
				continue;
			}
			$from_user = self::contact_from_user($user);
			if ($from_user === null) {
				continue;
			}
			if ($row['FIRSTNAME'] === '' && $from_user['FIRSTNAME'] !== '') {
				$out[ $email ]['FIRSTNAME'] = $from_user['FIRSTNAME'];
			}
			if ($row['LASTNAME'] === '' && $from_user['LASTNAME'] !== '') {
				$out[ $email ]['LASTNAME'] = $from_user['LASTNAME'];
			}
		}

		ksort($out);
		return $out;
	}

	/**
	 * @param int[] $product_ids
	 * @return int[]|null
	 */
	private static function paid_order_ids(string $from, string $to, array $product_ids): ?array {
		if ($product_ids === array()) {
			return null;
		}

		global $wpdb;
		$lookup = $wpdb->prefix . 'wc_order_product_lookup';
		$stats  = $wpdb->prefix . 'wc_order_stats';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $lookup)) !== $lookup) {
			return null;
		}
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stats)) !== $stats) {
			return null;
		}

		$in   = implode(',', array_fill(0, count($product_ids), '%d'));
		$sql  = "SELECT DISTINCT p.order_id
			FROM {$lookup} p
			INNER JOIN {$stats} s ON s.order_id = p.order_id
			WHERE s.parent_id = 0
			AND s.status IN ('wc-completed','wc-processing')
			AND (p.product_id IN ({$in}) OR p.variation_id IN ({$in}))";
		$args = array_merge($product_ids, $product_ids);

		if ($from !== '' && $to !== '') {
			$sql   .= ' AND s.date_created_gmt >= %s AND s.date_created_gmt <= %s';
			$args[] = get_gmt_from_date($from . ' 00:00:00');
			$args[] = get_gmt_from_date($to . ' 23:59:59');
		}

		$ids = $wpdb->get_col($wpdb->prepare($sql, $args));
		if (!is_array($ids)) {
			return array();
		}
		return array_values(array_filter(array_map('intval', $ids)));
	}

	/**
	 * @param array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}> $out
	 * @param array<string, true> $staff
	 * @param int[] $product_ids
	 */
	private static function add_buyer_from_order(array &$out, $order, array $staff, array $product_ids): void {
		if (!is_object($order) || !method_exists($order, 'get_billing_email')) {
			return;
		}
		if ($product_ids && !self::order_has_products($order, $product_ids)) {
			return;
		}
		$email = strtolower(trim((string) $order->get_billing_email()));
		if ($email === '' || !is_email($email) || isset($staff[ $email ])) {
			return;
		}
		$fn = trim((string) $order->get_billing_first_name());
		$ln = trim((string) $order->get_billing_last_name());
		if (!isset($out[ $email ])) {
			$out[ $email ] = array(
				'EMAIL'     => $email,
				'FIRSTNAME' => $fn,
				'LASTNAME'  => $ln,
			);
			return;
		}
		if ($out[ $email ]['FIRSTNAME'] === '' && $fn !== '') {
			$out[ $email ]['FIRSTNAME'] = $fn;
		}
		if ($out[ $email ]['LASTNAME'] === '' && $ln !== '') {
			$out[ $email ]['LASTNAME'] = $ln;
		}
	}

	/**
	 * @param int[] $ids
	 */
	private static function order_has_products($order, array $ids): bool {
		if (!$ids || !method_exists($order, 'get_items')) {
			return true;
		}
		$set = array_flip($ids);
		foreach ($order->get_items('line_item') as $item) {
			$pid = (int) $item->get_product_id();
			$vid = (int) $item->get_variation_id();
			if (isset($set[ $pid ]) || ($vid && isset($set[ $vid ]))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}> $base
	 * @param array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}> $extra
	 * @return array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}>
	 */
	private static function merge_contacts(array $base, array $extra): array {
		foreach ($extra as $email => $row) {
			if (!isset($base[ $email ])) {
				$base[ $email ] = $row;
				continue;
			}
			if ($base[ $email ]['FIRSTNAME'] === '' && $row['FIRSTNAME'] !== '') {
				$base[ $email ]['FIRSTNAME'] = $row['FIRSTNAME'];
			}
			if ($base[ $email ]['LASTNAME'] === '' && $row['LASTNAME'] !== '') {
				$base[ $email ]['LASTNAME'] = $row['LASTNAME'];
			}
		}
		ksort($base);
		return $base;
	}

	/**
	 * @return array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}|null
	 */
	private static function contact_from_user(WP_User $user): ?array {
		$email = strtolower(trim((string) $user->user_email));
		if ($email === '' || !is_email($email)) {
			return null;
		}
		$fn = trim((string) get_user_meta($user->ID, 'first_name', true));
		$ln = trim((string) get_user_meta($user->ID, 'last_name', true));
		if ($fn === '') {
			$fn = trim((string) get_user_meta($user->ID, 'billing_first_name', true));
		}
		if ($ln === '') {
			$ln = trim((string) get_user_meta($user->ID, 'billing_last_name', true));
		}
		return array(
			'EMAIL'     => $email,
			'FIRSTNAME' => $fn,
			'LASTNAME'  => $ln,
		);
	}

	/**
	 * @return array<string, true>
	 */
	private static function staff_emails(): array {
		$users = get_users(
			array(
				'number'   => -1,
				'fields'   => array('user_email'),
				'role__in' => self::SKIP_ROLES,
			)
		);
		$out = array();
		foreach ($users as $user) {
			$email = strtolower(trim((string) $user->user_email));
			if ($email !== '') {
				$out[ $email ] = true;
			}
		}
		return $out;
	}
}

SorteoSeguro_Brevo_Export::init();
