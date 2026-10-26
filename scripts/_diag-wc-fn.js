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
const conn = new Client();
conn.on('ready', async () => {
  try {
    const base = e.SSH_REMOTE_PATH;
    const cmds = [
      `grep -n "function woocommerce_checkout" ${base}/wp-content/plugins/woocommerce/includes/wc-template-functions.php | head -1`,
      `sed -n '1,80p' ${base}/wp-content/plugins/woocommerce/includes/wc-template-functions.php | grep -n woocommerce_checkout || true`,
      `find ${base}/../../logs ${base}/../logs /home/u367809546/logs -name '*.log' 2>/dev/null | head -5`,
      `ls -la ${base}/wp-content/ | head -20`,
    ];
    for (const c of cmds) {
      const r = await exec(conn, c);
      console.log('>', c.slice(0, 80));
      console.log(r.out.slice(0, 1500));
    }
    // find woocommerce_checkout function line
    const fn = await exec(conn, `awk '/function woocommerce_checkout\\(/,/^}/' ${base}/wp-content/plugins/woocommerce/includes/wc-template-functions.php | head -40`);
    console.log('--- woocommerce_checkout ---');
    console.log(fn.out);
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
