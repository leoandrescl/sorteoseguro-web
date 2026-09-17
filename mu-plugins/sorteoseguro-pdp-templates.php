<?php
/**
 * Plugin Name: Sorteo Seguro – PDP Templates
 * Description: Plantillas hardcodeadas de ficha de producto (piloto). Solo aplica a IDs registrados.
 * Author: Sorteo Seguro
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_PDP_Templates {

	const VERSION = '1.0.39';
	const META_YOUTUBE = '_ss_pdp_youtube_url';
	const META_FILE    = '_ss_pdp_video_file';
	const META_MARQUEE = '_ss_pdp_marquee_ids';

	/** product_id => relative template under mu-plugins/sorteoseguro-pdp/ */
	private static array $map = [
		874   => 'yamaha-fz25.php',
		1091  => 'vista-mar-dunares.php',
		45941 => 'jeep-avenger.php',
		53210 => 'peugeot-208.php',
		49102 => 'parcela-choros.php',
	];

	public static function init(): void {
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('body_class', [__CLASS__, 'body_class']);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
		add_action('wp_head', [__CLASS__, 'print_critical_css'], 3);
		add_action('wp_footer', [__CLASS__, 'print_js'], 20);
		add_filter('cmplz_whitelisted_script_tags', [__CLASS__, 'cmplz_whitelist_video']);
		add_action('add_meta_boxes', [__CLASS__, 'register_video_metabox']);
		add_action('add_meta_boxes', [__CLASS__, 'register_marquee_metabox']);
		add_action('save_post_product', [__CLASS__, 'save_video_metabox'], 20, 2);
		add_action('save_post_product', [__CLASS__, 'save_marquee_metabox'], 21, 2);
		add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::current_template_product_id() ? true : $enabled;
	}

	/**
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public static function body_class(array $classes): array {
		if (self::current_template_product_id()) {
			$classes[] = 'ss-pdp-template';
		}
		return $classes;
	}

	public static function current_template_product_id(): int {
		if (!is_singular('product')) {
			return 0;
		}
		$id = (int) get_queried_object_id();
		return isset(self::$map[$id]) ? $id : 0;
	}

	public static function template_path(int $product_id): string {
		$file = self::$map[$product_id] ?? '';
		if ($file === '') {
			return '';
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/' . $file;
		return is_readable($path) ? $path : '';
	}

	public static function template_include(string $template): string {
		$id = self::current_template_product_id();
		if (!$id) {
			return $template;
		}
		$custom = self::template_path($id);
		return $custom !== '' ? $custom : $template;
	}

	/**
	 * En front, evita que Elementor pinte la ficha de productos con plantilla propia.
	 * En admin/editor Elementor sigue funcionando.
	 */
	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		if (!isset(self::$map[(int) $object_id])) {
			return $value;
		}
		// Solo en vista pública del producto.
		if (!is_singular('product') || (int) get_queried_object_id() !== (int) $object_id) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		$id = self::current_template_product_id();
		if (!$id) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = content_url('mu-plugins/sorteoseguro-pdp/assets');
		$dir  = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($dir . '/pdp.css')) {
			wp_enqueue_style('ss-pdp', $base . '/pdp.css', $deps, $ver);
		}
		if (is_readable($dir . '/pdp.js')) {
			wp_enqueue_script('ss-pdp', $base . '/pdp.js', [], $ver, true);
		}
	}

	public static function print_critical_css(): void {
		if (!self::current_template_product_id()) {
			return;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/assets/pdp.css';
		if (!is_readable($path)) {
			return;
		}
		$css = file_get_contents($path);
		if ($css === false || $css === '') {
			return;
		}
		echo "\n<!-- ss-pdp critical v" . esc_html(self::VERSION) . " -->\n";
		echo '<style id="ss-pdp-critical" data-no-optimize="1">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function print_js(): void {
		if (!self::current_template_product_id()) {
			return;
		}
		$path = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-pdp/assets/pdp.js';
		if (!is_readable($path)) {
			return;
		}
		$js = file_get_contents($path);
		if ($js === false || $js === '') {
			return;
		}
		echo "\n<script id=\"ss-pdp-js\" data-no-optimize=\"1\" data-no-defer=\"1\">\n" . $js . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Complianz vacía iframes de YouTube en la primera visita. El video de la ficha
	 * es contenido propio, no un embed de marketing: no debe esperar consentimiento.
	 *
	 * @param array<int, string> $tags
	 * @return array<int, string>
	 */
	public static function cmplz_whitelist_video(array $tags): array {
		$tags[] = 'ss-pdp-media__iframe';
		$tags[] = 'ss-pdp-media__video';
		return $tags;
	}

	public static function admin_assets(string $hook): void {
		if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->post_type !== 'product') {
			return;
		}
		wp_enqueue_media();
	}

	public static function register_video_metabox(): void {
		add_meta_box(
			'ss_pdp_video',
			'Video de la ficha',
			[__CLASS__, 'render_video_metabox'],
			'product',
			'side',
			'high'
		);
	}

	public static function register_marquee_metabox(): void {
		add_meta_box(
			'ss_pdp_marquee',
			'Carrusel de imágenes (bajo miniaturas)',
			[__CLASS__, 'render_marquee_metabox'],
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * @return list<int>
	 */
	public static function product_marquee_ids(int $product_id): array {
		if ($product_id <= 0) {
			return [];
		}
		$raw = get_post_meta($product_id, self::META_MARQUEE, true);
		if (is_array($raw)) {
			$parts = $raw;
		} else {
			$parts = preg_split('/[\s,]+/', trim((string) $raw)) ?: [];
		}
		$ids = [];
		foreach ($parts as $part) {
			$id = (int) $part;
			if ($id > 0 && !in_array($id, $ids, true)) {
				$ids[] = $id;
			}
		}
		if ($ids !== []) {
			return $ids;
		}
		return self::product_gallery_ids($product_id);
	}

	/**
	 * Galería WooCommerce (destacada + miniaturas) para el carrusel si no hay IDs propios.
	 *
	 * @return list<int>
	 */
	public static function product_gallery_ids(int $product_id): array {
		if ($product_id <= 0 || !function_exists('wc_get_product')) {
			return [];
		}
		$product = wc_get_product($product_id);
		if (!$product) {
			return [];
		}
		$ids = [];
		$thumb = (int) $product->get_image_id();
		if ($thumb > 0) {
			$ids[] = $thumb;
		}
		foreach ($product->get_gallery_image_ids() as $gid) {
			$gid = (int) $gid;
			if ($gid > 0 && !in_array($gid, $ids, true)) {
				$ids[] = $gid;
			}
		}
		return $ids;
	}

	/**
	 * @return list<array{id:int,src:string,alt:string}>
	 */
	public static function product_marquee_images(int $product_id): array {
		$out = [];
		foreach (self::product_marquee_ids($product_id) as $id) {
			$src = wp_get_attachment_image_url($id, 'medium_large')
				?: wp_get_attachment_image_url($id, 'large')
				?: wp_get_attachment_image_url($id, 'full');
			if (!$src) {
				continue;
			}
			$alt = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
			$out[] = [
				'id'  => $id,
				'src' => $src,
				'alt' => $alt,
			];
		}
		return $out;
	}

	public static function render_marquee(int $product_id): void {
		$images = self::product_marquee_images($product_id);
		if ($images === []) {
			return;
		}
		// Duplicar el set hasta cubrir al menos 6 slides (2 vueltas de 3 visibles).
		$slides = $images;
		while (count($slides) < 6) {
			foreach ($images as $img) {
				$slides[] = $img;
				if (count($slides) >= 6) {
					break;
				}
			}
		}
		$loop = array_merge($slides, $slides);
		$secs = max(18, count($slides) * 3);
		?>
		<div
			class="ss-pdp-marquee"
			data-ss-marquee
			aria-hidden="true"
			style="--ss-marquee-duration: <?php echo (int) $secs; ?>s;"
		>
			<div class="ss-pdp-marquee__viewport">
				<div class="ss-pdp-marquee__track">
					<?php foreach ($loop as $img) : ?>
						<figure class="ss-pdp-marquee__item">
							<img
								src="<?php echo esc_url($img['src']); ?>"
								alt=""
								loading="lazy"
								decoding="async"
								draggable="false"
							>
						</figure>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_marquee_metabox(\WP_Post $post): void {
		$ids = self::product_marquee_ids((int) $post->ID);
		wp_nonce_field('ss_pdp_marquee_save', 'ss_pdp_marquee_nonce');
		?>
		<p style="margin:0 0 12px;">Galería aparte de la imagen destacada y las miniaturas WooCommerce. Se muestra como carrusel automático (3 a la vez) bajo las miniaturas en ficha, compra directa y oferta. Sin imágenes = no se muestra.</p>
		<input type="hidden" id="ss_pdp_marquee_ids" name="ss_pdp_marquee_ids" value="<?php echo esc_attr(implode(',', $ids)); ?>">
		<ul id="ss-pdp-marquee-list" class="ss-pdp-marquee-admin__list" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;padding:0;list-style:none;">
			<?php foreach ($ids as $id) :
				$thumb = wp_get_attachment_image_url($id, 'thumbnail');
				if (!$thumb) {
					continue;
				}
				?>
				<li data-id="<?php echo (int) $id; ?>" style="position:relative;width:84px;height:84px;border:1px solid #c3c4c7;border-radius:4px;overflow:hidden;background:#f0f0f1;">
					<img src="<?php echo esc_url($thumb); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
					<button type="button" class="button-link ss-pdp-marquee-admin__remove" style="position:absolute;top:2px;right:4px;color:#b32d2e;text-decoration:none;font-weight:700;" aria-label="Quitar">&times;</button>
				</li>
			<?php endforeach; ?>
		</ul>
		<p style="margin:0;">
			<button type="button" class="button" id="ss-pdp-marquee-add">Agregar imágenes</button>
			<button type="button" class="button-link" id="ss-pdp-marquee-clear" style="margin-left:8px;">Vaciar galería</button>
		</p>
		<script>
		(function () {
			var input = document.getElementById('ss_pdp_marquee_ids');
			var list = document.getElementById('ss-pdp-marquee-list');
			var addBtn = document.getElementById('ss-pdp-marquee-add');
			var clearBtn = document.getElementById('ss-pdp-marquee-clear');
			if (!input || !list || !addBtn || typeof wp === 'undefined' || !wp.media) return;

			function idsFromInput() {
				return (input.value || '').split(/[\s,]+/).map(function (v) {
					return parseInt(v, 10);
				}).filter(function (n) { return n > 0; });
			}

			function syncInput() {
				var ids = [];
				list.querySelectorAll('li[data-id]').forEach(function (li) {
					var id = parseInt(li.getAttribute('data-id'), 10);
					if (id > 0) ids.push(id);
				});
				input.value = ids.join(',');
			}

			function addItem(id, url) {
				if (!id || !url) return;
				if (list.querySelector('li[data-id="' + id + '"]')) return;
				var li = document.createElement('li');
				li.setAttribute('data-id', String(id));
				li.style.cssText = 'position:relative;width:84px;height:84px;border:1px solid #c3c4c7;border-radius:4px;overflow:hidden;background:#f0f0f1;';
				li.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">' +
					'<button type="button" class="button-link ss-pdp-marquee-admin__remove" style="position:absolute;top:2px;right:4px;color:#b32d2e;text-decoration:none;font-weight:700;" aria-label="Quitar">&times;</button>';
				list.appendChild(li);
			}

			list.addEventListener('click', function (e) {
				var btn = e.target.closest('.ss-pdp-marquee-admin__remove');
				if (!btn) return;
				e.preventDefault();
				var li = btn.closest('li');
				if (li) li.remove();
				syncInput();
			});

			addBtn.addEventListener('click', function (e) {
				e.preventDefault();
				var frame = wp.media({
					title: 'Carrusel bajo miniaturas',
					library: { type: 'image' },
					button: { text: 'Agregar al carrusel' },
					multiple: true
				});
				frame.on('select', function () {
					frame.state().get('selection').each(function (att) {
						var json = att.toJSON();
						var url = (json.sizes && json.sizes.thumbnail && json.sizes.thumbnail.url) || json.url || '';
						addItem(json.id, url);
					});
					syncInput();
				});
				frame.open();
			});

			if (clearBtn) {
				clearBtn.addEventListener('click', function (e) {
					e.preventDefault();
					list.innerHTML = '';
					input.value = '';
				});
			}
		})();
		</script>
		<?php
	}

	public static function save_marquee_metabox(int $post_id, $post = null): void {
		if (!isset($_POST['ss_pdp_marquee_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ss_pdp_marquee_nonce'])), 'ss_pdp_marquee_save')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		$raw = isset($_POST['ss_pdp_marquee_ids']) ? sanitize_text_field(wp_unslash($_POST['ss_pdp_marquee_ids'])) : '';
		$ids = [];
		foreach (preg_split('/[\s,]+/', $raw) ?: [] as $part) {
			$id = (int) $part;
			if ($id > 0 && !in_array($id, $ids, true) && wp_attachment_is_image($id)) {
				$ids[] = $id;
			}
		}
		if ($ids === []) {
			delete_post_meta($post_id, self::META_MARQUEE);
			return;
		}
		update_post_meta($post_id, self::META_MARQUEE, implode(',', $ids));
	}

	public static function render_video_metabox(\WP_Post $post): void {
		$yt   = (string) get_post_meta($post->ID, self::META_YOUTUBE, true);
		$file = (string) get_post_meta($post->ID, self::META_FILE, true);
		wp_nonce_field('ss_pdp_video_save', 'ss_pdp_video_nonce');
		?>
		<p style="margin:0 0 10px;">Si hay URL de YouTube, se usa esa. Si no, el archivo .mp4.</p>
		<p>
			<label for="ss_pdp_youtube_url"><strong>URL de YouTube</strong></label>
			<input type="url" class="widefat" id="ss_pdp_youtube_url" name="ss_pdp_youtube_url" value="<?php echo esc_attr($yt); ?>" placeholder="https://www.youtube.com/shorts/...">
		</p>
		<p>
			<label for="ss_pdp_video_file"><strong>Archivo de video</strong></label>
			<input type="url" class="widefat" id="ss_pdp_video_file" name="ss_pdp_video_file" value="<?php echo esc_attr($file); ?>" placeholder="https://.../video.mp4">
		</p>
		<p>
			<button type="button" class="button" id="ss-pdp-video-pick">Elegir archivo</button>
			<button type="button" class="button-link" id="ss-pdp-video-clear" style="margin-left:6px;">Quitar archivo</button>
		</p>
		<script>
		(function () {
			var input = document.getElementById('ss_pdp_video_file');
			var pick = document.getElementById('ss-pdp-video-pick');
			var clear = document.getElementById('ss-pdp-video-clear');
			if (!input || !pick || typeof wp === 'undefined' || !wp.media) return;
			pick.addEventListener('click', function (e) {
				e.preventDefault();
				var frame = wp.media({
					title: 'Video de la ficha',
					library: { type: 'video' },
					button: { text: 'Usar este video' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					input.value = att.url || '';
				});
				frame.open();
			});
			if (clear) {
				clear.addEventListener('click', function (e) {
					e.preventDefault();
					input.value = '';
				});
			}
		})();
		</script>
		<?php
	}

	public static function save_video_metabox(int $post_id, $post = null): void {
		if (!isset($_POST['ss_pdp_video_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ss_pdp_video_nonce'])), 'ss_pdp_video_save')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		$yt = isset($_POST['ss_pdp_youtube_url']) ? esc_url_raw(wp_unslash($_POST['ss_pdp_youtube_url'])) : '';
		$file = isset($_POST['ss_pdp_video_file']) ? esc_url_raw(wp_unslash($_POST['ss_pdp_video_file'])) : '';
		update_post_meta($post_id, self::META_YOUTUBE, $yt);
		update_post_meta($post_id, self::META_FILE, $file);
	}

	public static function youtube_id(string $url): string {
		$url = trim($url);
		if ($url === '') {
			return '';
		}
		if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i', $url, $m)) {
			return $m[1];
		}
		if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
			return $url;
		}
		return '';
	}

	/**
	 * @return array{type:string,youtube_id:string,embed_url:string,file_url:string}
	 */
	public static function product_video(int $product_id): array {
		$empty = [
			'type'       => '',
			'youtube_id' => '',
			'embed_url'  => '',
			'file_url'   => '',
		];
		$yt_id = self::youtube_id((string) get_post_meta($product_id, self::META_YOUTUBE, true));
		if ($yt_id !== '') {
			$origin = rawurlencode(home_url('/'));
			return [
				'type'       => 'youtube',
				'youtube_id' => $yt_id,
				'embed_url'  => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($yt_id)
					. '?rel=0&modestbranding=1&playsinline=1&enablejsapi=1&origin=' . $origin,
				'file_url'   => '',
			];
		}
		$file = esc_url_raw((string) get_post_meta($product_id, self::META_FILE, true));
		if ($file === '' && $product_id === 874) {
			$file = 'https://sorteoseguro.cl/wp-content/uploads/2025/11/video-moto-v2.mp4';
		}
		if ($file !== '') {
			return [
				'type'       => 'file',
				'youtube_id' => '',
				'embed_url'  => '',
				'file_url'   => $file,
			];
		}
		return $empty;
	}
}

SorteoSeguro_PDP_Templates::init();
