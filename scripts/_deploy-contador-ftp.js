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

const files = [
  'mu-plugins/sorteoseguro-contador.php',
  'mu-plugins/sorteoseguro-contador/bono-contador.php',
  'mu-plugins/sorteoseguro-contador/assets/bono-contador.css',
  'mu-plugins/sorteoseguro-contador/assets/bono-contador.js',
  'mu-plugins/sorteoseguro-contador/assets/ticket-bono.png',
  'mu-plugins/sorteoseguro-contador/assets/caveat-latin.woff2',
  'mu-plugins/sorteoseguro-comprar/layout-comprar.php',
  'mu-plugins/sorteoseguro-home/page-home.php',
];
const localRoot = 'C:/Proyectos/sorteoseguro/';
const bkpDir = `${localRoot}bkp-contador-${stamp}`;

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache', 'User-Agent': 'Mozilla/5.0' } }, (res) => {
      let body=''; res.on('data',(d)=>(body+=d));
      res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,body}));
    }).on('error', reject);
  });
}

async function main() {
  fs.mkdirSync(bkpDir, { recursive: true });
  for (const rel of files) {
    const dest = path.join(bkpDir, rel.replace(/\//g, '__'));
    fs.copyFileSync(path.join(localRoot, rel), dest);
  }
  console.log('local backup ->', bkpDir);

  const client = new ftp.Client(180000);
  try {
    await client.access({ host: e.FTP_HOST||e.FTP_HOSTNAME, port: +(e.FTP_PORT||21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });

    for (const rel of files) {
      const local = path.join(localRoot, rel);
      const remote = `${remoteBase}/wp-content/${rel}`;
      await client.ensureDir(path.posix.dirname(remote));
      try { await client.rename(remote, `${remote}.bak-${stamp}`); console.log('remote backup ok', rel); }
      catch (_) { console.log('remote backup skip', rel); }
      await client.uploadFrom(local, remote);
      console.log('uploaded', rel);
    }

    const key = 'ss-ctr-20260922';
    const bootLocal = `${localRoot}scripts/_tmp-ctr-flush.php`;
    const bootRemote = `${remoteBase}/_tmp-ctr-flush.php`;
    fs.writeFileSync(bootLocal,
`<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo "forbidden\\n"; exit; }
require __DIR__ . '/wp-load.php';
echo 'contador_fn=' . (function_exists('ss_bono_contador_render') ? 'yes' : 'no') . "\\n";
echo 'contador_active=' . (function_exists('ss_bono_contador_is_active') && ss_bono_contador_is_active() ? 'yes' : 'no') . "\\n";
if (function_exists('ss_bono_contador_time_left')) { echo 'time_left=' . ss_bono_contador_time_left() . "\\n"; }
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "flushed\\n"; }
echo "done\\n";
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-ctr-flush.php?k=${key}`);
    console.log('FLUSH', hit.status, JSON.stringify(hit.body.slice(0, 400)));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally { client.close(); }

  const comprar = await fetchText('https://sorteoseguro.cl/comprar/jeep-avenger/?ssv=ctr1');
  console.log('COMPRAR', comprar.status,
    'ss-contador=' + (comprar.body.includes('ss-contador') ? 'yes' : 'no'),
    'until=' + (comprar.body.includes('data-ss-contador-until') ? 'yes' : 'no'),
    'amount=' + (comprar.body.includes('15.000') ? 'yes' : 'no'),
    'css=' + (/bono-contador\.css/i.test(comprar.body) ? 'yes' : 'no'),
    'js=' + (/bono-contador\.js/i.test(comprar.body) ? 'yes' : 'no'));
  console.log('bkp-dir=' + path.basename(bkpDir));
}
main().catch((err)=>{ console.error(err); process.exit(1); });
