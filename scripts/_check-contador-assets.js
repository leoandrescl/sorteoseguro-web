const https = require('https');
function get(u) {
  return new Promise((res, rej) => {
    https.get(u, { rejectUnauthorized: false, headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' } }, (r) => {
      let b = ''; r.on('data', (d) => b += d); r.on('end', () => res({ status: r.statusCode, body: b }));
    }).on('error', rej);
  });
}
(async () => {
  const p = await get('https://sorteoseguro.cl/comprar/jeep-avenger/?ssv=ctr11');
  const links = [...p.body.matchAll(/<(?:link|script)[^>]+(?:href|src)="([^"]*bono-contador[^"]*)"/g)].map(m => m[1]);
  console.log('assets referenced:', links.length);
  for (const u of links) {
    const full = u.startsWith('http') ? u : 'https://sorteoseguro.cl' + u;
    const c = await get(full);
    const isCss = full.endsWith('.css') || full.includes('.css?');
    console.log(isCss ? 'CSS' : 'JS ', c.status, full.split('/').pop(),
      isCss ? ('blob=' + c.body.includes('ss-contador__blob') + ' foot=' + c.body.includes('ss-contador__foot')) : ('init=' + c.body.includes('__ssContadorInit')));
  }
})();
