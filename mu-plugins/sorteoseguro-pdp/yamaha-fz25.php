<?php
/**
 * PDP – DigiTicket Yamaha FZ-25A (product ID 874)
 */
if (!defined('ABSPATH')) {
	exit;
}

$ss = [
	'intro' => 'Participa por una Moto Yamaha FZ-25A adquiriendo tu DigiTicket digital. Evento transmitido públicamente y proceso transparente conforme a las bases legales.',
	'details' => [
		['icon' => 'calendar', 'label' => 'Año', 'value' => '2026'],
		['icon' => 'status', 'label' => 'Estado', 'value' => 'Nuevo'],
		['icon' => 'owners', 'label' => 'Dueños', 'value' => 'Sin dueños'],
		['icon' => 'clock', 'label' => 'Kilometraje', 'value' => '60 km (traslados logísticos)'],
	],
	'prizes' => [
		['badge' => '1º PREMIO', 'icon' => 'moto', 'name' => 'Yamaha FZ-25 A 2026', 'meta' => '0 km · Abs · Nueva', 'image' => 'featured'],
		['badge' => '2º PREMIO', 'icon' => 'cash', 'name' => '$1.000.000', 'meta' => 'Gift Card', 'placeholder' => 'Gift Card'],
		['badge' => '3º PREMIO', 'icon' => 'cash', 'name' => '$500.000', 'meta' => 'Gift Card', 'placeholder' => 'Gift Card'],
	],
	'discover_title' => 'DESCUBRE LA MOTO',
	'discover_icon'  => 'moto',
	'discover_lead'  => 'Yamaha FZ-25 A 2026, motocicleta nueva con frenos Abs, lista para entregar según bases legales del concurso.',
	'stats' => [
		['icon' => 'engine', 'label' => '249 CC'],
		['icon' => 'abs', 'label' => 'ABS'],
		['icon' => 'calendar', 'label' => 'AÑO 2026'],
		['icon' => 'figure', 'label' => '0 KM'],
	],
	'features' => [
		'Motor monocilíndrico',
		'Frenos Abs delantero/trasero',
		'Diseño naked deportivo',
		'Ideal uso diario y ciudad',
	],
	'checklist' => [
		'Yamaha FZ-25 A año 2026',
		'Motocicleta nueva (0 km, solo traslados logísticos)',
		'Cilindrada 249 cc',
		'Sistema de frenos Abs',
		'Estado: Nuevo',
		'Sin dueños previos',
		'Entrega conforme a bases legales',
		'Documentación según normativa vigente',
		'Evento transmitido públicamente',
		'Participación con DigiTicket digital único',
		'Proceso transparente y certificado',
		'Bases protocolizadas ante notario',
	],
	'value' => [
		'amount' => '$2.990.000',
		'name'   => 'Yamaha FZ-25 A 2026',
	],
	'bases_url' => 'https://sorteoseguro.cl/wp-content/uploads/2025/07/Bases-Legales-Repertorio-No3492-2025-_Sorteamos-la-Gran-Yamaha-FZ-25-A-Ano-2025_.pdf',
];

require __DIR__ . '/layout.php';
