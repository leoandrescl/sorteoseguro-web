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

const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const remoteBase = (env.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const localBase = 'C:/Proyectos/sorteoseguro';
const site = (env.SITE_URL || 'https://sorteoseguro.cl').replace(/\/$/, '');
const key = 'ss-checkout-gw-' + stamp;

const files = [
  'mu-plugins/sorteoseguro-checkout.php',
  'mu-plugins/sorteoseguro-checkout/assets/checkout.css',
  'mu-plugins/sorteoseguro-chrome/assets/logo-tuu.png',
  'mu-plugins/sorteoseguro-chrome/assets/logo-flow.png',
].map((rel) => ({ local: path.join(localBase, rel), remote: `${remoteBase}/wp-content/${rel}` }));

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache' } }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, body }));
      })
      .on('error', reject);
  });
}

async function backupRemote(client, remote, stamp) {
  const tmp = path.join(localBase, 'scripts', '_tmp-remote-backup.bin');
  try {
    await client.downloadTo(tmp, remote);
    await client.uploadFrom(tmp, `${remote}.bak-${stamp}`);
    fs.unlinkSync(tmp);
    return true;
  } catch (_) {
    try { fs.unlinkSync(tmp); } catch (e) {}
    return false;
  }
}

async function main() {
  const client = new ftp.Client(180000);
  try {
    await client.access({
      host: env.FTP_HOST || env.FTP_HOSTNAME,
      port: +(env.FTP_PORT || 21),
      user: env.FTP_USER,
      password: env.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });

    for (const f of files) {
      if (!fs.existsSync(f.local)) {
        console.log('MISSING    ', f.local);
        continue;
      }
      await client.ensureDir(path.posix.dirname(f.remote));
      const backed = await backupRemote(client, f.remote, stamp);
      console.log(backed ? 'backup ok  ' : 'backup skip', path.posix.basename(f.remote));
      const tmp = `${f.remote}.uploading`;
      await client.uploadFrom(f.local, tmp);
      await client.rename(tmp, f.remote);
      console.log('uploaded   ', path.posix.basename(f.remote));
    }

    const bootLocal = path.join(localBase, 'scripts/_tmp-checkout-gw.php');
    const bootRemote = `${remoteBase}/_tmp-checkout-gw.php`;
    fs.writeFileSync(
      bootLocal,
      `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
require __DIR__ . '/wp-load.php';
echo 'checkout_version=' . (class_exists('SorteoSeguro_Checkout') ? SorteoSeguro_Checkout::VERSION : '?') . "\\n";
echo 'order=' . implode(',', SorteoSeguro_Checkout::GATEWAY_ORDER) . "\\n";
echo 'method_title=' . (method_exists('SorteoSeguro_Checkout', 'rename_gateway_titles') ? 'yes' : 'no') . "\\n";
echo 'method_icons=' . (method_exists('SorteoSeguro_Checkout', 'replace_gateway_icons') ? 'yes' : 'no') . "\\n";
echo 'logo_tuu=' . (file_exists(WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-chrome/assets/logo-tuu.png') ? 'yes' : 'no') . "\\n";
echo 'logo_flow=' . (file_exists(WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-chrome/assets/logo-flow.png') ? 'yes' : 'no') . "\\n";
$css = (string) @file_get_contents(WP_CONTENT_DIR . '/mu-plugins/sorteoseguro-checkout/assets/checkout.css');
echo 'css_flow_rule=' . (strpos($css, 'payment_method_flowpayment') !== false ? 'yes' : 'no') . "\\n";
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flushed\\n"; }
try { do_action('litespeed_purge_all'); echo "litespeed_purged\\n"; } catch (\\Throwable $e) { echo 'purge_err=' . $e->getMessage() . "\\n"; }
echo "done\\n";
`
    );
    try {
      require('child_process').execSync(`php -l "${bootLocal}"`, { stdio: 'pipe' });
      console.log('boot php lint ok');
    } catch (e) {
      throw new Error('Boot PHP lint failed: ' + (e.stdout ? e.stdout.toString() : e.message));
    }
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`${site}/_tmp-checkout-gw.php?k=${key}`);
    console.log('--- BOOT', hit.status, '---');
    console.log(hit.body.slice(0, 900));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}

    for (const name of ['logo-tuu.png', 'logo-flow.png']) {
      const img = await fetchText(`${site}/wp-content/mu-plugins/sorteoseguro-chrome/assets/${name}?ssv=${stamp}`);
      console.log('--- LOGO', name, img.status, 'bytes=', img.body.length, '---');
    }
    const css = await fetchText(`${site}/wp-content/mu-plugins/sorteoseguro-checkout/assets/checkout.css?ssv=${stamp}`);
    console.log('--- CSS', css.status, 'flow_rule=', /payment_method_flowpayment/.test(css.body), 'flow_logo=', /logo-flow\.png/.test(css.body), '---');
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
