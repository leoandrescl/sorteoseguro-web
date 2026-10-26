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
$q = new WP_Query(['post_type'=>'product','s'=>'Dunares','posts_per_page'=>10]);
foreach ($q->posts as $p) {
  $prod = wc_get_product($p->ID);
  echo $p->ID.' | '.$p->post_title.' | price='.($prod?$prod->get_price():'?')."\\n";
}
// also by lottery targets of pack
$ids = [53210,49102,45965,45941,12146,6764,1091,874,395];
echo "=== pack targets ===\\n";
foreach ($ids as $id) {
  $prod = wc_get_product($id);
  if (!$prod) { echo "$id missing\\n"; continue; }
  echo "$id | ".$prod->get_name()." | price=".$prod->get_price()."\\n";
}

wc_load_cart();
WC()->cart->empty_cart();
// find most expensive lottery for pack 20x8
$best = 0; $bestPrice = 0;
foreach ($ids as $id) {
  $prod = wc_get_product($id);
  if ($prod && (float)$prod->get_price() > $bestPrice) { $best=$id; $bestPrice=(float)$prod->get_price(); }
}
echo "best=$best price=$bestPrice\\n";
$cart_item_data = [
  'ss_pack_campaign_id' => 74487,
  'ss_pack_claimed_at' => time(),
  'ss_pack_line_uid' => uniqid('sspack', true),
];
$key = WC()->cart->add_to_cart($best, 20, 0, [], $cart_item_data);
WC()->cart->calculate_totals();
echo "subtotal=".WC()->cart->get_subtotal()." total=".WC()->cart->get_total('edit')."\\n";
foreach (WC()->cart->get_fees() as $fee) {
  echo "FEE {$fee->name} = {$fee->amount}\\n";
}
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-promo-sim2.php && php /tmp/ss-promo-sim2.php`)).out);
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
