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
const key = 'ss-fix-packs-' + Date.now();

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

echo "=== ANTES ===\\n";
foreach ([45941 => 'jeep', 1091 => 'vista'] as $pid => $name) {
  echo $name . ' product_page=' . count(Promo_Engine_Stable::get_product_page_campaigns($pid)) . "\\n";
  echo $name . ' preload=' . count(Promo_Engine_Stable::get_preload_page_campaigns($pid)) . "\\n";
}

echo "=== campañas con _promo_end ===\\n";
$all = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids']);
$now = current_time('timestamp');
foreach ($all as $cid) {
  $end = (string) get_post_meta($cid, '_promo_end', true);
  if ($end === '') { continue; }
  $ts = strtotime(str_replace('/', '-', $end) . (strpos($end, ':') === false ? ' 23:59:59' : ''));
  $state = ($ts && $ts < $now) ? 'VENCIDA' : 'vigente';
  echo '  id=' . $cid . ' [' . $state . '] end="' . $end . '" type=' . get_post_meta($cid, '_promo_type', true) . ' title=' . get_the_title($cid) . "\\n";
}

echo "=== limpiando fin de packs regulares ===\\n";
foreach ([44640, 44653, 45526] as $cid) {
  $end = (string) get_post_meta($cid, '_promo_end', true);
  if ($end !== '') {
    update_post_meta($cid, '_promo_end', '');
    echo '  cleared ' . $cid . ' (' . get_the_title($cid) . ')\\n';
  }
}

echo "=== DESPUES ===\\n";
foreach ([45941 => 'jeep', 1091 => 'vista'] as $pid => $name) {
  $rows = Promo_Engine_Stable::get_product_page_campaigns($pid);
  echo $name . ' product_page=' . count($rows) . ' -> ';
  foreach ($rows as $r) { echo $r['id'] . ':' . $r['type'] . ' '; }
  echo "\\n";
  $pre = Promo_Engine_Stable::get_preload_page_campaigns($pid);
  echo $name . ' preload=' . count($pre) . ' -> ';
  foreach ($pre as $r) { echo $r['id'] . ':' . $r['type'] . ' '; }
  echo "\\n";
}
if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
try { if (class_exists('LiteSpeed\\Purge')) { LiteSpeed\\Purge::purge_all(); echo "purged\\n"; } } catch (\\Throwable $e) {}
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-fix-packs.php');
  const remote = `${remoteBase}/_tmp-fix-packs.php`;
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
    const hit = await fetchText(`${site}/_tmp-fix-packs.php?k=${key}`);
    console.log('FIX', hit.status);
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
