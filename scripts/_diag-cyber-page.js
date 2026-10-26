const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');

const env = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);
const remoteBase = (env.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const site = (env.SITE_URL || 'https://sorteoseguro.cl').replace(/\/$/, '');
const key = 'ss-cyber-page-' + Date.now();

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { rejectUnauthorized: false }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
      })
      .on('error', reject);
  });
}

const php = `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/wp-load.php';
$cid = wp_insert_post(['post_title' => 'SS CYBER PAGE TEST ' . time(), 'post_type' => 'promo_campaign', 'post_status' => 'publish'], true);
if (is_wp_error($cid)) { echo 'insert_err=' . $cid->get_error_message() . "\\n"; exit; }
$cid = (int) $cid;
update_post_meta($cid, '_promo_type', 'bulk');
update_post_meta($cid, '_promo_bulk_qty', 30);
update_post_meta($cid, '_promo_bulk_price', 50000);
update_post_meta($cid, '_promo_show_on_cyber', 'yes');
update_post_meta($cid, '_promo_cyber_lotteries', [45941]);
update_post_meta($cid, '_promo_show_on_product', 'no');
update_post_meta($cid, '_promo_limit_per_user', 0);
update_post_meta($cid, '_promo_segment_type', 'all');
update_post_meta($cid, '_promo_stackable', 'yes');
echo 'cid=' . $cid . "\\n";
$resp = wp_remote_get(home_url('/cyber/jeep-avenger/?ssv=diagpage'), ['sslverify' => false, 'timeout' => 40]);
$code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);
$body = is_wp_error($resp) ? '' : (string) wp_remote_retrieve_body($resp);
echo 'http=' . $code . ' len=' . strlen($body) . "\\n";
echo 'has_cid=' . (strpos($body, '"id":' . $cid) !== false ? 'yes' : 'no') . "\\n";
echo 'has_buy30=' . (strpos($body, '"buy":30') !== false ? 'yes' : 'no') . "\\n";
echo 'has_pack50000=' . (strpos($body, '"pack_price":50000') !== false ? 'yes' : 'no') . "\\n";
echo 'has_placeholder=' . (strpos($body, 'ss-cyber-nuevo-diseno') !== false ? 'yes' : 'no') . "\\n";
wp_delete_post($cid, true);
echo 'deleted=' . $cid . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-cyber-page.php');
  const remote = `${remoteBase}/_tmp-cyber-page.php`;
  try {
    await client.access({
      host: env.FTP_HOST || env.FTP_HOSTNAME,
      port: +(env.FTP_PORT || 21),
      user: env.FTP_USER,
      password: env.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });
    fs.writeFileSync(local, php);
    await client.uploadFrom(local, remote);
    const hit = await fetchText(`${site}/_tmp-cyber-page.php?k=${key}`);
    console.log('PAGE DIAG', hit.status);
    console.log(hit.body);
    try { await client.remove(remote); } catch (_) {}
    try { fs.unlinkSync(local); } catch (_) {}
  } finally {
    client.close();
  }
}
main().catch((e) => {
  console.error(e);
  process.exit(1);
});
