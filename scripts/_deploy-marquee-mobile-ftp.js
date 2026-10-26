const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const stamp = new Date().toISOString().slice(0,16).replace(/[-:T]/g,'').slice(0,12);
const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/,'') || '';
const localBase = 'C:/Proyectos/sorteoseguro/mu-plugins';
const files = [
  'sorteoseguro-pdp-templates.php',
  'sorteoseguro-pdp/assets/pdp.css',
].map((rel) => ({ local: path.join(localBase, rel), remote: `${remoteBase}/wp-content/mu-plugins/${rel}` }));
function fetchText(url, extraHeaders) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: Object.assign({ 'Cache-Control': 'no-cache' }, extraHeaders || {}), rejectUnauthorized: false }, (res) => {
      let body=''; res.on('data',(d)=>(body+=d)); res.on('end',()=>resolve({status:res.statusCode,body}));
    }).on('error', reject);
  });
}
async function main() {
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST||e.FTP_HOSTNAME, port: +(e.FTP_PORT||21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    for (const f of files) {
      await client.ensureDir(path.posix.dirname(f.remote));
      try { await client.rename(f.remote, `${f.remote}.bak-${stamp}`); console.log('remote backup ok', path.posix.basename(f.remote)); }
      catch (_) { console.log('remote backup skip', path.posix.basename(f.remote)); }
      await client.uploadFrom(f.local, f.remote);
      console.log('uploaded', f.remote.replace(remoteBase,''));
    }
    const key = 'ss-mg-css-20260915';
    const bootLocal = 'C:/Proyectos/sorteoseguro/scripts/_tmp-mg-css-flush.php';
    const bootRemote = `${remoteBase}/_tmp-mg-css-flush.php`;
    fs.writeFileSync(bootLocal,
`<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo "forbidden\\n"; exit; }
require __DIR__ . '/wp-load.php';
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flush\\n"; }
echo 'ver=' . (class_exists('SorteoSeguro_PDP_Templates') ? SorteoSeguro_PDP_Templates::VERSION : '?') . "\\n";
echo "done\\n";
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-mg-css-flush.php?k=${key}`);
    console.log('FLUSH', hit.status, JSON.stringify(hit.body.slice(0, 300)));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally { client.close(); }
  const css = await fetchText('https://sorteoseguro.cl/wp-content/mu-plugins/sorteoseguro-pdp/assets/pdp.css?ver=1.0.40');
  console.log('CSS', css.status, 'len=' + css.body.length,
    'has_24=' + (css.body.includes('/ 2.4') ? 'yes' : 'no'),
    'has_cover=' + (css.body.includes('object-position: center center') ? 'yes' : 'no'));
  const front = await fetchText('https://sorteoseguro.cl/producto/jeep-avenger/?ssverify=mgcss40', { 'User-Agent': 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15' });
  console.log('FRONT', front.status, 'marquee=' + (front.body.includes('ss-pdp-marquee') ? 'yes' : 'no'),
    'cssver40=' + (front.body.includes('pdp.css?ver=1.0.40') || front.body.includes('ss-pdp-critical') ? 'yes' : 'no'));
}
main().catch((err)=>{ console.error(err); process.exit(1); });
