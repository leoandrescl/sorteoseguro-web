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
const key = 'ss-cyber-diag-' + stamp;

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

const php = `<?php
header('Content-Type: text/plain; charset=utf-8');
if ((string)($_GET['k'] ?? '') !== '${key}') { http_response_code(403); exit; }
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/wp-load.php';
echo "loaded\\n";
$GLOBALS['ss_diag_cid'] = 0;
register_shutdown_function(function () {
  global $ss_diag_cid;
  if (!empty($ss_diag_cid)) { wp_delete_post((int) $ss_diag_cid, true); }
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    echo "FATAL: " . $e['message'] . " @ " . $e['file'] . ":" . $e['line'] . "\\n";
  }
});
$cid = wp_insert_post([
  'post_title'  => 'SS CYBER TEST ' . time(),
  'post_type'   => 'promo_campaign',
  'post_status' => 'publish',
], true);
if (is_wp_error($cid)) { echo 'insert_err=' . $cid->get_error_message() . "\\n"; exit; }
$cid = (int) $cid;
$GLOBALS['ss_diag_cid'] = $cid;
update_post_meta($cid, '_promo_type', 'bulk');
update_post_meta($cid, '_promo_bulk_qty', 30);
update_post_meta($cid, '_promo_bulk_price', 50000);
update_post_meta($cid, '_promo_show_on_cyber', 'yes');
update_post_meta($cid, '_promo_cyber_lotteries', [45941]);
update_post_meta($cid, '_promo_show_on_product', 'no');
update_post_meta($cid, '_promo_limit_per_user', 0);
update_post_meta($cid, '_promo_segment_type', 'all');
update_post_meta($cid, '_promo_stackable', 'yes');
echo 'cid=' . $cid . "\\n";
foreach (['get_cyber_page_campaigns','get_preload_page_campaigns','get_product_page_campaigns'] as $m) {
  echo 'method ' . $m . '=' . (method_exists('Promo_Engine_Stable', $m) ? 'yes' : 'no') . "\\n";
}
$rows = Promo_Engine_Stable::get_cyber_page_campaigns(45941);
echo 'cyber_rows=' . count($rows) . "\\n";
foreach ($rows as $r) { echo 'row id=' . $r['id'] . ' buy=' . $r['buy'] . ' pay=' . $r['pay'] . ' pack=' . $r['pack_price'] . ' list=' . $r['list_price'] . "\\n"; }
echo 'preload_rows=' . count(Promo_Engine_Stable::get_preload_page_campaigns(45941)) . "\\n";
echo 'product_rows=' . count(Promo_Engine_Stable::get_product_page_campaigns(45941)) . "\\n";
$p = wc_get_product(45941);
echo 'unit=' . (float) wc_get_price_to_display($p) . "\\n";
if (function_exists('wc_load_cart')) { wc_load_cart(); }
WC()->cart->empty_cart();
$tickets = [];
for ($i = 1; $i <= 30; $i++) { $tickets[] = (string) (770000 + $i); }
$_REQUEST['lty_lottery_ticket_numbers'] = implode(',', $tickets);
$_POST['lty_lottery_ticket_numbers']    = implode(',', $tickets);
$cart_key = WC()->cart->add_to_cart(45941, 30, 0, [], [
  'lty_lottery'         => ['tickets' => $tickets],
  'ss_pack_campaign_id' => $cid,
  'ss_pack_claimed_at'  => time(),
  'ss_pack_line_uid'    => uniqid('sspack', true),
]);
WC()->cart->calculate_totals();
echo 'cart_key=' . var_export($cart_key, true) . "\\n";
echo 'subtotal=' . WC()->cart->get_subtotal() . "\\n";
echo 'total=' . WC()->cart->get_total('edit') . "\\n";
foreach (WC()->cart->get_fees() as $fee) { echo 'FEE [' . html_entity_decode($fee->name) . '] ' . $fee->amount . "\\n"; }
WC()->cart->empty_cart();
echo "done\\n";
`;

async function main() {
  const client = new ftp.Client(180000);
  const local = path.join(localBase, 'scripts/_tmp-cyber-diag.php');
  const remote = `${remoteBase}/_tmp-cyber-diag.php`;
  try {
    await client.access({
      host: env.FTP_HOST || env.FTP_HOSTNAME,
      port: +(env.FTP_PORT || 21),
      user: env.FTP_USER,
      password: env.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });
    fs.writeFileSync(local, php);
    await client.uploadFrom(local, remote);
    const hit = await fetchText(`${site}/_tmp-cyber-diag.php?k=${key}`);
    console.log('DIAG', hit.status);
    console.log(hit.body);
    try { await client.remove(remote); } catch (_) {}
    try { fs.unlinkSync(local); } catch (_) {}
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
