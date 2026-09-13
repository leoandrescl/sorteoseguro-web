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
const base = 'C:/Proyectos/sorteoseguro/mu-plugins';
const files = [
  ['sorteoseguro-pdp-templates.php', 'wp-content/mu-plugins/sorteoseguro-pdp-templates.php'],
  ['sorteoseguro-pdp/layout.php', 'wp-content/mu-plugins/sorteoseguro-pdp/layout.php'],
  ['sorteoseguro-pdp/assets/pdp.css', 'wp-content/mu-plugins/sorteoseguro-pdp/assets/pdp.css'],
  ['sorteoseguro-comprar.php', 'wp-content/mu-plugins/sorteoseguro-comprar.php'],
  ['sorteoseguro-comprar/layout-comprar.php', 'wp-content/mu-plugins/sorteoseguro-comprar/layout-comprar.php'],
  ['sorteoseguro-comprar/assets/comprar.css', 'wp-content/mu-plugins/sorteoseguro-comprar/assets/comprar.css'],
  ['sorteoseguro-comprar-bono.php', 'wp-content/mu-plugins/sorteoseguro-comprar-bono.php'],
];

function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream
        .on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out)))
        .on('data', (d) => (out += d))
        .stderr.on('data', (d) => (out += d));
    });
  });
}

function upload(sftp, local, remote) {
  return new Promise((resolve, reject) => {
    const rs = fs.createReadStream(local);
    const ws = sftp.createWriteStream(remote);
    ws.on('close', resolve);
    ws.on('error', reject);
    rs.pipe(ws);
  });
}

const conn = new Client();
conn.on('ready', () => {
  conn.sftp(async (err, sftp) => {
    if (err) throw err;
    try {
      for (const [localRel, remoteRel] of files) {
        const local = path.join(base, localRel);
        const remote = `${e.SSH_REMOTE_PATH}/${remoteRel}`;
        const backup = `${remote}.bak-${stamp}`;
        await exec(conn, `cp ${remote} ${backup} 2>/dev/null || true`);
        await upload(sftp, local, remote);
        console.log('ok', remoteRel);
        console.log('backup:', backup);
      }
      console.log(await exec(conn, `php -l ${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-pdp-templates.php`));
      console.log(await exec(conn, `php -l ${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-comprar.php`));
      console.log(await exec(conn, `php -l ${e.SSH_REMOTE_PATH}/wp-content/mu-plugins/sorteoseguro-comprar-bono.php`));
      console.log(await exec(conn, `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp cache flush --allow-root 2>&1`));
      console.log(
        await exec(
          conn,
          `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp eval 'if(class_exists("LiteSpeed\\\\Purge")) LiteSpeed\\\\Purge::purge_all(); echo "purged";' --allow-root 2>&1`
        )
      );
      conn.end();
      console.log('deploy ok pdp-marquee');
    } catch (e2) {
      console.error(e2.message);
      conn.end();
      process.exit(1);
    }
  });
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
