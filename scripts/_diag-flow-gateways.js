const fs = require('fs');
const ftp = require('basic-ftp');
const https = require('https');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const key = 'ssdiag' + Date.now();
const php = `<?php
if (($_GET['k'] ?? '') !== '${key}') { http_response_code(403); echo 'forbidden'; exit; }
header('Content-Type: text/plain');
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
echo "--- ACTIVE PLUGINS ---\\n";
foreach ((array) get_option('active_plugins') as $p) echo $p . "\\n";
echo "\\n--- FLOW FILE PATCH CHECK ---\\n";
$f = WP_PLUGIN_DIR . '/flowpaymentfl/flowpayment-fl.php';
if (is_file($f)) {
  $src = file_get_contents($f);
  echo "file exists, size=" . filesize($f) . "\\n";
  echo "has_default_id_param=" . (strpos($src, 'function ( $icon, $id = null )') !== false ? 'YES' : 'NO') . "\\n";
} else { echo "flow file NOT FOUND\\n"; }
echo "\\n--- FLOW PLUGIN ACTIVE? ---\\n";
echo 'flowpaymentfl/flowpayment-fl.php => ' . (is_plugin_active('flowpaymentfl/flowpayment-fl.php') ? 'ACTIVE' : 'inactive') . "\\n";
echo 'payment-plugin-woocommerce (TUU) => ' . (is_plugin_active('payment-plugin-woocommerce/payment-plugin-woocommerce.php') ? 'ACTIVE' : 'inactive') . "\\n";
echo "\\n--- GATEWAYS ---\\n";
if (function_exists('WC') && WC()->payment_gateways()) {
  foreach (WC()->payment_gateways()->payment_gateways() as $id => $gw) {
    $enabled = is_callable([$gw, 'is_available']) ? ($gw->is_available() ? 'available' : 'not-available') : '?';
    echo $id . ' | enabled_setting=' . ($gw->enabled ?? '') . ' | ' . $enabled . '\\n';
  }
}
echo "\\n--- FLOW SETTINGS ---\\n";
$s = get_option('woocommerce_flowpayment_settings');
if ($s === false) { echo "no settings option\\n"; } else { print_r($s); }
echo "\\n--- TUU SETTINGS ---\\n";
$t = get_option('woocommerce_wcplugingateway_settings');
echo $t === false ? "none\\n" : (print_r($t, true));
echo "\\nDONE\\n";
`;
(async () => {
  const client = new ftp.Client(120000);
  const local = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow/_ss_diag_gateways.php';
  fs.writeFileSync(local, php);
  try {
    await client.access({ host: e.FTP_HOST, port: +e.FTP_PORT, user: e.FTP_USER,
      password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.uploadFrom(local, '/_ss_diag_gateways.php');
    const body = await new Promise((resolve, reject) => {
      https.get({ host: 'sorteoseguro.cl', path: '/_ss_diag_gateways.php?k=' + key + '&t=' + Date.now(),
        headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' }, rejectUnauthorized: false },
        (r) => { let b = ''; r.on('data', (d) => (b += d)); r.on('end', () => resolve('HTTP ' + r.statusCode + '\n' + b)); })
        .on('error', reject);
    });
    console.log(body);
    try { await client.remove('/_ss_diag_gateways.php'); } catch (_) {}
    try { fs.unlinkSync(local); } catch (_) {}
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }
})();
