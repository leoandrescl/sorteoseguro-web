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
const localBase = 'C:/Proyectos/sorteoseguro';
const site = (env.SITE_URL || 'https://sorteoseguro.cl').replace(/\/$/, '');
const key = 'ss-cyber-clean-' + Date.now();

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
require __DIR__ . '/wp-load.php';
$q = get_posts(['post_type' => 'promo_campaign', 'post_status' => 'any', 'posts_per_page' => 20, 's' => 'SS CYBER']);
echo 'test_campaigns=' . count($q) . "\\n";
foreach ($q as $p) {
  echo 'deleting ' . $p->ID . ' ' . $p->post_title . "\\n";
  wp_delete_post((int) $p->ID, true);
}
$p = get_page_by_path('cyber/jeep-avenger');
echo 'cyber_child=' . ($p ? $p->ID : 'MISSING') . "\\n";
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join(localBase, 'scripts/_tmp-cyber-clean.php');
  const remote = `${remoteBase}/_tmp-cyber-clean.php`;
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
    const hit = await fetchText(`${site}/_tmp-cyber-clean.php?k=${key}`);
    console.log('CLEANUP', hit.status);
    console.log(hit.body);
    try { await client.remove(remote); } catch (_) {}
    try { fs.unlinkSync(local); } catch (_) {}
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
