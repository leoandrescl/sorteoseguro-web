const fs = require('fs');
const path = require('path');

// División político-administrativa oficial de Chile (SUBDERE / INE / BCN) — 16 regiones / 346 comunas.
const REGIONS = [
  ['CL-AP', 'Arica y Parinacota', ['Arica', 'Camarones', 'General Lagos', 'Putre']],
  ['CL-TA', 'Tarapac\u00e1', ['Iquique', 'Alto Hospicio', 'Cami\u00f1a', 'Colchane', 'Huara', 'Pica', 'Pozo Almonte']],
  ['CL-AN', 'Antofagasta', ['Antofagasta', 'Mejillones', 'Sierra Gorda', 'Taltal', 'Calama', 'Ollag\u00fce', 'San Pedro de Atacama', 'Tocopilla', 'Mar\u00eda Elena']],
  ['CL-AT', 'Atacama', ['Copiap\u00f3', 'Caldera', 'Tierra Amarilla', 'Cha\u00f1aral', 'Diego de Almagro', 'Vallenar', 'Alto del Carmen', 'Freirina', 'Huasco']],
  ['CL-CO', 'Coquimbo', ['La Serena', 'Coquimbo', 'Andacollo', 'La Higuera', 'Paihuano', 'Vicu\u00f1a', 'Illapel', 'Canela', 'Los Vilos', 'Salamanca', 'Ovalle', 'Combarbal\u00e1', 'Monte Patria', 'Punitaqui', 'R\u00edo Hurtado']],
  ['CL-VS', 'Valpara\u00edso', ['Valpara\u00edso', 'Casablanca', 'Conc\u00f3n', 'Juan Fern\u00e1ndez', 'Puchuncav\u00ed', 'Quintero', 'Vi\u00f1a del Mar', 'Isla de Pascua', 'Los Andes', 'Calle Larga', 'Rinconada', 'San Esteban', 'La Ligua', 'Cabildo', 'Papudo', 'Petorca', 'Zapallar', 'Quillota', 'La Calera', 'Hijuelas', 'La Cruz', 'Nogales', 'Olmu\u00e9', 'San Antonio', 'Algarrobo', 'Cartagena', 'El Quisco', 'El Tabo', 'Santo Domingo', 'San Felipe', 'Catemu', 'Llaillay', 'Panquehue', 'Putaendo', 'Santa Mar\u00eda', 'Quilpu\u00e9', 'Limache', 'Villa Alemana']],
  ['CL-RM', 'Metropolitana de Santiago', ['Santiago', 'Cerrillos', 'Cerro Navia', 'Conchal\u00ed', 'El Bosque', 'Estaci\u00f3n Central', 'Huechuraba', 'Independencia', 'La Cisterna', 'La Florida', 'La Granja', 'La Pintana', 'La Reina', 'Las Condes', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado', 'Macul', 'Maip\u00fa', '\u00d1u\u00f1oa', 'Pedro Aguirre Cerda', 'Pe\u00f1alol\u00e9n', 'Providencia', 'Pudahuel', 'Quilicura', 'Quinta Normal', 'Recoleta', 'Renca', 'San Joaqu\u00edn', 'San Miguel', 'San Ram\u00f3n', 'Vitacura', 'Colina', 'Lampa', 'Tiltil', 'Puente Alto', 'Pirque', 'San Jos\u00e9 de Maipo', 'San Bernardo', 'Buin', 'Calera de Tango', 'Paine', 'Melipilla', 'Alhu\u00e9', 'Curacav\u00ed', 'Mar\u00eda Pinto', 'San Pedro', 'Talagante', 'El Monte', 'Isla de Maipo', 'Padre Hurtado', 'Pe\u00f1aflor']],
  ['CL-LI', "O'Higgins", ['Rancagua', 'Codegua', 'Coinco', 'Coltauco', 'Do\u00f1ihue', 'Graneros', 'Las Cabras', 'Machal\u00ed', 'Malloa', 'Mostazal', 'Olivar', 'Peumo', 'Pichidegua', 'Quinta de Tilcoco', 'Rengo', 'Requ\u00ednoa', 'San Vicente de Tagua Tagua', 'San Fernando', 'Ch\u00e9pica', 'Chimbarongo', 'Lolol', 'Nancagua', 'Palmilla', 'Peralillo', 'Placilla', 'Pumanque', 'Santa Cruz', 'Pichilemu', 'La Estrella', 'Litueche', 'Marchihue', 'Navidad', 'Paredones']],
  ['CL-ML', 'Maule', ['Talca', 'Constituci\u00f3n', 'Curepto', 'Empedrado', 'Maule', 'Pelarco', 'Pencahue', 'R\u00edo Claro', 'San Clemente', 'San Rafael', 'Cauquenes', 'Chanco', 'Pelluhue', 'Curic\u00f3', 'Hual\u00e1\u00f1\u00e9', 'Licant\u00e9n', 'Molina', 'Rauco', 'Romeral', 'Sagrada Familia', 'Teno', 'Vichuqu\u00e9n', 'Linares', 'Colb\u00fan', 'Longav\u00ed', 'Parral', 'Retiro', 'San Javier de Loncomilla', 'Villa Alegre', 'Yerbas Buenas']],
  ['CL-NB', '\u00d1uble', ['Chill\u00e1n', 'Bulnes', 'Chill\u00e1n Viejo', 'El Carmen', 'Pemuco', 'Pinto', 'Quill\u00f3n', 'San Ignacio', 'Yungay', 'San Carlos', 'Coihueco', '\u00d1iqu\u00e9n', 'San Fabi\u00e1n', 'San Nicol\u00e1s', 'Quirihue', 'Cobquecura', 'Coelemu', 'Ninhue', 'Portezuelo', 'R\u00e1nquil', 'Trehuaco']],
  ['CL-BI', 'Biob\u00edo', ['Concepci\u00f3n', 'Coronel', 'Chiguayante', 'Florida', 'Hualqui', 'Lota', 'Penco', 'San Pedro de la Paz', 'Santa Juana', 'Talcahuano', 'Tom\u00e9', 'Hualp\u00e9n', 'Lebu', 'Arauco', 'Ca\u00f1ete', 'Contulmo', 'Curanilahue', 'Los \u00c1lamos', 'Tir\u00faa', 'Los \u00c1ngeles', 'Antuco', 'Cabrero', 'Laja', 'Mulch\u00e9n', 'Nacimiento', 'Negrete', 'Quilaco', 'Quilleco', 'San Rosendo', 'Santa B\u00e1rbara', 'Tucapel', 'Yumbel', 'Alto Biob\u00edo']],
  ['CL-AR', 'La Araucan\u00eda', ['Temuco', 'Carahue', 'Cholchol', 'Cunco', 'Curarrehue', 'Freire', 'Galvarino', 'Gorbea', 'Lautaro', 'Loncoche', 'Melipeuco', 'Nueva Imperial', 'Padre Las Casas', 'Perquenco', 'Pitrufqu\u00e9n', 'Puc\u00f3n', 'Saavedra', 'Teodoro Schmidt', 'Tolt\u00e9n', 'Vilc\u00fan', 'Villarrica', 'Angol', 'Collipulli', 'Curacaut\u00edn', 'Ercilla', 'Lonquimay', 'Los Sauces', 'Lumaco', 'Pur\u00e9n', 'Renaico', 'Traigu\u00e9n', 'Victoria']],
  ['CL-LR', 'Los R\u00edos', ['Valdivia', 'Corral', 'Futrono', 'Lago Ranco', 'Lanco', 'Los Lagos', 'M\u00e1fil', 'Paillaco', 'Panguipulli', 'R\u00edo Bueno', 'La Uni\u00f3n', 'San Jos\u00e9 de la Mariquina']],
  ['CL-LL', 'Los Lagos', ['Puerto Montt', 'Calbuco', 'Cocham\u00f3', 'Fresia', 'Frutillar', 'Los Muermos', 'Llanquihue', 'Maull\u00edn', 'Puerto Varas', 'Castro', 'Ancud', 'Chonchi', 'Curaco de V\u00e9lez', 'Dalcahue', 'Puqueld\u00f3n', 'Queil\u00e9n', 'Quell\u00f3n', 'Quemchi', 'Quinchao', 'Osorno', 'Puerto Octay', 'Purranque', 'Puyehue', 'R\u00edo Negro', 'San Juan de la Costa', 'San Pablo', 'Chait\u00e9n', 'Futaleuf\u00fa', 'Hualaihu\u00e9', 'Palena']],
  ['CL-AI', 'Ays\u00e9n', ['Coyhaique', 'Lago Verde', 'Ays\u00e9n', 'Cisnes', 'Guaitecas', 'Chile Chico', 'R\u00edo Ib\u00e1\u00f1ez', 'Cochrane', "O'Higgins", 'Tortel']],
  ['CL-MA', 'Magallanes', ['Punta Arenas', 'Laguna Blanca', 'R\u00edo Verde', 'San Gregorio', 'Natales', 'Torres del Paine', 'Porvenir', 'Primavera', 'Timaukel', 'Cabo de Hornos', 'Ant\u00e1rtica']],
];

const phpStr = (s) => "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";

const expected = { 'CL-AP': 4, 'CL-TA': 7, 'CL-AN': 9, 'CL-AT': 9, 'CL-CO': 15, 'CL-VS': 38, 'CL-RM': 52, 'CL-LI': 33, 'CL-ML': 30, 'CL-NB': 21, 'CL-BI': 33, 'CL-AR': 32, 'CL-LR': 12, 'CL-LL': 30, 'CL-AI': 10, 'CL-MA': 11 };

let total = 0;
let ok = true;
for (const [code, name, communes] of REGIONS) {
  total += communes.length;
  if (expected[code] !== undefined && expected[code] !== communes.length) {
    ok = false;
    console.error('!!', code, communes.length, '!=' , expected[code]);
  }
  console.log(code.padEnd(6), String(communes.length).padStart(3), name);
}
console.log('\nTOTAL:', total, '(esperado 346)');
if (total !== 346 || !ok) {
  console.error('!! Validacion fallida, no se escribe');
  process.exit(1);
}

const lines = [];
lines.push('<?php');
lines.push('/**');
lines.push(' * Regiones y comunas de Chile — 16 regiones / 346 comunas (actualizado 2026).');
lines.push(' *');
lines.push(' * Fuente: división político-administrativa oficial (SUBDERE / INE / BCN).');
lines.push(' * Códigos de región = códigos de estado de WooCommerce para CL.');
lines.push(' *');
lines.push(' * NO editar a mano: regenerar con scripts/_gen-regiones-comunas.js');
lines.push(' */');
lines.push("if (!defined('ABSPATH')) {");
lines.push('\texit;');
lines.push('}');
lines.push('');
lines.push('return [');
for (const [code, name, communes] of REGIONS) {
  lines.push(`\t'${code}' => [`);
  lines.push(`\t\t'name'     => ${phpStr(name)},`);
  lines.push(`\t\t'communes' => [${communes.map(phpStr).join(', ')}],`);
  lines.push('\t],');
}
lines.push('];');
lines.push('');

const out = path.resolve(__dirname, '..', 'mu-plugins', 'sorteoseguro-checkout-location', 'data', 'chile-regiones-comunas.php');
fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, lines.join('\n'), 'utf8');
console.log('escrito:', out);
