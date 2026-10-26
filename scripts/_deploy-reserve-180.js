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
function sftpPut(conn, local, remote) {
  return new Promise((resolve, reject) => {
    conn.sftp((err, sftp) => {
      if (err) return reject(err);
      sftp.fastPut(local, remote, (e2) => (e2 ? reject(e2) : resolve()));
    });
  });
}
const stamp = '20260904-2132';
const files = [
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery.php',
    remote: `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-packs-lottery.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-pending-payment-reminders.php',
    remote: `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-pending-payment-reminders.php`,
  },
];
const conn = new Client();
conn.on('ready', async () => {
  try {
    for (const f of files) {
      console.log('backup', f.remote.split('/').pop());
      await exec(conn, `cp -a '${f.remote}' '${f.remote}.bak-${stamp}'`);
      await sftpPut(conn, f.local, f.remote);
      console.log('ok', f.remote.split('/').pop());
    }
    console.log((await exec(conn, `php -l '${files[0].remote}'; php -l '${files[1].remote}'`)).out);

    const php = `<?php
require '${e.SSH_REMOTE_PATH}/wp-load.php';
// Force bootstrap migration
if (class_exists('SorteoSeguro_Packs_Lottery')) {
  SorteoSeguro_Packs_Lottery::bootstrap_reserve_setting();
  echo "reserve_minutes=".SorteoSeguro_Packs_Lottery::reserve_minutes()."\\n";
}
echo "option=".get_option('lty_settings_reserve_ticket_time_in_min')."\\n";
echo "flag180=".get_option('ss_packs_reserve_180_applied')."\\n";
echo "wc_hold=".get_option('woocommerce_hold_stock_minutes')."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-res180.php && php /tmp/ss-res180.php`)).out);
    console.log(
      (
        await exec(
          conn,
          `cd '${e.SSH_REMOTE_PATH}' && wp cache flush --allow-root 2>&1 | tail -2; php -r "require 'wp-load.php'; if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo \"litespeed purged\\n\"; }"`
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
