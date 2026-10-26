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
echo "lty_reserve_min=".get_option('lty_settings_reserve_ticket_time_in_min')."\\n";
echo "wc_hold_stock_min=".get_option('woocommerce_hold_stock_minutes')."\\n";
echo "ss_packs_prev_reserve_enabled=".get_option('ss_packs_prev_reserve_enabled')."\\n";
echo "ss_packs_prev_reserve_minutes=".get_option('ss_packs_prev_reserve_minutes')."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-res-ttl.php && php /tmp/ss-res-ttl.php`)).out);
    console.log((await exec(conn, `grep -n "reserve_ticket_time\\|180\\|hold_stock\\|bootstrap_reserve" '${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-packs-lottery.php' | head -40`)).out);
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
