<?php
/**
 * Regiones y comunas de Chile — 16 regiones / 346 comunas (actualizado 2026).
 *
 * Fuente: división político-administrativa oficial (SUBDERE / INE / BCN).
 * Códigos de región = códigos de estado de WooCommerce para CL.
 *
 * NO editar a mano: regenerar con scripts/_gen-regiones-comunas.js
 */
if (!defined('ABSPATH')) {
	exit;
}

return [
	'CL-AP' => [
		'name'     => 'Arica y Parinacota',
		'communes' => ['Arica', 'Camarones', 'General Lagos', 'Putre'],
	],
	'CL-TA' => [
		'name'     => 'Tarapacá',
		'communes' => ['Iquique', 'Alto Hospicio', 'Camiña', 'Colchane', 'Huara', 'Pica', 'Pozo Almonte'],
	],
	'CL-AN' => [
		'name'     => 'Antofagasta',
		'communes' => ['Antofagasta', 'Mejillones', 'Sierra Gorda', 'Taltal', 'Calama', 'Ollagüe', 'San Pedro de Atacama', 'Tocopilla', 'María Elena'],
	],
	'CL-AT' => [
		'name'     => 'Atacama',
		'communes' => ['Copiapó', 'Caldera', 'Tierra Amarilla', 'Chañaral', 'Diego de Almagro', 'Vallenar', 'Alto del Carmen', 'Freirina', 'Huasco'],
	],
	'CL-CO' => [
		'name'     => 'Coquimbo',
		'communes' => ['La Serena', 'Coquimbo', 'Andacollo', 'La Higuera', 'Paihuano', 'Vicuña', 'Illapel', 'Canela', 'Los Vilos', 'Salamanca', 'Ovalle', 'Combarbalá', 'Monte Patria', 'Punitaqui', 'Río Hurtado'],
	],
	'CL-VS' => [
		'name'     => 'Valparaíso',
		'communes' => ['Valparaíso', 'Casablanca', 'Concón', 'Juan Fernández', 'Puchuncaví', 'Quintero', 'Viña del Mar', 'Isla de Pascua', 'Los Andes', 'Calle Larga', 'Rinconada', 'San Esteban', 'La Ligua', 'Cabildo', 'Papudo', 'Petorca', 'Zapallar', 'Quillota', 'La Calera', 'Hijuelas', 'La Cruz', 'Nogales', 'Olmué', 'San Antonio', 'Algarrobo', 'Cartagena', 'El Quisco', 'El Tabo', 'Santo Domingo', 'San Felipe', 'Catemu', 'Llaillay', 'Panquehue', 'Putaendo', 'Santa María', 'Quilpué', 'Limache', 'Villa Alemana'],
	],
	'CL-RM' => [
		'name'     => 'Metropolitana de Santiago',
		'communes' => ['Santiago', 'Cerrillos', 'Cerro Navia', 'Conchalí', 'El Bosque', 'Estación Central', 'Huechuraba', 'Independencia', 'La Cisterna', 'La Florida', 'La Granja', 'La Pintana', 'La Reina', 'Las Condes', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado', 'Macul', 'Maipú', 'Ñuñoa', 'Pedro Aguirre Cerda', 'Peñalolén', 'Providencia', 'Pudahuel', 'Quilicura', 'Quinta Normal', 'Recoleta', 'Renca', 'San Joaquín', 'San Miguel', 'San Ramón', 'Vitacura', 'Colina', 'Lampa', 'Tiltil', 'Puente Alto', 'Pirque', 'San José de Maipo', 'San Bernardo', 'Buin', 'Calera de Tango', 'Paine', 'Melipilla', 'Alhué', 'Curacaví', 'María Pinto', 'San Pedro', 'Talagante', 'El Monte', 'Isla de Maipo', 'Padre Hurtado', 'Peñaflor'],
	],
	'CL-LI' => [
		'name'     => 'O\'Higgins',
		'communes' => ['Rancagua', 'Codegua', 'Coinco', 'Coltauco', 'Doñihue', 'Graneros', 'Las Cabras', 'Machalí', 'Malloa', 'Mostazal', 'Olivar', 'Peumo', 'Pichidegua', 'Quinta de Tilcoco', 'Rengo', 'Requínoa', 'San Vicente de Tagua Tagua', 'San Fernando', 'Chépica', 'Chimbarongo', 'Lolol', 'Nancagua', 'Palmilla', 'Peralillo', 'Placilla', 'Pumanque', 'Santa Cruz', 'Pichilemu', 'La Estrella', 'Litueche', 'Marchihue', 'Navidad', 'Paredones'],
	],
	'CL-ML' => [
		'name'     => 'Maule',
		'communes' => ['Talca', 'Constitución', 'Curepto', 'Empedrado', 'Maule', 'Pelarco', 'Pencahue', 'Río Claro', 'San Clemente', 'San Rafael', 'Cauquenes', 'Chanco', 'Pelluhue', 'Curicó', 'Hualáñé', 'Licantén', 'Molina', 'Rauco', 'Romeral', 'Sagrada Familia', 'Teno', 'Vichuquén', 'Linares', 'Colbún', 'Longaví', 'Parral', 'Retiro', 'San Javier de Loncomilla', 'Villa Alegre', 'Yerbas Buenas'],
	],
	'CL-NB' => [
		'name'     => 'Ñuble',
		'communes' => ['Chillán', 'Bulnes', 'Chillán Viejo', 'El Carmen', 'Pemuco', 'Pinto', 'Quillón', 'San Ignacio', 'Yungay', 'San Carlos', 'Coihueco', 'Ñiquén', 'San Fabián', 'San Nicolás', 'Quirihue', 'Cobquecura', 'Coelemu', 'Ninhue', 'Portezuelo', 'Ránquil', 'Trehuaco'],
	],
	'CL-BI' => [
		'name'     => 'Biobío',
		'communes' => ['Concepción', 'Coronel', 'Chiguayante', 'Florida', 'Hualqui', 'Lota', 'Penco', 'San Pedro de la Paz', 'Santa Juana', 'Talcahuano', 'Tomé', 'Hualpén', 'Lebu', 'Arauco', 'Cañete', 'Contulmo', 'Curanilahue', 'Los Álamos', 'Tirúa', 'Los Ángeles', 'Antuco', 'Cabrero', 'Laja', 'Mulchén', 'Nacimiento', 'Negrete', 'Quilaco', 'Quilleco', 'San Rosendo', 'Santa Bárbara', 'Tucapel', 'Yumbel', 'Alto Biobío'],
	],
	'CL-AR' => [
		'name'     => 'La Araucanía',
		'communes' => ['Temuco', 'Carahue', 'Cholchol', 'Cunco', 'Curarrehue', 'Freire', 'Galvarino', 'Gorbea', 'Lautaro', 'Loncoche', 'Melipeuco', 'Nueva Imperial', 'Padre Las Casas', 'Perquenco', 'Pitrufquén', 'Pucón', 'Saavedra', 'Teodoro Schmidt', 'Toltén', 'Vilcún', 'Villarrica', 'Angol', 'Collipulli', 'Curacautín', 'Ercilla', 'Lonquimay', 'Los Sauces', 'Lumaco', 'Purén', 'Renaico', 'Traiguén', 'Victoria'],
	],
	'CL-LR' => [
		'name'     => 'Los Ríos',
		'communes' => ['Valdivia', 'Corral', 'Futrono', 'Lago Ranco', 'Lanco', 'Los Lagos', 'Máfil', 'Paillaco', 'Panguipulli', 'Río Bueno', 'La Unión', 'San José de la Mariquina'],
	],
	'CL-LL' => [
		'name'     => 'Los Lagos',
		'communes' => ['Puerto Montt', 'Calbuco', 'Cochamó', 'Fresia', 'Frutillar', 'Los Muermos', 'Llanquihue', 'Maullín', 'Puerto Varas', 'Castro', 'Ancud', 'Chonchi', 'Curaco de Vélez', 'Dalcahue', 'Puqueldón', 'Queilén', 'Quellón', 'Quemchi', 'Quinchao', 'Osorno', 'Puerto Octay', 'Purranque', 'Puyehue', 'Río Negro', 'San Juan de la Costa', 'San Pablo', 'Chaitén', 'Futaleufú', 'Hualaihué', 'Palena'],
	],
	'CL-AI' => [
		'name'     => 'Aysén',
		'communes' => ['Coyhaique', 'Lago Verde', 'Aysén', 'Cisnes', 'Guaitecas', 'Chile Chico', 'Río Ibáñez', 'Cochrane', 'O\'Higgins', 'Tortel'],
	],
	'CL-MA' => [
		'name'     => 'Magallanes',
		'communes' => ['Punta Arenas', 'Laguna Blanca', 'Río Verde', 'San Gregorio', 'Natales', 'Torres del Paine', 'Porvenir', 'Primavera', 'Timaukel', 'Cabo de Hornos', 'Antártica'],
	],
];
