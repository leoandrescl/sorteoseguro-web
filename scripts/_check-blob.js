const https = require('https');
https.get('https://sorteoseguro.cl/comprar/jeep-avenger/?ssv=ctr31', { rejectUnauthorized: false, headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' } }, (r) => {
  let b = ''; r.on('data', (d) => b += d);
  r.on('end', () => {
    const m = b.match(/<span class="ss-contador__blob-text"[^>]*>/);
    console.log(m ? m[0] : 'NOT FOUND');
  });
});
