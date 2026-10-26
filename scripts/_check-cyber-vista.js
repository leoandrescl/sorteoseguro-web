const fs = require('fs');
const https = require('https');

const env = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);
const site = (env.SITE_URL || 'https://sorteoseguro.cl').replace(/\/$/, '');

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { rejectUnauthorized: false }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
      })
      .on('error', reject);
  });
}

(async () => {
  const r = await fetchText(`${site}/cyber/sorteo-vista-mar-dunares/?ssv=chk`);
  const b = r.body;
  const checks = {
    banner: 'class="ss-cyber-banner"',
    title: 'Pack Cyber Vista',
    cardTop: 'class="ss-cyber-banner__card-top"',
    bullets: 'class="ss-cyber-banner__bullets"',
    bullet1: 'Participa por la Casa Vista Mar Dunares',
    decoHouse: 'ss-cyber-banner__deco-ticket-house"',
    decoLast: 'class="ss-cyber-banner__deco-ticket-last"',
    pill: 'MÁS DE 83% DE DESCUENTO',
    price: '$50.000',
    list: '$300.000',
    timer: 'class="ss-cyber-banner__timer"',
    until: 'data-ss-cyber-until',
    js: 'cyber.js',
    placeholderDom: 'class="ss-cyber-nuevo-diseno"',
    cssTwoRows: 'grid-template-areas',
    cssUnitsRow: '"units units"',
    subBold: '<strong>Más de un <span class="ss-cyber-banner__pct">83%</span> de descuento</strong><br>para tus DigiTickets',
    cssTicketTop: 'top: 60px',
    cssTicketRot: 'rotate(7deg)',
    cssIco: 'clamp(46px, 10cqi, 64px)',
  };
  console.log('HTTP', r.status);
  for (const [k, t] of Object.entries(checks)) console.log(k.padEnd(16), b.includes(t) ? 'yes' : 'no');
  const m = b.match(/data-ss-cyber-until="(\d+)"/);
  console.log('until_value    ', m ? m[1] : '-');
})();
