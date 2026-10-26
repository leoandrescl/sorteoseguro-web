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
        .on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out)))
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
      const root = e.SSH_REMOTE_PATH;
      console.log(await exec(conn, `php -l ${root}/wp-content/mu-plugins/sorteoseguro-auth.php`));
      console.log(await exec(conn, `cd ${JSON.stringify(root)} && wp cache flush --allow-root 2>&1`));
      try {
        console.log(
          await exec(
            conn,
            `cd ${JSON.stringify(root)} && wp eval 'if(class_exists("LiteSpeed\\\\Purge")) LiteSpeed\\\\Purge::purge_all(); echo "purged";' --allow-root 2>&1`
          )
        );
      } catch (err) {
        console.log('purge skipped');
      }
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
