const https = require('https');
function get(u) {
  return new Promise((res, rej) => {
    https.get(u, { rejectUnauthorized: false, headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' } }, (r) => {
      let b = ''; r.on('data', (d) => b += d); r.on('end', () => res({ status: r.statusCode, body: b }));
    }).on('error', rej);
  });
}
(async () => {
  const urls = [
    ['HOME', 'https://sorteoseguro.cl/'],
    ['COMPRAR', 'https://sorteoseguro.cl/comprar/jeep-avenger/'],
    ['BONIF', 'https://sorteoseguro.cl/bonificaciones/jeep-avenger/'],
    ['BONIF2', 'https://sorteoseguro.cl/bonificaciones/sorteo-vista-mar-dunares/'],
  ];
  for (const [label, u] of urls) {
    const p = await get(u + (u.includes('?') ? '&' : '?') + 'ssv=chk' + Date.now());
    console.log(label.padEnd(8), p.status,
      'ss-contador=' + (p.body.includes('ss-contador--') ? 'YES' : 'no'),
      'css=' + (p.body.includes('bono-contador.css') ? 'y' : 'n'),
      'js=' + (p.body.includes('bono-contador.js') ? 'y' : 'n'));
  }
})();
