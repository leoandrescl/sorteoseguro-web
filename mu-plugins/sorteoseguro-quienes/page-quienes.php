<?php
/**
 * Plantilla Quiénes somos – página ID 76256
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!have_posts()) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	return;
}
the_post();

$ico = static function (string $name): string {
	return class_exists('SorteoSeguro_Quienes') ? SorteoSeguro_Quienes::icon($name) : '';
};
$img = static function (string $file, string $alt = '', bool $eager = false): string {
	return class_exists('SorteoSeguro_Quienes') ? SorteoSeguro_Quienes::media_img($file, $alt, $eager) : '';
};
$bases_url = class_exists('SorteoSeguro_Quienes')
	? SorteoSeguro_Quienes::bases_url()
	: home_url('/bases-legales/');

$steps = [
	[
		'n'     => '01',
		'mod'   => 'purple',
		'icon'  => 'pencil',
		'title' => 'Creamos',
		'text'  => 'Nuestro equipo diseña ilustraciones digitales y stickers exclusivos.',
	],
	[
		'n'     => '02',
		'mod'   => 'green',
		'icon'  => 'ticket',
		'title' => 'Adquieres',
		'text'  => 'El usuario compra una ilustración digital identificada mediante un DigiTicket.',
	],
	[
		'n'     => '03',
		'mod'   => 'blue',
		'icon'  => 'user',
		'title' => 'Participas',
		'text'  => 'La adquisición otorga excepcionalmente el derecho a participar en el concurso promocional asociado.',
	],
	[
		'n'     => '04',
		'mod'   => 'orange',
		'icon'  => 'doc',
		'title' => 'Bases legales',
		'text'  => 'Cada concurso cuenta con sus propias bases, condiciones y mínimo de participaciones.',
	],
	[
		'n'     => '05',
		'mod'   => 'red',
		'icon'  => 'trophy',
		'title' => 'Sorteo y resultado',
		'text'  => 'Al cumplirse las condiciones establecidas, se realiza el sorteo conforme a sus bases legales.',
	],
];

$transparency_cards = [
	[
		'file' => 'transparencia-1.webp',
		'icon' => 'doc',
		'mod'  => 'purple',
		'title'=> 'Bases legales propias',
		'text' => 'Cada concurso posee bases legales específicas, disponibles públicamente antes de participar.',
	],
	[
		'file' => 'transparencia-2.webp',
		'icon' => 'shield-check',
		'mod'  => 'green',
		'title'=> 'Protocolización notarial',
		'text' => 'Las bases correspondientes son protocolizadas ante Notario Público.',
	],
	[
		'file' => 'transparencia-3.webp',
		'icon' => 'mail',
		'mod'  => 'blue',
		'title'=> 'Resultados públicos',
		'text' => 'El acta notarial del sorteo se envía por email a todos los participantes y se publica en el sitio web y redes sociales.',
	],
	[
		'file' => 'transparencia-4.webp',
		'icon' => 'users',
		'mod'  => 'orange',
		'title'=> 'Nómina de participantes',
		'text' => 'La nómina de participantes se publica en nuestro sitio web una vez fijada la fecha y hora del evento del sorteo.',
	],
];

$tech_items = [
	[
		'icon'  => 'ticket',
		'mod'   => 'purple',
		'title' => 'DigiTickets únicos',
		'text'  => 'Cada participación está identificada con un código único e transferible.',
	],
	[
		'icon'  => 'user',
		'mod'   => 'green',
		'title' => 'Cuenta de usuario',
		'text'  => 'Accede a tu historial de compras, DigiTickets y concursos activos.',
	],
	[
		'icon'  => 'lock',
		'mod'   => 'rose',
		'title' => 'Pagos seguros',
		'text'  => 'Procesamiento a través de pasarelas de pago oficiales y certificadas.',
	],
	[
		'icon'  => 'plane',
		'mod'   => 'blue',
		'title' => 'Comunicación digital',
		'text'  => 'Recibes confirmaciones, documentación e información por email.',
	],
];

$why_cards = [
	[
		'icon'  => 'target',
		'title' => 'Concursos justos',
		'text'  => 'Realizados conforme a las bases legales de cada promoción.',
	],
	[
		'icon'  => 'checklist',
		'title' => 'Reglas claras',
		'text'  => 'Bases legales accesibles antes de comprar o participar.',
	],
	[
		'icon'  => 'search',
		'title' => 'Sin letra chica',
		'text'  => 'Información clara y directa sobre premios, condiciones y plazos.',
	],
	[
		'icon'  => 'shield',
		'title' => 'Sorteos supervisados',
		'text'  => 'Con presencia de Notario Público y bajo estrictos procedimientos.',
	],
	[
		'icon'  => 'bulb',
		'title' => 'Innovación constante',
		'text'  => 'Nuevas ideas, premios y experiencias para nuestros usuarios.',
	],
	[
		'icon'  => 'heart',
		'title' => 'Compromiso real',
		'text'  => 'Escuchamos a nuestra comunidad y mejoramos cada día.',
	],
];

$band_items = [
	['icon' => 'shield-check', 'label' => 'Procesos transparentes'],
	['icon' => 'doc', 'label' => 'Información pública'],
	['icon' => 'heart', 'label' => 'Tu confianza primero'],
];

get_header();
?>
<main class="ss-qs">
	<section class="ss-qs-hero" aria-label="Quiénes somos">
		<div class="ss-qs__shell ss-qs-hero__inner">
			<div class="ss-qs-hero__copy">
				<p class="ss-qs-kicker">¡Quiénes somos!</p>
				<h1 class="ss-qs-hero__title">
					Somos una <em>plataforma tecnológica</em> que transforma sueños en <strong>oportunidades.</strong>
				</h1>
				<p class="ss-qs-hero__lead">Vendemos ilustraciones digitales, las cuales otorgan de forma excepcional el derecho a participar en concursos promocionales, cada uno con sus propias bases legales. Contamos con un equipo de diseñadores que crean nuestras ilustraciones y stickers exclusivos.</p>
				<div class="ss-qs-hero__facts">
					<article class="ss-qs-fact">
						<span class="ss-qs-fact__ico" aria-hidden="true"><?php echo $ico('shield-ticket'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<h2>Seguridad y transparencia</h2>
							<p>Procesos certificados y supervisados por Notario Público.</p>
						</div>
					</article>
					<article class="ss-qs-fact">
						<span class="ss-qs-fact__ico" aria-hidden="true"><?php echo $ico('medal'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<h2>Confianza y respaldo</h2>
							<p>Cumplimos con la normativa legal vigente en Chile.</p>
						</div>
					</article>
				</div>
			</div>
			<figure class="ss-qs-media ss-qs-media--hero">
				<?php echo $img('hero-banner.webp', '', true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</figure>
		</div>
	</section>

	<section class="ss-qs-section" id="ss-qs-modelo" aria-labelledby="ss-qs-modelo-title">
		<div class="ss-qs__shell">
			<header class="ss-qs-head">
				<p class="ss-qs-kicker">¿Cómo funciona nuestro modelo?</p>
				<h2 id="ss-qs-modelo-title">Así funciona nuestro modelo</h2>
				<p class="ss-qs-head__sub">Simple, transparente y 100% digital.</p>
			</header>
			<ol class="ss-qs-steps">
				<?php foreach ($steps as $step) : ?>
					<li class="ss-qs-step ss-qs-step--<?php echo esc_attr($step['mod']); ?>">
						<span class="ss-qs-step__n"><?php echo esc_html($step['n']); ?></span>
						<span class="ss-qs-step__ico" aria-hidden="true"><?php echo $ico($step['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h3><?php echo esc_html($step['title']); ?></h3>
						<p><?php echo esc_html($step['text']); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>

	<section class="ss-qs-section" aria-labelledby="ss-qs-trans-title">
		<div class="ss-qs__shell">
			<div class="ss-qs-panel">
				<div class="ss-qs-split ss-qs-split--trans">
					<div class="ss-qs-split__copy">
						<p class="ss-qs-kicker">Transparencia que puedes comprobar</p>
						<h2 id="ss-qs-trans-title">La confianza se construye con información pública.</h2>
						<p>Ponemos a disposición de todos nuestros participantes la documentación que respalda cada proceso, antes y después de cada concurso.</p>
						<a class="ss-qs-btn ss-qs-btn--primary" href="<?php echo esc_url($bases_url); ?>">
							Ver bases legales de concursos
						</a>
					</div>
					<ul class="ss-qs-tgrid">
						<?php foreach ($transparency_cards as $card) : ?>
							<li class="ss-qs-tcard">
								<figure class="ss-qs-media ss-qs-media--card">
									<?php echo $img($card['file'], ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</figure>
								<div class="ss-qs-tcard__body">
									<span class="ss-qs-miniico ss-qs-miniico--<?php echo esc_attr($card['mod']); ?>" aria-hidden="true"><?php echo $ico($card['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<h3><?php echo esc_html($card['title']); ?></h3>
									<p><?php echo esc_html($card['text']); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="ss-qs-note">
					<span class="ss-qs-note__ico" aria-hidden="true"><?php echo $ico('shield-check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<p>Todo nuestro proceso es supervisado por Notario Público, garantizando transparencia, legalidad y confianza.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="ss-qs-section" aria-labelledby="ss-qs-tech-title">
		<div class="ss-qs__shell">
			<div class="ss-qs-split ss-qs-split--tech">
				<div class="ss-qs-split__copy">
					<p class="ss-qs-kicker">Tecnología que garantiza tu experiencia</p>
					<h2 id="ss-qs-tech-title">Una plataforma 100% digital, segura y confiable.</h2>
				</div>
				<ul class="ss-qs-feats">
					<?php foreach ($tech_items as $item) : ?>
						<li class="ss-qs-feat">
							<span class="ss-qs-miniico ss-qs-miniico--<?php echo esc_attr($item['mod']); ?>" aria-hidden="true"><?php echo $ico($item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<div>
								<h3><?php echo esc_html($item['title']); ?></h3>
								<p><?php echo esc_html($item['text']); ?></p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<figure class="ss-qs-media ss-qs-media--mock">
					<?php echo $img('tecnologia-mockup.webp', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</figure>
			</div>
		</div>
	</section>

	<section class="ss-qs-section" aria-labelledby="ss-qs-why-title">
		<div class="ss-qs__shell">
			<div class="ss-qs-panel ss-qs-panel--why">
				<div class="ss-qs-split ss-qs-split--why">
					<div class="ss-qs-split__copy">
						<p class="ss-qs-kicker">¿Por qué confiar en Sorteo Seguro?</p>
						<h2 id="ss-qs-why-title">Nuestro compromiso con la transparencia y las buenas prácticas.</h2>
						<p>Trabajamos cada día para ofrecer concursos justos, innovadores y con total claridad en cada etapa. Tu confianza es nuestra mayor motivación.</p>
					</div>
					<ul class="ss-qs-whygrid">
						<?php foreach ($why_cards as $card) : ?>
							<li class="ss-qs-whycard">
								<span class="ss-qs-miniico ss-qs-miniico--purple" aria-hidden="true"><?php echo $ico($card['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<h3><?php echo esc_html($card['title']); ?></h3>
								<p><?php echo esc_html($card['text']); ?></p>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</section>

	<section class="ss-qs-section" id="ss-qs-diseno" aria-labelledby="ss-qs-diseno-title">
		<div class="ss-qs__shell">
			<div class="ss-qs-split ss-qs-split--design">
				<div class="ss-qs-split__copy">
					<p class="ss-qs-kicker">Creamos experiencias únicas</p>
					<h2 id="ss-qs-diseno-title">Diseñamos nuestras ilustraciones y stickers exclusivos.</h2>
					<p>Contamos con un equipo de diseñadores profesionales que crea contenido digital original, pensado para que cada concurso sea especial y tenga su propia identidad.</p>
					<a class="ss-qs-btn ss-qs-btn--outline" href="#ss-qs-modelo">
						Conoce más sobre nuestro proceso creativo <span aria-hidden="true">›</span>
					</a>
				</div>
				<figure class="ss-qs-media ss-qs-media--design">
					<?php echo $img('experiencias-diseno.webp', ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</figure>
			</div>
		</div>
	</section>

	<section class="ss-qs-band" aria-label="Tu tranquilidad es nuestra responsabilidad">
		<div class="ss-qs__shell">
			<div class="ss-qs-band__inner">
				<div class="ss-qs-band__copy">
					<span class="ss-qs-band__shield" aria-hidden="true"><?php echo $ico('shield-check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div>
						<h2>Tu tranquilidad es nuestra responsabilidad</h2>
						<p>En Sorteo Seguro trabajamos con total transparencia para que puedas participar con confianza y disfrutar la emoción de cada sorteo.</p>
					</div>
				</div>
				<ul class="ss-qs-band__items">
					<?php foreach ($band_items as $item) : ?>
						<li>
							<span aria-hidden="true"><?php echo $ico($item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php echo esc_html($item['label']); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
