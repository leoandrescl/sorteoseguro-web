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
  'mu-plugins/sorteoseguro-comprar/layout-comprar.php',
  'mu-plugins/sorteoseguro-comprar-bono.php',
  'mu-plugins/sorteoseguro-comprar/assets/comprar.css',
  'mu-plugins/sorteoseguro-comprar/assets/img-bono-regalo.png',
  'mu-plugins/sorteoseguro-comprar/assets/img-bono-ticket.png',
];
const localRoot = 'C:/Proyectos/sorteoseguro/';
const bkpDir = `${localRoot}bkp-bono-banner-${stamp}`;

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
    fs.copyFileSync(path.join(localRoot, rel), path.join(bkpDir, rel.replace(/\//g, '__')));
  }
  console.log('local backup ->', bkpDir);

  const client = new ftp.Client(120000);
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

    const key = 'ss-bono-20260916';
    const bootLocal = `${localRoot}scripts/_tmp-bono-flush.php`;
    const bootRemote = `${remoteBase}/_tmp-bono-flush.php`;
    fs.writeFileSync(bootLocal,
`<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo "forbidden\\n"; exit; }
require __DIR__ . '/wp-load.php';
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "flushed\\n"; }
echo "done\\n";
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-bono-flush.php?k=${key}`);
    console.log('FLUSH', hit.status, JSON.stringify(hit.body.slice(0, 200)));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally { client.close(); }

  const url = 'https://sorteoseguro.cl/bonificaciones/sorteo-vista-mar-dunares/?ssv=bono1';
  const r = await fetchText(url);
  console.log('VERIF', r.status,
    'banner=' + (r.body.includes('ss-bono-banner') ? 'yes' : 'no'),
    'gift=' + (r.body.includes('img-bono-regalo.png') ? 'yes' : 'no'),
    'ticket=' + (r.body.includes('img-bono-ticket.png') ? 'yes' : 'no'),
    'amount=' + (/\\$[0-9\\.]+/.test(r.body) ? 'found' : 'missing'));
}
main().catch((err)=>{ console.error(err); process.exit(1); });