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
const key = 'ss-pe-logs-' + Date.now();

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
$paths = [
  WP_CONTENT_DIR . '/debug.log',
  ABSPATH . 'wp-content/debug.log',
  ABSPATH . 'error_log',
  WP_CONTENT_DIR . '/error_log',
];
foreach ($paths as $p) {
  echo '=== ' . $p . ' === exists=' . (file_exists($p) ? 'yes' : 'no');
  if (file_exists($p)) {
    echo ' size=' . filesize($p) . "\\n";
    $fh = fopen($p, 'rb');
    fseek($fh, max(0, filesize($p) - 6000));
    echo fread($fh, 6000);
    fclose($fh);
    echo "\\n";
  } else {
    echo "\\n";
  }
}
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join('C:/Proyectos/sorteoseguro', 'scripts/_tmp-pe-logs.php');
  const remote = `${remoteBase}/_tmp-pe-logs.php`;
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
    const hit = await fetchText(`${site}/_tmp-pe-logs.php?k=${key}`);
    console.log('LOGS', hit.status);
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
