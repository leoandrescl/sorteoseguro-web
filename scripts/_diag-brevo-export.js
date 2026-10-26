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
      stream
        .on('close', () => resolve(out))
        .on('data', (d) => {
          out += d;
        });
      stream.stderr.on('data', (d) => {
        out += d;
      });
    });
  });
}
const conn = new Client();
conn
  .on('ready', async () => {
    try {
      const base = e.SSH_REMOTE_PATH;
      const php = `echo count(SorteoSeguro_Brevo_Export::collect('2026-05-15','2026-09-04','registrados_mas_compradores'));`;
      console.log(await exec(conn, `cd ${JSON.stringify(base)} && wp eval ${JSON.stringify(php)} --allow-root 2>&1`));
      console.log('---log---');
      console.log(await exec(conn, `tail -n 40 ${base}/wp-content/debug.log 2>/dev/null || true`));
      conn.end();
    } catch (err) {
      console.error(err.message);
      conn.end();
      process.exit(1);
    }
  })
  .connect({
    host: e.SSH_HOST,
    port: +e.SSH_PORT,
    username: e.SSH_USER,
    password: e.SSH_PASSWORD,
  });
