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
global $wpdb;
$pid = 45941;
$p = wc_get_product($pid);
$rem = method_exists($p,'get_remaining_tickets') ? $p->get_remaining_tickets() : [];
$hold = array_filter(array_map('strval', (array) get_post_meta($pid,'_lty_hold_tickets', true)));
$rem = array_map('strval', (array)$rem);
$inter = array_values(array_intersect($rem, $hold));
echo "remaining=".count($rem)." hold=".count($hold)." remaining_in_hold=".count($inter)." free_approx=".(count($rem)-count($inter))."\\n";

// When was hold meta updated?
$row = $wpdb->get_row($wpdb->prepare("SELECT meta_id, LENGTH(meta_value) as len FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_lty_hold_tickets'", $pid));
echo "hold_meta=".wp_json_encode($row)."\\n";

// Search lottery plugin for hold_tickets usage
$paths = [
  '${e.SSH_REMOTE_PATH}/wp-content/plugins/lottery-for-woocommerce',
];
foreach ($paths as $base) {
  $cmd = "grep -Rnl '_lty_hold_tickets' ".escapeshellarg($base)." 2>/dev/null | head -20";
}
`;
    // also grep on server
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-hold.php && php /tmp/ss-jeep-hold.php`)).out);
    console.log((await exec(conn, `grep -Rnl '_lty_hold_tickets' '${e.SSH_REMOTE_PATH}/wp-content/plugins/lottery-for-woocommerce' 2>/dev/null | head -30`)).out);
    console.log((await exec(conn, `grep -Rn '_lty_hold_tickets' '${e.SSH_REMOTE_PATH}/wp-content/mu-plugins' 2>/dev/null | head -40`)).out);
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
