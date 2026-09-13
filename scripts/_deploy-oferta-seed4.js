const fs = require('fs');
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

async function main() {
  const client = new ftp.Client(120000);
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME,
      port: +(e.FTP_PORT || 21),
      user: e.FTP_USER,
      password: e.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });
    const remote = '/wp-content/mu-plugins/sorteoseguro-comprar-bono.php';
    const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
    try {
      await client.rename(remote, `${remote}.bak-${stamp}`);
      console.log('backup', stamp);
    } catch (_) {}
    await client.uploadFrom('C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar-bono.php', remote);
    console.log('uploaded');

    const boot = `<?php
require __DIR__ . '/wp-load.php';
header('Content-Type: text/plain; charset=utf-8');
if ((isset($_GET['k']) ? (string) $_GET['k'] : '') !== 'ss-oferta-seed4') {
  status_header(403);
  echo "forbidden\\n";
  exit;
}
if (class_exists('SorteoSeguro_Comprar_Bono')) {
  SorteoSeguro_Comprar_Bono::maybe_seed_pages();
  foreach (SorteoSeguro_Comprar_Bono::product_slugs() as $slug => $pid) {
    $p = get_page_by_path('oferta/' . $slug);
    echo $slug . ' pid=' . (int) $pid . ' page=' . ($p ? $p->ID : 0) . ' url=' . ($p ? get_permalink($p->ID) : '-') . "\\n";
  }
} else {
  echo "class missing\\n";
}
if (function_exists('wp_cache_flush')) {
  wp_cache_flush();
}
if (class_exists('LiteSpeed\\\\Purge')) {
  LiteSpeed\\Purge::purge_all();
}
@unlink(__FILE__);
echo "done\\n";
`;
    fs.writeFileSync('C:/Proyectos/sorteoseguro/scripts/_tmp-oferta-seed4.php', boot);
    await client.uploadFrom('C:/Proyectos/sorteoseguro/scripts/_tmp-oferta-seed4.php', '/_tmp-oferta-seed4.php');
    const res = await fetch('https://sorteoseguro.cl/_tmp-oferta-seed4.php?k=ss-oferta-seed4');
    console.log(await res.text());
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
