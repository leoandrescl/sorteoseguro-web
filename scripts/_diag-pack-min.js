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
    for (const step of ['echo start', 'wc_load_cart(); echo cart_ok', 'WC()->cart->empty_cart(); echo empty_ok', 'echo add=' . " . var_export(WC()->cart->add_to_cart(45941,1), true);"]) {
      // noop
    }
    const tests = [
      `echo STEP0; wc_load_cart(); echo STEP1;`,
      `wc_load_cart(); WC()->cart->empty_cart(); echo STEP2;`,
      `wc_load_cart(); WC()->cart->empty_cart(); $k=WC()->cart->add_to_cart(45941,1); echo 'STEP3 key=' . var_export($k,true);`,
    ];
    for (const php of tests) {
      const r = await exec(conn, `cd ${JSON.stringify(base)} && wp eval ${JSON.stringify(php)} --allow-root 2>&1`);
      console.log('---', php.slice(0, 60), '---');
      console.log('code', r.code, r.out.trim());
    }
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
