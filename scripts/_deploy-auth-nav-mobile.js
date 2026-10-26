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
const root = e.SSH_REMOTE_PATH;
const files = [
  'mu-plugins/sorteoseguro-auth.php',
  'mu-plugins/sorteoseguro-auth/assets/auth-account.css',
];

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

function put(sftp, local, remote) {
  return new Promise((resolve, reject) => {
    const ws = sftp.createWriteStream(remote);
    ws.on('close', resolve);
    ws.on('error', reject);
    fs.createReadStream(local).pipe(ws);
  });
}

const conn = new Client();
conn
  .on('ready', () => {
    conn.sftp(async (err, sftp) => {
      if (err) throw err;
      try {
        for (const rel of files) {
          const local = path.join('C:/Proyectos/sorteoseguro', rel);
          const remote = `${root}/wp-content/${rel.replace(/\\/g, '/')}`;
          const backup = `${remote}.bak-${stamp}`;
          await exec(conn, `cp ${JSON.stringify(remote)} ${JSON.stringify(backup)}`);
          console.log('backup', backup);
          await put(sftp, local, remote);
          console.log('up', remote);
        }
        console.log(await exec(conn, `php -l ${root}/wp-content/mu-plugins/sorteoseguro-auth.php`));
        console.log(await exec(conn, `cd ${JSON.stringify(root)} && wp cache flush --allow-root 2>&1`));
        try {
          console.log(
            await exec(
              conn,
              `cd ${JSON.stringify(root)} && wp eval 'if(class_exists("LiteSpeed\\\\Purge")) LiteSpeed\\\\Purge::purge_all(); echo "purged";' --allow-root 2>&1`
            )
          );
        } catch (purgeErr) {
          console.log('purge skipped:', purgeErr.message.slice(0, 120));
        }
        conn.end();
        console.log('deploy ok v1.0.5');
      } catch (e2) {
        console.error(e2.message);
        conn.end();
        process.exit(1);
      }
    });
  })
  .connect({
    host: e.SSH_HOST,
    port: +e.SSH_PORT,
    username: e.SSH_USER,
    password: e.SSH_PASSWORD,
  });
