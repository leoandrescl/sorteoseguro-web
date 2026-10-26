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
      stream.on('close', (code) => resolve(out)).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}
const conn = new Client();
conn.on('ready', async () => {
  const base = e.SSH_REMOTE_PATH;
  const php = `<?php
require '${base}/wp-load.php';
$page = get_post(76764);
global $wp_query, $post;
$wp_query = new WP_Query(['page_id'=>76764]);
$post = $page;
$wp_query->is_page = true;
$wp_query->is_singular = true;
$wp_query->queried_object = $page;
$wp_query->queried_object_id = 76764;
wc_load_cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart(45941,3);
echo 'is_comprar_child='.(SorteoSeguro_Comprar::is_comprar_child()?'y':'n')."\\n";
echo 'should_preload='.(SorteoSeguro_Comprar::should_preload_embedded_checkout()?'y':'n')."\\n";
echo 'is_checkout='.(function_exists('is_checkout')&&is_checkout()?'y':'n')."\\n";
echo 'filter_wc='.(apply_filters('woocommerce_is_checkout', false)?'y':'n')."\\n";
`;
  await exec(conn, `cat > /tmp/ss-is-co.php << 'EOFPHP'\n${php}\nEOFPHP`);
  console.log(await exec(conn, 'php /tmp/ss-is-co.php 2>&1'));
  conn.end();
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
