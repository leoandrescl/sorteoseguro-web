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
const local = 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery.php';
const remote = `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-packs-lottery.php`;
const bak = `${remote}.bak-20260904-1925`;
const conn = new Client();
conn.on('ready', async () => {
  try {
    console.log('backup');
    console.log((await exec(conn, `cp -a '${remote}' '${bak}' && ls -la '${bak}'`)).out);
    await sftpPut(conn, local, remote);
    console.log((await exec(conn, `php -l '${remote}'; grep -n "1.3.29\\|purge_orphan_hold\\|get_blocked_ticket_map\\|free_pool" '${remote}' | head -20`)).out);

    const php = `<?php
require '${e.SSH_REMOTE_PATH}/wp-load.php';
$pid=45941;
$before=count(array_filter((array)get_post_meta($pid,'_lty_hold_tickets',true)));
echo "hold_before=$before\\n";
$ref=new ReflectionClass('SorteoSeguro_Packs_Lottery');
$purge=$ref->getMethod('purge_orphan_hold_tickets');
$purge->setAccessible(true);
$purge->invoke(null,$pid);
$after=count(array_filter((array)get_post_meta($pid,'_lty_hold_tickets',true)));
echo "hold_after=$after\\n";
$m=$ref->getMethod('pick_and_claim_tickets');
$m->setAccessible(true);
$cust='fix-'.wp_generate_password(6,false);
foreach ([1,3,10,20] as $qty) {
  $t0=microtime(true);
  $got=$m->invoke(null,$pid,$qty,$cust);
  echo "pick qty=$qty got=".count($got)." ms=".((int)((microtime(true)-$t0)*1000))."\\n";
  if ($got) {
    $rel=$ref->getMethod('release_claimed_tickets');
    $rel->setAccessible(true);
    $rel->invoke(null,$pid,$got,$cust);
  }
}
echo "VERSION=".$ref->getConstant('VERSION')."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-fix.php && php /tmp/ss-jeep-fix.php`)).out);
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
