const fs = require('fs');
const { Client } = require('ssh2');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);
function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream.on('close', (code) => resolve({ code, out })).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}
const conn = new Client();
conn.on('ready', async () => {
  try {
    const php = `<?php
require '${e.SSH_REMOTE_PATH}/wp-load.php';
wc_load_cart();
WC()->cart->empty_cart();
$pid = 1091;
$tickets = [];
for ($i=1;$i<=20;$i++) $tickets[] = (string)(900000+$i);
$_REQUEST['lty_lottery_ticket_numbers'] = implode(',', $tickets);
$_POST['lty_lottery_ticket_numbers'] = implode(',', $tickets);
$cart_item_data = [
  'lty_lottery' => ['tickets' => $tickets],
  'ss_pack_campaign_id' => 74487,
  'ss_pack_claimed_at' => time(),
  'ss_pack_line_uid' => uniqid('sspack', true),
];
$key = WC()->cart->add_to_cart($pid, 20, 0, [], $cart_item_data);
echo "key=".var_export($key,true)."\\n";
$notices = wc_get_notices();
if ($notices) echo "notices=".wp_json_encode($notices)."\\n";
wc_clear_notices();
WC()->cart->calculate_totals();
echo "subtotal=".WC()->cart->get_subtotal()."\\n";
echo "total=".WC()->cart->get_total('edit')."\\n";
foreach (WC()->cart->get_fees() as $fee) {
  echo "FEE ".html_entity_decode($fee->name)." = {$fee->amount}\\n";
}
// Also directly test calculate for campaign 78771
if (class_exists('Promo_Engine_Stable')) {
  $ref = new ReflectionClass('Promo_Engine_Stable');
  // get singleton? construct will re-add hooks - use new carefully
  $eng = $ref->newInstanceWithoutConstructor();
  $m = $ref->getMethod('calculate_discount_for_campaign');
  $m->setAccessible(true);
  $calc = $m->invoke($eng, 78771, WC()->cart, []);
  echo "bono78771 calc=".wp_json_encode($calc)."\\n";
  $calc2 = $m->invoke($eng, 78771, WC()->cart, ['x'=>20]);
  // with consumed map on real keys
  $consumed = [];
  foreach (WC()->cart->get_cart() as $k=>$it) $consumed[$k]=20;
  $calc3 = $m->invoke($eng, 78771, WC()->cart, $consumed);
  echo "bono78771 with full consume=".wp_json_encode($calc3)."\\n";
}
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-promo-sim3.php && php /tmp/ss-promo-sim3.php`)).out);
  } catch (err) {
    console.error(err);
  } finally {
    conn.end();
  }
});
conn.connect({
  host: e.SSH_HOST,
  port: +(e.SSH_PORT || 22),
  username: e.SSH_USER,
  password: e.SSH_PASSWORD,
});
