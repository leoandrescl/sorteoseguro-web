const fs = require('fs');
const path = require('path');
const { Client } = require('ssh2');

const e = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);

const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const files = [
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-ingresos.php',
    remote: `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-ingresos.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-ingresos/assets/ingresos.css',
    remote: `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-ingresos/assets/ingresos.css`,
  },
];

function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream
        .on('close', (code) => resolve({ code, out }))
        .on('data', (d) => (out += d));
      stream.stderr.on('data', (d) => (out += d));
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

const conn = new Client();
conn.on('ready', async () => {
  try {
    for (const f of files) {
      console.log('backup', path.basename(f.remote), stamp);
      console.log((await exec(conn, `cp -a '${f.remote}' '${f.remote}.bak-${stamp}'`)).out);
      await sftpPut(conn, f.local, f.remote);
      console.log('uploaded', path.basename(f.remote));
    }
    console.log((await exec(conn, `php -l '${files[0].remote}'`)).out);
    console.log(
      (
        await exec(
          conn,
          `grep -n "ss_dow\\|1\\.2\\.0\\|Todo el historial\\|DOW_LABELS" '${files[0].remote}' | head -20`
        )
      ).out
    );
    console.log(
      (
        await exec(
          conn,
          `cd '${e.SSH_REMOTE_PATH}' && wp cache flush --allow-root 2>&1 | tail -3; php -r "require 'wp-load.php'; if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo \\"litespeed purged\\n\\"; }"`
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
