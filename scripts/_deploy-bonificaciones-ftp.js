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
  'sorteoseguro-comprar-bono.php',
  'sorteoseguro-comprar-bono/page-oferta.php',
  'sorteoseguro-pdp-templates.php',
].map((rel) => ({ local: path.join(localBase, rel), remote: `${remoteBase}/wp-content/mu-plugins/${rel}` }));
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
    for (const f of files) {
      await client.ensureDir(path.posix.dirname(f.remote));
      try { await client.rename(f.remote, `${f.remote}.bak-${stamp}`); console.log('remote backup ok', path.posix.basename(f.remote)); }
      catch (_) { console.log('remote backup skip', path.posix.basename(f.remote)); }
      await client.uploadFrom(f.local, f.remote);
      console.log('uploaded', f.remote.replace(remoteBase,''));
    }
    // Boot: fuerza init (corre migración + seed) y reporta estado de páginas.
    const key = 'ss-boni-seed-20260915';
    const bootLocal = 'C:/Proyectos/sorteoseguro/scripts/_tmp-boni-seed.php';
    const bootRemote = `${remoteBase}/_tmp-boni-seed.php`;
    fs.writeFileSync(bootLocal,
`<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo "forbidden\\n"; exit; }
require __DIR__ . '/wp-load.php';
echo 'ver_bono=' . (class_exists('SorteoSeguro_Comprar_Bono') ? SorteoSeguro_Comprar_Bono::VERSION : '?') . "\\n";
SorteoSeguro_Comprar_Bono::maybe_seed_pages();
echo "seed=done\\n";
$new = get_page_by_path('bonificaciones');
$old = get_page_by_path('oferta');
echo 'parent_new=' . ($new ? ($new->ID . ' slug=' . $new->post_name . ' title=' . $new->post_title) : 'missing') . "\\n";
echo 'parent_old=' . ($old ? ($old->ID . ' slug=' . $old->post_name) : 'gone') . "\\n";
if ($new) {
  $kids = get_posts(['post_type'=>'page','post_status'=>'publish','post_parent'=>(int)$new->ID,'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true]);
  echo 'children=' . count($kids) . "\\n";
  foreach ($kids as $kid) {
    echo 'kid ' . $kid . ' ' . get_post_field('post_name',$kid) . ' | ' . get_post_field('post_title',$kid) . ' | ' . get_permalink($kid) . "\\n";
  }
  $pid = SorteoSeguro_Comprar_Bono::current_product_id();
  echo 'current_product_id_here=' . $pid . "\\n";
}
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "flushed\\n"; }
echo "done\\n";
`);
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-boni-seed.php?k=${key}`);
    console.log('SEED', hit.status, hit.body.slice(0, 2000));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally { client.close(); }
  // Verificación HTTP: nueva URL 200, vieja URL 301, padre redirige.
  for (const u of ['https://sorteoseguro.cl/bonificaciones/jeep-avenger/?ssv=bn140',
                   'https://sorteoseguro.cl/bonificaciones/?ssv=bn140']) {
    const r = await fetchText(u);
    console.log('NEW', r.status, u, 'loc=' + (r.headers.location || '-'),
      'marquee=' + (r.body.includes('ss-pdp-marquee') ? 'yes' : 'no'));
  }
  for (const u of ['https://sorteoseguro.cl/oferta/jeep-avenger/?ssv=bn140',
                   'https://sorteoseguro.cl/oferta/?ssv=bn140']) {
    const r = await fetchText(u);
    console.log('OLD', r.status, u, 'loc=' + (r.headers.location || '-'));
  }
}
main().catch((err)=>{ console.error(err); process.exit(1); });
