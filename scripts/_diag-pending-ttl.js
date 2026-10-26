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
echo "reserve_min=".get_option('lty_settings_reserve_ticket_time_in_min', 'n/a')."\\n";
echo "woocommerce_hold_stock_minutes=".get_option('woocommerce_hold_stock_minutes', 'n/a')."\\n";
echo "woocommerce_cancel_unpaid=".get_option('woocommerce_cancel_unpaid_orders', 'n/a')."\\n";
// common options
foreach (['lty_settings_reserve_ticket_time_in_min','woocommerce_hold_stock_minutes','stock_reserved_time'] as $k) {
  echo "$k=".var_export(get_option($k),true)."\\n";
}
$orders = wc_get_orders(['status'=>['pending','on-hold'],'limit'=>20,'return'=>'ids','orderby'=>'date','order'=>'DESC']);
echo "pending_count_sample=".count($orders)." ids=".wp_json_encode($orders)."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-pending-ttl.php && php /tmp/ss-pending-ttl.php`)).out);
    console.log((await exec(conn, `grep -RIn "cancel_unpaid\\|hold_stock\\|pending.*min\\|expir" '${e.SSH_REMOTE_PATH}/wp-content/mu-plugins' --include='*.php' 2>/dev/null | head -40`)).out);
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
