<?php
/**
 * Plugin Name: Sorteo Seguro – Exportar contactos Brevo
 * Description: Panel wp-admin para descargar CSV (EMAIL, FIRSTNAME, LASTNAME) listo para importar en Brevo.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 *
 * mu-plugin: borrar este archivo para rollback.
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Brevo_Export {

	const VERSION = '1.0.0';
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
				'help'  => 'Cuentas creadas entre esas fechas, hayan comprado o no.',
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
				'help'  => 'Cuentas creadas en el período sin pedido pagado en esas fechas.',
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
.ss-brevo li {
	font-family: var(--ss-font-family, "Montserrat", "Poppins", "Segoe UI", sans-serif) !important;
}
.ss-brevo { max-width: 760px; color: #1f2430; }
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
.ss-brevo__field span { font-size: 12px; font-weight: 700; color: #6b7280; }
.ss-brevo input[type="date"] {
	border: 1px solid #e6e8ef;
	border-radius: 8px;
	padding: 8px 10px;
	font-size: 14px;
}
.ss-brevo__opts { display: flex; flex-direction: column; gap: 10px; margin: 4px 0 18px; }
.ss-brevo__opt {
	display: block;
	border: 1px solid #e6e8ef;
	border-radius: 8px;
	padding: 12px 14px;
	background: #fff;
	cursor: pointer;
}
.ss-brevo__opt:hover { border-color: #6b3bb8; }
.ss-brevo__opt.is-on { border-color: #6b3bb8; background: #f3ecff; }
.ss-brevo__opt strong { display: block; font-size: 14px; margin: 0 0 2px; }
.ss-brevo__opt small { color: #6b7280; font-size: 12px; line-height: 1.4; }
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

		$notice = isset($_GET['ss_brevo']) ? sanitize_key(wp_unslash($_GET['ss_brevo'])) : '';

		echo '<div class="wrap ss-brevo">';
		echo '<h1>Exportar contactos para Brevo</h1>';
		echo '<p class="ss-brevo__lead">Descarga un CSV con las columnas <strong>EMAIL, FIRSTNAME, LASTNAME</strong> listo para importar. Un email = una fila. No incluye staff.</p>';

		if ($notice === 'empty') {
			echo '<div class="notice notice-warning is-dismissible"><p>No hay contactos para ese rango y condición.</p></div>';
		} elseif ($notice === 'dates') {
			echo '<div class="notice notice-error is-dismissible"><p>Revisa las fechas: “desde” no puede ser posterior a “hasta”.</p></div>';
		} elseif ($notice === 'wc') {
			echo '<div class="notice notice-error is-dismissible"><p>WooCommerce tiene que estar activo para exportar compradores.</p></div>';
		}

		echo '<form class="ss-brevo__card" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '" />';
		wp_nonce_field(self::NONCE);

		echo '<div class="ss-brevo__row">';
		echo '<label class="ss-brevo__field"><span>Desde</span>';
		echo '<input type="date" name="from" value="' . esc_attr($from) . '" required /></label>';
		echo '<label class="ss-brevo__field"><span>Hasta</span>';
		echo '<input type="date" name="to" value="' . esc_attr($to) . '" required /></label>';
		echo '</div>';

		echo '<p style="margin:0 0 8px;font-size:12px;font-weight:700;color:#6b7280;">Condición</p>';
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
			var form = document.querySelector(".ss-brevo__card");
			if (!form) return;
			form.addEventListener("change", function(e){
				if (e.target && e.target.name === "condicion") {
					form.querySelectorAll(".ss-brevo__opt").forEach(function(el){ el.classList.remove("is-on"); });
					if (e.target.closest) e.target.closest(".ss-brevo__opt").classList.add("is-on");
				}
			});
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

		$back = admin_url('admin.php?page=' . self::PAGE);

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
			wp_safe_redirect(add_query_arg(array('ss_brevo' => 'dates', 'from' => $from, 'to' => $to, 'condicion' => $cond), $back));
			exit;
		}
		if (!isset(self::conditions()[ $cond ])) {
			wp_safe_redirect(add_query_arg(array('ss_brevo' => 'dates', 'from' => $from, 'to' => $to), $back));
			exit;
		}

		$needs_orders = ($cond !== 'registrados');
		if ($needs_orders && !function_exists('wc_get_orders')) {
			wp_safe_redirect(add_query_arg(array('ss_brevo' => 'wc', 'from' => $from, 'to' => $to, 'condicion' => $cond), $back));
			exit;
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(120);
		}

		$contacts = self::collect($from, $to, $cond);
		if ($contacts === array()) {
			wp_safe_redirect(add_query_arg(array('ss_brevo' => 'empty', 'from' => $from, 'to' => $to, 'condicion' => $cond), $back));
			exit;
		}

		$slug = $cond . '-' . $from . '-' . $to;
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
	 * @return array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}>
	 */
	public static function collect(string $from, string $to, string $cond): array {
		$registered = ($cond === 'compradores') ? array() : self::registered_contacts($from, $to);
		$buyers     = ($cond === 'registrados') ? array() : self::buyer_contacts($from, $to);

		switch ($cond) {
			case 'registrados':
				return $registered;
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
	 * @return array<string, array{EMAIL:string,FIRSTNAME:string,LASTNAME:string}>
	 */
	private static function buyer_contacts(string $from, string $to): array {
		$staff = self::staff_emails();
		$out   = array();
		$page  = 1;

		do {
			$batch = wc_get_orders(
				array(
					'limit'        => 200,
					'page'         => $page,
					'date_created' => $from . '...' . $to,
					'status'       => self::PAID_STATUSES,
					'type'         => 'shop_order',
					'return'       => 'objects',
				)
			);
			if (!is_array($batch)) {
				$batch = array();
			}
			foreach ($batch as $order) {
				$email = strtolower(trim((string) $order->get_billing_email()));
				if ($email === '' || !is_email($email) || isset($staff[ $email ])) {
					continue;
				}
				$fn = trim((string) $order->get_billing_first_name());
				$ln = trim((string) $order->get_billing_last_name());
				if (!isset($out[ $email ])) {
					$out[ $email ] = array(
						'EMAIL'     => $email,
						'FIRSTNAME' => $fn,
						'LASTNAME'  => $ln,
					);
				} else {
					if ($out[ $email ]['FIRSTNAME'] === '' && $fn !== '') {
						$out[ $email ]['FIRSTNAME'] = $fn;
					}
					if ($out[ $email ]['LASTNAME'] === '' && $ln !== '') {
						$out[ $email ]['LASTNAME'] = $ln;
					}
				}
			}
			$page++;
		} while (count($batch) === 200);

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
