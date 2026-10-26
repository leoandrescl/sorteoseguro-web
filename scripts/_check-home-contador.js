const https = require('https');
function get(u) {
  return new Promise((res, rej) => {
    https.get(u, { rejectUnauthorized: false, headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' } }, (r) => {
      let b = ''; r.on('data', (d) => b += d); r.on('end', () => res({ status: r.statusCode, body: b }));
    }).on('error', rej);
  });
}
(async () => {
  const p = await get('https://sorteoseguro.cl/?ssv=home1');
  console.log('HOME', p.status,
    'ss-home-contador=' + (p.body.includes('ss-home-contador') ? 'yes' : 'no'),
    'ss-contador--home=' + (p.body.includes('ss-contador--home') ? 'yes' : 'no'),
    'blob=' + (p.body.includes('ss-contador__blob') ? 'yes' : 'no'),
    'css=' + (p.body.includes('bono-contador.css') ? 'yes' : 'no'),
    'js=' + (p.body.includes('bono-contador.js') ? 'yes' : 'no'));
  const noq = await get('https://sorteoseguro.cl/');
  console.log('HOME(no query)', noq.status, 'ss-contador=' + (noq.body.includes('ss-contador') ? 'yes' : 'no'));
})();
