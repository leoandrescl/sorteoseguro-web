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
      register_shutdown_function(function () {
        $e = error_get_last();
        if ($e) {
          echo "SHUTDOWN: {$e['type']} {$e['message']} @ {$e['file']}:{$e['line']}\\n";
        }
      });
      set_error_handler(function ($n, $s, $f, $l) {
        echo "ERR[$n] $s @ $f:$l\\n";
        return false;
      });
      $pid = 45941;
      wc_load_cart();
      WC()->cart->empty_cart();
      $key = WC()->cart->add_to_cart($pid, 3);
      echo 'add_key=' . var_export($key, true) . "\\n";
      if (!$key) { echo wc_print_notices(true); exit; }
      WC()->cart->calculate_totals();
      echo 'step1 ok\\n';
      $checkout = WC()->checkout();
      echo 'step2 ok\\n';
      do_action('woocommerce_before_checkout_form', $checkout);
      echo 'step3 before ok\\n';
      wc_get_template('checkout/form-checkout.php', ['checkout' => $checkout]);
      echo 'step4 template ok\\n';
      do_action('woocommerce_after_checkout_form', $checkout);
      echo 'step5 after ok\\n';
    `;
    const r = await exec(conn, `cd ${JSON.stringify(base)} && wp eval ${JSON.stringify(php)} --allow-root 2>&1`);
    console.log('exit', r.code);
    console.log(r.out.slice(-4000));
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
