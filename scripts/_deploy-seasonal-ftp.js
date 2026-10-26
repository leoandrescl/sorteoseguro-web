const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');

const projectRoot = path.resolve(__dirname, '..');
const e = Object.fromEntries(
  fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const key = 'ssseasonal' + stamp;

const files = [
  ['mu-plugins/sorteoseguro-seasonal.php', 'wp-content/mu-plugins/sorteoseguro-seasonal.php'],
  ['mu-plugins/sorteoseguro-seasonal/assets/seasonal.css', 'wp-content/mu-plugins/sorteoseguro-seasonal/assets/seasonal.css'],
  ['mu-plugins/sorteoseguro-seasonal/assets/seasonal.js', 'wp-content/mu-plugins/sorteoseguro-seasonal/assets/seasonal.js'],
  ['mu-plugins/sorteoseguro-seasonal/assets/halloween/cursor-pumpkin.png', 'wp-content/mu-plugins/sorteoseguro-seasonal/assets/halloween/cursor-pumpkin.png'],
  ['mu-plugins/sorteoseguro-seasonal/assets/halloween/cursor-pointer.png', 'wp-content/mu-plugins/sorteoseguro-seasonal/assets/halloween/cursor-pointer.png'],
  ['mu-plugins/sorteoseguro-seasonal/assets/halloween/calabaza.png', 'wp-content/mu-plugins/sorteoseguro-seasonal/assets/halloween/calabaza.png'],
  ['mu-plugins/sorteoseguro-seasonal/assets/halloween/telarana.png', 'wp-content/mu-plugins/sorteoseguro-seasonal/assets/halloween/telarana.png'],
];

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' }, rejectUnauthorized: false }, (res) => {
      let body = ''; res.on('data', (d) => (body += d)); res.on('end', () => resolve({ status: res.statusCode, body }));
    }).on('error', reject);
  });
}

async function main() {
  const client = new ftp.Client(120000);
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false },
    });

    for (const [localRel, remoteRel] of files) {
      const local = path.join(projectRoot, localRel);
      const remote = `${remoteBase}/${remoteRel}`;
      await client.ensureDir(path.posix.dirname(remote));
      await client.cd(remoteBase || '/');
      try { await client.rename(remote, `${remote}.bak-${stamp}`); console.log('backup', remoteRel); }
      catch (_) { console.log('new', remoteRel); }
      await client.uploadFrom(local, remote);
      console.log('uploaded', remoteRel);
    }

    const php = `<?php
$key='${key}';
if((string)($_GET['k']??'')!==$key){http_response_code(403);exit('forbidden');}
header('Content-Type: text/plain; charset=utf-8');
require __DIR__.'/wp-load.php';
echo 'class='.(class_exists('SorteoSeguro_Seasonal')?'yes':'no')."\\n";
if(class_exists('SorteoSeguro_Seasonal')){
  echo 'mode='.SorteoSeguro_Seasonal::mode()."\\n";
  echo 'cursor='.(SorteoSeguro_Seasonal::cursor_enabled()?'1':'0')."\\n";
  echo 'opt_mode='.(string)get_option('ss_seasonal_mode','(unset)')."\\n";
}
if(function_exists('litespeed_purge_all')){litespeed_purge_all();echo "ls_purge_all\\n";}
else { do_action('litespeed_purge_all'); echo "ls_purge_action\\n"; }
if(function_exists('wp_cache_flush')){wp_cache_flush();echo "wp_cache_flush\\n";}
`;
    const localPhp = path.join(projectRoot, 'scripts', '_tmp-seasonal-check.php');
    const remotePhp = `${remoteBase}/_tmp-seasonal-check.php`;
    fs.writeFileSync(localPhp, php);
    await client.cd(remoteBase || '/');
    await client.uploadFrom(localPhp, remotePhp);
    const hit = await fetchText(`https://sorteoseguro.cl/_tmp-seasonal-check.php?k=${key}`);
    console.log('remote', hit.status, hit.body.trim());
    try { await client.remove(remotePhp); } catch (_) {}
    try { fs.unlinkSync(localPhp); } catch (_) {}
    console.log('deploy ok seasonal');
  } finally { client.close(); }
}
main().catch((err) => { console.error(err); process.exit(1); });
