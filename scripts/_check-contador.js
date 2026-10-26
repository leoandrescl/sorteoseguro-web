const https = require('https');
function get(u) {
  return new Promise((res, rej) => {
    https.get(u, { rejectUnauthorized: false, headers: { 'User-Agent': 'Mozilla/5.0' } }, (r) => {
      let b = ''; r.on('data', (d) => b += d); r.on('end', () => res({ status: r.statusCode, body: b }));
    }).on('error', rej);
  });
}
(async () => {
  const p = await get('https://sorteoseguro.cl/comprar/jeep-avenger/?ssv=ctr9');
  const html = p.body;
  const checks = [
    'ss-contador__blob',
    'puede comenzar aqu',
    'ss-contador__foot',
    'Confianza en cada concurso',
    'Caveat',
    'ss-contador__blob-underline',
  ];
  checks.forEach((k) => console.log(k.padEnd(34), html.includes(k) ? 'YES' : 'no'));
  const css = [...html.matchAll(/href="(https:\/\/sorteoseguro\.cl\/wp-content\/litespeed\/css\/[^"]+)"/g)].map(m => m[1]);
  for (const u of css) {
    const c = await get(u);
    const keys = ['ss-contador__blob', 'ss-contador__foot', 'ss-contador__blob-underline', 'ss-contador__blob-text'];
    console.log('CSS', u.split('/').pop(), keys.map(k => k.replace('ss-contador__', '') + '=' + (c.body.includes(k) ? 'Y' : 'n')).join(' '));
  }
})();
