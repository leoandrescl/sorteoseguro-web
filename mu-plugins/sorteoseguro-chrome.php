<?php
/**
 * Plugin Name: Sorteo Seguro – Chrome (Header/Footer)
 * Description: Header compacto y footer estandarizado para plantillas custom (PDP, FAQ, etc.).
 * Author: Sorteo Seguro
 * Version: 1.2.9
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Chrome {

	const VERSION = '1.2.9';

	/** Tipografía estándar del proyecto (usar en CSS vía --ss-font-family). */
	const FONT_STACK = '"Montserrat", "Poppins", "Segoe UI", sans-serif';

	private static bool $footer_printed = false;
	private static bool $fonts_enqueued = false;

	public static function init(): void {
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 40);
		add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_theme_fonts'], 999);
		add_action('wp_head', [__CLASS__, 'inline_critical'], 99);
		add_action('wp_head', [__CLASS__, 'print_font_override'], 99999);
		add_action('wp_footer', [__CLASS__, 'print_font_override'], 99999);
		add_action('wp_footer', [__CLASS__, 'header_fix'], 1);
		add_action('wp_footer', [__CLASS__, 'print_footer'], 1);
	}

	/** Encola Montserrat + Poppins (idempotente). Llamar desde FAQ/PDP/packs/cookies. */
	public static function enqueue_fonts(): void {
		if (self::$fonts_enqueued || wp_style_is('ss-fonts', 'enqueued') || wp_style_is('ss-fonts', 'done')) {
			return;
		}
		self::$fonts_enqueued = true;
		wp_enqueue_style(
			'ss-fonts',
			'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800&display=swap',
			[],
			null
		);
	}

	public static function font_stack(): string {
		return self::FONT_STACK;
	}

	/** Evita precargas/CSS de Geologica (Bunny Custom Fonts) en páginas chrome. */
	public static function dequeue_theme_fonts(): void {
		if (!self::enabled() && !self::footer_enabled()) {
			return;
		}
		wp_dequeue_style('local-google-fonts');
		wp_deregister_style('local-google-fonts');
	}

	/**
	 * Override tardío: gana al CSS global WPCode
	 * `body, p, h1… { font-family: Geologica !important }`.
	 */
	public static function print_font_override(): void {
		if (!self::enabled() && !self::footer_enabled() && !self::$fonts_enqueued) {
			return;
		}
		$hook = (string) current_filter();
		static $printed = [];
		if (isset($printed[$hook])) {
			return;
		}
		$printed[$hook] = true;
		$stack = self::FONT_STACK;
		$id = $hook === 'wp_footer' ? 'ss-font-override-footer' : 'ss-font-override';
		echo "\n<style id=\"{$id}\">\n";
		echo ":root{--ss-font-family:{$stack};}\n";
		echo "body.ss-chrome,body.ss-chrome p,body.ss-chrome h1,body.ss-chrome h2,body.ss-chrome h3,body.ss-chrome h4,body.ss-chrome h5,body.ss-chrome h6,";
		echo "body.ss-chrome a,body.ss-chrome span,body.ss-chrome li,body.ss-chrome label,body.ss-chrome strong,body.ss-chrome em,body.ss-chrome small,";
		echo "body.ss-chrome button,body.ss-chrome input,body.ss-chrome select,body.ss-chrome textarea,";
		echo "body.ss-chrome .ss-pdp,body.ss-chrome .ss-pdp *,body.ss-chrome .ss-faq,body.ss-chrome .ss-faq *,body.ss-chrome .ss-home,body.ss-chrome .ss-home *,";
		echo "body.ss-chrome .ss-contact,body.ss-chrome .ss-contact *,body.ss-chrome .ss-auth,body.ss-chrome .ss-auth *,body.ss-chrome .ss-qs,body.ss-chrome .ss-qs *,";
		echo "body.ss-chrome .ss-legal,body.ss-chrome .ss-legal *,";
		echo "body.ss-chrome .ss-co,body.ss-chrome .ss-co *,body.ss-chrome .ss-co-reserve,body.ss-chrome .ss-co-reserve *,";
		echo "body.ss-chrome .ss-co-legal,body.ss-chrome .ss-co-legal *,body.ss-chrome .ss-co-help,body.ss-chrome .ss-co-help *,";
		echo "body.ss-chrome .ss-cart,body.ss-chrome .ss-cart *,";
		echo "body.ss-chrome.woocommerce-checkout,body.ss-chrome.woocommerce-checkout *,";
		echo "body.ss-chrome.woocommerce-cart,body.ss-chrome.woocommerce-cart .ss-cart,body.ss-chrome.woocommerce-cart .ss-cart *,";
		echo "body.ss-chrome .ss-footer,body.ss-chrome .ss-footer *,body.ss-chrome .ss-packs-wrap,body.ss-chrome .ss-packs-wrap *,";
		echo "body.ss-chrome .purchase-panel,body.ss-chrome .purchase-panel *,body.ss-chrome #masthead,body.ss-chrome #masthead *,";
		echo "body.ss-chrome .main-header,body.ss-chrome .main-header *,body.ss-chrome .main-header-v2,body.ss-chrome .main-header-v2 *,";
		echo ".ss-pdp,.ss-pdp *,.ss-faq,.ss-faq *,.ss-home,.ss-home *,.ss-contact,.ss-contact *,.ss-auth,.ss-auth *,.ss-qs,.ss-qs *,.ss-legal,.ss-legal *,.ss-co,.ss-co *,.ss-co-reserve,.ss-co-reserve *,.ss-co-legal,.ss-co-legal *,.ss-co-help,.ss-co-help *,.ss-cart,.ss-cart *,.ss-footer,.ss-footer *,.ss-packs-wrap,.ss-packs-wrap *,";
		echo "#cmplz-cookiebanner-container,.cmplz-cookiebanner,.cmplz-cookiebanner *{font-family:{$stack}!important;}\n";
		echo "body.ss-chrome .bx,body.ss-chrome i.bx,body.ss-chrome .bxs,body.ss-chrome .bxl,";
		echo "body.ss-chrome [class^=\"bx\"],body.ss-chrome [class*=\" bx\"],";
		echo "body.ss-chrome .menu-toggle i,body.ss-chrome .icon-btn i,";
		echo "body.ss-chrome .main-header i,body.ss-chrome .main-header-v2 i,body.ss-chrome #masthead i";
		echo "{font-family:boxicons!important;font-weight:normal!important;font-style:normal!important;}\n";
		echo "</style>\n";
	}

	public static function enabled(): bool {
		return (bool) apply_filters('ss_chrome_enabled', false);
	}

	public static function footer_enabled(): bool {
		return (bool) apply_filters('ss_chrome_footer_enabled', self::enabled());
	}

	public static function body_class(array $classes): array {
		if (self::enabled()) {
			$classes[] = 'ss-chrome';
		}
		return $classes;
	}

	public static function css_path(): string {
		return WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-chrome/assets/chrome.css';
	}

	public static function footer_css_path(): string {
		return WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-chrome/assets/footer.css';
	}

	public static function footer_template_path(): string {
		return WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-chrome/footer.php';
	}

	public static function assets(): void {
		$base = content_url('mu-plugins/sorteoseguro-chrome/assets');
		if (self::enabled()) {
			self::enqueue_fonts();
			$path = self::css_path();
			if (is_readable($path)) {
				wp_enqueue_style('ss-chrome', $base . '/chrome.css', ['ss-fonts'], self::VERSION);
			}
		}
		if (self::footer_enabled()) {
			self::enqueue_fonts();
			$footer_css = self::footer_css_path();
			if (is_readable($footer_css)) {
				wp_enqueue_style('ss-chrome-footer', $base . '/footer.css', ['ss-fonts'], self::VERSION);
			}
		}
	}

	/** Inline de respaldo: garantiza el header compacto aunque LiteSpeed combine/omit CSS. */
	public static function inline_critical(): void {
		if (!self::enabled()) {
			return;
		}
		$path = self::css_path();
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-chrome v" . esc_html(self::VERSION) . " -->\n<style id=\"ss-chrome-inline\">\n" . $css . "\n</style>\n";
	}

	/** Evita que el CSS global del header (WPCode) deje el menú encima del icono de usuario. */
	public static function header_fix(): void {
		if (!self::enabled()) {
			return;
		}
		echo "\n<style id=\"ss-chrome-header-fix\">\n";
		echo 'body.ss-chrome .header-actions{position:relative!important;overflow:visible!important;}';
		echo 'body.ss-chrome span.promo-valor-header{position:absolute!important;z-index:1!important;bottom:-30px!important;right:0!important;white-space:nowrap!important;}';
		echo '@media (max-width:1200px){';
		echo 'body.ss-chrome .header-actions{position:relative!important;top:auto!important;right:auto!important;left:auto!important;bottom:auto!important;overflow:visible!important;}';
		echo 'body.ss-chrome .menu-toggle,body.ss-chrome button.menu-toggle{position:static!important;width:44px!important;height:44px!important;margin:0 0 0 auto!important;}';
		echo 'body.ss-chrome .header-container,body.ss-chrome .main-header-v2 .header-container{display:flex!important;flex-wrap:nowrap!important;grid-template-columns:none!important;}';
		echo 'body.ss-chrome .logo{flex:1 1 0%!important;min-width:0!important;overflow:hidden!important;white-space:nowrap!important;}';
		echo 'body.ss-chrome .header-actions{flex:0 0 auto!important;flex-wrap:nowrap!important;flex-shrink:0!important;}';
		echo 'body.ss-chrome .main-nav{position:absolute!important;top:calc(100% + .35rem)!important;left:0!important;right:0!important;flex:none!important;}';
		echo "}\n</style>\n";
		echo "<script id=\"ss-chrome-header-fix-js\">\n";
		echo '(function(){function place(){document.querySelectorAll("body.ss-chrome .header-container").forEach(function(row){var t=row.querySelector(":scope > .menu-toggle");var a=row.querySelector(":scope > .header-actions");if(t&&a){row.insertBefore(t,a);}});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",place);}else{place();}})();';
		echo "\n</script>\n";
	}

	public static function page_url(string $path, string $fallback = ''): string {
		$page = get_page_by_path(trim($path, '/'));
		if ($page instanceof WP_Post) {
			return (string) get_permalink($page);
		}
		return $fallback !== '' ? $fallback : home_url('/' . trim($path, '/') . '/');
	}

	/** @return array<string, mixed> */
	public static function footer_data(): array {
		$faq     = get_permalink(15) ?: self::page_url('preguntas_frecuentes');
		$contact = get_permalink(1387) ?: self::page_url('contacto');
		$bases   = apply_filters('ss_chrome_bases_url', self::page_url('politica-funcionamiento-concurso'));

		$data = [
			'home'      => home_url('/'),
			'logo_icon' => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo-1-e1751661451410.png',
			'logo_full' => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo_sin_fondo.png',
			'notaria'   => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/logo-notaria.jpg',
			'bases'     => $bases,
			'instagram' => 'https://www.instagram.com/ssdigitalchile/',
			'facebook'  => 'https://www.facebook.com/share/1AeiDamo9U',
			'tiktok'    => 'https://www.tiktok.com/@sorteoseguro.cl',
			'youtube'   => 'https://www.youtube.com/@SorteoSeguro',
			'email'     => 'contacto@sorteoseguro.com',
			'hours'     => 'Lunes a Viernes 09:00 - 19:00 hrs.',
			'legal'     => [
				['label' => 'Términos y Condiciones', 'url' => self::page_url('terminos-condiciones')],
				['label' => 'Políticas de Privacidad', 'url' => self::page_url('politica-privacidad')],
				['label' => 'Política de Funcionamiento de los Concursos', 'url' => self::page_url('politica-funcionamiento-concurso')],
				['label' => 'Política de envío y reembolsos', 'url' => self::page_url('politicas-de-envio-reembolso')],
				['label' => 'Políticas de Cookies', 'url' => self::page_url('politica-de-cookies')],
			],
			'help'      => [
				['label' => 'Preguntas Frecuentes', 'url' => $faq],
				['label' => '¿Cómo participar?', 'url' => $faq],
				['label' => 'Formas de pago', 'url' => $faq],
				['label' => 'Bases legales', 'url' => $bases],
				['label' => 'Contacto', 'url' => $contact],
			],
			'pay_mp'    => content_url('mu-plugins/sorteoseguro-chrome/assets/logo-mercado-pago.png'),
			'pay_wp'    => content_url('mu-plugins/sorteoseguro-chrome/assets/logo-web-pay-plus.png'),
		];

		return apply_filters('ss_chrome_footer_data', $data);
	}

	public static function render_footer(): void {
		if (self::$footer_printed) {
			return;
		}
		$path = self::footer_template_path();
		if (!is_readable($path)) {
			return;
		}
		self::$footer_printed = true;
		$ss_footer = self::footer_data();
		include $path;
	}

	public static function print_footer(): void {
		if (!self::footer_enabled()) {
			return;
		}
		self::render_footer();
	}
}

function ss_chrome_footer(): void {
	if (class_exists('SorteoSeguro_Chrome')) {
		SorteoSeguro_Chrome::render_footer();
	}
}

SorteoSeguro_Chrome::init();
