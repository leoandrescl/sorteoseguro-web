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
    const remote = `${e.SSH_REMOTE_PATH}/wp-content/plugins/promo-engine/promo-engine.php`;
    // Pull key sections: class methods related to fees, stacking, gift, fixed, cart total
    const r1 = await exec(
      conn,
      `grep -nE 'function |acumul|stack|gift|fixed|monto|threshold|min_amount|cart_total|add_fee|\\[PROMO\\]|exclude|pack|buy.?pay|apply_promo|calculate' ${remote} | head -200`
    );
    console.log(r1.out);
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
