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
const key = 'ss-reg-diag-' + Date.now();

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
require_once ABSPATH . 'wp-admin/includes/plugin.php';

echo 'pe_active=' . (is_plugin_active('promo-engine/promo-engine.php') ? 'yes' : 'no') . "\\n";
echo 'pe_class=' . (class_exists('Promo_Engine_Stable') ? 'yes' : 'no') . "\\n";
echo 'packs_class=' . (class_exists('SorteoSeguro_Packs_Lottery') ? 'yes' : 'no') . "\\n";

$pids = [45941 => 'jeep', 1091 => 'vista', 49102 => 'parcela', 53210 => 'peugeot'];
foreach ($pids as $pid => $name) {
  echo "=== $name $pid ===\\n";
  if (class_exists('Promo_Engine_Stable')) {
    $rows = Promo_Engine_Stable::get_product_page_campaigns($pid);
    echo '  product_page=' . count($rows) . "\\n";
    foreach ($rows as $r) { echo '    id=' . $r['id'] . ' type=' . $r['type'] . ' buy=' . $r['buy'] . ' pay=' . $r['pay'] . "\\n"; }
    $pre = Promo_Engine_Stable::get_preload_page_campaigns($pid);
    echo '  preload=' . count($pre) . "\\n";
    foreach ($pre as $r) { echo '    id=' . $r['id'] . ' type=' . $r['type'] . "\\n"; }
  }
}

foreach ([74487, 45526, 44653, 44640] as $cid) {
  $p = get_post($cid);
  echo '--- seed campaign ' . $cid . ' status=' . ($p ? $p->post_status : 'MISSING') . ' title=' . ($p ? $p->post_title : '') . "\\n";
  foreach (['_promo_type','_promo_show_on_product','_promo_target_lotteries','_promo_show_on_preload','_promo_preload_lotteries','_promo_start','_promo_end','_promo_limit_total','_promo_redemptions','_promo_product_sort'] as $k) {
    echo '    ' . $k . '=' . wp_json_encode(get_post_meta($cid, $k, true)) . "\\n";
  }
}

$q = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 200,
  'meta_query' => [['key' => '_promo_show_on_product', 'value' => 'yes']]]);
echo 'show_on_product_yes_total=' . count($q) . "\\n";
foreach ($q as $p) { echo '  sp id=' . $p->ID . ' status=' . $p->post_status . ' title=' . $p->post_title . "\\n"; }

$q2 = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 200,
  'meta_query' => [['key' => '_promo_show_on_preload', 'value' => 'yes']]]);
echo 'show_on_preload_yes_total=' . count($q2) . "\\n";
foreach ($q2 as $p) { echo '  pl id=' . $p->ID . ' status=' . $p->post_status . ' title=' . $p->post_title . "\\n"; }

$all = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids']);
echo 'total_campaigns=' . count($all) . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-reg-diag.php');
  const remote = `${remoteBase}/_tmp-reg-diag.php`;
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
    const hit = await fetchText(`${site}/_tmp-reg-diag.php?k=${key}`);
    console.log('REG DIAG', hit.status);
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
