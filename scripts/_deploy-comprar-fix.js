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
const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 13);
const local = 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar.php';
const remoteDir = `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins`;
const remote = `${remoteDir}/sorteoseguro-comprar.php`;
const backup = `${remote}.bak-${stamp}`;

function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream.on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out))).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}

const conn = new Client();
conn.on('ready', () => {
  conn.sftp((err, sftp) => {
    if (err) throw err;
    conn.exec(`cp ${remote} ${backup}`, async (e2) => {
      if (e2) {
        console.error(e2);
        conn.end();
        return;
      }
      const rs = fs.createReadStream(local);
      const ws = sftp.createWriteStream(remote);
      ws.on('close', async () => {
        try {
          console.log('backup:', backup);
          console.log(await exec(conn, `php -l ${remote}`));
          console.log(await exec(conn, `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp cache flush --allow-root 2>&1`));
          console.log(await exec(conn, `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp eval 'if(class_exists("LiteSpeed\\\\Purge")) LiteSpeed\\\\Purge::purge_all(); echo "purged";' --allow-root 2>&1`));
          conn.end();
          console.log('deploy ok v1.0.13');
        } catch (err) {
          console.error(err.message);
          conn.end();
          process.exit(1);
        }
      });
      rs.pipe(ws);
    });
  });
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
