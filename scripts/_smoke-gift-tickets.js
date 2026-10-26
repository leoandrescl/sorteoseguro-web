const fs = require('fs');
const https = require('https');

const e = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);

function get(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { headers: { 'User-Agent': 'ss-smoke', 'Cache-Control': 'no-cache' } }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, body }));
      })
      .on('error', reject);
  });
}

const urls = [
  `${e.SITE_URL}/?sscachebust=${Date.now()}`,
  `${e.SITE_URL}/tienda/?sscachebust=${Date.now()}`,
  `${e.SITE_URL}/producto/sorteo-vista-mar-dunares/?sscachebust=${Date.now()}`,
];

(async () => {
  for (const u of urls) {
    try {
      const r = await get(u);
      const fatal = /Fatal error|There has been a critical error|WordPress database error/i.test(r.body);
      const loaded = /sorteoseguro|woocommerce|SS_|ss-/i.test(r.body);
      console.log(`${r.status}  fatal=${fatal}  len=${r.body.length}  ${u}`);
      if (/\/producto\//.test(u)) {
        const hasJs = (t) => new RegExp(t).test(r.body);
        console.log(`   js: isGift=${hasJs('isGift')} giftTag=${hasJs('giftTag')} packGift=${hasJs('packGift')} giftBadge=${hasJs('giftBadge')}`);
      }
      if (fatal) {
        const m = r.body.match(/.{0,120}(Fatal error|critical error|database error).{0,200}/i);
        console.log('   >>>', m ? m[0].replace(/\s+/g, ' ') : '');
      }
    } catch (err) {
      console.log('ERR', u, err.message);
    }
  }
})();
