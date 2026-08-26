<?php
/**
 * Contenido: Política de Privacidad (página ID 56573).
 */
if (!defined('ABSPATH')) {
	exit;
}

$cookies = class_exists('SorteoSeguro_Chrome')
	? SorteoSeguro_Chrome::page_url('politica-de-cookies')
	: home_url('/politica-de-cookies/');
$site = class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::site_link() : 'sorteoseguro.cl';

return [
	'id'            => 56573,
	'slug'          => 'politica-privacidad',
	'doc_title'     => 'Política de Privacidad',
	'eyebrow'       => 'Información legal',
	'title'         => 'Política de Privacidad',
	'lead'          => 'En Sorteo Seguro protegemos tu información personal y respetamos tu privacidad. Conoce cómo recopilamos, utilizamos y protegemos tus datos.',
	'updated'       => '15 de diciembre del año 2025',
	'hero'          => '',
	'hero_alt'      => '',
	'hero_badge'    => 'lock-badge',
	'split'         => 4,
	'confirm_icon'  => 'shield-check',
	'confirm_mod'   => 'solid',
	'confirm_title' => 'Tu privacidad es importante para nosotros',
	'confirm'       => 'En Sorteo Seguro trabajamos continuamente para garantizar la protección de tus datos y la transparencia en el uso de la información.',
	'items'         => [
		[
			'icon'  => 'user',
			'title' => 'Responsable del tratamiento de datos',
			'html'  => '<p>La plataforma digital ' . $site . ' es operada por <strong>Grupo WH SpA</strong>, sociedad constituida conforme a las leyes de la República de Chile.</p><p>Razón Social: Grupo WH SpA<br>RUT: 77.887.705-8</p><p>En adelante denominada “Sorteo Seguro”, “la Plataforma” o “la Empresa”.</p>',
		],
		[
			'icon'  => 'id-card',
			'title' => 'Datos personales que recopilamos',
			'html'  => '<p>Al utilizar nuestra plataforma, podemos recopilar los siguientes datos personales:</p><ul><li>Nombre y apellido</li><li>Correo electrónico</li><li>Número de teléfono</li><li>Número de identificación (RUT)</li><li>Dirección de facturación</li><li>Información necesaria para la participación en concursos</li></ul><p>También podemos recopilar información técnica como:</p><ul><li>Dirección IP</li><li>Tipo de navegador</li><li>Dispositivo utilizado</li><li>Páginas visitadas dentro del sitio</li></ul>',
		],
		[
			'icon'  => 'target',
			'title' => 'Finalidad del uso de los datos',
			'html'  => '<p>Los datos personales recopilados serán utilizados para las siguientes finalidades:</p><ul><li>Gestionar la participación en concursos</li><li>Procesar compras de DigiTickets</li><li>Enviar confirmaciones de compra</li><li>Comunicar resultados de sorteos</li><li>Enviar información relevante sobre concursos activos</li><li>Mejorar la experiencia de uso de la plataforma</li><li>Cumplir obligaciones legales</li></ul>',
		],
		[
			'icon'  => 'scales',
			'title' => 'Base legal del tratamiento',
			'html'  => '<p>El tratamiento de los datos personales se realiza conforme a lo dispuesto en la <strong>Ley N° 19.628 sobre Protección de la Vida Privada</strong>, así como a la normativa vigente aplicable en la República de Chile.</p>',
		],
		[
			'icon'  => 'shield-lock',
			'title' => 'Almacenamiento y seguridad',
			'html'  => '<p>Sorteo Seguro adopta medidas razonables de seguridad para proteger los datos personales de los usuarios contra accesos no autorizados, alteración, pérdida o divulgación. Los datos serán almacenados únicamente durante el tiempo necesario para cumplir las finalidades descritas en esta política.</p>',
		],
		[
			'icon'  => 'database',
			'title' => 'Compartición de datos',
			'html'  => '<p>Sorteo Seguro no venderá ni cederá los datos personales de los usuarios a terceros. Sin embargo, los datos podrán ser compartidos cuando sea necesario para:</p><ul><li>Cumplir obligaciones legales</li><li>Realizar procesos de pago</li><li>Gestionar servicios tecnológicos de la plataforma</li><li>Cumplir con procedimientos notariales relacionados con los concursos</li></ul>',
		],
		[
			'icon'  => 'user-check',
			'title' => 'Derechos del usuario',
			'html'  => '<p>Los usuarios podrán ejercer los derechos establecidos en la Ley N°19.628, incluyendo:</p><ul><li>Solicitar acceso a sus datos personales</li><li>Solicitar corrección o actualización de información</li><li>Solicitar eliminación de sus datos cuando corresponda</li></ul><p>Para ejercer estos derechos, el usuario podrá comunicarse a través de los canales de contacto disponibles en el sitio web.</p>',
		],
		[
			'icon'  => 'cookie',
			'title' => 'Uso de cookies',
			'html'  => '<p>Nuestro sitio utiliza cookies para mejorar la experiencia de navegación y analizar el uso de la plataforma. El detalle del uso de cookies se encuentra descrito en nuestra <a href="' . esc_url($cookies) . '">Política de Cookies</a>.</p>',
		],
		[
			'icon'  => 'pencil',
			'title' => 'Modificaciones de esta política',
			'html'  => '<p>Sorteo Seguro podrá modificar la presente Política de Privacidad en cualquier momento, publicando la versión actualizada en el sitio web.</p>',
		],
		[
			'icon'  => 'mail',
			'title' => 'Contacto',
			'html'  => '<p>Para consultas relacionadas con esta política, los usuarios pueden comunicarse a través de los canales disponibles en el sitio web ' . $site . '.</p>',
		],
	],
];
