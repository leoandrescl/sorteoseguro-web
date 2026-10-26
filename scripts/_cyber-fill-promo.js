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
const key = 'ss-cyber-fill-' + Date.now();

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

$end = '2026-10-02 23:59';
$q = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 50,
  'meta_query' => [['key' => '_promo_show_on_cyber', 'value' => 'yes']]]);
echo 'cyber_campaigns=' . count($q) . "\\n";
foreach ($q as $p) {
  $id = (int) $p->ID;
  $lots = array_map('absint', (array) get_post_meta($id, '_promo_cyber_lotteries', true));
  echo 'id=' . $id . ' status=' . $p->post_status . ' title="' . $p->post_title . '" lots=' . implode(',', $lots) . "\\n";
  echo '  cyber_title="' . get_post_meta($id, '_promo_cyber_title', true) . '" end="' . get_post_meta($id, '_promo_end', true) . '"' . "\\n";
  if (in_array(1091, $lots, true)) {
    update_post_meta($id, '_promo_cyber_title', 'Pack Cyber Vista');
    update_post_meta($id, '_promo_cyber_bullet_1', 'Participa por la Casa Vista Mar Dunares');
    update_post_meta($id, '_promo_cyber_bullet_2', 'Incluye los 2 autos 0 Km.');
    update_post_meta($id, '_promo_cyber_bullet_3', 'Más oportunidades de ganar');
    if ((string) get_post_meta($id, '_promo_end', true) === '') {
      update_post_meta($id, '_promo_end', $end);
    }
    echo "  -> textos cargados\\n";
  }
}
echo 'banner=' . wp_json_encode(Promo_Engine_Stable::get_cyber_banner_data(1091)) . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-cyber-fill.php');
  const remote = `${remoteBase}/_tmp-cyber-fill.php`;
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
    const hit = await fetchText(`${site}/_tmp-cyber-fill.php?k=${key}`);
    console.log('FILL', hit.status);
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
