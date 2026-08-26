<?php
/**
 * Contenido: Política de funcionamiento de los concursos (página ID 56545).
 */
if (!defined('ABSPATH')) {
	exit;
}

$site = class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::site_link() : 'sorteoseguro.cl';

return [
	'id'            => 56545,
	'slug'          => 'politica-funcionamiento-concurso',
	'doc_title'     => 'Política de funcionamiento de los concursos',
	'eyebrow'       => 'Información legal',
	'title'         => 'Política de<br>funcionamiento<br>de los concursos',
	'lead'          => 'Conoce cómo funcionan nuestros concursos, los requisitos de participación, sorteos y entrega de premios.',
	'updated'       => '9 de Diciembre del año 2025',
	'hero'          => '',
	'hero_alt'      => '',
	'hero_badge'    => '',
	'layout'        => 'rows',
	'confirm_icon'  => 'shield-check',
	'confirm_mod'   => 'solid',
	'confirm_title' => 'Comprometidos con la transparencia',
	'confirm'       => 'En Sorteo Seguro trabajamos para entregar concursos justos, claros y confiables, resguardando siempre los derechos de nuestros participantes.',
	'items'         => [
		[
			'icon'  => 'monitor',
			'title' => 'Sobre la plataforma Sorteo Seguro',
			'html'  => '<p>Sorteo Seguro es una plataforma digital que organiza <strong>concursos promocionales</strong>, en los cuales los participantes pueden acceder a premios como propiedades, vehículos u otros bienes mediante la adquisición de ilustraciones digitales denominadas <strong>DigiTickets</strong>.</p><p>Cada concurso publicado en la plataforma cuenta con <strong>Bases Legales propias</strong>, que regulan las condiciones específicas de participación.</p>',
		],
		[
			'icon'  => 'ticket',
			'title' => '¿Qué es un DigiTicket?',
			'html'  => '<p>El DigiTicket corresponde a una ilustración digital numerada que el participante adquiere para participar en un concurso promocional.</p><p>Cada DigiTicket:</p><ul><li>Contiene un <strong>número único de participación</strong></li><li>Es entregado en formato digital</li><li>Puede ser elegido por el usuario (si está disponible) o asignado automáticamente por el sistema</li><li>Este número participa en el sorteo correspondiente al concurso en el que fue adquirido.</li></ul>',
		],
		[
			'icon'  => 'megaphone',
			'title' => 'Sobre los concursos publicados',
			'html'  => '<p>En la plataforma pueden existir <strong>varios concursos activos simultáneamente</strong>, cada uno con sus propias condiciones, premios y tiempos de ejecución. Por esta razón, los plazos de cada concurso pueden variar dependiendo de las condiciones establecidas en sus Bases Legales.</p>',
		],
		[
			'icon'  => 'users',
			'title' => 'Mínimo de participaciones',
			'html'  => '<p>Cada concurso establece en sus Bases Legales un <strong>mínimo de participaciones requeridas</strong> para realizar el sorteo. Este mínimo tiene como finalidad garantizar la viabilidad del concurso y permitir la correcta ejecución del premio ofrecido. El sorteo se realizará únicamente cuando se alcance dicho mínimo de participaciones.</p>',
		],
		[
			'icon'  => 'calendar',
			'title' => 'Prórroga de los concursos',
			'html'  => '<p>En caso de no alcanzarse el mínimo de participaciones dentro del plazo inicial informado, el concurso podrá <strong>prorrogar su vigencia</strong>, conforme a lo establecido en sus Bases Legales. Esto significa que el concurso <strong>no se cancela</strong>, sino que continúa vigente hasta cumplir la condición mínima requerida.</p>',
		],
		[
			'icon'  => 'calendar',
			'title' => 'Fecha del sorteo',
			'html'  => '<p>La fecha que aparece inicialmente publicada corresponde a una <strong>fecha referencial</strong>, sujeta al cumplimiento del mínimo de participaciones requerido. Una vez alcanzada dicha condición:</p><ul><li>Se informa oficialmente la fecha definitiva del sorteo</li><li>Se comunica a los participantes mediante correo electrónico</li><li>El sorteo se realiza dentro del plazo establecido en las Bases Legales</li></ul>',
		],
		[
			'icon'  => 'shuffle',
			'title' => 'Realización del sorteo',
			'html'  => '<p>El sorteo se realiza mediante un <strong>sistema informático de selección aleatoria</strong>. El proceso se realiza:</p><ul><li>Mediante transmisión en vivo</li><li>Bajo supervisión de Notario Público</li><li>Dejando constancia mediante acta notarial</li></ul>',
		],
		[
			'icon'  => 'doc',
			'title' => 'Publicación de resultados',
			'html'  => '<p>Una vez realizado el sorteo:</p><ul><li>El resultado se anuncia durante la transmisión en vivo</li><li>Se publica en el sitio web oficial</li><li>Se envía un correo electrónico a los participantes con el acta notarial</li></ul>',
		],
		[
			'icon'  => 'gift',
			'title' => 'Entrega del premio',
			'html'  => '<p>La entrega del premio se realiza conforme a las condiciones establecidas en las Bases Legales. En bienes registrables, el proceso incluye las formalidades legales para su transferencia al ganador.</p>',
		],
		[
			'icon'  => 'users',
			'title' => 'Sobre los premios',
			'html'  => '<p>En algunos concursos, los premios pueden corresponder a bienes pertenecientes a terceros participantes de la promoción, tales como propietarios particulares o socios comerciales.</p>',
		],
		[
			'icon'  => 'shield-check',
			'title' => 'Transparencia del proceso',
			'html'  => '<p>Sorteo Seguro tiene como objetivo realizar los concursos bajo principios de transparencia, claridad en las reglas y supervisión notarial. Toda la información está disponible antes de participar.</p>',
		],
		[
			'icon'  => 'users',
			'title' => 'Participación responsable',
			'html'  => '<p>Se recomienda a los participantes revisar cuidadosamente las Bases Legales, las condiciones de participación y los plazos antes de adquirir un DigiTicket.</p>',
		],
		[
			'icon'  => 'info',
			'title' => 'Canales de información',
			'html'  => '<p>Toda la información oficial se comunica a través del sitio web ' . $site . ', los canales oficiales y correo electrónico.</p>',
		],
		[
			'icon'  => 'mail',
			'title' => 'Contacto',
			'html'  => '<p>Para consultas relacionadas con los concursos o el funcionamiento de la plataforma, los usuarios pueden comunicarse a través de los canales de contacto disponibles en el sitio web.</p>',
		],
	],
];
