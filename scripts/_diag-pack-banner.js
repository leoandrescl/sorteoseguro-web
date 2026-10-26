const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');
const env = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const remoteBase = (env.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const site = (env.SITE_URL || 'https://sorteoseguro.cl').replace(/\/$/, '');
const key = 'ssbannerdiag';
const bootLocal = path.join('C:/Proyectos/sorteoseguro/scripts', '_tmp-banner-diag.php');
const bootRemote = `${remoteBase}/_tmp-banner-diag.php`;
fs.writeFileSync(bootLocal, `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
require __DIR__ . '/wp-load.php';
if (!class_exists('Promo_Engine_Stable')) { exit('no engine'); }
foreach ([85256, 84830] as $cid) {
  echo "#{$cid} type=" . get_post_meta($cid, '_promo_type', true) . " show_product=" . (get_post_meta($cid, '_promo_show_on_product', true) ?: '-') . " banner=" . (get_post_meta($cid, '_promo_show_on_product_banner', true) ?: '-') . " status=" . get_post_status($cid) . " end=" . get_post_meta($cid, '_promo_end', true) . "\\n";
}
foreach ([45941, 1091] as $pid) {
  echo "=== product {$pid}: campaign ids = ";
  $ids = [];
  foreach (Promo_Engine_Stable::get_product_page_campaigns($pid) as $c) { $ids[] = $c['id']; }
  echo implode(',', $ids) . "\\n";
  echo "banners=" . json_encode(Promo_Engine_Stable::get_product_page_banners($pid)) . "\\n";
}
echo "done\\n";
`);
function fetchText(url){return new Promise((res,rej)=>{https.get(url,{rejectUnauthorized:false},r=>{let b='';r.on('data',d=>b+=d);r.on('end',()=>res({status:r.statusCode,body:b}));}).on('error',rej);});}
(async()=>{
  const client = new ftp.Client(180000);
  try {
    await client.access({ host: env.FTP_HOST||env.FTP_HOSTNAME, port:+(env.FTP_PORT||21), user:env.FTP_USER, password:env.FTP_PASSWORD, secure:true, secureOptions:{rejectUnauthorized:false} });
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`${site}/_tmp-banner-diag.php?k=${key}`);
    console.log(hit.body);
    try { await client.remove(bootRemote); } catch(_) {}
    try { fs.unlinkSync(bootLocal); } catch(_) {}
  } finally { client.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
