const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');

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

const files = [
  {
    local: 'C:/Proyectos/sorteoseguro/plugins/promo-engine/promo-engine.php',
    remote: `${remoteBase}/wp-content/plugins/promo-engine/promo-engine.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery.php',
    remote: `${remoteBase}/wp-content/mu-plugins/sorteoseguro-packs-lottery.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar/layout-comprar.php',
    remote: `${remoteBase}/wp-content/mu-plugins/sorteoseguro-comprar/layout-comprar.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar-bono.php',
    remote: `${remoteBase}/wp-content/mu-plugins/sorteoseguro-comprar-bono.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar-bono/page-oferta.php',
    remote: `${remoteBase}/wp-content/mu-plugins/sorteoseguro-comprar-bono/page-oferta.php`,
  },
];

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
      // backup if exists
      try {
        await client.rename(f.remote, `${f.remote}.bak-${stamp}`);
        console.log('backup', f.remote);
        // upload then we already renamed away — need to upload to original name
      } catch (_) {
        // new file
      }
      await client.uploadFrom(f.local, f.remote);
      console.log('uploaded', f.remote);
    }

    // bootstrap seed + purge once (NOT in mu-plugins — would auto-load)
    const bootLocal = 'C:/Proyectos/sorteoseguro/scripts/_tmp-oferta-boot.php';
    const bootRemote = `${remoteBase}/_tmp-oferta-boot.php`;
    const bootPhp = `<?php
/**
 * One-shot: seed /oferta/jeep-avenger/ + purge caches. Delete after use.
 */
require __DIR__ . '/wp-load.php';
header('Content-Type: text/plain; charset=utf-8');
$key = isset($_GET['k']) ? (string) $_GET['k'] : '';
if ($key !== 'ss-oferta-boot-20260913') {
  status_header(403);
  echo "forbidden\\n";
  exit;
}
if (class_exists('SorteoSeguro_Comprar_Bono')) {
  SorteoSeguro_Comprar_Bono::maybe_seed_pages();
  $p = get_page_by_path('oferta/jeep-avenger');
  echo $p ? ('OK page=' . $p->ID . ' url=' . get_permalink($p->ID) . "\\n") : "page=missing\\n";
  echo 'class=yes pe=' . (class_exists('Promo_Engine_Stable') ? 'yes' : 'no') . "\\n";
  echo 'preload_fn=' . (method_exists('Promo_Engine_Stable', 'get_preload_page_campaigns') ? 'yes' : 'no') . "\\n";
} else {
  echo "class missing\\n";
}
if (function_exists('wp_cache_flush')) {
  wp_cache_flush();
  echo "wp cache flushed\\n";
}
if (class_exists('LiteSpeed\\\\Purge')) {
  LiteSpeed\\Purge::purge_all();
  echo "litespeed purged\\n";
}
@unlink(__FILE__);
echo "done\\n";
`;
    fs.writeFileSync(bootLocal, bootPhp);
    await client.uploadFrom(bootLocal, bootRemote);
    console.log('uploaded bootstrap');

    const url = 'https://sorteoseguro.cl/_tmp-oferta-boot.php?k=ss-oferta-boot-20260913';
    const res = await fetch(url);
    const text = await res.text();
    console.log('boot http', res.status);
    console.log(text);

    console.log('OK deploy stamp', stamp);
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
