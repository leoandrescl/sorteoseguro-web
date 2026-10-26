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
$hold=array_values(array_filter(array_map('strval',(array)get_post_meta($pid,'_lty_hold_tickets',true))));
$reserve=$p->get_reserved_tickets_data();
$mins=(int)get_option('lty_settings_reserve_ticket_time_in_min',30);
$now=time();
$active_res=[];
foreach ((array)$reserve as $t=>$rows) {
  if (!is_array($rows)) continue;
  foreach ($rows as $cid=>$ts) {
    if (absint($ts)+(60*$mins)>=$now) { $active_res[(string)$t]=true; break; }
  }
}
$hold_with_res=0; $hold_without=0;
foreach ($hold as $t) {
  if (isset($active_res[$t])) $hold_with_res++; else $hold_without++;
}
echo "hold=".count($hold)." active_res_tickets=".count($active_res)." hold_with_active_res=$hold_with_res hold_orphan=$hold_without\\n";

// pending orders with these tickets?
global $wpdb;
$pending = $wpdb->get_col(\"SELECT ID FROM {$wpdb->posts} WHERE post_type='shop_order' AND post_status IN ('wc-pending','wc-on-hold','wc-checkout-draft') ORDER BY ID DESC LIMIT 200\");
echo "pending_orders_sample=".count($pending)."\\n";
$tickets_in_pending=0;
foreach ($pending as $oid) {
  $o=wc_get_order($oid);
  if(!$o) continue;
  foreach ($o->get_items() as $item) {
    if ((int)$item->get_product_id()!==$pid) continue;
    $t=$item->get_meta('lty_tickets');
    if (!$t) $t=$item->get_meta('_lty_ticket_numbers');
    // lottery item meta keys vary
    $metas=$item->get_meta_data();
  }
}
// item meta search
$like = $wpdb->esc_like('_lty').'%';
$rows=$wpdb->get_results($wpdb->prepare(
  \"SELECT om.order_item_id, om.meta_key, LEFT(om.meta_value,80) mv, oi.order_id
   FROM {$wpdb->prefix}woocommerce_order_itemmeta om
   JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id=om.order_item_id
   JOIN {$wpdb->posts} p ON p.ID=oi.order_id
   WHERE p.post_status IN ('wc-pending','wc-on-hold','wc-checkout-draft')
     AND om.meta_key LIKE %s
   LIMIT 30\", '%ticket%'));
// simpler
$rows=$wpdb->get_results(
  \"SELECT om.meta_key, COUNT(*) c FROM {$wpdb->prefix}woocommerce_order_itemmeta om
   JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id=om.order_item_id
   JOIN {$wpdb->posts} p ON p.ID=oi.order_id
   WHERE p.post_status IN ('wc-pending','wc-on-hold','wc-checkout-draft')
     AND (om.meta_key LIKE '%ticket%' OR om.meta_key LIKE '%lty%')
   GROUP BY om.meta_key ORDER BY c DESC LIMIT 40\");
echo "pending_meta_keys=".wp_json_encode($rows)."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-orphan.php && php /tmp/ss-jeep-orphan.php`)).out);
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
