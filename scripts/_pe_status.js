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
const key = 'ss-pe-status-' + Date.now();

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
echo 'php=' . PHP_VERSION . "\\n";
echo 'class=' . (class_exists('Promo_Engine_Stable') ? 'yes' : 'no') . "\\n";
$active = (array) get_option('active_plugins');
echo 'active_count=' . count($active) . "\\n";
foreach ($active as $p) { if (strpos($p, 'promo-engine') !== false) { echo 'active_pe=' . $p . "\\n"; } }
$all = function_exists('get_plugins') ? get_plugins() : [];
foreach ($all as $file => $data) {
  if (strpos($file, 'promo-engine') !== false) { echo 'found_plugin=' . $file . "\\n"; }
}
echo 'recovery_last=' . get_option('recovery_mode_email_last_sent') . "\\n";
echo 'paused=' . (function_exists('wp_is_recovery_mode') && wp_is_recovery_mode() ? 'yes' : 'no') . "\\n";
$f = WP_PLUGIN_DIR . '/promo-engine/promo-engine.php';
echo 'file_exists=' . (file_exists($f) ? 'yes' : 'no') . ' size=' . (@filesize($f) ?: 0) . "\\n";
$src = @file_get_contents($f);
echo 'src_len=' . strlen((string) $src) . ' has_class=' . (strpos((string) $src, 'final class Promo_Engine_Stable') !== false ? 'yes' : 'no') . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-pe-status.php');
  const remote = `${remoteBase}/_tmp-pe-status.php`;
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
    const hit = await fetchText(`${site}/_tmp-pe-status.php?k=${key}`);
    console.log('STATUS', hit.status);
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
