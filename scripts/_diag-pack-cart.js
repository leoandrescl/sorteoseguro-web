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
    const php = `
      $pid = (int) (SorteoSeguro_Comprar::product_slugs()['jeep-avenger'] ?? 0);
      echo 'jeep pid=' . $pid . "\\n";
      wc_load_cart();
      WC()->cart->empty_cart();
      WC()->cart->add_to_cart($pid, 3);
      WC()->cart->calculate_totals();
      echo 'cart count=' . WC()->cart->get_cart_contents_count() . "\\n";
      echo 'cart_has=' . (SorteoSeguro_Comprar::cart_has_current_product() ? 'yes' : 'no') . "\\n";
      ob_start();
      try {
        SorteoSeguro_Comprar::render_embedded_checkout();
        $html = ob_get_clean();
        echo 'render len=' . strlen($html) . "\\n";
        echo (strpos($html, 'error cr') !== false ? 'HAS CRITICAL MSG' : 'no critical msg') . "\\n";
      } catch (Throwable $t) {
        ob_end_clean();
        echo 'FATAL: ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine() . "\\n";
      }
    `;
    console.log(await exec(conn, `cd ${JSON.stringify(base)} && wp eval ${JSON.stringify(php)} --allow-root 2>&1`));
    console.log('--- recent log ---');
    console.log(await exec(conn, `grep -E 'Fatal|Parse|comprar|form-checkout' ${base}/wp-content/debug.log 2>/dev/null | tail -n 20 || true`));
    console.log('--- version grep ---');
    console.log(await exec(conn, `grep -n 'VERSION' ${base}/wp-content/mu-plugins/sorteoseguro-comprar.php | head -3`));
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
