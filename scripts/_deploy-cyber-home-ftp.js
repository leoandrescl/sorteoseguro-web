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
const key = 'ss-cyber-home-' + stamp;

const files = [
  'mu-plugins/sorteoseguro-home.php',
  'mu-plugins/sorteoseguro-home/page-home.php',
  'mu-plugins/sorteoseguro-home/assets/home.css',
  'mu-plugins/sorteoseguro-home/assets/home.js',
  'mu-plugins/sorteoseguro-home/assets/hero/cyber-casa.jpg',
  'mu-plugins/sorteoseguro-home/assets/hero/cyber-jeep.jpg',
  'mu-plugins/sorteoseguro-home/assets/fonts/creepster.woff2',
].map((rel) => ({ local: path.join(localBase, rel), remote: `${remoteBase}/wp-content/${rel}` }));

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache' } }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
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

    const bootLocal = path.join(localBase, 'scripts/_tmp-cyber-home.php');
    const bootRemote = `${remoteBase}/_tmp-cyber-home.php`;
    fs.writeFileSync(
      bootLocal,
      `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
require __DIR__ . '/wp-load.php';
echo 'home_version=' . (class_exists('SorteoSeguro_Home') ? SorteoSeguro_Home::VERSION : '?') . "\\n";
echo 'cyber_method=' . (class_exists('SorteoSeguro_Home') && method_exists('SorteoSeguro_Home', 'cyber_config') ? 'yes' : 'no') . "\\n";
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flushed\\n"; }
try { if (function_exists('litespeed_purge_all')) { litespeed_purge_all(); echo "litespeed_purged\\n"; } } catch (\\Throwable $e) { echo 'purge_err=' . $e->getMessage() . "\\n"; }
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
    const hit = await fetchText(`${site}/_tmp-cyber-home.php?k=${key}`);
    console.log('--- BOOT', hit.status, '---');
    console.log(hit.body.slice(0, 800));
    try { await client.remove(bootRemote); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}

    const home = await fetchText(`${site}/?sscyber=${stamp}`);
    const hasSection = home.body.includes('ss-home-cyber');
    const hasCasa = home.body.includes('cyber-casa.jpg');
    const hasJeep = home.body.includes('cyber-jeep.jpg');
    console.log('--- HOME', home.status, '---');
    console.log('ss-home-cyber=', hasSection, 'cyber-casa=', hasCasa, 'cyber-jeep=', hasJeep);
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
