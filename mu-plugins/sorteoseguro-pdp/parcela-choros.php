<?php
/**
 * PDP – DigiTicket Parcela 5.500 m² Punta de Choros (product ID 49102)
 */
if (!defined('ABSPATH')) {
	exit;
}

$ss = [
	'intro' => 'Terreno de 5.500 m² en una de las zonas con mayor proyección turística del norte de Chile. Vive, invierte o desarrolla tu proyecto en un entorno natural privilegiado.',
	'details' => [
		['icon' => 'pin', 'label' => 'Ubicación', 'value' => 'Punta de Choros – Coquimbo'],
		['icon' => 'status', 'label' => 'Estado', 'value' => 'Terreno'],
		['icon' => 'terrain', 'label' => 'Superficie', 'value' => '5.500 m²'],
		['icon' => 'doc', 'label' => 'Entrega', 'value' => 'Escritura pública'],
	],
	'prizes' => [
		['badge' => '1º PREMIO', 'icon' => 'land', 'name' => 'Parcela de 5.500 m²', 'meta' => 'Punta de Choros · Propiedad individual', 'image' => 'featured'],
	],
	'discover_title' => 'DESCUBRE LA PARCELA',
	'discover_icon'  => 'land',
	'discover_lead'  => 'Parcela de 5.500 m² ubicada en Punta de Choros, una zona reconocida por su entorno natural, proyección turística y cercanía con destinos icónicos como La Serena, Valle del Elqui y el borde costero del norte chico.',
	'stats' => [
		['icon' => 'terrain', 'label' => '5.500 m²'],
		['icon' => 'pin', 'label' => 'COQUIMBO'],
		['icon' => 'land', 'label' => 'TERRENO'],
		['icon' => 'doc', 'label' => 'ESCRITURA'],
	],
	'features' => [
		'5.500 m² de terreno',
		'Entorno natural privilegiado',
		'Proyección turística',
		'Cercanía a La Serena y Valle del Elqui',
	],
	'checklist' => [
		'Parcela de 5.500 m² en Punta de Choros',
		'Región de Coquimbo',
		'Propiedad individual',
		'Transferencia legal mediante escritura pública',
		'Entorno natural privilegiado',
		'Zona de proyección turística',
		'Cercanía con La Serena, Valle del Elqui y el borde costero',
		'Oportunidad para vivir, invertir o desarrollar un proyecto',
		'Entrega conforme a lo establecido en las Bases Legales',
		'Evento transmitido públicamente',
		'Participación con DigiTicket digital único',
		'Bases protocolizadas ante notario',
	],
	'value' => null,
	'bases_url' => 'https://sorteoseguro.cl/wp-content/uploads/2026/01/Bases-Legales-Protocolizadas-Sorteo-Parcela-Punta-de-Choros-.pdf',
];

require __DIR__ . '/layout.php';
