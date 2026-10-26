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
    const php = `<?php
require '${base}/wp-load.php';
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    echo "\\nSHUTDOWN: {$e['message']} @ {$e['file']}:{$e['line']}\\n";
  }
});
$page = get_post(76764);
if (!$page) { echo "no page\\n"; exit(1); }
global $wp_query, $post;
$wp_query = new WP_Query(['page_id' => 76764]);
$post = $page;
setup_postdata($page);
$wp_query->is_page = true;
$wp_query->is_singular = true;
$wp_query->queried_object = $page;
$wp_query->queried_object_id = 76764;
echo 'is_child=' . (SorteoSeguro_Comprar::is_comprar_child() ? 'y' : 'n') . ' pid=' . SorteoSeguro_Comprar::current_product_id() . "\\n";
wc_load_cart();
WC()->cart->empty_cart();
$key = WC()->cart->add_to_cart(45941, 3);
WC()->cart->calculate_totals();
echo 'cart_has=' . (SorteoSeguro_Comprar::cart_has_current_product() ? 'yes' : 'no') . "\\n";
echo 'preload=' . (SorteoSeguro_Comprar::is_checkout_preload() ? 'yes' : 'no') . "\\n";
ob_start();
SorteoSeguro_Comprar::render_embedded_checkout();
$html = ob_get_clean();
echo 'len=' . strlen($html) . "\\n";
if (strpos($html, 'Hubo un error') !== false) echo "WP_FATAL\\n";
if (preg_match('/ss-co-products.*?<\\/div>/s', $html, $m)) echo 'products_snip=' . substr(strip_tags($m[0]), 0, 120) . "\\n";
`;
    await exec(conn, `cat > /tmp/ss-diag-cart3.php << 'EOFPHP'\n${php}\nEOFPHP`);
    const r = await exec(conn, `php /tmp/ss-diag-cart3.php 2>&1`);
    console.log(r.out);
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
