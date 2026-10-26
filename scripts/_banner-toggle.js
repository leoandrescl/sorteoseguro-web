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
const key = 'ssbannertoggle';
const action = process.argv[2] || 'on';
const campaign = parseInt(process.argv[3] || '85256', 10);
const bootLocal = path.join('C:/Proyectos/sorteoseguro/scripts', '_tmp-banner-toggle.php');
const bootRemote = `${remoteBase}/_tmp-banner-toggle.php`;
fs.writeFileSync(bootLocal, `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
require __DIR__ . '/wp-load.php';
$cid = ${campaign};
$act = '${action}';
if (!get_post($cid)) { exit('campaign not found'); }
if ($act === 'on') {
  update_post_meta($cid, '_promo_show_on_product_banner', 'yes');
  update_post_meta($cid, '_promo_show_on_product', 'yes');
  echo "enabled {$cid}\\n";
} else {
  delete_post_meta($cid, '_promo_show_on_product_banner');
  echo "disabled {$cid}\\n";
}
if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
do_action('litespeed_purge_all');
echo 'banner_meta=' . (get_post_meta($cid, '_promo_show_on_product_banner', true) ?: '-') . "\\n";
echo "done\\n";
`);
function fetchText(url){return new Promise((res,rej)=>{https.get(url,{rejectUnauthorized:false,headers:{'Cache-Control':'no-cache'}},r=>{let b='';r.on('data',d=>b+=d);r.on('end',()=>res({status:r.statusCode,body:b}));}).on('error',rej);});}
(async()=>{
  const client = new ftp.Client(180000);
  try {
    await client.access({ host: env.FTP_HOST||env.FTP_HOSTNAME, port:+(env.FTP_PORT||21), user:env.FTP_USER, password:env.FTP_PASSWORD, secure:true, secureOptions:{rejectUnauthorized:false} });
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`${site}/_tmp-banner-toggle.php?k=${key}&action=${action}&c=${campaign}`);
    console.log(hit.body);
    try { await client.remove(bootRemote); } catch(_) {}
    try { fs.unlinkSync(bootLocal); } catch(_) {}
  } finally { client.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
