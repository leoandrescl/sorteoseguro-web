<?php
/**
 * PDP – DigiTicket Peugeot 208 – 2017 (product ID 53210)
 */
if (!defined('ABSPATH')) {
	exit;
}

$ss = [
	'intro' => 'Participa por este Peugeot 208 Active PureTech 1.2 AT. adquiriendo tu DigiTicket digital. Evento transmitido públicamente y proceso transparente conforme a las bases legales.',
	'details' => [
		['icon' => 'calendar', 'label' => 'Año', 'value' => '2017'],
		['icon' => 'status', 'label' => 'Estado', 'value' => 'Usado'],
		['icon' => 'owners', 'label' => 'Dueños', 'value' => '2 Dueños'],
		['icon' => 'clock', 'label' => 'Kilometraje', 'value' => '89.500 km'],
	],
	'prizes' => [
		['badge' => '1º PREMIO', 'icon' => 'car', 'name' => 'Peugeot 208 Active PureTech 1.2 AT', 'meta' => 'Año 2017 · Automático', 'image' => 'featured'],
	],
	'discover_title' => 'DESCUBRE EL AUTO',
	'discover_icon'  => 'car',
	'discover_lead'  => 'Peugeot 208 Active PureTech 1.2 AT, año 2017. Este evento cuenta con solo 6.000 DigiTickets disponibles, lo que aumenta significativamente las probabilidades de ganar.',
	'stats' => [
		['icon' => 'engine', 'label' => '1.2 PURETECH'],
		['icon' => 'car', 'label' => 'AT'],
		['icon' => 'calendar', 'label' => 'AÑO 2017'],
		['icon' => 'clock', 'label' => '89.500 KM'],
	],
	'features' => [
		'Active PureTech 1.2',
		'Caja automática (AT)',
		'Año 2017',
		'Solo 6.000 DigiTickets',
	],
	'checklist' => [
		'Peugeot 208 Active PureTech 1.2 AT',
		'Año 2017',
		'Estado: Usado',
		'2 dueños',
		'Kilometraje: 89.500 km',
		'Caja automática',
		'6.000 DigiTickets disponibles',
		'Entrega conforme a bases legales',
		'Documentación según normativa vigente',
		'Evento transmitido públicamente',
		'Participación con DigiTicket digital único',
		'Bases protocolizadas ante notario',
	],
	'value' => null,
	'bases_url' => 'https://sorteoseguro.cl/wp-content/uploads/2026/03/Bases-Legales-Repertorio-Nro.-991-2026-Sorteo-Segugo-Sorteo-Peugeot-208.pdf',
];

require __DIR__ . '/layout.php';
