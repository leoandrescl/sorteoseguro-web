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
$orders = wc_get_orders([
  'status' => ['pending','on-hold','checkout-draft'],
  'limit' => 100,
  'return' => 'ids',
]);
echo "pending_ids=".count($orders)."\\n";
$pid=45941;
$with=0;
foreach ($orders as $oid) {
  $o=wc_get_order($oid);
  if(!$o) continue;
  foreach ($o->get_items() as $item) {
    if ((int)$item->get_product_id()===$pid) { $with++; echo "order $oid qty=".$item->get_quantity()."\\n"; break; }
  }
}
echo "pending_with_jeep=$with\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-pending.php && php /tmp/ss-jeep-pending.php`)).out);
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
