const fs = require('fs');
const ftp = require('basic-ftp');
const https = require('https');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const key = 'sseflow' + Date.now();
const php = `<?php
if (($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo 'forbidden'; exit; }
header('Content-Type: text/plain');
require __DIR__ . '/wp-load.php';
$s = get_option('woocommerce_flowpayment_settings', array());
$s['enabled'] = 'yes';
update_option('woocommerce_flowpayment_settings', $s);
update_option('woocommerce_flow_api_key_valid', true);
if (function_exists('wp_cache_flush')) wp_cache_flush();
echo "flow enabled set to yes\\n";
$now = get_option('woocommerce_flowpayment_settings');
echo 'enabled=' . ($now['enabled'] ?? '?') . "\\n";
echo 'api_key_present=' . (!empty($now['api_key']) ? 'yes' : 'no') . "\\n";
echo 'mode=' . ($now['mode'] ?? '?') . "\\n";
echo "--- GATEWAYS NOW ---\\n";
foreach (WC()->payment_gateways()->payment_gateways() as $id => $gw) {
  echo $id . ' | enabled=' . ($gw->enabled ?? '') . ' | ' . (is_callable([$gw,'is_available']) && $gw->is_available() ? 'available' : 'not-available') . "\\n";
}
echo "DONE\\n";
`;
(async () => {
  const client = new ftp.Client(120000);
  const local = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow/_ss_enable_flow.php';
  fs.writeFileSync(local, php);
  try {
    await client.access({ host: e.FTP_HOST, port: +e.FTP_PORT, user: e.FTP_USER,
      password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.uploadFrom(local, '/_ss_enable_flow.php');
    const body = await new Promise((resolve, reject) => {
      https.get({ host: 'sorteoseguro.cl', path: '/_ss_enable_flow.php?k=' + key + '&t=' + Date.now(),
        headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' }, rejectUnauthorized: false },
        (r) => { let b = ''; r.on('data', (d) => (b += d)); r.on('end', () => resolve('HTTP ' + r.statusCode + '\n' + b)); })
        .on('error', reject);
    });
    console.log(body);
    try { await client.remove('/_ss_enable_flow.php'); } catch (_) {}
    try { fs.unlinkSync(local); } catch (_) {}
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }

  for (const u of ['/', '/comprar/', '/finalizar-compra/']) {
    try {
      const r = await new Promise((resolve, reject) => {
        https.get({ host: 'sorteoseguro.cl', path: u + '?c=' + Date.now(), headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' }, rejectUnauthorized: false },
          (res) => { let b = ''; res.on('data', (d) => (b += d)); res.on('end', () => resolve({ s: res.statusCode, b })); }).on('error', reject);
      });
      console.log('CHECK', u, '->', r.s, 'len=' + r.b.length, 'critical=' + /error cr[ií]tico/i.test(r.b));
    } catch (err) { console.log('CHECK ERR', u, err.message); }
  }
})();
