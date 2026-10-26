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
const rel = 'sorteoseguro-comprar/layout-comprar.php';
const local = 'C:/Proyectos/sorteoseguro/mu-plugins/' + rel;
const remote = `${remoteBase}/wp-content/mu-plugins/${rel}`;
function fetchText(url, opts) {
  return new Promise((resolve, reject) => {
    https.get(url, Object.assign({ rejectUnauthorized: false }, opts || {}), (res) => {
      let body=''; res.on('data',(d)=>(body+=d));
      res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,body}));
    }).on('error', reject);
  });
}
async function main() {
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST||e.FTP_HOSTNAME, port: +(e.FTP_PORT||21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.ensureDir(path.posix.dirname(remote));
    try { await client.rename(remote, `${remote}.bak-${stamp}`); console.log('remote backup ok'); }
    catch (_) { console.log('remote backup skip'); }
    await client.uploadFrom(local, remote);
    console.log('uploaded', remote.replace(remoteBase,''));
    const key = 'ss-ctd-20260916';
    const bootLocal = 'C:/Proyectos/sorteoseguro/scripts/_tmp-ctd-flush.php';
    const bootRemote = `${remoteBase}/_tmp-ctd-flush.php`;
    fs.writeFileSync(bootLocal,
`<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo "forbidden\\n"; exit; }
require __DIR__ . '/wp-load.php';
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "flushed\\n"; }
echo "done\\n";
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-ctd-flush.php?k=${key}`);
    console.log('FLUSH', hit.status, JSON.stringify(hit.body.slice(0, 200)));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally { client.close(); }
  for (const u of ['https://sorteoseguro.cl/bonificaciones/sorteo-vista-mar-dunares/?ssv=ctd16',
                   'https://sorteoseguro.cl/bonificaciones/jeep-avenger/?ssv=ctd16']) {
    const r = await fetchText(u);
    console.log('FRONT', r.status, u,
      'countdown=' + (r.body.includes('countdown-container') ? 'yes' : 'no'),
      'timer=' + (r.body.includes('lty-lottery-countdown-timer') ? 'yes' : 'no'),
      'fecha=' + (r.body.includes('Fecha estimativa del evento') ? 'yes' : 'no'));
  }
}
main().catch((err)=>{ console.error(err); process.exit(1); });