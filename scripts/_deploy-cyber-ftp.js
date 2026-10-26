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

const files = [
  'plugins/promo-engine/promo-engine.php',
  'mu-plugins/sorteoseguro-packs-lottery.php',
  'mu-plugins/sorteoseguro-comprar-cyber.php',
  'mu-plugins/sorteoseguro-comprar-cyber/page-cyber.php',
  'mu-plugins/sorteoseguro-comprar-cyber/layout-cyber.php',
  'mu-plugins/sorteoseguro-comprar-cyber/assets/cyber.css',
  'mu-plugins/sorteoseguro-comprar-cyber/assets/cyber.js',
].map((rel) => ({ local: path.join(localBase, rel), remote: `${remoteBase}/wp-content/${rel}` }));

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

async function backupRemote(client, remote, stamp) {
  const tmp = path.join('C:/Proyectos/sorteoseguro', 'scripts', '_tmp-remote-backup.bin');
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
      await client.ensureDir(path.posix.dirname(f.remote));
      const backed = await backupRemote(client, f.remote, stamp);
      console.log(backed ? 'backup ok  ' : 'backup skip', path.posix.basename(f.remote));
      const tmp = `${f.remote}.uploading`;
      await client.uploadFrom(f.local, tmp);
      await client.rename(tmp, f.remote);
      console.log('uploaded   ', path.posix.basename(f.remote));
    }

    const key = 'ss-cyber-v2-' + stamp;
    const bootLocal = path.join(localBase, 'scripts/_tmp-cyber-v2.php');
    const bootRemote = `${remoteBase}/_tmp-cyber-v2.php`;
    fs.writeFileSync(
      bootLocal,
      `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin = 'promo-engine/promo-engine.php';
if (!is_plugin_active($plugin)) { activate_plugin($plugin); echo "reactivated\\n"; } else { echo "active\\n"; }
echo 'class=' . (class_exists('Promo_Engine_Stable') ? 'yes' : 'no') . "\\n";
echo 'banner_method=' . (method_exists('Promo_Engine_Stable', 'get_cyber_banner_data') ? 'yes' : 'no') . "\\n";
echo 'cyber_ver=' . (class_exists('SorteoSeguro_Comprar_Cyber') ? SorteoSeguro_Comprar_Cyber::VERSION : '?') . "\\n";
SorteoSeguro_Comprar_Cyber::maybe_seed_pages();
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flushed\\n"; }
try { if (class_exists('LiteSpeed\\Purge')) { LiteSpeed\\Purge::purge_all(); echo "litespeed_purged\\n"; } } catch (\\Throwable $e) { echo 'purge_err=' . $e->getMessage() . "\\n"; }
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
    const hit = await fetchText(`${site}/_tmp-cyber-v2.php?k=${key}`);
    console.log('--- BOOT', hit.status, '---');
    console.log(hit.body.slice(0, 1500));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
