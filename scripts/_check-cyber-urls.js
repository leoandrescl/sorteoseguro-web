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

const tags = {
  banner: 'class="ss-bono-banner"',
  contador: 'ss-contador--card',
  placeholder: 'ss-cyber-nuevo-diseno',
  bannerCyber: 'class="ss-cyber-banner"',
  packs: 'ss-packs-lottery-config',
};

async function check(pathname) {
  const r = await fetchText(`${site}${pathname}?ssv=cy-check`);
  const has = {};
  for (const [k, t] of Object.entries(tags)) has[k] = r.body.includes(t);
  console.log(r.status, pathname, JSON.stringify(has), 'loc=' + (r.headers.location || '-'));
}

(async () => {
  for (const p of [
    '/cyber/jeep-avenger/',
    '/cyber/parcela/',
    '/cyber/peugeot-208/',
    '/cyber/sorteo-vista-mar-dunares/',
    '/bonificaciones/jeep-avenger/',
    '/comprar/jeep-avenger/',
  ]) {
    await check(p);
  }
})();
