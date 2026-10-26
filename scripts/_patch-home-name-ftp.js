const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');

const projectRoot = path.resolve(__dirname, '..');
const e = Object.fromEntries(
  fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const key = 'sspatchname' + stamp;
const FROM = 'clamp(16px, 4.7vw, 20px)';
const TO = 'clamp(14px, 3.6vw, 20px)';
const TARGET = 'wp-content/mu-plugins/sorteoseguro-home/assets/home.css';

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' }, rejectUnauthorized: false }, (res) => {
      let body = ''; res.on('data', (d) => (body += d)); res.on('end', () => resolve({ status: res.statusCode, body }));
    }).on('error', reject);
  });
}

async function main() {
  const client = new ftp.Client(120000);
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false },
    });

    const php = `<?php
$key='${key}';
if((string)($_GET['k']??'')!==$key){http_response_code(403);exit('forbidden');}
header('Content-Type: text/plain; charset=utf-8');
require __DIR__.'/wp-load.php';
$p=ABSPATH.'${TARGET}';
if(!file_exists($p)){echo "missing\\n";exit;}
$c=file_get_contents($p);
$n=str_replace('${FROM}','${TO}',$c,$count);
if($count>0){copy($p,$p.'.bak-name-${stamp}');file_put_contents($p,$n);echo "replaced $count\\n";}
else{echo "no-match\\n";}
if(function_exists('litespeed_purge_all')){litespeed_purge_all();echo "ls_purge_all\\n";}else{do_action('litespeed_purge_all');echo "ls_purge_action\\n";}
if(function_exists('wp_cache_flush')){wp_cache_flush();echo "wp_cache_flush\\n";}
`;
    const localPhp = path.join(projectRoot, 'scripts', '_tmp-home-name-patch.php');
    const remotePhp = `${remoteBase}/_tmp-home-name-patch.php`;
    fs.writeFileSync(localPhp, php);
    await client.cd(remoteBase || '/');
    await client.uploadFrom(localPhp, remotePhp);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-home-name-patch.php?k=${key}`);
    console.log('remote', hit.status, hit.body.trim());
    try { await client.remove(remotePhp); } catch (_) {}
    try { fs.unlinkSync(localPhp); } catch (_) {}
    console.log('patch done');
  } finally { client.close(); }
}
main().catch((err) => { console.error(err); process.exit(1); });
