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
    const f = `${e.SSH_REMOTE_PATH}/wp-content/plugins/lottery-for-woocommerce/inc/entity/class-lty-lottery-product.php`;
    console.log((await exec(conn, `sed -n '980,1050p;1220,1280p' '${f}'`)).out);
    console.log('==== overall ====');
    console.log((await exec(conn, `grep -n "function get_overall_tickets" '${f}'; awk '/function get_overall_tickets/,/^\\tpublic function /{print; if(NR>1 && /^\\tpublic function / && !/get_overall/) exit}' '${f}' | head -60`)).out);
  } catch (err) {
    console.error(err);
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
