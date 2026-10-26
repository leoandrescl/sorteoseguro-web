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
const key = 'ss-cyber-vista-' + Date.now();

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

$q = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 50,
  'meta_query' => [['key' => '_promo_show_on_cyber', 'value' => 'yes']]]);
echo 'cyber_campaigns=' . count($q) . "\\n";
$keys = ['_promo_type','_promo_bulk_qty','_promo_bulk_price','_promo_show_on_cyber','_promo_cyber_lotteries','_promo_cyber_title','_promo_cyber_bullet_1','_promo_cyber_bullet_2','_promo_cyber_bullet_3','_promo_segment_type','_promo_segment_data','_promo_audience_condition','_promo_audience_from','_promo_audience_to','_promo_start','_promo_end','_promo_limit_total','_promo_redemptions'];
foreach ($q as $p) {
  echo '--- id=' . $p->ID . ' status=' . $p->post_status . ' title=' . $p->post_title . "\\n";
  foreach ($keys as $k) { echo '   ' . $k . '=' . wp_json_encode(get_post_meta($p->ID, $k, true)) . "\\n"; }
}

echo '=== temp campaign on 1091 (Vista) ===' . "\\n";
$cid = wp_insert_post(['post_title' => 'SS CYBER VISTA TEST ' . time(), 'post_type' => 'promo_campaign', 'post_status' => 'publish'], true);
$cid = (int) $cid;
update_post_meta($cid, '_promo_type', 'bulk');
update_post_meta($cid, '_promo_bulk_qty', 30);
update_post_meta($cid, '_promo_bulk_price', 50000);
update_post_meta($cid, '_promo_show_on_cyber', 'yes');
update_post_meta($cid, '_promo_cyber_lotteries', [1091]);
update_post_meta($cid, '_promo_show_on_product', 'no');
update_post_meta($cid, '_promo_limit_per_user', 0);
update_post_meta($cid, '_promo_segment_type', 'all');
update_post_meta($cid, '_promo_stackable', 'yes');
update_post_meta($cid, '_promo_cyber_title', 'Pack Cyber Vista');
update_post_meta($cid, '_promo_cyber_bullet_1', 'Participa por la Casa Vista Mar Dunares');
update_post_meta($cid, '_promo_cyber_bullet_2', 'Incluye los 2 autos 0 Km.');
update_post_meta($cid, '_promo_cyber_bullet_3', 'Más oportunidades de ganar');
update_post_meta($cid, '_promo_end', date('Y-m-d H:i', time() + 86400));
$banner = Promo_Engine_Stable::get_cyber_banner_data(1091);
echo 'banner=' . wp_json_encode($banner) . "\\n";
$resp = wp_remote_get(home_url('/cyber/sorteo-vista-mar-dunares/?ssv=vista'), ['sslverify' => false, 'timeout' => 40]);
$body = is_wp_error($resp) ? '' : (string) wp_remote_retrieve_body($resp);
echo 'http=' . (is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp)) . "\\n";
echo 'has_banner=' . (strpos($body, 'class="ss-cyber-banner"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_bullets=' . (strpos($body, 'class="ss-cyber-banner__bullets"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_timer=' . (strpos($body, 'class="ss-cyber-banner__timer"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_price=' . (strpos($body, '$50.000') !== false ? 'yes' : 'no') . "\\n";
wp_delete_post($cid, true);
echo 'deleted=' . $cid . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-cyber-vista.php');
  const remote = `${remoteBase}/_tmp-cyber-vista.php`;
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
    require('child_process').execSync(`php -l "${local}"`, { stdio: 'pipe' });
    await client.uploadFrom(local, remote);
    const hit = await fetchText(`${site}/_tmp-cyber-vista.php?k=${key}`);
    console.log('VISTA DIAG', hit.status);
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
