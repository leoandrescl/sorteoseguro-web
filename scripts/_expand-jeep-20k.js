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
$pid = 45941;
$p = wc_get_product($pid);
if (!$p || !function_exists('lty_is_lottery_product') || !lty_is_lottery_product($p)) {
  fwrite(STDERR, "invalid product\\n");
  exit(1);
}

$bak = [
  'maximum' => $p->get_lty_maximum_tickets(),
  'user_max' => $p->get_lty_user_maximum_tickets(),
  'min' => $p->get_lty_minimum_tickets(),
  'stock' => $p->get_stock_quantity(),
  'ticket_count_meta' => get_post_meta($pid, '_lty_ticket_count', true),
  'purchased' => method_exists($p,'get_purchased_ticket_count') ? $p->get_purchased_ticket_count() : null,
  'remaining_count' => method_exists($p,'get_remaining_ticket_count') ? $p->get_remaining_ticket_count() : null,
  'remaining_list' => method_exists($p,'get_remaining_tickets') ? count((array)$p->get_remaining_tickets()) : null,
];
update_option('ss_jeep_45941_max_bak_20260904', $bak, false);
echo "BACKUP=".wp_json_encode($bak)."\\n";

$new_max = 20000;
$p->set_lty_maximum_tickets($new_max);
// Permitir hasta el total del sorteo por usuario (antes 10000).
if ((int) $p->get_lty_user_maximum_tickets() < $new_max) {
  $p->set_lty_user_maximum_tickets($new_max);
}
// Tabs de números: cubrir el nuevo total.
if ((int) $p->get_lty_tickets_per_tab() < $new_max) {
  $p->set_lty_tickets_per_tab($new_max);
}

// Invalidar cache de números formateados si existiera.
delete_post_meta($pid, '_lty_formatted_automatic_ticket_numbers');

$p->save();
clean_post_cache($pid);
wc_delete_product_transients($pid);

$p = wc_get_product($pid);
$purchased = (int) $p->get_purchased_ticket_count();
$remaining_count = max(0, $new_max - $purchased);

// Alinear stock WC al cupo restante real.
if (function_exists('wc_update_product_stock')) {
  wc_update_product_stock($p, $remaining_count, 'set');
} else {
  $p->set_stock_quantity($remaining_count);
  $p->set_stock_status($remaining_count > 0 ? 'instock' : 'outofstock');
  $p->save();
}
clean_post_cache($pid);
wc_delete_product_transients($pid);
$p = wc_get_product($pid);

echo "AFTER max=".$p->get_lty_maximum_tickets()
  ." user_max=".$p->get_lty_user_maximum_tickets()
  ." purchased=$purchased"
  ." remaining_count=".$p->get_remaining_ticket_count()
  ." remaining_list=".count((array)$p->get_remaining_tickets())
  ." stock=".$p->get_stock_quantity()
  ."\\n";

// smoke pick 20
if (class_exists('SorteoSeguro_Packs_Lottery')) {
  $ref = new ReflectionClass('SorteoSeguro_Packs_Lottery');
  $m = $ref->getMethod('pick_and_claim_tickets');
  $m->setAccessible(true);
  $cust = 'expand-'.wp_generate_password(6,false);
  $got = $m->invoke(null, $pid, 20, $cust);
  echo "pick20=".count($got)."\\n";
  if ($got) {
    $rel = $ref->getMethod('release_claimed_tickets');
    $rel->setAccessible(true);
    $rel->invoke(null, $pid, $got, $cust);
  }
}
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-expand.php && php /tmp/ss-jeep-expand.php`)).out);
    console.log(
      (
        await exec(
          conn,
          `cd '${e.SSH_REMOTE_PATH}' && wp cache flush --allow-root 2>&1 | tail -3; php -r "require 'wp-load.php'; if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo \"litespeed purged\\n\"; }"`
        )
      ).out
    );
  } catch (err) {
    console.error(err);
    process.exitCode = 1;
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
