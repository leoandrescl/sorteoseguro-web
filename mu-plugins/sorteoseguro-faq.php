<?php
/**
 * Plugin Name: Sorteo Seguro – FAQ
 * Description: CPT de preguntas frecuentes + plantilla PHP para la página FAQ (sin Elementor).
 * Author: Sorteo Seguro
 * Version: 1.2.0
 */
if (!defined('ABSPATH')) {
	exit;
}

final class SorteoSeguro_FAQ {

	const VERSION     = '1.2.0';
	const CPT         = 'ss_faq';
	const TAX         = 'ss_faq_cat';
	const PAGE_ID     = 15;
	const OPTION_SEED = 'ss_faq_seeded_v3';

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
				'a' => '<p>Sí. Cada concurso publicado en nuestra plataforma cuenta con sus respectivas Bases Legales, formalizadas ante Notario Público, conforme a la normativa vigente en la República de Chile.</p><p>Estas Bases establecen claramente las condiciones de cada concurso, incluyendo los premios, las reglas y requisitos de participación, el mínimo de DigiTickets requerido y el mecanismo mediante el cual se realizará el concurso y se determinarán sus ganadores.</p><p>Además, las Bases Legales correspondientes a cada concurso se encuentran disponibles públicamente en nuestro sitio web para que puedan ser consultadas por cualquier persona antes de participar.</p>',
				'cats' => ['concursos'],
				'order' => 1,
			],
			[
				'q' => '¿Qué es un DigiTicket?',
				'a' => '<p>El DigiTicket es una ilustración digital numerada que adquieres a través de nuestra plataforma.</p><p>Cada DigiTicket incluye, adicionalmente, un número único de participación, el cual te otorga el derecho a participar en el concurso promocional correspondiente y forma parte del proceso de selección aleatoria mediante el cual se determinará al ganador.</p><p>Por lo tanto, cada DigiTicket adquirido contiene un número único y representa una oportunidad independiente de resultar ganador en el concurso al cual se encuentra asociado.</p>',
				'cats' => ['digitickets'],
				'order' => 2,
			],
			[
				'q' => '¿Cómo sé que mi participación es válida?',
				'a' => '<p>Una vez finalizado el proceso de compra y completado el registro correspondiente, recibirás tus DigiTickets directamente en tu correo electrónico.</p><p>Además, siempre podrás acceder a tus DigiTickets desde tu perfil de usuario dentro de la plataforma.</p><p>Cada DigiTicket contiene un número único de participación, el cual acredita tu participación válida en el concurso correspondiente.</p>',
				'cats' => ['participacion', 'digitickets'],
				'order' => 3,
			],
			[
				'q' => '¿Puedo elegir el número de mi DigiTicket?',
				'a' => '<p>No. Los números de participación no pueden ser elegidos manualmente.</p><p>Al momento de realizar tu compra, nuestro sistema asigna de forma automática y aleatoria los números de participación correspondientes a tus DigiTickets, seleccionándolos entre aquellos que se encuentren disponibles para ese concurso.</p><p>De esta manera, todos los participantes utilizan el mismo mecanismo de asignación, sin posibilidad de escoger o reservar números específicos.</p>',
				'cats' => ['digitickets', 'participacion'],
				'order' => 4,
			],
			[
				'q' => '¿Cuántas veces puedo participar?',
				'a' => '<p>Puedes participar tantas veces como desees.</p><p>Cada DigiTicket adquirido contiene un número único de participación y representa una nueva oportunidad de participar en el concurso. Por lo tanto, mientras más DigiTickets tengas, mayores serán tus probabilidades de resultar ganador.</p>',
				'cats' => ['participacion'],
				'order' => 5,
			],
			[
				'q' => '¿Qué ocurre si no se venden todos los DigiTickets?',
				'a' => '<p>No es necesario que se venda la totalidad de los DigiTickets disponibles para realizar el concurso.</p><p>Cada concurso establece en sus Bases Legales un mínimo de DigiTickets que debe alcanzarse para poder llevar a cabo el concurso correspondiente.</p><p>Si llegada la fecha estimada del concurso aún no se ha alcanzado dicho mínimo, la fecha se ajustará automáticamente hasta que se cumpla el mínimo de DigiTickets establecido en las Bases Legales.</p><p>Una vez alcanzado el mínimo requerido, se informará oportunamente a los participantes la fecha y hora en que se realizará el concurso, continuando el proceso conforme a lo establecido en sus respectivas Bases Legales.</p>',
				'cats' => ['digitickets', 'concursos'],
				'order' => 6,
			],
			[
				'q' => '¿Por qué se prorrogan algunos concursos?',
				'a' => '<p>Un concurso puede prorrogarse cuando, llegada la fecha estimada inicialmente, aún no se ha alcanzado el mínimo de DigiTickets establecido en sus Bases Legales para poder realizarlo.</p><p>En estos casos, la fecha del concurso se ajusta automáticamente y el concurso continúa vigente hasta alcanzar el mínimo requerido, conforme a las condiciones establecidas en sus respectivas Bases Legales.</p><p>Esta prórroga no afecta los DigiTickets ya adquiridos ni sus números de participación, los cuales continúan plenamente vigentes para el concurso correspondiente.</p><p>Las fechas y sus actualizaciones pueden ser consultadas directamente en nuestra plataforma y a través de nuestros canales oficiales. Una vez alcanzado el mínimo requerido, informaremos oportunamente la fecha y hora de realización del concurso.</p>',
				'cats' => ['concursos'],
				'order' => 7,
			],
			[
				'q' => '¿Qué pasa si el concurso tarda más de lo esperado?',
				'a' => '<p>Algunos concursos pueden requerir más tiempo para alcanzar el mínimo de DigiTickets establecido en sus Bases Legales.</p><p>Si esto ocurre, el concurso continúa vigente y su fecha se ajusta hasta alcanzar el mínimo requerido, conforme a las condiciones establecidas en sus respectivas Bases Legales.</p><p>Durante este período, todos los DigiTickets adquiridos y sus respectivos números de participación continúan plenamente vigentes, por lo que no pierdes tu participación aunque la fecha del concurso sea actualizada.</p><p>Una vez alcanzado el mínimo requerido, informaremos oportunamente a los participantes la fecha y hora en que se realizará el concurso.</p><p>Nuestro objetivo es realizar cada concurso con transparencia y respetando las condiciones establecidas e informadas en sus Bases Legales.</p>',
				'cats' => ['concursos'],
				'order' => 8,
			],
			[
				'q' => '¿Cuándo se realiza el concurso?',
				'a' => '<p>Cada concurso cuenta con condiciones particulares y un mínimo de DigiTickets propio, establecido e informado en sus respectivas Bases Legales.</p><p>Una vez alcanzado el mínimo de DigiTickets correspondiente a ese concurso, se informará oficialmente la fecha y hora de su realización.</p><p>Esta información será comunicada oportunamente a los participantes mediante correo electrónico y a través de nuestros canales oficiales.</p><p>Las condiciones y el mínimo requerido para cada concurso pueden ser consultados en todo momento en sus respectivas Bases Legales publicadas en nuestra plataforma.</p>',
				'cats' => ['concursos'],
				'order' => 9,
			],
			[
				'q' => '¿Qué sucede una vez alcanzado el mínimo de DigiTickets?',
				'a' => '<p>Una vez alcanzado el mínimo de DigiTickets establecido en las Bases Legales del concurso, se inicia el proceso para coordinar y fijar oficialmente la fecha y hora de realización del concurso, conforme a las condiciones establecidas en dichas Bases.</p><p>La fecha y hora serán comunicadas oportunamente a todos los participantes mediante correo electrónico y a través de nuestros canales oficiales.</p><p>El concurso se realizará de acuerdo con el mecanismo establecido en sus respectivas Bases Legales, considerando todos los DigiTickets válidamente adquiridos y sus correspondientes números de participación.</p>',
				'cats' => ['concursos', 'participacion'],
				'order' => 10,
			],
			[
				'q' => '¿Cómo se elige al ganador?',
				'a' => '<p>El ganador se selecciona mediante un sistema informático de selección aleatoria, que elige un número entre todos los números únicos de participación asociados a los DigiTickets válidamente adquiridos para el concurso.</p><p>El proceso se realiza mediante transmisión en vivo, con la presencia de un Notario Público, quien da fe de la realización del concurso y de su resultado.</p><p>Una vez determinado el ganador, se deja constancia del resultado mediante el acta notarial correspondiente.</p><p>De esta manera, el proceso queda respaldado y documentado, aportando transparencia y seguridad a la determinación del ganador.</p>',
				'cats' => ['ganadores', 'concursos'],
				'order' => 11,
			],
			[
				'q' => '¿Quién supervisa el concurso?',
				'a' => '<p>El concurso se realiza con la presencia de un Notario Público, quien actúa como Ministro de Fe, dando fe de que el procedimiento se lleve a cabo conforme a las condiciones establecidas en las Bases Legales del concurso.</p><p>Una vez realizado el concurso y determinado el ganador, se levanta el acta notarial correspondiente, dejando constancia de la realización del proceso y del número de participación ganador.</p>',
				'cats' => ['ganadores', 'concursos'],
				'order' => 12,
			],
			[
				'q' => '¿Dónde puedo ver el concurso?',
				'a' => '<p>El concurso será transmitido en vivo a través de los canales oficiales de Sorteo Seguro.</p><p>Una vez alcanzado el mínimo de DigiTickets establecido en las Bases Legales correspondientes, se informará oportunamente a los participantes la fecha, hora y canal oficial a través del cual podrán seguir la transmisión en vivo.</p>',
				'cats' => ['ganadores', 'concursos'],
				'order' => 13,
			],
			[
				'q' => '¿Dónde se anuncian los resultados?',
				'a' => '<p>Una vez realizado el concurso:</p><ul><li>El resultado se anuncia durante la transmisión en vivo.</li><li>Se publica en el sitio web oficial de Sorteo Seguro.</li><li>Se comunica a través de nuestros canales oficiales.</li><li>Se envía un correo electrónico a los participantes.</li></ul><p>Además, se pondrá a disposición el acta notarial correspondiente, en la cual se deja constancia oficial de la realización del concurso y de su resultado.</p>',
				'cats' => ['ganadores'],
				'order' => 14,
			],
			[
				'q' => '¿Se puede solicitar devolución del dinero?',
				'a' => '<p>No. Una vez confirmada la compra y entregado el DigiTicket, no procede la devolución del dinero, conforme a las condiciones establecidas en las Bases Legales y Términos y Condiciones aplicables.</p><p>El DigiTicket corresponde a un producto digital: una ilustración digital numerada, que es entregada electrónicamente al comprador una vez completado el proceso de compra y que también queda disponible en su perfil de usuario.</p><p>Cada DigiTicket incorpora, adicionalmente, un número único de participación, que otorga el derecho a participar en el concurso promocional correspondiente, conforme a sus respectivas Bases Legales.</p><p>Al tratarse de un producto digital que es entregado y puesto a disposición del comprador una vez confirmada la compra, no se realizan devoluciones por desistimiento posterior a su adquisición.</p><p>Sin perjuicio de lo anterior, si existiera un cobro duplicado, error de pago, problema técnico u otra situación excepcional, el cliente puede comunicarse con nuestro equipo de soporte para que el caso sea revisado.</p>',
				'cats' => ['pagos'],
				'order' => 15,
			],
			[
				'q' => '¿Los premios siempre pertenecen a Sorteo Seguro?',
				'a' => '<p>No necesariamente. Dependiendo del concurso, el premio puede ser propiedad de Sorteo Seguro o pertenecer a un tercero que participa como responsable o propietario del bien promocionado.</p><p>Esta información se encuentra claramente detallada en las Bases Legales de cada concurso, donde se individualizan las partes responsables, los premios y las condiciones correspondientes.</p><p>En todos los casos, Sorteo Seguro administra el concurso y vela por el fiel cumplimiento de las condiciones establecidas en sus Bases Legales, hasta la determinación del ganador y posterior proceso de entrega del premio.</p>',
				'cats' => ['concursos', 'ganadores'],
				'order' => 16,
			],
			[
				'q' => '¿Cómo se entregan los premios?',
				'a' => '<p>La entrega de los premios se realiza conforme a las condiciones y plazos establecidos en las Bases Legales de cada concurso, los cuales pueden variar dependiendo del tipo de premio.</p><p>Una vez confirmado oficialmente el ganador, Sorteo Seguro se pondrá en contacto directamente con él para coordinar el proceso de entrega.</p><p>En el caso de premios registrables, como vehículos o propiedades, se realizarán las gestiones legales y administrativas necesarias para efectuar su transferencia al ganador, de acuerdo con las condiciones establecidas en las Bases Legales y la normativa vigente.</p>',
				'cats' => ['ganadores'],
				'order' => 17,
			],
			[
				'q' => '¿Cómo puedo enterarme de nuevos concursos?',
				'a' => '<p>Puedes seguirnos en nuestros canales oficiales, revisar periódicamente sorteoseguro.cl y mantenerte atento a nuestras comunicaciones por correo electrónico.</p><p>En nuestro sitio web encontrarás todos los concursos activos, junto con la información correspondiente y sus respectivas Bases Legales.</p>',
				'cats' => ['concursos'],
				'order' => 18,
			],
			[
				'q' => '¿Qué respaldo tiene Sorteo Seguro?',
				'a' => '<p>Sorteo Seguro opera bajo un modelo basado en la transparencia, información previa y cumplimiento de las condiciones establecidas en las Bases Legales de cada concurso, las cuales son formalizadas ante Notario Público.</p><p>Antes de participar, cada usuario puede consultar las Bases Legales correspondientes al concurso, donde se detallan sus condiciones, premios, mínimo de DigiTickets requerido, responsables y mecanismo mediante el cual se determinará al ganador.</p><p>Además, nuestros concursos se realizan mediante transmisión en vivo, con la presencia de un Notario Público y utilizando un sistema informático de selección aleatoria. Finalizado el proceso, se deja constancia de su realización y resultado mediante el acta notarial correspondiente.</p><p>Nuestro compromiso es que cada participante pueda conocer cómo funciona el concurso y cuáles son sus condiciones antes de adquirir sus DigiTickets, manteniendo la información relevante disponible públicamente en nuestra plataforma.</p>',
				'cats' => ['concursos'],
				'order' => 19,
			],
			[
				'q' => '¿La fecha publicada de un concurso es definitiva?',
				'a' => '<p>No. La fecha publicada corresponde a una fecha estimada de realización, ya que cada concurso debe alcanzar previamente el mínimo de DigiTickets establecido en sus respectivas Bases Legales.</p><p>Si llegada la fecha estimada aún no se ha alcanzado dicho mínimo, la fecha se ajustará automáticamente y el concurso continuará vigente hasta cumplir con esta condición.</p><p>Una vez alcanzado el mínimo requerido, Sorteo Seguro informará oportunamente a todos los participantes la fecha y hora de realización del concurso, a través de correo electrónico y nuestros canales oficiales.</p><p>Las condiciones, requisitos y mínimo de DigiTickets de cada concurso pueden ser consultados previamente en sus respectivas Bases Legales disponibles en nuestra plataforma.</p>',
				'cats' => ['concursos'],
				'order' => 20,
			],
			[
				'q' => '¿Qué pasa con mis DigiTickets si cambia la fecha del concurso?',
				'a' => '<p>Tus DigiTickets continúan plenamente vigentes aunque la fecha estimada del concurso sea modificada.</p><p>Cada DigiTicket conserva su número único de participación y continúa participando automáticamente en el concurso correspondiente, por lo que no necesitas realizar ninguna gestión adicional ni adquirir nuevos DigiTickets.</p><p>El cambio de fecha no afecta ni invalida las participaciones ya adquiridas, las cuales se mantienen vigentes hasta la realización del concurso, conforme a lo establecido en sus respectivas Bases Legales.</p>',
				'cats' => ['concursos', 'digitickets'],
				'order' => 21,
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
		if (get_option(self::OPTION_SEED) === '3') {
			return;
		}
		if (!post_type_exists(self::CPT) || !taxonomy_exists(self::TAX)) {
			return;
		}

		self::ensure_terms();
		self::replace_all_faqs(self::default_items());
		update_option(self::OPTION_SEED, '3', false);
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
