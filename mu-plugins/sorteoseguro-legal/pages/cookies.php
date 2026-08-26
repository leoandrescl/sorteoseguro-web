<?php
/**
 * Contenido: Política de Cookies (página ID 56586).
 */
if (!defined('ABSPATH')) {
	exit;
}

$site = class_exists('SorteoSeguro_Legal') ? SorteoSeguro_Legal::site_link() : 'sorteoseguro.cl';

return [
	'id'            => 56586,
	'slug'          => 'politica-de-cookies',
	'doc_title'     => 'Política de Cookies',
	'eyebrow'       => 'Información legal',
	'title'         => 'Política de<br>Cookies',
	'lead'          => 'Conoce cómo utilizamos las cookies para mejorar tu experiencia de navegación en Sorteo Seguro.',
	'updated'       => '21 de Febrero del año 2026',
	'hero'          => '',
	'hero_alt'      => '',
	'hero_badge'    => 'cookie',
	'layout'        => 'stack',
	'confirm_icon'  => 'shield-check',
	'confirm_mod'   => 'solid',
	'confirm_title' => 'Tu privacidad es importante',
	'confirm'       => 'En Sorteo Seguro nos comprometemos a usar las cookies de manera responsable y transparente para brindar la mejor experiencia en nuestra plataforma.',
	'items'         => [
		[
			'icon'  => 'cookie',
			'title' => '¿Qué son las cookies?',
			'html'  => '<p>Las cookies son pequeños archivos de texto que se almacenan en el dispositivo del usuario cuando visita un sitio web. Estas permiten que el sitio recuerde información sobre la navegación del usuario para mejorar su experiencia de uso en la plataforma.</p>',
		],
		[
			'icon'  => 'layers',
			'title' => '¿Qué tipos de cookies utilizamos?',
			'html'  => '<p>En nuestro sitio web podemos utilizar los siguientes tipos de cookies:</p><ul class="ss-legal-cookie-types"><li><strong>Cookies esenciales</strong><p>Son necesarias para el funcionamiento básico del sitio web, permitiendo funciones como:</p><ul class="ss-legal-chips"><li>Navegación dentro del sitio</li><li>Acceso al panel de usuario</li><li>Proceso de compra de DigiTickets</li></ul></li><li><strong>Cookies de análisis</strong><p>Permiten analizar cómo los usuarios interactúan con el sitio web para mejorar su funcionamiento. Estas pueden incluir herramientas como <strong class="ss-legal-tool">Google Analytics</strong>.</p></li><li><strong>Cookies de marketing</strong><p>Se utilizan para mostrar contenido o publicidad relevante. Estas pueden incluir herramientas como <strong class="ss-legal-tool">Meta Pixel (Facebook / Instagram)</strong> y <strong class="ss-legal-tool">Google Ads</strong>.</p></li></ul>',
		],
		[
			'icon'  => 'sliders',
			'title' => 'Gestión de cookies',
			'html'  => '<p>Los usuarios pueden configurar su navegador para aceptar, rechazar o eliminar cookies almacenadas. Sin embargo, deshabilitar ciertas cookies (especialmente las esenciales) podría afectar el correcto funcionamiento del sitio y el proceso de compra.</p>',
		],
		[
			'icon'  => 'users',
			'title' => 'Cookies de terceros',
			'html'  => '<p>Algunas cookies pueden ser gestionadas por servicios externos utilizados por la plataforma para análisis o marketing digital. Estas cookies se encuentran sujetas a las políticas de privacidad de dichos proveedores.</p>',
		],
		[
			'icon'  => 'refresh',
			'title' => 'Cambios en esta política',
			'html'  => '<p>Sorteo Seguro podrá actualizar esta Política de Cookies en cualquier momento, publicando la versión actualizada en el sitio web ' . $site . '.</p>',
		],
	],
];
