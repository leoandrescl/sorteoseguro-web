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
    const phpFile = '/tmp/ss-diag-cart.php';
    const php = `<?php
require '${base}/wp-load.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    echo "\\nSHUTDOWN: {$e['message']} @ {$e['file']}:{$e['line']}\\n";
  }
});
$pid = 45941;
wc_load_cart();
WC()->cart->empty_cart();
$key = WC()->cart->add_to_cart($pid, 3);
echo "add_key=" . var_export($key, true) . "\\n";
WC()->cart->calculate_totals();
echo "cart_has=" . (SorteoSeguro_Comprar::cart_has_current_product() ? 'yes' : 'no') . "\\n";
ob_start();
SorteoSeguro_Comprar::render_embedded_checkout();
$html = ob_get_clean();
echo "html_len=" . strlen($html) . "\\n";
echo (strpos($html, 'Hubo un error') !== false ? "HAS_WP_FATAL\\n" : "ok\\n");
`;
    await exec(conn, `cat > ${phpFile} << 'EOFPHP'\n${php}\nEOFPHP`);
    const r = await exec(conn, `php ${phpFile} 2>&1`);
    console.log('exit', r.code);
    console.log(r.out.slice(-3000));
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
