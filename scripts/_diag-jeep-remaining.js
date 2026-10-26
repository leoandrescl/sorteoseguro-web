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
    console.log((await exec(conn, `grep -n "function get_remaining_tickets\\|function get_remaining_ticket_count\\|function get_purchased_ticket_count\\|function get_ticket_numbers\\|function get_placed_ticket" '${f}'`)).out);
    // print those functions roughly
    console.log((await exec(conn, `grep -n "function get_remaining_tickets" '${f}'`)).out);
    const n = await exec(conn, `grep -n "function get_remaining_tickets\\|function get_remaining_ticket_count" '${f}'`);
    console.log(n.out);
    // extract line numbers and sed
    console.log((await exec(conn, `awk '/function get_remaining_tickets/,/^\\tpublic function |^\\tprivate function |^\\tprotected function /{print NR\":\"$0}' '${f}' | head -80`)).out);
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
