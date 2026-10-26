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
const key = 'ss-pe-act-' + Date.now();

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
$plugin = 'promo-engine/promo-engine.php';
echo 'before=' . (is_plugin_active($plugin) ? 'active' : 'inactive') . "\\n";
$res = activate_plugin($plugin);
echo 'res=' . var_export($res, true) . "\\n";
echo 'after=' . (is_plugin_active($plugin) ? 'active' : 'inactive') . "\\n";
echo 'class=' . (class_exists('Promo_Engine_Stable') ? 'yes' : 'no') . "\\n";
echo 'cyber_method=' . (method_exists('Promo_Engine_Stable', 'get_cyber_page_campaigns') ? 'yes' : 'no') . "\\n";
if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-pe-act.php');
  const remote = `${remoteBase}/_tmp-pe-act.php`;
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
    const hit = await fetchText(`${site}/_tmp-pe-act.php?k=${key}`);
    console.log('ACTIVATE', hit.status);
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
