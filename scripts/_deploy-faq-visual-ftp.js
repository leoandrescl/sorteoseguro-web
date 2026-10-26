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
const key = 'ssfaqvisual' + stamp;

const relPhp = [
  'wp-content/mu-plugins/sorteoseguro-home/page-home.php',
  'wp-content/mu-plugins/sorteoseguro-autos/page-autos.php',
  'wp-content/mu-plugins/sorteoseguro-propiedades/page-propiedades.php',
];
const relAsset = 'wp-content/mu-plugins/sorteoseguro-home/assets/faq-visual.jpeg';
const localAsset = path.join(projectRoot, 'mu-plugins/sorteoseguro-home/assets/faq-visual.jpeg');

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false }, (res) => {
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

    await client.ensureDir(path.posix.dirname(`${remoteBase}/${relAsset}`));
    await client.cd(remoteBase || '/');
    try { await client.rename(`${remoteBase}/${relAsset}`, `${remoteBase}/${relAsset}.bak-${stamp}`); console.log('asset backup ok'); }
    catch (_) { console.log('asset backup skip (no prev)'); }
    await client.uploadFrom(localAsset, `${remoteBase}/${relAsset}`);
    console.log('uploaded', relAsset);

    const list = relPhp.map((r) => `'${r}'`).join(',');
    const php = `<?php
$key='${key}';
if((string)($_GET['k']??'')!==$key){http_response_code(403);exit('forbidden');}
ini_set('display_errors','1');error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');
require __DIR__.'/wp-load.php';
foreach([${list}] as $rel){
  $p=ABSPATH.$rel;
  if(!file_exists($p)){echo "missing $rel\\n";continue;}
  $c=file_get_contents($p);
  $n=str_replace('faq-visual.png','faq-visual.jpeg',$c,$count);
  if($count>0){
    copy($p,$p.'.bak-faqjpeg-${stamp}');
    file_put_contents($p,$n);
    echo "replaced $count in $rel\\n";
  } else { echo "no-match $rel\\n"; }
}
if(function_exists('litespeed_purge_all')){litespeed_purge_all();echo "ls_purge_all\\n";}
else { do_action('litespeed_purge_all'); echo "ls_purge_action\\n"; }
if(function_exists('wp_cache_flush')){wp_cache_flush();echo "wp_cache_flush\\n";}
`;
    const localPhp = path.join(projectRoot, 'scripts', '_tmp-faq-visual-deploy.php');
    const remotePhp = `${remoteBase}/_tmp-faq-visual-deploy.php`;
    fs.writeFileSync(localPhp, php);
    await client.cd(remoteBase || '/');
    await client.uploadFrom(localPhp, remotePhp);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-faq-visual-deploy.php?k=${key}`);
    console.log('remote', hit.status, hit.body.trim());
    try { await client.remove(remotePhp); } catch (_) {}
    try { fs.unlinkSync(localPhp); } catch (_) {}
    console.log('deploy ok faq-visual');
  } finally { client.close(); }
}
main().catch((err) => { console.error(err); process.exit(1); });
