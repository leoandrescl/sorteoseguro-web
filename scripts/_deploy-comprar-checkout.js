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
const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 13);
const files = [
  ['C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar.php', 'wp-content/mu-plugins/sorteoseguro-comprar.php'],
  ['C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar/layout-comprar.php', 'wp-content/mu-plugins/sorteoseguro-comprar/layout-comprar.php'],
  ['C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar/assets/comprar.js', 'wp-content/mu-plugins/sorteoseguro-comprar/assets/comprar.js'],
];
const base = e.SSH_REMOTE_PATH;
function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream.on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out))).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}
function upload(sftp, local, remote) {
  return new Promise((resolve, reject) => {
    const rs = fs.createReadStream(local);
    const ws = sftp.createWriteStream(remote);
    ws.on('close', resolve).on('error', reject);
    rs.pipe(ws);
  });
}
const conn = new Client();
conn.on('ready', () => {
  conn.sftp(async (err, sftp) => {
    try {
      for (const [, rel] of files) {
        await exec(conn, `cp ${base}/${rel} ${base}/${rel}.bak-${stamp} 2>/dev/null || true`);
      }
      for (const [local, rel] of files) {
        await upload(sftp, local, `${base}/${rel}`);
        console.log('up', rel);
      }
      console.log(await exec(conn, `cd ${JSON.stringify(base)} && wp cache flush --allow-root 2>&1`));
      conn.end();
      console.log('deploy ok v1.0.21');
    } catch (e2) {
      console.error(e2.message);
      conn.end();
      process.exit(1);
    }
  });
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
