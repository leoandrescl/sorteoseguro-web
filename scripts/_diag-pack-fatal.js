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
    const php = `
      ini_set('display_errors', '1');
      error_reporting(E_ALL);
      $pid = (int) (SorteoSeguro_Comprar::product_slugs()['jeep-avenger'] ?? 0);
      wc_load_cart();
      WC()->cart->empty_cart();
      WC()->cart->add_to_cart($pid, 3);
      WC()->cart->calculate_totals();
      echo 'cart_has=' . (SorteoSeguro_Comprar::cart_has_current_product() ? 'yes' : 'no') . "\\n";
      ob_start();
      woocommerce_checkout();
      $html = ob_get_clean();
      echo 'wc_checkout len=' . strlen($html) . "\\n";
    `;
    const r = await exec(conn, `cd ${JSON.stringify(base)} && wp eval ${JSON.stringify(php)} --allow-root 2>&1`);
    console.log('exit', r.code);
    console.log(r.out);
    console.log('--- log tail ---');
    const log = await exec(conn, `tail -n 5 ${base}/wp-content/debug.log 2>/dev/null || true`);
    console.log(log.out);
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
