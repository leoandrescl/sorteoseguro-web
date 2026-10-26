const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const https = require('https');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const key = 'ssflow' + stamp;
const localFile = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow/flowpaymentfl/flowpayment-fl.php';
const remoteFile = '/wp-content/plugins/flowpaymentfl/flowpayment-fl.php';

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false, headers: { 'Cache-Control': 'no-cache', 'User-Agent': 'Mozilla/5.0' } }, (res) => {
      let body = ''; res.on('data', (d) => (body += d));
      res.on('end', () => resolve({ status: res.statusCode, body }));
    }).on('error', reject);
  });
}

(async () => {
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST, port: +e.FTP_PORT, user: e.FTP_USER,
      password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    console.log('remote backup ->', await client.rename(remoteFile, remoteFile + '.bak-' + stamp).then(() => 'ok').catch((x) => 'skip ' + x.message));
    await client.uploadFrom(localFile, remoteFile);
    console.log('uploaded patched flowpayment-fl.php');

    const boot = `<?php
if (($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo 'forbidden'; exit; }
header('Content-Type: text/plain');
if (function_exists('opcache_reset')) { opcache_reset(); echo "opcache_reset ok\\n"; } else { echo "no opcache\\n"; }
require __DIR__ . '/wp-load.php';
echo "wp loaded ok\\n";
if (function_exists('wp_cache_flush')) { wp_cache_flush(); echo "wp_cache_flush ok\\n"; }
if (function_exists('do_action')) { do_action('litespeed_purge_all'); echo "litespeed_purge_all fired\\n"; }
echo "done\\n";
`;
    const bootLocal = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow/_ss_fix_purge.php';
    fs.writeFileSync(bootLocal, boot);
    await client.uploadFrom(bootLocal, '/_ss_fix_purge.php');
    const hit = await fetchText(`https://sorteoseguro.cl/_ss_fix_purge.php?k=${key}&t=${Date.now()}`);
    console.log('PURGE', hit.status, hit.body.trim());
    try { await client.remove('/_ss_fix_purge.php'); console.log('removed purge script'); } catch (_) {}
    try { fs.unlinkSync(bootLocal); } catch (_) {}
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }

  for (const u of ['https://sorteoseguro.cl/', `https://sorteoseguro.cl/?ssv=${stamp}`]) {
    try {
      const r = await fetchText(u);
      console.log('CHECK', u, '->', r.status, 'len=' + r.body.length,
        'critical=' + /error cr[ií]tico/i.test(r.body),
        'title=' + (r.body.match(/<title>([^<]*)<\/title>/i) || [, '?'])[1].trim());
    } catch (err) { console.log('CHECK ERR', u, err.message); }
  }
})();
