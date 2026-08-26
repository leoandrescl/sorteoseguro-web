<?php
/**
 * PDP – DigiTicket Gran casa Vista Mar Dunares (product ID 1091)
 */
if (!defined('ABSPATH')) {
	exit;
}

$ss = [
	'intro' => 'Participa por esta espectacular casa con vista al mar en Mantagua. Evento certificado, proceso transparente y DigiTicket digital inmediato.',
	'details' => [
		['icon' => 'pin', 'label' => 'Ubicación', 'value' => 'Mantagua / Quinta Región'],
		['icon' => 'bed', 'label' => 'Dormitorios', 'value' => '5'],
		['icon' => 'status', 'label' => 'Estado', 'value' => 'Usado'],
		['icon' => 'home', 'label' => 'Construidos', 'value' => '260 m²'],
		['icon' => 'terrain', 'label' => 'Terreno', 'value' => '850 m²'],
	],
	'prizes' => [
		['badge' => '1º PREMIO', 'icon' => 'house', 'name' => 'Gran Casa Vista Mar', 'meta' => 'Mantagua · Quinta Región', 'image' => 'featured'],
		['badge' => '2º PREMIO', 'icon' => 'car', 'name' => 'MG New HS 1.5T DCT DLX', 'meta' => 'Automático · Año 2026', 'image' => 2266],
		['badge' => '3º PREMIO', 'icon' => 'car', 'name' => 'MG3 1.5 AT COM', 'meta' => 'Automático · Año 2026', 'image' => 2265],
	],
	'discover_title' => 'DESCUBRE LA CASA',
	'discover_icon'  => 'house',
	'discover_lead'  => 'Propiedad ubicada en la comuna de Quintero, sector Mantagua, en una zona residencial consolidada de la Región de Valparaíso. Amplios espacios interiores y exteriores, ideal para disfrutar de la tranquilidad del entorno costero.',
	'stats' => [
		['icon' => 'bed', 'label' => '5 DORM'],
		['icon' => 'bath', 'label' => '3 BAÑOS'],
		['icon' => 'home', 'label' => '260 m²'],
		['icon' => 'terrain', 'label' => '850 m²'],
	],
	'features' => [
		'Living y comedor independientes',
		'Cocina equipada con alacena y logia',
		'Amplia sala de estar en nivel -1',
		'Quincho y fogón exterior',
		'260 m² construidos',
		'Terreno de 850 m² con árboles frutales',
	],
	'checklist' => [
		'Gran Casa Vista Mar en Mantagua, Quinta Región',
		'5 dormitorios y 3 baños',
		'260 m² construidos',
		'Terreno aproximado de 850 m² con árboles frutales',
		'Living y comedor independientes',
		'Cocina equipada con alacena y logia',
		'Amplia sala de estar en nivel -1',
		'Quincho y fogón exterior',
		'Entorno residencial seguro, con accesos controlados',
		'Sistema de videovigilancia y conserjería permanente',
		'Entrega conforme a bases legales',
		'Bases protocolizadas ante notario',
	],
	'value' => [
		'amount' => 'UF 15.900',
		'name'   => 'Gran Casa Vista Mar Dunares',
	],
	'bases_url' => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/Bases-Legales-Repertorio-No3494-2025-_Sorteamos-Gran-casa-Vista-mar-Dunares_.pdf',
];

require __DIR__ . '/layout.php';
