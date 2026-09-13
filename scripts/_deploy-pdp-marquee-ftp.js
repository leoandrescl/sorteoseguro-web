const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');

const e = Object.fromEntries(
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
const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const localBase = 'C:/Proyectos/sorteoseguro/mu-plugins';

const files = [
  'sorteoseguro-pdp-templates.php',
  'sorteoseguro-pdp/layout.php',
  'sorteoseguro-pdp/assets/pdp.css',
  'sorteoseguro-comprar.php',
  'sorteoseguro-comprar/layout-comprar.php',
  'sorteoseguro-comprar/assets/comprar.css',
  'sorteoseguro-comprar-bono.php',
].map((rel) => ({
  local: path.join(localBase, rel),
  remote: `${remoteBase}/wp-content/mu-plugins/${rel.replace(/\\/g, '/')}`,
}));

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { rejectUnauthorized: false }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, body }));
      })
      .on('error', reject);
  });
}

async function main() {
  const client = new ftp.Client(120000);
  client.ftp.verbose = false;
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME,
      port: +(e.FTP_PORT || 21),
      user: e.FTP_USER,
      password: e.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });

    for (const f of files) {
      const dir = path.posix.dirname(f.remote);
      await client.ensureDir(dir);
      try {
        await client.rename(f.remote, `${f.remote}.bak-${stamp}`);
        console.log('backup', path.posix.basename(f.remote));
      } catch (_) {
        // new or missing
      }
      await client.uploadFrom(f.local, f.remote);
      console.log('uploaded', f.remote.replace(remoteBase, ''));
    }

    const bootLocal = 'C:/Proyectos/sorteoseguro/scripts/_tmp-pdp-marquee-purge.php';
    const bootRemote = `${remoteBase}/_tmp-pdp-marquee-purge.php`;
    const key = 'ss-marquee-purge-20260913';
    fs.writeFileSync(
      bootLocal,
      `<?php
require __DIR__ . '/wp-load.php';
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { status_header(403); echo "forbidden\\n"; exit; }
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flush\\n"; }
if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo "litespeed_purged\\n"; }
echo 'ok marquee=' . (method_exists('SorteoSeguro_PDP_Templates', 'render_marquee') ? 'yes' : 'no') . "\\n";
echo 'ver=' . (defined('SorteoSeguro_PDP_Templates::VERSION') || class_exists('SorteoSeguro_PDP_Templates') ? SorteoSeguro_PDP_Templates::VERSION : '?') . "\\n";
`
    );
    await client.uploadFrom(bootLocal, bootRemote);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-pdp-marquee-purge.php?k=${key}`);
    console.log('purge', hit.status, hit.body.trim());
    try {
      await client.remove(bootRemote);
    } catch (_) {}
    try {
      fs.unlinkSync(bootLocal);
    } catch (_) {}

    console.log('deploy ok pdp-marquee via ftp');
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
