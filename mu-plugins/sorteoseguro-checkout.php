<?php
/**
 * Plugin Name: Sorteo Seguro – Checkout
 * Description: Checkout clásico alineado al diseño (banner reserva, pasos, resumen, trust, legales).
 * Author: Sorteo Seguro
 * Version: 1.0.28
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Checkout {

	const VERSION = '1.0.28';
	const DIR     = __DIR__ . '/sorteoseguro-checkout';
	const DEFAULT_GATEWAY = 'woo-mercado-pago-basic';
	const PAGE_ID = 13;

	/** Custom CSS & JS viejos de “finalizar compra” (ya en draft; dequeue por si reaparecen). */
	const LEGACY_CCJ_IDS = [58350, 58352];

	public static function init(): void {
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_filter('woocommerce_locate_template', [__CLASS__, 'locate_template'], 50, 3);
		add_filter('the_content', [__CLASS__, 'force_classic_shortcode'], 5);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('woocommerce_get_notices', [__CLASS__, 'filter_notices'], 50, 1);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 60);
		add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_legacy_ccj'], 1000);
		add_action('wp_footer', [__CLASS__, 'inline_assets'], 20);
		add_action('wp_head', [__CLASS__, 'print_font_override'], 99999);
		add_action('wp_footer', [__CLASS__, 'print_font_override'], 99999);
		add_action('template_redirect', [__CLASS__, 'bypass_page_cache'], 0);
		add_action('woocommerce_before_checkout_form', [__CLASS__, 'render_reserve_banner'], 5);
		add_action('woocommerce_after_checkout_form', [__CLASS__, 'render_after_form'], 20);
		add_action('woocommerce_checkout_process', [__CLASS__, 'sync_contact_fields']);
		add_filter('woocommerce_gateway_icon', [__CLASS__, 'replace_gateway_icons'], 99, 2);
		// Lottery fuerza registration_required=true con DigiTickets; el theme desactiva
		// registro en checkout → bloqueaba invitados. Permitimos compra sin cuenta;
		// el correo “Completa tu Registro” (WPCode) sigue al thank-you.
		add_filter('woocommerce_checkout_registration_required', '__return_false', 999);
		// Al cargar checkout: Mercado Pago primero y preseleccionado (no TUU por sesión previa).
		add_action('woocommerce_before_checkout_form', [__CLASS__, 'force_default_payment_method'], 1);
		add_filter('woocommerce_available_payment_gateways', [__CLASS__, 'prefer_mercado_pago_gateway'], 100);
	}

	/**
	 * Prefija Mercado Pago en la lista y deja el chosen de sesión en MP en carga de página.
	 * No pisa la elección del usuario en update_order_review (AJAX): ahí WC usa el POST.
	 */
	public static function force_default_payment_method(): void {
		if (!self::is_checkout() || is_wc_endpoint_url('order-pay')) {
			return;
		}
		if (!function_exists('WC') || !WC()->session) {
			return;
		}
		// Solo en request “completa” del checkout, no en el fragment AJAX.
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return;
		}
		WC()->session->set('chosen_payment_method', self::DEFAULT_GATEWAY);
	}

	/**
	 * @param array<string, WC_Payment_Gateway> $gateways
	 * @return array<string, WC_Payment_Gateway>
	 */
	public static function prefer_mercado_pago_gateway(array $gateways): array {
		if (!self::is_checkout() || !isset($gateways[self::DEFAULT_GATEWAY])) {
			return $gateways;
		}
		$mp = $gateways[self::DEFAULT_GATEWAY];
		unset($gateways[self::DEFAULT_GATEWAY]);
		return array_merge([self::DEFAULT_GATEWAY => $mp], $gateways);
	}

	/**
	 * CSS completo en <head> (prioridad 3): evita FOUC / iconos gigantes.
	 * LiteSpeed puede diferir el enqueue; el first paint no puede depender del footer.
	 */
	public static function print_critical_css(): void {
		if (!self::is_checkout()) {
			return;
		}
		$path = self::DIR . '/assets/checkout.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		if (!is_user_logged_in()) {
			$guest = self::DIR . '/assets/checkout-guest-critical.css';
			if (is_readable($guest)) {
				$extra = file_get_contents($guest);
				if (is_string($extra) && $extra !== '') {
					$css .= "\n" . $extra;
				}
			}
		}
		echo "\n<!-- ss-checkout critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-checkout-critical" data-no-optimize="1">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Logos del footer (MP + Webpay Plus) en el selector de pago. */
	public static function replace_gateway_icons($icon, $id = ''): string {
		$map = [
			'woo-mercado-pago-basic' => [
				'src' => set_url_scheme(content_url('mu-plugins/sorteoseguro-chrome/assets/logo-mercado-pago.png'), 'https'),
				'alt' => 'Mercado Pago',
			],
			'wcplugingateway'        => [
				'src' => set_url_scheme(content_url('mu-plugins/sorteoseguro-chrome/assets/logo-web-pay-plus.png'), 'https'),
				'alt' => 'Webpay Plus',
			],
		];
		if ($id === '' || !isset($map[$id])) {
			return is_string($icon) ? $icon : '';
		}
		$src = esc_url($map[$id]['src']);
		$alt = esc_attr($map[$id]['alt']);
		return '<img src="' . $src . '" alt="' . $alt . '">';
	}

	/** Dequeue por si el plugin encola el CSS/JS como archivo. */
	public static function dequeue_legacy_ccj(): void {
		if (!self::is_checkout()) {
			return;
		}
		foreach (self::LEGACY_CCJ_IDS as $id) {
			wp_dequeue_style('ccj-' . $id);
			wp_deregister_style('ccj-' . $id);
			wp_dequeue_script('ccj-' . $id);
			wp_deregister_script('ccj-' . $id);
			wp_dequeue_style('custom-css-js-' . $id);
			wp_dequeue_script('custom-css-js-' . $id);
		}
	}

	/**
	 * Oculta el aviso viejo de reserva Lottery (reemplazado por .ss-co-reserve).
	 * Conserva errores y mensajes de cupón/éxito.
	 *
	 * @param array<string, mixed> $notices
	 * @return array<string, mixed>
	 */
	public static function filter_notices(array $notices): array {
		if (!self::is_checkout() || empty($notices['notice']) || !is_array($notices['notice'])) {
			return $notices;
		}
		$notices['notice'] = array_values(array_filter($notices['notice'], static function ($item) {
			$msg = is_array($item) ? (string) ($item['notice'] ?? '') : (string) $item;
			if ($msg === '') {
				return true;
			}
			if (stripos($msg, 'reservado por') !== false) {
				return false;
			}
			if (stripos($msg, 'completa tu compra') !== false) {
				return false;
			}
			return true;
		}));
		if (!$notices['notice']) {
			unset($notices['notice']);
		}
		return $notices;
	}

	/** Sin avisos WC/Lottery en checkout invitado (solo panel login/registro). */

	/** Espejo PHP de contacto → billing (por si falla el JS). */
	public static function sync_contact_fields(): void {
		$map = [
			'contact_first_name' => 'billing_first_name',
			'contact_last_name'  => 'billing_last_name',
			'contact_phone'      => 'billing_phone',
		];
		foreach ($map as $from => $to) {
			if (empty($_POST[$to]) && !empty($_POST[$from])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$_POST[$to] = sanitize_text_field(wp_unslash((string) $_POST[$from])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_checkout() ? true : $enabled;
	}

	/** @param array<int, string> $classes */
	public static function body_class(array $classes): array {
		if (self::is_checkout()) {
			$classes[] = 'ss-checkout-template';
			if (!is_user_logged_in()) {
				$classes[] = 'ss-checkout-guest';
			}
			if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay')) {
				$classes[] = 'ss-checkout-pay';
			}
		}
		return $classes;
	}

	public static function is_guest_checkout(): bool {
		return self::is_checkout() && !is_user_logged_in();
	}

	public static function guest_login_url(): string {
		$checkout = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/finalizar-compra/');
		$account  = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/mi-cuenta/');
		if (!$account) {
			$account = home_url('/mi-cuenta/');
		}
		return add_query_arg('redirect_to', rawurlencode($checkout), $account);
	}

	public static function guest_register_url(): string {
		return home_url('/registro/');
	}

	public static function is_checkout(): bool {
		if (function_exists('is_order_received_page') && is_order_received_page()) {
			return false;
		}
		if (function_exists('is_checkout') && is_checkout()) {
			return true;
		}
		if (is_page(self::PAGE_ID) || is_page('finalizar-compra')) {
			return true;
		}
		$wc_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('checkout') : 0;
		return $wc_id > 0 && is_page($wc_id);
	}

	public static function bypass_page_cache(): void {
		if (!self::is_checkout()) {
			return;
		}
		do_action('litespeed_control_set_nocache', 'ss-checkout');
		if (!headers_sent()) {
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
		}
	}

	/**
	 * @param mixed $value
	 * @param mixed $object_id
	 * @param mixed $meta_key
	 * @param mixed $single
	 * @return mixed
	 */
	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		$checkout_id = function_exists('wc_get_page_id') ? (int) wc_get_page_id('checkout') : self::PAGE_ID;
		if ((int) $object_id !== self::PAGE_ID && (int) $object_id !== $checkout_id) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function reserve_minutes(): int {
		$mins = (int) apply_filters('ss_checkout_reserve_minutes', 30);
		return max(1, $mins);
	}

	/**
	 * @param array<int, string> $template
	 */
	public static function locate_template(string $template, string $template_name, string $template_path): string {
		$custom = self::DIR . '/templates/' . ltrim($template_name, '/');
		if (is_readable($custom)) {
			return $custom;
		}
		return $template;
	}

	/** Fuerza shortcode clásico en la página de checkout (el diseño no es Blocks). */
	public static function force_classic_shortcode(string $content): string {
		if (!self::is_checkout() || is_admin()) {
			return $content;
		}
		if (has_shortcode($content, 'woocommerce_checkout')) {
			return $content;
		}
		return '[woocommerce_checkout]';
	}

	public static function assets(): void {
		if (!self::is_checkout()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = content_url('mu-plugins/sorteoseguro-checkout/assets');
		$dir  = self::DIR . '/assets';
		$ver  = self::VERSION;
		if (is_readable($dir . '/checkout.css')) {
			wp_enqueue_style('ss-checkout', $base . '/checkout.css', ['ss-fonts'], $ver);
		}
		if (is_readable($dir . '/checkout.js')) {
			wp_enqueue_script('ss-checkout', $base . '/checkout.js', ['jquery'], $ver, true);
			wp_localize_script('ss-checkout', 'ssCheckout', [
				'reserveMinutes' => self::reserve_minutes(),
				'storageKey'     => 'ss_checkout_reserve_until',
			]);
		}
	}

	/** Override tardío: gana a Geologica/WPCode en checkout. */
	public static function print_font_override(): void {
		if (!self::is_checkout()) {
			return;
		}
		$hook = (string) current_filter();
		static $printed = [];
		if (isset($printed[$hook])) {
			return;
		}
		$printed[$hook] = true;
		$stack = class_exists('SorteoSeguro_Chrome')
			? SorteoSeguro_Chrome::FONT_STACK
			: '"Montserrat", "Poppins", "Segoe UI", sans-serif';
		$id = $hook === 'wp_footer' ? 'ss-checkout-font-footer' : 'ss-checkout-font';
		echo "\n<style id=\"{$id}\">\n";
		echo ":root{--ss-font-family:{$stack};}\n";
		// No usar `body *` (rompe boxicons/header). Solo superficies del checkout.
		echo "body.woocommerce-checkout .ss-co,body.woocommerce-checkout .ss-co *,";
		echo "body.woocommerce-checkout .ss-co-reserve,body.woocommerce-checkout .ss-co-reserve *,";
		echo "body.woocommerce-checkout .ss-co-legal,body.woocommerce-checkout .ss-co-legal *,";
		echo "body.woocommerce-checkout .ss-co-help,body.woocommerce-checkout .ss-co-help *,";
		echo "body.woocommerce-checkout .ss-co-guest,body.woocommerce-checkout .ss-co-guest *,";
		echo "body.woocommerce-checkout .custom-checkout-form,body.woocommerce-checkout .custom-checkout-form *,";
		echo "body.woocommerce-checkout .order-review-card,body.woocommerce-checkout .order-review-card *,";
		echo "body.woocommerce-order-pay .ss-co-order-pay,body.woocommerce-order-pay .ss-co-order-pay *";
		echo "{font-family:{$stack}!important;}\n";
		echo "</style>\n";
	}

	/** Inline de respaldo (LiteSpeed). */
	public static function inline_assets(): void {
		if (!self::is_checkout()) {
			return;
		}
		$css = self::DIR . '/assets/checkout.css';
		$js  = self::DIR . '/assets/checkout.js';
		if (is_readable($css)) {
			echo "\n<!-- ss-checkout v" . esc_html(self::VERSION) . " -->\n<style id=\"ss-checkout-inline\">\n";
			echo file_get_contents($css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</style>\n";
		}
		if (is_readable($js)) {
			$mins = (int) self::reserve_minutes();
			echo "<script id=\"ss-checkout-inline-js\" data-no-optimize=\"1\">window.ssCheckout=window.ssCheckout||{reserveMinutes:{$mins},storageKey:'ss_checkout_reserve_until'};\n";
			echo file_get_contents($js); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo "\n</script>\n";
		}
	}

	public static function render_reserve_banner(): void {
		if (!self::is_checkout() || !is_user_logged_in()) {
			return;
		}
		$mins = self::reserve_minutes();
		?>
		<div class="ss-co-shell">
			<div class="ss-co-reserve" data-ss-reserve-banner aria-live="polite">
				<div class="ss-co-reserve__copy">
					<span class="ss-co-reserve__ico" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 3.2 5 6v5.2c0 4.6 3 8.6 7 9.8 4-1.2 7-5.2 7-9.8V6l-7-2.8z"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m8.8 12 2.2 2.2 4.3-4.4"/></svg>
					</span>
					<p>
						<strong>Tu carrito está reservado por <?php echo (int) $mins; ?> minutos.</strong>
						Completa tu compra dentro de este tiempo para asegurar tus DigiTickets.
					</p>
				</div>
				<div class="ss-co-reserve__timer">
					<span class="ss-co-reserve__timer-ico" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M12 8v4.5l3 1.8"/></svg>
					</span>
					<div>
						<small>Tiempo restante</small>
						<strong data-ss-reserve-clock>00:00</strong>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_guest_gate(): void {
		$login_url    = self::guest_login_url();
		$register_url = self::guest_register_url();
		?>
		<section class="ss-co-shell ss-co-guest" aria-labelledby="ss-co-guest-title">
			<div class="ss-co-guest__panel">
				<span class="ss-co-guest__ico" aria-hidden="true" style="width:64px;height:64px;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;"><?php echo str_replace('<svg ', '<svg width="32" height="32" ', self::icon('user')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<h2 class="ss-co-guest__title" id="ss-co-guest-title">Inicia sesión para continuar</h2>
				<p class="ss-co-guest__sub">Para completar tu compra y asegurar tus DigiTickets necesitas una cuenta en Sorteo Seguro. Es rápido y 100% online.</p>
				<div class="ss-co-guest__actions">
					<a class="ss-co-guest__btn ss-co-guest__btn--primary" href="<?php echo esc_url($login_url); ?>">Iniciar sesión</a>
					<a class="ss-co-guest__btn ss-co-guest__btn--ghost" href="<?php echo esc_url($register_url); ?>">Crear cuenta</a>
				</div>
				<p class="ss-co-guest__hint">Al volver, retomarás tu compra en esta misma página.</p>
			</div>
		</section>
		<?php
	}

	public static function render_after_form(): void {
		if (!self::is_checkout()) {
			return;
		}
		/* Bloque legal no se muestra en checkout (sí se reutiliza en bases). */
		self::render_help_block();
	}

	/** @return array<int, array{label:string,url:string,ico:string}> */
	public static function legal_links(): array {
		$page = static function (string $path, string $fallback = ''): string {
			if (class_exists('SorteoSeguro_Chrome')) {
				return SorteoSeguro_Chrome::page_url($path, $fallback !== '' ? $fallback : home_url('/' . trim($path, '/') . '/'));
			}
			return $fallback !== '' ? $fallback : home_url('/' . trim($path, '/') . '/');
		};
		$links = [
			['label' => 'Términos y Condiciones', 'url' => $page('terminos-condiciones', home_url('/terminos-y-condiciones/')), 'ico' => 'doc'],
			['label' => 'Políticas de Privacidad', 'url' => $page('politica-privacidad', home_url('/politica-de-privacidad/')), 'ico' => 'shield'],
			['label' => 'Política de Funcionamiento de los Concursos', 'url' => $page('politica-funcionamiento-concurso'), 'ico' => 'gavel'],
			['label' => 'Política de envío y reembolsos', 'url' => $page('politicas-de-envio-reembolso', home_url('/politica-de-envio-y-reembolsos/')), 'ico' => 'box'],
			['label' => 'Políticas de Cookies', 'url' => $page('politica-de-cookies'), 'ico' => 'cookie'],
		];
		return apply_filters('ss_checkout_legal_links', $links);
	}

	public static function render_legal_block(): void {
		$links = self::legal_links();
		?>
		<section class="ss-co-shell ss-co-legal" aria-label="Información legal y políticas">
			<div class="ss-co-legal__panel">
				<header class="ss-co-legal__head">
					<span class="ss-co-legal__head-ico" aria-hidden="true"><?php echo self::icon('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2 class="ss-co-legal__title">Información legal y políticas</h2>
						<p class="ss-co-legal__sub">Consulta toda la información legal y las políticas que rigen el funcionamiento de la plataforma.</p>
					</div>
				</header>
				<ul class="ss-co-legal__grid">
					<?php foreach ($links as $item) : ?>
						<li>
							<a class="ss-co-legal__card" href="<?php echo esc_url((string) ($item['url'] ?? '#')); ?>">
								<span class="ss-co-legal__ico" aria-hidden="true"><?php echo self::icon((string) ($item['ico'] ?? 'doc')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="ss-co-legal__label"><?php echo esc_html((string) ($item['label'] ?? '')); ?></span>
								<span class="ss-co-legal__arrow" aria-hidden="true">→</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
	}

	public static function render_help_block(): void {
		$faq = get_permalink(15) ?: home_url('/preguntas_frecuentes/');
		?>
		<section class="ss-co-shell ss-co-help" aria-label="Ayuda">
			<div class="ss-co-help__inner">
				<div class="ss-co-help__copy">
					<span class="ss-co-help__ico" aria-hidden="true"><?php echo self::icon('chat_fill'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="ss-co-help__text">
						<strong class="ss-co-help__title">¿Tienes dudas?</strong>
						<p class="ss-co-help__sub">Resuelve tus inquietudes en nuestra sección de Preguntas Frecuentes.</p>
					</div>
				</div>
				<a class="ss-co-help__btn" href="<?php echo esc_url($faq); ?>">
					Ir a Preguntas Frecuentes
					<span class="ss-co-help__btn-arrow" aria-hidden="true">→</span>
				</a>
			</div>
		</section>
		<?php
	}

	public static function icon(string $name): string {
		$map = [
			/* Legal grid — ref diseño bases/checkout (círculo + trazo) */
			'doc'    => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.65" stroke-linejoin="round" d="M7 3.2h7.1L17.8 7v13.3A1.5 1.5 0 0 1 16.3 21.8H7A1.5 1.5 0 0 1 5.5 20.3V4.7A1.5 1.5 0 0 1 7 3.2z"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" d="M14 3.3V7h3.7"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" d="M8.6 11h6.8M8.6 14.2h6.8M8.6 17.4h4.4"/></svg>',
			'shield' => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.65" stroke-linejoin="round" d="M12 3.1 4.8 6.1v5.4c0 4.8 3.1 9 7.2 10.3 4.1-1.3 7.2-5.5 7.2-10.3V6.1L12 3.1z"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" d="m8.7 12.1 2.3 2.3 4.4-4.5"/></svg>',
			/* Martillo de juez en diagonal + líneas de impacto (ref diseño) */
			'gavel'  => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" d="M5.2 13.1 10.4 7.9l3.1 3.1-5.2 5.2z"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" d="m12.2 9.8 6.6-5.1"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" d="m17.6 3.4 2.3 1.8"/><path stroke="currentColor" stroke-width="1.55" stroke-linecap="round" d="M3.6 17.4h6.2M4.5 19.2h4.8M5.6 21h3"/></svg>',
			'box'    => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.65" stroke-linejoin="round" d="M3.6 8.4 12 3.8l8.4 4.6v9.2L12 22.2 3.6 17.6V8.4z"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" d="M12 22.2V13M3.6 8.4 12 13l8.4-4.6"/><path stroke="currentColor" stroke-width="1.65" stroke-linecap="round" d="M8.2 5.9 15.8 10"/></svg>',
			'cookie' => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.65" stroke-linejoin="round" d="M12.2 3.4a8.6 8.6 0 1 0 8.2 8.1 3.2 3.2 0 0 1-3.1-3.1 3.2 3.2 0 0 1-5.1-5z"/><circle cx="9.2" cy="10.2" r="1" fill="currentColor"/><circle cx="13.6" cy="9.4" r="0.9" fill="currentColor"/><circle cx="11.1" cy="14.2" r="1" fill="currentColor"/><circle cx="15.2" cy="13.6" r="0.85" fill="currentColor"/><circle cx="8.6" cy="14.8" r="0.7" fill="currentColor"/></svg>',
			'chat'   => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M5 5.5h14A1.5 1.5 0 0 1 20.5 7v8A1.5 1.5 0 0 1 19 16.5H10L6 20v-3.5H5A1.5 1.5 0 0 1 3.5 15V7A1.5 1.5 0 0 1 5 5.5z"/></svg>',
			'chat_fill' => '<svg viewBox="0 0 24 24" fill="none"><path fill="currentColor" d="M12 3.2C7.2 3.2 3.4 6.5 3.4 10.6c0 2.5 1.3 4.7 3.4 6.1v3.1c0 .5.5.8 1 .6l3.5-1.5c.5.1 1.1.1 1.7.1 4.8 0 8.6-3.3 8.6-7.4S16.8 3.2 12 3.2z"/><path stroke="#fff" stroke-width="1.8" stroke-linecap="round" d="M8.2 9.2h7.2M8.2 12h5.2M8.2 14.8h3.6"/></svg>',
			'user'   => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M5.5 18.5c1.6-3 4-4.5 6.5-4.5s4.9 1.5 6.5 4.5"/></svg>',
			'mail'   => '<svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5.5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="m4.5 7.5 7.5 6 7.5-6"/></svg>',
			'phone'  => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" d="M7.5 3.5h3L12 8l-2 1.5a12 12 0 0 0 4.5 4.5L16 12l4.5 1.5v3A2 2 0 0 1 18.5 19 15 15 0 0 1 5 5.5a2 2 0 0 1 2.5-2z"/></svg>',
			'lock'   => '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.7"/><path stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
			'check'  => '<svg viewBox="0 0 24 24" fill="none"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 12 4 4 8-8"/></svg>',
		];
		return $map[$name] ?? $map['doc'];
	}
}

SorteoSeguro_Checkout::init();
