<?php
/**
 * Plugin Name: Sorteo Seguro – Región y Comuna en Checkout
 * Description: Añade Región y Comuna (Chile, 2026) al checkout con buscador, validación y guardado en el pedido. Aislado del core: si se elimina/renombra este archivo, el checkout vuelve exactamente a su estado anterior.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Checkout_Location {

	const VERSION = '1.0.0';
	const FIELD_REGION = 'ss_region';
	const FIELD_COMUNA = 'ss_comuna';

	/** @var array<string, array{name:string, communes:array<int,string>}>|null */
	private static $data = null;

	public static function init(): void {
		add_action('woocommerce_checkout_process', [__CLASS__, 'validate']);
		add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_to_order'], 10, 2);
		// Datos para el combobox (antes del JS inline del checkout, que sale en wp_footer prio 20).
		add_action('wp_footer', [__CLASS__, 'print_data'], 15);
	}

	/**
	 * Feature habilitada (dataset cargado + filtro on). No depende de la superficie:
	 * la validación y el guardado deben correr también en el checkout por AJAX (?wc-ajax=checkout).
	 * Apagado sin tocar archivos: add_filter('ss_checkout_location_enabled','__return_false').
	 */
	public static function is_enabled(): bool {
		return apply_filters('ss_checkout_location_enabled', true) && self::data() !== [];
	}

	/**
	 * Visible/renderizable: solo en superficies de checkout (no order-pay, no admin).
	 */
	public static function is_visible(): bool {
		return self::is_enabled() && self::is_active_surface() && !self::is_order_pay();
	}

	/**
	 * @return array<string, array{name:string, communes:array<int,string>}>
	 */
	public static function data(): array {
		if (self::$data !== null) {
			return self::$data;
		}
		$file = __DIR__ . '/sorteoseguro-checkout-location/data/chile-regiones-comunas.php';
		self::$data = [];
		if (is_readable($file)) {
			$loaded = include $file;
			if (is_array($loaded)) {
				self::$data = $loaded;
			}
		}
		return self::$data;
	}

	private static function is_active_surface(): bool {
		if (is_admin() && !wp_doing_ajax()) {
			return false;
		}
		if (class_exists('SorteoSeguro_Checkout') && method_exists('SorteoSeguro_Checkout', 'is_active_surface')) {
			return (bool) SorteoSeguro_Checkout::is_active_surface();
		}
		return function_exists('is_checkout') && is_checkout();
	}

	private static function is_order_pay(): bool {
		return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay');
	}

	/**
	 * Imprime el JSON de regiones/comunas una sola vez, antes del JS del checkout.
	 */
	public static function print_data(): void {
		if (!self::is_visible()) {
			return;
		}
		$out = ['regions' => []];
		foreach (self::data() as $code => $info) {
			$out['regions'][] = [
				'code'     => $code,
				'name'     => (string) ($info['name'] ?? $code),
				'communes' => array_values((array) ($info['communes'] ?? [])),
			];
		}
		echo "\n<script type=\"application/json\" id=\"ss-co-loc-data\" data-no-optimize=\"1\">"
			. wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			. "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Valida región/comuna al procesar el checkout. Fail-open si la feature está apagada.
	 */
	public static function validate(): void {
		if (!self::is_enabled() || self::is_order_pay()) {
			return;
		}
		$region = isset($_POST[self::FIELD_REGION]) ? sanitize_text_field(wp_unslash((string) $_POST[self::FIELD_REGION])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$comuna = isset($_POST[self::FIELD_COMUNA]) ? sanitize_text_field(wp_unslash((string) $_POST[self::FIELD_COMUNA])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data   = self::data();

		if ($region === '') {
			wc_add_notice('Selecciona tu región.', 'error');
		} elseif (!isset($data[$region])) {
			wc_add_notice('La región seleccionada no es válida.', 'error');
		}

		if ($comuna === '') {
			wc_add_notice('Selecciona tu comuna.', 'error');
		} elseif (!$region || !isset($data[$region]) || !in_array($comuna, $data[$region]['communes'], true)) {
			wc_add_notice('La comuna seleccionada no es válida.', 'error');
		}
	}

	/**
	 * Guarda región (código) y comuna en el pedido.
	 *
	 * @param WC_Order            $order
	 * @param array<string,mixed> $data
	 */
	public static function save_to_order($order, $data): void {
		if (!self::is_enabled() || self::is_order_pay() || !($order instanceof WC_Order)) {
			return;
		}
		$region = isset($_POST[self::FIELD_REGION]) ? sanitize_text_field(wp_unslash((string) $_POST[self::FIELD_REGION])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$comuna = isset($_POST[self::FIELD_COMUNA]) ? sanitize_text_field(wp_unslash((string) $_POST[self::FIELD_COMUNA])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$map    = self::data();

		if (isset($map[$region])) {
			$order->set_billing_state($region);
			$order->update_meta_data('_ss_region', $region);
			$order->update_meta_data('_ss_region_name', (string) $map[$region]['name']);
			if ($comuna !== '' && in_array($comuna, $map[$region]['communes'], true)) {
				$order->set_billing_city($comuna);
				$order->update_meta_data('_ss_comuna', $comuna);
			}
		}
	}

	/**
	 * Renderiza la fila Región + Comuna (tras el teléfono). Se invoca desde el template
	 * del checkout, protegido por enabled().
	 *
	 * @param WC_Checkout $checkout
	 */
	public static function render_fields($checkout): void {
		if (!self::is_visible()) {
			return;
		}
		$data = self::data();

		// Prefill solo para usuarios logueados con dirección guardada. En invitados
		// WC devuelve el estado base de la tienda (RM) y sería un falso preseleccionado.
		$current_region = '';
		$current_comuna = '';
		if (is_user_logged_in() && is_object($checkout) && method_exists($checkout, 'get_value')) {
			$current_region = (string) $checkout->get_value('billing_state');
			$current_comuna = (string) $checkout->get_value('billing_city');
		}

		$region_code  = isset($data[$current_region]) ? $current_region : '';
		$region_label = $region_code !== '' ? (string) $data[$region_code]['name'] : '';
		$comuna_label = '';
		if ($region_code !== '' && $current_comuna !== '' && in_array($current_comuna, $data[$region_code]['communes'], true)) {
			$comuna_label = $current_comuna;
		}

		$pin = '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.6" stroke="currentColor" stroke-width="1.7"/></svg>';
		$caret = '<span class="ss-co-combo__caret" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg></span>';
		?>
		<div class="ss-co-row ss-co-loc" data-ss-loc>
			<span class="ss-co-row__ico" aria-hidden="true"><?php echo $pin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="ss-co-row__fields ss-co-row__fields--split">
				<div class="ss-co-field is-float ss-co-combo" data-ss-combo="region">
					<input type="text" class="input-text ss-co-combo__input" id="ss_region_input" placeholder=" " autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ss_region_list" value="<?php echo esc_attr($region_label); ?>">
					<label for="ss_region_input">Región</label>
					<input type="hidden" name="<?php echo esc_attr(self::FIELD_REGION); ?>" id="ss_region" value="<?php echo esc_attr($region_code); ?>">
					<?php echo $caret; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<ul class="ss-co-combo__list" id="ss_region_list" role="listbox" hidden></ul>
				</div>
				<div class="ss-co-field is-float ss-co-combo" data-ss-combo="comuna">
					<input type="text" class="input-text ss-co-combo__input" id="ss_comuna_input" placeholder=" " autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ss_comuna_list" value="<?php echo esc_attr($comuna_label); ?>"<?php echo $region_code === '' ? ' disabled' : ''; ?>>
					<label for="ss_comuna_input">Comuna</label>
					<input type="hidden" name="<?php echo esc_attr(self::FIELD_COMUNA); ?>" id="ss_comuna" value="<?php echo esc_attr($comuna_label); ?>">
					<?php echo $caret; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<ul class="ss-co-combo__list" id="ss_comuna_list" role="listbox" hidden></ul>
				</div>
			</div>
		</div>
		<?php
	}
}

SorteoSeguro_Checkout_Location::init();
