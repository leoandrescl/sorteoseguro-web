<?php
/**
 * PDP – DigiTicket Jeep Avenger 2026 Híbrido (product ID 45941)
 */
if (!defined('ABSPATH')) {
	exit;
}

$ss = [
	'intro' => 'Participa por un Jeep Avenger 2026 (0 km.) adquiriendo tu DigiTicket digital. Evento transmitido públicamente y proceso transparente conforme a las bases legales.',
	'details' => [
		['icon' => 'calendar', 'label' => 'Año', 'value' => '2026'],
		['icon' => 'status', 'label' => 'Estado', 'value' => 'Nuevo'],
		['icon' => 'owners', 'label' => 'Dueños', 'value' => 'Sin dueños'],
		['icon' => 'clock', 'label' => 'Kilometraje', 'value' => '60 km (traslados logísticos)'],
	],
	'prizes' => [
		['badge' => '1º PREMIO', 'icon' => 'car', 'name' => 'Jeep Avenger Altitude AT Mild Hybrid 2026', 'meta' => '0 km · SUV · Nuevo', 'image' => 'featured'],
		['badge' => '2º PREMIO', 'icon' => 'cash', 'name' => '$2.000.000', 'meta' => 'En efectivo', 'image' => 7633],
		['badge' => '3º PREMIO', 'icon' => 'cash', 'name' => '$1.000.000', 'meta' => 'En efectivo', 'image' => 7634],
	],
	'discover_title' => 'DESCUBRE EL JEEP',
	'discover_icon'  => 'car',
	'discover_lead'  => 'Jeep Avenger Altitude AT Mild Hybrid 2026, SUV nuevo 0 km, listo para entregar según bases legales del concurso.',
	'stats' => [
		['icon' => 'leaf', 'label' => 'MILD HYBRID'],
		['icon' => 'car', 'label' => 'SUV'],
		['icon' => 'calendar', 'label' => 'AÑO 2026'],
		['icon' => 'figure', 'label' => '0 KM'],
	],
	'features' => [
		'Motor Mild Hybrid',
		'Diseño urbano moderno',
		'Tecnología y conectividad',
		'Confort y seguridad',
	],
	'checklist' => [
		'Jeep Avenger Altitude AT Mild Hybrid año 2026',
		'SUV nuevo (0 km, solo traslados logísticos)',
		'Motor Mild Hybrid',
		'Diseño urbano moderno',
		'Tecnología y conectividad',
		'Confort y seguridad',
		'Estado: Nuevo',
		'Sin dueños previos',
		'Entrega conforme a bases legales',
		'Documentación según normativa vigente',
		'Evento transmitido públicamente',
		'Bases protocolizadas ante notario',
	],
	'value' => [
		'amount' => '$23.990.000',
		'name'   => 'Jeep Avenger Altitude AT Mild Hybrid 2026',
	],
	'bases_url' => 'https://sorteoseguro.cl/wp-content/uploads/2025/08/Bases-Legales-Sorteo-Seguro-BASES-DE-LA-PROMOCION-Sorteo-Seguro-Div.-Vehicular-_Sorteamos-el-nuevo-Jeep-Avenger-2026_-SSJ.pdf',
];

require __DIR__ . '/layout.php';
