<?php
/**
 * Plugin Name: Sorteo Seguro – Temporada (Halloween / Navidad)
 * Description: Motor de ambientación estacional global. Se activa/apaga en Ajustes → Sorteo Seguro · Temporada.
 * Author: Sorteo Seguro
 * Version: 1.3.1
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_Seasonal {

	const VERSION       = '1.3.1';
	const OPTION_MODE   = 'ss_seasonal_mode';
	const OPTION_CURSOR = 'ss_seasonal_cursor';
	const MENU_SLUG     = 'ss-seasonal';
	const NONCE         = 'ss_seasonal_save';

	/** @var string[] */
	const THEMES = ['off', 'halloween', 'christmas'];

	public static function init(): void {
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_action('wp_head', [__CLASS__, 'print_css'], 5);
		add_action('wp_footer', [__CLASS__, 'print_js'], 20);
		add_action('admin_menu', [__CLASS__, 'admin_menu']);
		add_action('admin_init', [__CLASS__, 'maybe_install']);
		add_action('admin_post_' . self::NONCE, [__CLASS__, 'handle_save']);
	}

	public static function assets_dir(): string {
		return WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-seasonal/assets';
	}

	public static function assets_url(): string {
		return content_url('mu-plugins/sorteoseguro-seasonal/assets');
	}

	/** Crea las opciones con su default la primera vez (sin autoload). */
	public static function maybe_install(): void {
		if (get_option(self::OPTION_MODE, null) === null) {
			add_option(self::OPTION_MODE, 'off', '', false);
		}
		if (get_option(self::OPTION_CURSOR, null) === null) {
			add_option(self::OPTION_CURSOR, '1', '', false);
		}
	}

	/**
	 * Tema activo. Preview para administradores vía ?ss_theme=halloween|christmas|off.
	 */
	public static function mode(): string {
		$mode = (string) get_option(self::OPTION_MODE, 'off');

		if (isset($_GET['ss_theme']) && current_user_can('manage_options')) {
			$req = sanitize_key(wp_unslash($_GET['ss_theme']));
			if (in_array($req, self::THEMES, true)) {
				$mode = $req;
			}
		}

		$mode = (string) apply_filters('ss_seasonal_theme', $mode);

		return in_array($mode, self::THEMES, true) ? $mode : 'off';
	}

	public static function active(): bool {
		return !is_admin() && self::mode() !== 'off';
	}

	public static function cursor_enabled(): bool {
		return (bool) get_option(self::OPTION_CURSOR, '1');
	}

	/** @param array<int, string> $classes @return array<int, string> */
	public static function body_class(array $classes): array {
		if (is_admin()) {
			return $classes;
		}
		$mode = self::mode();
		if ($mode === 'off') {
			return $classes;
		}
		$classes[] = 'ss-theme--' . $mode;
		if (self::cursor_enabled()) {
			$classes[] = 'ss-seasonal-cursor';
		}
		return $classes;
	}

	public static function print_css(): void {
		if (!self::active()) {
			return;
		}
		$path = self::assets_dir() . '/seasonal.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-seasonal v" . esc_html(self::VERSION) . " -->\n";
		echo "<style id=\"ss-seasonal-css\">\n" . $css . "\n</style>\n";
	}

	public static function print_js(): void {
		if (!self::active()) {
			return;
		}
		$path = self::assets_dir() . '/seasonal.js';
		if (!is_readable($path)) {
			return;
		}
		$js = file_get_contents($path);
		if ($js === false || $js === '') {
			return;
		}
		$config = [
			'theme'   => self::mode(),
			'cursor'  => self::cursor_enabled(),
			'assets'  => self::assets_url(),
			'version' => self::VERSION,
		];
		echo "\n<script id=\"ss-seasonal-config\">window.SS_SEASONAL=" . wp_json_encode($config) . ";</script>\n";
		echo "<script id=\"ss-seasonal-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n" . $js . "\n</script>\n";
	}

	public static function admin_menu(): void {
		add_options_page(
			'Sorteo Seguro · Temporada',
			'Sorteo Seguro · Temporada',
			'manage_options',
			self::MENU_SLUG,
			[__CLASS__, 'render_admin']
		);
	}

	public static function render_admin(): void {
		if (!current_user_can('manage_options')) {
			return;
		}
		$mode   = (string) get_option(self::OPTION_MODE, 'off');
		$cursor = (bool) get_option(self::OPTION_CURSOR, '1');
		$saved  = isset($_GET['ss_saved']);
		$labels = [
			'off'       => 'Desactivado (sitio normal)',
			'halloween' => 'Halloween',
			'christmas' => 'Navidad',
		];
		?>
		<div class="wrap">
			<h1>Sorteo Seguro · Temporada</h1>
			<?php if ($saved) : ?>
				<div class="notice notice-success is-dismissible"><p>Cambios guardados y caché purgada.</p></div>
			<?php endif; ?>
			<p>Ambientación estacional global del sitio. El diseño base no se modifica: todo se activa con este interruptor.</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr(self::NONCE); ?>">
				<?php wp_nonce_field(self::NONCE); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Tema</th>
						<td>
							<fieldset>
								<?php foreach (self::THEMES as $key) : ?>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="ss_mode" value="<?php echo esc_attr($key); ?>" <?php checked($mode, $key); ?>>
										<?php echo esc_html($labels[$key] ?? $key); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row">Cursor temático</th>
						<td>
							<label>
								<input type="checkbox" name="ss_cursor" value="1" <?php checked($cursor); ?>>
								Usar cursor especial en escritorio (se ignora en móvil)
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button('Guardar y purgar caché'); ?>
			</form>
			<hr>
			<p><strong>Vista previa sin activar:</strong> agrega <code>?ss_theme=halloween</code> o <code>?ss_theme=christmas</code> a cualquier URL estando con sesión de administrador.</p>
		</div>
		<?php
	}

	public static function handle_save(): void {
		if (!current_user_can('manage_options')) {
			wp_die('Sin permisos.');
		}
		check_admin_referer(self::NONCE);

		$mode = isset($_POST['ss_mode']) ? sanitize_key(wp_unslash($_POST['ss_mode'])) : 'off';
		if (!in_array($mode, self::THEMES, true)) {
			$mode = 'off';
		}
		$cursor = !empty($_POST['ss_cursor']) ? '1' : '0';

		update_option(self::OPTION_MODE, $mode, false);
		update_option(self::OPTION_CURSOR, $cursor, false);

		if (function_exists('wp_cache_flush')) {
			wp_cache_flush();
		}
		if (function_exists('litespeed_purge_all')) {
			litespeed_purge_all();
		}
		do_action('litespeed_purge_all');

		wp_safe_redirect(add_query_arg([
			'page'     => self::MENU_SLUG,
			'ss_saved' => '1',
		], admin_url('options-general.php')));
		exit;
	}
}

SorteoSeguro_Seasonal::init();
