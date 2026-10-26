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
      stream.on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out))).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}
const conn = new Client();
conn.on('ready', async () => {
  try {
    const base = e.SSH_REMOTE_PATH;
    console.log(await exec(conn, `php -l ${base}/wp-content/mu-plugins/sorteoseguro-comprar.php`));
    console.log(await exec(conn, `php -l ${base}/wp-content/mu-plugins/sorteoseguro-checkout/templates/checkout/form-checkout.php`));
    const php = `SorteoSeguro_Comprar::render_embedded_checkout(); echo 'render ok';`;
    console.log(await exec(conn, `cd ${JSON.stringify(base)} && wp eval ${JSON.stringify(php)} --allow-root 2>&1`));
    console.log(await exec(conn, `tail -n 15 ${base}/wp-content/debug.log 2>/dev/null || true`));
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
