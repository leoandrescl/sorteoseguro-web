<?php
/**
 * Plugin Name: Sorteo Seguro – FAQ
 * Description: CPT de preguntas frecuentes + plantilla PHP para la página FAQ (sin Elementor).
 * Author: Sorteo Seguro
 * Version: 1.1.6
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_FAQ {

	const VERSION     = '1.1.6';
	const CPT         = 'ss_faq';
	const TAX         = 'ss_faq_cat';
	const PAGE_ID     = 15;
	const OPTION_SEED = 'ss_faq_seeded_v2';

	public static function init(): void {
		add_action('init', [__CLASS__, 'register']);
		add_action('init', [__CLASS__, 'maybe_seed'], 20);
		add_filter('template_include', [__CLASS__, 'template_include'], 99999);
		add_filter('get_post_metadata', [__CLASS__, 'disable_elementor_builder'], 10, 4);
		add_filter('ss_chrome_enabled', [__CLASS__, 'enable_chrome']);
		add_action('wp_enqueue_scripts', [__CLASS__, 'assets'], 50);
	}

	public static function enable_chrome(bool $enabled): bool {
		return self::is_faq_page() ? true : $enabled;
	}

	public static function register(): void {
		register_post_type(self::CPT, [
			'labels' => [
				'name'               => 'Preguntas frecuentes',
				'singular_name'      => 'Pregunta',
				'add_new'            => 'Añadir pregunta',
				'add_new_item'       => 'Añadir pregunta',
				'edit_item'          => 'Editar pregunta',
				'new_item'           => 'Nueva pregunta',
				'view_item'          => 'Ver pregunta',
				'search_items'       => 'Buscar preguntas',
				'not_found'          => 'No se encontraron preguntas',
				'not_found_in_trash' => 'No hay preguntas en la papelera',
				'menu_name'          => 'Preguntas FAQ',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 26,
			'menu_icon'           => 'dashicons-editor-help',
			'supports'            => ['title', 'editor', 'page_attributes'],
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => true,
		]);

		register_taxonomy(self::TAX, self::CPT, [
			'labels' => [
				'name'          => 'Categorías FAQ',
				'singular_name' => 'Categoría FAQ',
				'search_items'  => 'Buscar categorías',
				'all_items'     => 'Todas las categorías',
				'edit_item'     => 'Editar categoría',
				'update_item'   => 'Actualizar categoría',
				'add_new_item'  => 'Añadir categoría',
				'new_item_name' => 'Nueva categoría',
				'menu_name'     => 'Categorías',
			],
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
			'show_in_rest'      => true,
		]);
	}

	/** @return array<string, array{name:string, icon:string}> */
	public static function categories_config(): array {
		return [
			'digitickets'   => ['name' => 'DigiTickets', 'icon' => 'ticket'],
			'participacion' => ['name' => 'Participación', 'icon' => 'user'],
			'concursos'     => ['name' => 'Concursos', 'icon' => 'trophy'],
			'pagos'         => ['name' => 'Pagos', 'icon' => 'card'],
			'ganadores'     => ['name' => 'Ganadores', 'icon' => 'star'],
		];
	}

	public static function ensure_terms(): void {
		foreach (self::categories_config() as $slug => $cfg) {
			if (!term_exists($slug, self::TAX)) {
				wp_insert_term($cfg['name'], self::TAX, ['slug' => $slug]);
			}
		}
	}

	/** @return array<int, array{q:string,a:string,cats:string[],order:int}> */
	public static function default_items(): array {
		return [
			[
				'q' => '¿Es legal el concurso?',
				'a' => '<p>Sí. Cada concurso publicado en nuestra plataforma cuenta con sus respectivas Bases Legales, las cuales han sido firmadas ante Notario Público y reducidas a escritura pública, conforme a la normativa vigente en la República de Chile.</p><p>Estas Bases establecen claramente las condiciones del concurso, incluyendo el premio, las reglas de participación, el mínimo de participaciones requerido y el mecanismo mediante el cual se realizará el sorteo.</p>',
				'cats' => ['concursos'],
				'order' => 1,
			],
			[
				'q' => '¿Qué es un DigiTicket?',
				'a' => '<p>El DigiTicket es una ilustración digital numerada que adquieres en nuestra plataforma y que te da derecho a participar en el concurso correspondiente.</p><p>Este archivo digital contiene un número único de participación, el cual forma parte del proceso de selección aleatoria del concurso en el que fue adquirido.</p><p>Cada DigiTicket representa una oportunidad de resultar ganador.</p>',
				'cats' => ['digitickets'],
				'order' => 2,
			],
			[
				'q' => '¿Cómo sé que mi participación es válida?',
				'a' => '<p>Una vez finalizado el proceso de compra y completado el registro correspondiente, recibirás tu DigiTicket directamente en tu correo electrónico.</p><p>Además, siempre podrás acceder a tus DigiTickets desde tu perfil de usuario dentro de la plataforma.</p><p>Cada DigiTicket contiene un número único que acredita tu participación válida en el concurso correspondiente.</p>',
				'cats' => ['participacion', 'digitickets'],
				'order' => 3,
			],
			[
				'q' => '¿Puedo elegir el número de mi DigiTicket?',
				'a' => '<p>Sí. Puedes seleccionar el número de DigiTicket de tu preferencia siempre que se encuentre disponible.</p><p>Si el número elegido ya ha sido asignado a otro participante, el sistema podrá sugerirte otro número disponible o asignarte uno automáticamente.</p>',
				'cats' => ['digitickets', 'participacion'],
				'order' => 4,
			],
			[
				'q' => '¿Cuántas veces puedo participar?',
				'a' => '<p>Puedes participar tantas veces como desees.</p><p>Cada DigiTicket adquirido representa una nueva participación en el concurso, por lo que mientras más DigiTickets tengas, mayores serán tus probabilidades de resultar ganador.</p>',
				'cats' => ['participacion'],
				'order' => 5,
			],
			[
				'q' => '¿Qué ocurre si no se venden todos los DigiTickets?',
				'a' => '<p>El concurso no depende necesariamente de vender la totalidad de los DigiTickets disponibles.</p><p>Cada concurso establece en sus Bases Legales un mínimo de participaciones requerido para poder realizar el sorteo.</p><p>Una vez alcanzado ese mínimo, el proceso continúa conforme a lo establecido en dichas Bases.</p>',
				'cats' => ['digitickets', 'concursos'],
				'order' => 6,
			],
			[
				'q' => '¿Por qué se prorrogan algunos concursos?',
				'a' => '<p>Las prórrogas pueden ocurrir cuando aún no se ha alcanzado el mínimo de participaciones requerido para realizar el sorteo.</p><p>En estos casos, el concurso continúa vigente hasta cumplir esa condición, conforme a lo establecido en sus Bases Legales.</p><p>Las actualizaciones de fechas se informan siempre en la plataforma y en nuestros canales oficiales.</p>',
				'cats' => ['concursos'],
				'order' => 7,
			],
			[
				'q' => '¿Qué pasa si el concurso tarda más de lo esperado?',
				'a' => '<p>Algunos concursos pueden tardar más tiempo en alcanzar el mínimo de participaciones requerido.</p><p>Cuando esto ocurre, el concurso continúa vigente hasta cumplir dicha condición, momento en el cual se informa oficialmente la fecha definitiva del sorteo conforme a lo establecido en las Bases Legales.</p><p>Nuestro objetivo es realizar cada concurso con total transparencia y respetando las reglas informadas desde el inicio.</p>',
				'cats' => ['concursos'],
				'order' => 8,
			],
			[
				'q' => '¿Cuándo se realiza el sorteo?',
				'a' => '<p>Una vez alcanzado el mínimo de participaciones requerido en las Bases Legales, se informa oficialmente la fecha definitiva del sorteo.</p><p>Esta información se comunica a los participantes mediante correo electrónico y a través de los canales oficiales de la plataforma.</p>',
				'cats' => ['concursos'],
				'order' => 9,
			],
			[
				'q' => '¿Qué sucede una vez alcanzado el mínimo de participaciones?',
				'a' => '<p>Una vez alcanzado el mínimo de participaciones establecido en las Bases Legales del concurso, se fija oficialmente la fecha del sorteo.</p><p>Dicha fecha se comunica a los participantes y el sorteo se realiza dentro del plazo establecido en las Bases Legales correspondientes.</p>',
				'cats' => ['concursos', 'participacion'],
				'order' => 10,
			],
			[
				'q' => '¿Cómo se elige al ganador?',
				'a' => '<p>El ganador se selecciona mediante un sistema informático de selección aleatoria que elige un número entre todos los DigiTickets válidamente emitidos para el concurso.</p><p>El proceso se realiza:</p><ul><li>mediante transmisión en vivo</li><li>bajo supervisión de Notario Público</li><li>dejando constancia mediante acta notarial</li></ul><p>Esto garantiza transparencia y seguridad en el procedimiento.</p>',
				'cats' => ['ganadores', 'concursos'],
				'order' => 11,
			],
			[
				'q' => '¿Quién supervisa el sorteo?',
				'a' => '<p>El sorteo es supervisado por un Notario Público, quien actúa como Ministro de Fe certificando que el procedimiento se realice conforme a las Bases Legales del concurso.</p><p>Una vez realizado el sorteo, el Notario levanta un acta notarial donde se deja constancia del proceso y del número ganador.</p>',
				'cats' => ['ganadores', 'concursos'],
				'order' => 12,
			],
			[
				'q' => '¿Dónde puedo ver el sorteo?',
				'a' => '<p>El sorteo se transmite en vivo a través de los canales oficiales de Sorteo Seguro.</p><p>La fecha y hora de la transmisión se informan previamente a los participantes una vez alcanzado el mínimo de participaciones requerido.</p>',
				'cats' => ['ganadores', 'concursos'],
				'order' => 13,
			],
			[
				'q' => '¿Dónde se anuncian los resultados?',
				'a' => '<p>Una vez realizado el sorteo:</p><ul><li>el resultado se anuncia durante la transmisión en vivo</li><li>se publica en el sitio web oficial</li><li>se comunica en nuestros canales oficiales</li><li>se envía un correo electrónico a todos los participantes</li></ul><p>Este correo incluye el acta notarial que certifica al ganador o ganadores del concurso.</p>',
				'cats' => ['ganadores'],
				'order' => 14,
			],
			[
				'q' => '¿Se puede solicitar devolución del dinero?',
				'a' => '<p>Los DigiTickets corresponden a productos digitales entregados de forma inmediata una vez realizada la compra.</p><p>Por esta razón, no aplica derecho a retracto ni devolución, conforme a lo establecido en las Bases Legales de cada concurso.</p>',
				'cats' => ['pagos'],
				'order' => 15,
			],
			[
				'q' => '¿Los premios siempre pertenecen a Sorteo Seguro?',
				'a' => '<p>En algunos concursos, los premios pueden corresponder a bienes pertenecientes a terceros participantes de la promoción, como propietarios de propiedades, vehículos u otros bienes.</p><p>La entrega del premio se realiza conforme a las condiciones establecidas en las Bases Legales del concurso correspondiente.</p>',
				'cats' => ['concursos', 'ganadores'],
				'order' => 16,
			],
			[
				'q' => '¿Cómo se entregan los premios?',
				'a' => '<p>La entrega de los premios se realiza conforme a las condiciones establecidas en las Bases Legales de cada concurso.</p><p>En el caso de premios registrables, como vehículos o propiedades, el proceso incluye las gestiones legales necesarias para su transferencia al ganador, de acuerdo con la normativa vigente.</p><p>Una vez confirmado el ganador, se coordina directamente con éste el proceso de entrega del premio.</p>',
				'cats' => ['ganadores'],
				'order' => 17,
			],
			[
				'q' => '¿Cómo puedo enterarme de nuevos concursos?',
				'a' => '<p>Puedes seguirnos en nuestros canales oficiales y revisar periódicamente el sitio web sorteoseguro.cl, donde publicamos todos los concursos disponibles junto con sus respectivas Bases Legales.</p>',
				'cats' => ['concursos'],
				'order' => 18,
			],
			[
				'q' => '¿Qué respaldo tiene Sorteo Seguro?',
				'a' => '<p>Sorteo Seguro opera bajo un modelo transparente y conforme a las condiciones establecidas en las Bases Legales de cada concurso, las cuales se encuentran firmadas ante Notario Público y reducidas a escritura pública.</p><p>Además, nuestros concursos se realizan:</p><ul><li>mediante transmisión en vivo</li><li>bajo supervisión de Notario Público</li><li>utilizando un sistema informático de selección aleatoria</li><li>dejando constancia del resultado mediante acta notarial</li></ul><p>Toda la información relevante de cada concurso se encuentra disponible en nuestra plataforma antes de participar.</p>',
				'cats' => ['concursos'],
				'order' => 19,
			],
		];
	}

	/** @param array<int, array{q:string,a:string,cats:string[],order:int}> $items */
	public static function replace_all_faqs(array $items): void {
		$old = get_posts([
			'post_type'      => self::CPT,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]);
		foreach ($old as $id) {
			wp_delete_post((int) $id, true);
		}

		foreach ($items as $item) {
			$id = wp_insert_post([
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => $item['q'],
				'post_content' => $item['a'],
				'menu_order'   => $item['order'],
			], true);
			if (!is_wp_error($id) && $id) {
				wp_set_object_terms($id, $item['cats'], self::TAX);
			}
		}
	}

	public static function maybe_seed(): void {
		if (get_option(self::OPTION_SEED) === '2') {
			return;
		}
		if (!post_type_exists(self::CPT) || !taxonomy_exists(self::TAX)) {
			return;
		}

		self::ensure_terms();
		self::replace_all_faqs(self::default_items());
		update_option(self::OPTION_SEED, '2', false);
	}

	public static function is_faq_page(): bool {
		return is_page(self::PAGE_ID);
	}

	public static function template_include(string $template): string {
		if (!self::is_faq_page()) {
			return $template;
		}
		$custom = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-faq/page-faq.php';
		return is_readable($custom) ? $custom : $template;
	}

	public static function disable_elementor_builder($value, $object_id, $meta_key, $single) {
		if ($meta_key !== '_elementor_edit_mode') {
			return $value;
		}
		if (is_admin() && !(defined('REST_REQUEST') && REST_REQUEST)) {
			return $value;
		}
		if ((int) $object_id !== self::PAGE_ID) {
			return $value;
		}
		if (!self::is_faq_page()) {
			return $value;
		}
		return $single ? '' : [''];
	}

	public static function assets(): void {
		if (!self::is_faq_page()) {
			return;
		}
		if (class_exists('SorteoSeguro_Chrome')) {
			SorteoSeguro_Chrome::enqueue_fonts();
		}
		$base = content_url('mu-plugins/sorteoseguro-faq/assets');
		$dir  = WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-faq/assets';
		$ver  = self::VERSION;
		$deps = class_exists('SorteoSeguro_Chrome') && wp_style_is('ss-fonts', 'enqueued') ? ['ss-fonts'] : [];
		if (is_readable($dir . '/faq.css')) {
			wp_enqueue_style('ss-faq', $base . '/faq.css', $deps, $ver);
		}
		if (is_readable($dir . '/faq.js')) {
			wp_enqueue_script('ss-faq', $base . '/faq.js', [], $ver, true);
		}
	}

	/** @return array<int, array{id:int,q:string,a:string,cats:string[]}> */
	public static function get_faqs(): array {
		$query = new WP_Query([
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
			'no_found_rows'  => true,
		]);

		$out = [];
		foreach ($query->posts as $post) {
			$terms = wp_get_post_terms($post->ID, self::TAX, ['fields' => 'slugs']);
			if (is_wp_error($terms)) {
				$terms = [];
			}
			$out[] = [
				'id'   => (int) $post->ID,
				'q'    => get_the_title($post),
				'a'    => apply_filters('the_content', $post->post_content),
				'cats' => array_values($terms),
			];
		}
		wp_reset_postdata();
		return $out;
	}
}

SorteoSeguro_FAQ::init();
