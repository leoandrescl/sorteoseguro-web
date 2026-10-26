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
$pid=45941;
$p=wc_get_product($pid);
echo "name=".$p->get_name()."\\n";
echo "stock=".$p->get_stock_quantity()."\\n";
$keys=['_stock','lty_total_tickets','lty_ticket_numbers_from','lty_ticket_numbers_to','lty_existing_tickets','lty_tickets_count','lty_max_tickets','lty_min_tickets','_lty_total_tickets','lty_ticket_price'];
foreach ($keys as $k) {
  $v=get_post_meta($pid,$k,true);
  if (is_array($v)) $v='array:'.count($v);
  echo "$k=".var_export($v,true)."\\n";
}
if (method_exists($p,'get_ticket_numbers_from')) echo "from=".$p->get_ticket_numbers_from()."\\n";
if (method_exists($p,'get_ticket_numbers_to')) echo "to=".$p->get_ticket_numbers_to()."\\n";
if (method_exists($p,'get_remaining_tickets')) echo "remaining=".count((array)$p->get_remaining_tickets())."\\n";
if (method_exists($p,'get_sold_tickets')) {
  $sold=$p->get_sold_tickets();
  echo "sold_count=".(is_array($sold)?count($sold):'n/a')."\\n";
}
// list lottery meta keys
global $wpdb;
$metas=$wpdb->get_results($wpdb->prepare("SELECT meta_key, LEFT(meta_value,80) v, LENGTH(meta_value) len FROM {$wpdb->postmeta} WHERE post_id=%d AND (meta_key LIKE '%lty%' OR meta_key LIKE '%ticket%' OR meta_key IN ('_stock','_stock_status')) ORDER BY meta_key", $pid));
foreach ($metas as $m) echo "META {$m->meta_key} len={$m->len} v={$m->v}\\n";
echo "reserve_min=".get_option('lty_settings_reserve_ticket_time_in_min',30)."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-meta.php && php /tmp/ss-jeep-meta.php`)).out);

    // how hold is released
    console.log('==== hold release context ====');
    console.log((await exec(conn, `sed -n '560,620p' '${e.SSH_REMOTE_PATH}/wp-content/plugins/lottery-for-woocommerce/inc/class-lty-order-handler.php'`)).out);
    console.log((await exec(conn, `sed -n '1470,1545p' '${e.SSH_REMOTE_PATH}/wp-content/plugins/lottery-for-woocommerce/inc/entity/class-lty-lottery-product.php'`)).out);
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
