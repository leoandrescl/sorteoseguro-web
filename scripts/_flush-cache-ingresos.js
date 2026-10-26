const fs = require('fs');
const { Client } = require('ssh2');
const e = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      let v = l.slice(i + 1);
      if (
        (v.startsWith('"') && v.endsWith('"')) ||
        (v.startsWith("'") && v.endsWith("'"))
      ) {
        v = v.slice(1, -1);
      }
      return [l.slice(0, i), v.trim()];
    })
);

const conn = new Client();
conn
  .on('ready', () => {
    const cmd = `cd '${e.SSH_REMOTE_PATH}' && wp cache flush --allow-root 2>&1 | tail -5; php -r "require 'wp-load.php'; if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo \\"litespeed purged\\n\\"; } echo 'ok\\n';"`;
    conn.exec(cmd, (err, stream) => {
      if (err) {
        console.error(err);
        conn.end();
        process.exit(1);
      }
      let out = '';
      stream
        .on('close', (code) => {
          console.log(out);
          console.log('exit', code);
          conn.end();
        })
        .on('data', (d) => (out += d));
      stream.stderr.on('data', (d) => (out += d));
    });
  })
  .on('error', (err) => {
    console.error('ssh error', err.message);
    process.exit(1);
  })
  .connect({
    host: e.SSH_HOST,
    port: +(e.SSH_PORT || 22),
    username: e.SSH_USER,
    password: e.SSH_PASSWORD,
    readyTimeout: 20000,
  });
