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
const rel = 'sorteoseguro-pdp-templates.php';
const local = 'C:/Proyectos/sorteoseguro/mu-plugins/' + rel;
const remote = `${remoteBase}/wp-content/mu-plugins/${rel}`;

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false }, (res) => {
      let body=''; res.on('data',(d)=>(body+=d)); res.on('end',()=>resolve({status:res.statusCode,body}));
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
    catch (_) { console.log('remote backup skip (no prev)'); }
    await client.uploadFrom(local, remote);
    console.log('uploaded', remote.replace(remoteBase,''));
    const bootLocal = 'C:/Proyectos/sorteoseguro/scripts/_tmp-marquee-gallery-purge.php';
    const bootRemote = `${remoteBase}/_tmp-marquee-gallery-purge.php`;
    const key = 'ss-marquee-gallery-20260915';
    fs.writeFileSync(bootLocal,
`<?php
require __DIR__ . '/wp-load.php';
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { status_header(403); echo "forbidden\\n"; exit; }
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flush\\n"; }
if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo "litespeed_purged\\n"; }
echo 'has_marquee=' . (method_exists('SorteoSeguro_PDP_Templates','render_marquee')?'yes':'no') . "\\n";
echo 'has_gallery_helper=' . (method_exists('SorteoSeguro_PDP_Templates','product_gallery_ids')?'yes':'no') . "\\n";
echo 'ver=' . (class_exists('SorteoSeguro_PDP_Templates') ? SorteoSeguro_PDP_Templates::VERSION : '?') . "\\n";
$pid = 45941;
if (class_exists('SorteoSeguro_PDP_Templates')) {
  $imgs = SorteoSeguro_PDP_Templates::product_marquee_images($pid);
  $gids = SorteoSeguro_PDP_Templates::product_gallery_ids($pid);
  echo 'marquee_count_'.$pid.'='.count($imgs)."\\n";
  echo 'gallery_ids_'.$pid.'='.implode(',', $gids)."\\n";
}
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-marquee-gallery-purge.php?k=${key}`);
    console.log('purge', hit.status, hit.body.trim());
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
    console.log('deploy ok marquee-gallery-auto via ftp');
  } finally { client.close(); }
}
main().catch((err)=>{ console.error(err); process.exit(1); });
