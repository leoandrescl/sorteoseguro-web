<?php
/**
 * Contenido: Políticas de Envío y Reembolso (página ID 57070).
 */
if (!defined('ABSPATH')) {
	exit;
}

$site = class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::site_link() : 'sorteoseguro.cl';

return [
	'id'            => 57070,
	'slug'          => 'politicas-de-envio-reembolso',
	'doc_title'     => 'Políticas de Envío y Reembolso',
	'eyebrow'       => 'Información legal',
	'title'         => 'Políticas de<br>Envío y Reembolso',
	'lead'          => 'Conoce nuestras políticas sobre la entrega de productos digitales, acceso a DigiTickets, reembolsos y resolución de problemas.',
	'updated'       => '12 de Diciembre del año 2026',
	'hero'          => '',
	'hero_alt'      => '',
	'hero_badge'    => 'doc-download',
	'layout'        => 'rows',
	'confirm_icon'  => 'shield-check',
	'confirm_mod'   => 'solid',
	'confirm_title' => 'Tu confianza es importante',
	'confirm'       => 'Trabajamos para entregar una experiencia segura, transparente y confiable en cada participación.',
	'items'         => [
		[
			'icon'  => 'doc',
			'title' => 'Naturaleza del producto ofrecido',
			'html'  => '<p>Los productos ofrecidos en la plataforma ' . $site . ' corresponden a <strong>productos digitales</strong>, denominados <strong>DigiTickets</strong>, los cuales consisten en ilustraciones digitales numeradas que poseen un valor propio como contenido digital.</p><p>La adquisición de un DigiTicket corresponde a la compra de un producto digital entregado electrónicamente, el cual otorga al usuario el derecho a participar en el concurso <strong>promocional asociado</strong>. La compra del DigiTicket no corresponde a la adquisición directa del premio ofrecido.</p>',
		],
		[
			'icon'  => 'mail',
			'title' => 'Entrega del producto digital',
			'html'  => '<p>Una vez confirmado el pago, el DigiTicket será entregado mediante:</p><ul><li><strong>Correo electrónico:</strong> El usuario recibirá la confirmación y su DigiTicket. Este proceso puede tardar algunos minutos según el proveedor.</li><li><strong>Perfil del usuario:</strong> Estará disponible de forma inmediata en la sección “Pedidos” de su cuenta, donde podrá visualizarlo y descargarlo.</li></ul>',
		],
		[
			'icon'  => 'check',
			'title' => 'Confirmación del pago',
			'html'  => '<p>La entrega se realiza una vez que el pago es confirmado por la pasarela utilizada (Mercado Pago, MACH, dLocal Go, entre otras). Los tiempos de confirmación pueden variar según el medio de pago seleccionado por el usuario.</p>',
		],
		[
			'icon'  => 'sparkle',
			'title' => 'Acceso permanente al DigiTicket',
			'html'  => '<p>El usuario podrá acceder en cualquier momento a su historial de compras y a sus DigiTickets activos a través de la sección <strong>Pedidos</strong> de su perfil en la plataforma.</p>',
		],
		[
			'icon'  => 'user',
			'title' => 'Responsabilidad del usuario',
			'html'  => '<p>Es responsabilidad del usuario proporcionar un correo electrónico válido, revisar su carpeta de SPAM y conservar su comprobante. El DigiTicket siempre estará respaldado en su perfil de usuario.</p>',
		],
		[
			'icon'  => 'headset',
			'title' => 'Problemas en la entrega',
			'html'  => '<p>Si el usuario presenta dificultades para visualizar su compra, podrá contactar a soporte a través de los canales oficiales. La plataforma verificará la transacción para facilitar nuevamente el acceso al producto digital.</p>',
		],
		[
			'icon'  => 'refresh',
			'title' => 'Política de reembolsos',
			'html'  => '<p>Debido a que los DigiTickets son productos digitales entregados de forma inmediata, <strong>no aplica derecho a retracto</strong> ni devolución. Al completar la transacción, el usuario acepta la recepción electrónica del contenido y su participación en el concurso asociado.</p>',
		],
		[
			'icon'  => 'alert',
			'title' => 'Compras duplicadas o errores técnicos',
			'html'  => '<p>En caso de errores técnicos comprobables del sistema de pago o duplicidad involuntaria, el usuario podrá contactar a la plataforma para evaluar el caso y adoptar las medidas correctivas correspondientes.</p>',
		],
		[
			'icon'  => 'trophy',
			'title' => 'Participación en concursos promocionales',
			'html'  => '<p>La adquisición del DigiTicket se rige por las Bases Legales de cada sorteo, donde se detalla el premio, el mínimo de participaciones y el mecanismo de selección del ganador.</p>',
		],
		[
			'icon'  => 'pencil',
			'title' => 'Modificaciones de esta política',
			'html'  => '<p>Sorteo Seguro podrá modificar estas Políticas de Envío y Reembolso en cualquier momento, publicando la versión actualizada en el sitio web ' . $site . '.</p>',
		],
	],
];
