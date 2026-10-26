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
$ids = [45941, 45965];
foreach ($ids as $pid) {
  $p = wc_get_product($pid);
  if (!$p) { echo "$pid MISSING\\n"; continue; }
  echo "==== $pid ".$p->get_name()." status=".$p->get_status()."\\n";
  echo "price=".$p->get_price()." closed=".(method_exists($p,'is_closed')&&$p->is_closed()?'yes':'no')."\\n";
  $rem = method_exists($p,'get_remaining_tickets') ? $p->get_remaining_tickets() : null;
  echo "remaining_count=".(is_array($rem)?count($rem):'n/a')."\\n";
  if (function_exists('lty_is_lottery_product')) echo "is_lottery=".(lty_is_lottery_product($p)?'yes':'no')."\\n";
  if (function_exists('lty_get_random_user_chooses_ticket_numbers_by_quantity')) {
    $cand = lty_get_random_user_chooses_ticket_numbers_by_quantity($p, 1);
    echo "random_1=".wp_json_encode($cand)."\\n";
    $cand3 = lty_get_random_user_chooses_ticket_numbers_by_quantity($p, 3);
    echo "random_3_count=".(is_array($cand3)?count($cand3):0)."\\n";
  }
  // meta keys related to tickets
  foreach (['_lty_total_tickets','_lty_remaining_tickets','_stock','_stock_status'] as $k) {
    $v = get_post_meta($pid, $k, true);
    if (is_array($v)) $v = 'array:'.count($v);
    echo "meta $k=".substr((string)$v,0,120)."\\n";
  }
}
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-diag.php && php /tmp/ss-jeep-diag.php`)).out);
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
