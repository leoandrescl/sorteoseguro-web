const fs = require('fs');
const path = require('path');
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
function sftpPut(conn, local, remote) {
  return new Promise((resolve, reject) => {
    conn.sftp((err, sftp) => {
      if (err) return reject(err);
      sftp.fastPut(local, remote, (e2) => (e2 ? reject(e2) : resolve()));
    });
  });
}
const stamp = '20260904-1918';
const files = [
  {
    local: 'C:/Proyectos/sorteoseguro/plugins/promo-engine/promo-engine.php',
    remote: `${e.SSH_REMOTE_PATH}/wp-content/plugins/promo-engine/promo-engine.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-cart/templates/cart/cart-totals.php',
    remote: `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-cart/templates/cart/cart-totals.php`,
  },
];
const conn = new Client();
conn.on('ready', async () => {
  try {
    for (const f of files) {
      console.log('backup', path.basename(f.remote));
      console.log((await exec(conn, `cp -a '${f.remote}' '${f.remote}.bak-${stamp}'`)).out);
      await sftpPut(conn, f.local, f.remote);
      console.log('uploaded', path.basename(f.remote));
    }
    console.log((await exec(conn, `php -l '${files[0].remote}'; grep -n "stable-p11\\|fee único de carrito\\|cart_payable_after" '${files[0].remote}' | head -15`)).out);

    const php = `<?php
require '${e.SSH_REMOTE_PATH}/wp-load.php';
wc_load_cart();
WC()->cart->empty_cart();
$pid = 1091;
$tickets = [];
for ($i=1;$i<=20;$i++) $tickets[] = (string)(910000+$i);
$_REQUEST['lty_lottery_ticket_numbers'] = implode(',', $tickets);
$_POST['lty_lottery_ticket_numbers'] = implode(',', $tickets);
$key = WC()->cart->add_to_cart($pid, 20, 0, [], [
  'lty_lottery' => ['tickets' => $tickets],
  'ss_pack_campaign_id' => 74487,
  'ss_pack_claimed_at' => time(),
  'ss_pack_line_uid' => uniqid('sspack', true),
]);
WC()->cart->calculate_totals();
echo "total=".WC()->cart->get_total('edit')."\\n";
foreach (WC()->cart->get_fees() as $fee) {
  echo "FEE [".html_entity_decode($fee->name)."]\\n";
}
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-promo-label.php && php /tmp/ss-promo-label.php`)).out);

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
