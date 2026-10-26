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
const key = 'ss-cyber-banner-' + Date.now();

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
$cid = wp_insert_post(['post_title' => 'SS CYBER BANNER TEST ' . time(), 'post_type' => 'promo_campaign', 'post_status' => 'publish'], true);
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
update_post_meta($cid, '_promo_cyber_title', 'Pack Cyber Vista');
update_post_meta($cid, '_promo_cyber_bullet_1', 'Participa por la Casa Vista Mar Dunares');
update_post_meta($cid, '_promo_cyber_bullet_2', 'Incluye los 2 autos 0 Km.');
update_post_meta($cid, '_promo_cyber_bullet_3', 'Más oportunidades de ganar');
update_post_meta($cid, '_promo_end', date('Y-m-d H:i', time() + 86400));
echo 'cid=' . $cid . "\\n";

$banner = Promo_Engine_Stable::get_cyber_banner_data(45941);
echo 'banner=' . wp_json_encode($banner) . "\\n";

$resp = wp_remote_get(home_url('/cyber/jeep-avenger/?ssv=banner'), ['sslverify' => false, 'timeout' => 40]);
$body = is_wp_error($resp) ? '' : (string) wp_remote_retrieve_body($resp);
echo 'jeep_http=' . (is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp)) . "\\n";
echo 'has_banner=' . (strpos($body, 'class="ss-cyber-banner"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_until=' . (strpos($body, 'data-ss-cyber-until') !== false ? 'yes' : 'no') . "\\n";
echo 'has_title=' . (strpos($body, 'Pack Cyber Vista') !== false ? 'yes' : 'no') . "\\n";
echo 'has_price=' . (strpos($body, '$50.000') !== false ? 'yes' : 'no') . "\\n";
echo 'has_list=' . (strpos($body, '$150.000') !== false ? 'yes' : 'no') . "\\n";
echo 'has_card_top=' . (strpos($body, 'class="ss-cyber-banner__card-top"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_bullets=' . (strpos($body, 'class="ss-cyber-banner__bullets"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_bullet1=' . (strpos($body, 'Participa por la Casa Vista Mar Dunares') !== false ? 'yes' : 'no') . "\\n";
echo 'has_deco_last=' . (strpos($body, 'class="ss-cyber-banner__deco-ticket-last"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_timer=' . (strpos($body, 'class="ss-cyber-banner__timer"') !== false ? 'yes' : 'no') . "\\n";
echo 'has_units=' . (strpos($body, 'data-ss-cyber-h') !== false ? 'yes' : 'no') . "\\n";
echo 'has_js=' . (strpos($body, 'cyber.js') !== false ? 'yes' : 'no') . "\\n";
echo 'has_placeholder_jeep=' . (strpos($body, 'class="ss-cyber-nuevo-diseno"') !== false ? 'yes' : 'no') . "\\n";

$resp2 = wp_remote_get(home_url('/cyber/peugeot-208/?ssv=banner'), ['sslverify' => false, 'timeout' => 40]);
$body2 = is_wp_error($resp2) ? '' : (string) wp_remote_retrieve_body($resp2);
echo 'peugeot_http=' . (is_wp_error($resp2) ? 0 : wp_remote_retrieve_response_code($resp2)) . "\\n";
echo 'peugeot_has_banner=' . (strpos($body2, 'class="ss-cyber-banner"') !== false ? 'yes' : 'no') . "\\n";
echo 'peugeot_has_placeholder=' . (strpos($body2, 'class="ss-cyber-nuevo-diseno"') !== false ? 'yes' : 'no') . "\\n";

wp_delete_post($cid, true);
echo 'deleted=' . $cid . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-cyber-banner.php');
  const remote = `${remoteBase}/_tmp-cyber-banner.php`;
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
    const hit = await fetchText(`${site}/_tmp-cyber-banner.php?k=${key}`);
    console.log('BANNER DIAG', hit.status);
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
