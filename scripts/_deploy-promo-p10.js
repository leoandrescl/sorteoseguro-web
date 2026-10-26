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
const local = 'C:/Proyectos/sorteoseguro/_remote-promo-engine.php';
const remote = `${e.SSH_REMOTE_PATH}/wp-content/plugins/promo-engine/promo-engine.php`;
const bak = `${remote}.bak-20260904-1849`;
const conn = new Client();
conn.on('ready', async () => {
  try {
    console.log('1) backup');
    console.log((await exec(conn, `cp -a '${remote}' '${bak}' && ls -la '${bak}'`)).out);

    console.log('2) sftp upload');
    await new Promise((resolve, reject) => {
      conn.sftp((err, sftp) => {
        if (err) return reject(err);
        sftp.fastPut(local, remote, (e2) => (e2 ? reject(e2) : resolve()));
      });
    });

    console.log('3) lint + marker');
    console.log(
      (
        await exec(
          conn,
          `php -l '${remote}'; grep -n "stable-p10\\|is_pack_campaign\\|Inventario virtual solo" '${remote}' | head -20`
        )
      ).out
    );

    console.log('4) simulate pack 20x8 + bono 78771');
    const php = `<?php
require '${e.SSH_REMOTE_PATH}/wp-load.php';
wc_load_cart();
WC()->cart->empty_cart();
$pid = 53210; // Gran casa Vista Mar Dunares if exists; fallback below
$p = wc_get_product($pid);
if (!$p) {
  foreach ([45941,49102,53210,12146] as $try) {
    if (wc_get_product($try)) { $pid=$try; break; }
  }
}
$campaign = 74487; // pack 20x8
$cart_item_data = [
  'lty_lottery' => ['tickets' => range(1,20)],
  'ss_pack_campaign_id' => $campaign,
  'ss_pack_claimed_at' => time(),
  'ss_pack_line_uid' => uniqid('sspack', true),
];
$key = WC()->cart->add_to_cart($pid, 20, 0, [], $cart_item_data);
echo "product=$pid key=".var_export($key,true)."\\n";
WC()->cart->calculate_totals();
echo "subtotal=".WC()->cart->get_subtotal()."\\n";
echo "total=".WC()->cart->get_total('edit')."\\n";
foreach (WC()->cart->get_fees() as $fee) {
  echo "FEE {$fee->name} = {$fee->amount}\\n";
}
echo "version marker: ";
$src = file_get_contents('${remote}');
echo (strpos($src,'stable-p10')!==false ? 'p10-ok' : 'MISSING')."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-promo-sim.php && php /tmp/ss-promo-sim.php`)).out);

    console.log('5) purge');
    console.log(
      (
        await exec(
          conn,
          `cd '${e.SSH_REMOTE_PATH}' && wp cache flush --allow-root 2>&1 | tail -5; php -r "require 'wp-load.php'; if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo \"litespeed purged\\n\"; }"`
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
