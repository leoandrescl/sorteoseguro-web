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
  'mu-plugins/sorteoseguro-comprar-bono.php',
  'mu-plugins/sorteoseguro-comprar/assets/comprar.css',
];
const localRoot = 'C:/Proyectos/sorteoseguro/';
const bkpDir = `${localRoot}bkp-title-weight-${stamp}`;

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
  for (const rel of files) fs.copyFileSync(path.join(localRoot, rel), path.join(bkpDir, rel.replace(/\//g, '__')));
  console.log('backup', bkpDir);
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST||e.FTP_HOSTNAME, port: +(e.FTP_PORT||21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    for (const rel of files) {
      const local = path.join(localRoot, rel);
      const remote = `${remoteBase}/wp-content/${rel}`;
      await client.ensureDir(path.posix.dirname(remote));
      try { await client.rename(remote, `${remote}.bak-${stamp}`); } catch (_) {}
      await client.uploadFrom(local, remote);
      console.log('uploaded', rel);
    }
    const key = 'ss-title-20260916';
    const bootLocal = `${localRoot}scripts/_tmp-title-flush.php`;
    const bootRemote = `${remoteBase}/_tmp-title-flush.php`;
    fs.writeFileSync(bootLocal, `<?php header('Content-Type: text/plain'); if ((string)($_GET['k'] ?? '')!== '${key}') {http_response_code(403);echo 'no';exit;} require __DIR__.'/wp-load.php'; if(function_exists('wp_cache_flush')){wp_cache_flush();} echo 'ok';`);
    await client.uploadFrom(bootLocal, bootRemote);
    await fetchText(`https://sorteoseguro.cl/_tmp-title-flush.php?k=${key}`);
    try { await client.remove(bootRemote);} catch(_){}
    try { fs.unlinkSync(bootLocal);} catch(_){}
  } finally { client.close(); }
  const r = await fetchText('https://sorteoseguro.cl/bonificaciones/sorteo-vista-mar-dunares/?ssv=title1');
  console.log('ok', r.status, 'has override', r.body.includes('ss-pdp-title') ? 'yes' : 'no');
}
main().catch(e=>{console.error(e);process.exit(1);});