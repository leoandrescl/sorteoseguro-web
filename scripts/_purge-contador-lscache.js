const fs = require('fs');
const ftp = require('basic-ftp');
const https = require('https');

const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/,'') || '';
const localRoot = 'C:/Proyectos/sorteoseguro/';

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache', 'User-Agent': 'Mozilla/5.0' } }, (res) => {
      let body=''; res.on('data',(d)=>(body+=d));
      res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,body}));
    }).on('error', reject);
  });
}

(async () => {
  const key = 'purgecontador20260922';
  const local = `${localRoot}scripts/_tmp-purge-contador.php`;
  const remote = `${remoteBase}/_tmp-purge-contador.php`;
  fs.writeFileSync(local,
`<?php
if((string)($_GET['k'] ?? '') !== '${key}'){ http_response_code(403); exit('no'); }
require __DIR__.'/wp-load.php';
$out=[];
if(function_exists('litespeed_purge_all')){ litespeed_purge_all(); $out[]='purge_all'; }
do_action('litespeed_purge_all');
$paths=['/','/comprar/jeep-avenger/','/comprar/parcela/','/comprar/peugeot-208/','/comprar/sorteo-vista-mar-dunares/','/bonificaciones/jeep-avenger/','/bonificaciones/parcela/','/bonificaciones/peugeot-208/','/bonificaciones/sorteo-vista-mar-dunares/'];
foreach($paths as $p){ if(function_exists('litespeed_purge_url')){ litespeed_purge_url(home_url($p)); } }
if(function_exists('wp_cache_flush')){ wp_cache_flush(); $out[]='wp_flush'; }
echo 'ok '.implode(',',$out);
`);
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST||e.FTP_HOSTNAME, port: +(e.FTP_PORT||21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.ensureDir(remoteBase || '/');
    await client.uploadFrom(local, remote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-purge-contador.php?k=${key}`);
    console.log('PURGE', hit.status, hit.body.slice(0, 300));
    try { await client.remove(remote); } catch (_) {}
    try { fs.unlinkSync(local); } catch (_) {}
  } finally { client.close(); }

  const check = await fetchText('https://sorteoseguro.cl/comprar/jeep-avenger/');
  console.log('CHECK(no query)', check.status,
    'ss-contador=' + (check.body.includes('ss-contador') ? 'yes' : 'no'),
    'until=' + (check.body.includes('data-ss-contador-until') ? 'yes' : 'no'));
})().catch((err) => { console.error(err); process.exit(1); });
