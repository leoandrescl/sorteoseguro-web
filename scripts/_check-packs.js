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
        if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
          const next = res.headers.location.startsWith('http') ? res.headers.location : site + res.headers.location;
          return fetchText(next).then(resolve).catch(reject);
        }
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
      })
      .on('error', reject);
  });
}

function packsOf(body) {
  const m = body.match(/window\.ssPacksLottery = (\{[\s\S]*?\});<\/script>/);
  if (!m) return null;
  try {
    const cfg = JSON.parse(m[1]);
    return (cfg.campaigns || []).map((c) => c.buy + 'x' + c.pay);
  } catch (e) {
    return ['parse_error'];
  }
}

(async () => {
  for (const path of [
    '/comprar/jeep-avenger/',
    '/bonificaciones/jeep-avenger/',
    '/producto/jeep-avenger/',
  ]) {
    const r = await fetchText(`${site}${path}?ssv=chk`);
    const packs = packsOf(r.body);
    console.log(r.status, path, packs === null ? 'sin-config' : JSON.stringify(packs));
  }
  // Descubrir slug real de /producto/ para el producto 45941
  const redir = await fetchText(`${site}/?p=45941`);
  console.log('product_url_final_status', redir.status, redir.headers.location || '');
})();
