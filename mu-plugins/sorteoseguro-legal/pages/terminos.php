<?php
/**
 * Contenido: Términos y Condiciones (página ID 56521).
 */
if (!defined('ABSPATH')) {
	exit;
}

$site = class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::site_link() : 'sorteoseguro.cl';

return [
	'id'       => 56521,
	'slug'     => 'terminos-condiciones',
	'doc_title'=> 'Términos y Condiciones',
	'eyebrow'  => 'Información legal',
	'title'    => 'Términos y Condiciones<br>de uso de la plataforma',
	'lead'     => 'En Sorteo Seguro promovemos la transparencia y la confianza. Conoce las reglas y condiciones que rigen el uso de nuestra plataforma y la participación en nuestros sorteos.',
	'updated'  => '12 de Marzo del año 2026',
	'hero'     => '',
	'hero_alt' => '',
	'split'    => 8,
	'confirm'  => 'Al utilizar nuestro sitio y participar en los concursos, confirmas que has leído, comprendido y aceptado estos Términos y Condiciones y las Bases Legales correspondientes.',
	'items'    => [
		[
			'icon'  => 'user',
			'title' => 'Identificación del prestador',
			'html'  => '<p>La plataforma digital ' . $site . ' es operada por Grupo WH SpA, sociedad constituida conforme a las leyes de la República de Chile.</p><p>Razón Social: Grupo WH SpA<br>RUT: 77.887.705-8</p><p>En adelante denominada “Sorteo Seguro”, “la Plataforma” o “la Empresa”.</p>',
		],
		[
			'icon'  => 'shield-check',
			'title' => 'Aceptación de los términos',
			'html'  => '<p>El acceso, navegación y uso del sitio web ' . $site . ' implica la aceptación plena de los presentes Términos y Condiciones, así como de las Bases Legales específicas de cada concurso.</p>',
		],
		[
			'icon'  => 'monitor',
			'title' => 'Naturaleza de la plataforma',
			'html'  => '<p>Sorteo Seguro opera como una plataforma digital de organización y difusión de concursos promocionales, en los cuales los usuarios pueden participar mediante la adquisición de ilustraciones digitales denominadas DigiTicket.</p><p>En determinados concursos, los premios ofrecidos pueden corresponder a bienes pertenecientes a terceros, tales como propietarios particulares, empresas o socios comerciales que participan en la promoción.</p>',
		],
		[
			'icon'  => 'ticket',
			'title' => 'Definición de DigiTicket',
			'html'  => '<p>El DigiTicket corresponde a una ilustración digital numerada que otorga al usuario un número único de participación en el concurso promocional correspondiente. La adquisición del DigiTicket otorga el derecho a participar en el sorteo conforme a las Bases Legales del concurso respectivo.</p>',
		],
		[
			'icon'  => 'gavel',
			'title' => 'Bases legales de los concursos',
			'html'  => '<p>Cada concurso publicado en la plataforma cuenta con sus propias Bases Legales, que regulan las condiciones específicas de participación, incluyendo:</p><ul><li>Premio ofrecido</li><li>Requisitos de participación</li><li>Mínimo requerido de participaciones</li><li>Mecanismo del sorteo</li><li>Posibles prórrogas</li><li>Condiciones de entrega del premio</li></ul><p>Las Bases Legales forman parte integrante de estos Términos.</p>',
		],
		[
			'icon'  => 'calendar',
			'title' => 'Condición de realización del sorteo',
			'html'  => '<p>Los sorteos se realizarán únicamente una vez alcanzado el mínimo de participaciones establecido en las Bases Legales del concurso. En caso de no alcanzarse dicho mínimo dentro del plazo inicialmente informado, el organizador podrá prorrogar el concurso conforme a lo establecido en las Bases.</p>',
		],
		[
			'icon'  => 'shuffle',
			'title' => 'Mecanismo del sorteo',
			'html'  => '<p>El sorteo se realizará mediante un sistema informático de selección aleatoria, supervisado por Notario Público, y será transmitido en vivo. El resultado será certificado mediante acta notarial.</p>',
		],
		[
			'icon'  => 'megaphone',
			'title' => 'Comunicación de resultados',
			'html'  => '<p>Los resultados del sorteo serán:</p><ul><li>Anunciados durante la transmisión en vivo</li><li>Publicados en el sitio web</li><li>Comunicados en los canales oficiales</li><li>Enviados a los participantes mediante correo electrónico junto con el acta notarial correspondiente</li></ul>',
		],
		[
			'icon'  => 'box',
			'title' => 'Productos digitales y política de no devolución',
			'html'  => '<p>Los DigiTickets corresponden a productos digitales entregados inmediatamente tras su compra. Por tratarse de contenido digital entregado de forma inmediata, no aplica derecho a retracto ni devolución, conforme a lo informado en las Bases Legales del concurso.</p>',
		],
		[
			'icon'  => 'gift',
			'title' => 'Premios provenientes de terceros',
			'html'  => '<p>En determinados concursos, los premios ofrecidos pueden corresponder a bienes que pertenecen a terceros participantes de la promoción. En estos casos:</p><ul><li>El tercero es responsable de la titularidad del bien ofrecido</li><li>El premio debe encontrarse libre de impedimentos legales para su transferencia</li><li>La entrega del premio se realizará conforme a lo establecido en las Bases Legales</li></ul><p>Sorteo Seguro actúa como organizador y plataforma tecnológica del concurso.</p>',
		],
		[
			'icon'  => 'alert',
			'title' => 'Cancelación excepcional del concurso',
			'html'  => '<p>En situaciones extraordinarias (imposibilidad legal, incumplimiento del tercero, fuerza mayor), el organizador podrá cancelar el concurso, adoptando medidas conforme a las Bases Legales, tales como:</p><ul><li>Reemplazo del premio</li><li>Realización de un sorteo alternativo</li><li>Otras soluciones establecidas en las Bases Legales</li></ul>',
		],
		[
			'icon'  => 'shield',
			'title' => 'Limitación de responsabilidad',
			'html'  => '<p>Sorteo Seguro no será responsable por incumplimientos imputables al propietario del premio o situaciones legales externas que afecten la transferencia. El organizador se compromete a actuar bajo las Bases Legales.</p>',
		],
		[
			'icon'  => 'user',
			'title' => 'Verificación del ganador',
			'html'  => '<p>La empresa podrá solicitar documentación para verificar la identidad del ganador. En caso de fraude, la participación podrá ser invalidada.</p>',
		],
		[
			'icon'  => 'lock',
			'title' => 'Protección de datos personales',
			'html'  => '<p>Los datos personales serán tratados conforme a la Ley N° 19.628 sobre Protección de la Vida Privada.</p>',
		],
		[
			'icon'  => 'doc',
			'title' => 'Propiedad intelectual',
			'html'  => '<p>Todo el contenido de la plataforma se encuentra protegido por la legislación vigente sobre propiedad intelectual.</p>',
		],
		[
			'icon'  => 'ban',
			'title' => 'Uso indebido y manifestaciones públicas',
			'html'  => '<p>Queda prohibido realizar acciones que afecten la reputación de la empresa o difundir información falsa. Sorteo Seguro se reserva el derecho de restringir el acceso y ejercer acciones legales.</p>',
		],
		[
			'icon'  => 'pencil',
			'title' => 'Modificaciones',
			'html'  => '<p>Sorteo Seguro podrá modificar estos Términos y Condiciones en cualquier momento, publicando la versión actualizada en el sitio web.</p>',
		],
		[
			'icon'  => 'book',
			'title' => 'Legislación aplicable',
			'html'  => '<p>Estos Términos se rigen por las leyes de la República de Chile (Ley N° 19.496, N° 19.628, N° 19.799, Código Civil y de Comercio).</p>',
		],
		[
			'icon'  => 'courthouse',
			'title' => 'Jurisdicción',
			'html'  => '<p>Cualquier controversia será sometida a los tribunales ordinarios de justicia de la República de Chile.</p>',
		],
		[
			'icon'  => 'chat',
			'title' => 'Contacto',
			'html'  => '<p>Para consultas o información adicional, los usuarios podrán utilizar los canales de contacto disponibles en el sitio web ' . $site . '.</p>',
		],
	],
];
