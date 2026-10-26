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
const rel = 'mu-plugins/sorteoseguro-flow-auto-complete.php';
const local = `C:/Proyectos/sorteoseguro/${rel}`;
const remote = `${remoteBase}/wp-content/${rel}`;

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache', 'User-Agent': 'Mozilla/5.0' } }, (res) => {
      let body=''; res.on('data',(d)=>(body+=d));
      res.on('end',()=>resolve({status:res.statusCode, body}));
    }).on('error', reject);
  });
}

async function main() {
  const client = new ftp.Client(180000);
  try {
    await client.access({ host: e.FTP_HOST||e.FTP_HOSTNAME, port: +(e.FTP_PORT||21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.ensureDir(path.posix.dirname(remote));
    try { await client.rename(remote, `${remote}.bak-${stamp}`); console.log('remote backup ok'); }
    catch (_) { console.log('remote backup skip (nuevo)'); }
    await client.uploadFrom(local, remote);
    console.log('uploaded', rel);

    const key = 'ss-flow-ac-20260928';
    const bootLocal = `C:/Proyectos/sorteoseguro/scripts/_tmp-flow-flush.php`;
    const bootRemote = `${remoteBase}/_tmp-flow-flush.php`;
    fs.writeFileSync(bootLocal,
`<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo "forbidden\\n"; exit; }
require __DIR__ . '/wp-load.php';
echo 'woo=' . (class_exists('WooCommerce') ? 'yes' : 'no') . "\\n";
echo 'class=' . (class_exists('SorteoSeguro_Flow_Auto_Complete') ? 'yes' : 'no') . "\\n";
echo 'version=' . (class_exists('SorteoSeguro_Flow_Auto_Complete') ? SorteoSeguro_Flow_Auto_Complete::VERSION : 'n/a') . "\\n";
echo 'cutoff=' . (string)get_option('ss_flow_ac_cutoff','') . "\\n";
echo 'cron=' . (wp_next_scheduled('ss_flow_ac_scan_pending') ? 'scheduled' : 'no') . "\\n";
if (function_exists('litespeed_purge_all')) { litespeed_purge_all(); echo "purge_all\\n"; }
do_action('litespeed_purge_all');
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_flush\\n"; }
echo "done\\n";
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-flow-flush.php?k=${key}`);
    console.log('CHECK', hit.status, JSON.stringify(hit.body.slice(0, 600)));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally { client.close(); }

  const home = await fetchText('https://sorteoseguro.cl/?ssv=flow1');
  console.log('SITE', home.status, 'fatal=' + (/Fatal error/i.test(home.body) ? 'YES' : 'no'));
}
main().catch((err)=>{ console.error(err); process.exit(1); });
