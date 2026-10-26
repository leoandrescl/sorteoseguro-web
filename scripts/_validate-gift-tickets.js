const fs = require('fs');
const https = require('https');
const { Client } = require('ssh2');

const e = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
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
      stream
        .on('close', (code) => resolve({ code, out }))
        .on('data', (d) => (out += d))
        .stderr.on('data', (d) => (out += d));
    });
  });
}

function httpGet(url) {
  return new Promise((resolve, reject) => {
    https
      .get(url, { headers: { 'User-Agent': 'ss-validate' } }, (res) => {
        let body = '';
        res.on('data', (d) => (body += d));
        res.on('end', () => resolve({ status: res.statusCode, body }));
      })
      .on('error', reject);
  });
}

const base = `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins`;
const names = [
  'sorteoseguro-gift-tickets.php',
  'sorteoseguro-home.php',
  'sorteoseguro-mis-sorteos.php',
  'sorteoseguro-cart.php',
  'sorteoseguro-thankyou.php',
  'sorteoseguro-ingresos.php',
];

const conn = new Client();
conn.on('ready', async () => {
  try {
    for (const n of names) {
      const r = await exec(conn, `php -l '${base}/${n}' 2>&1; ls -la '${base}/${n}' | awk '{print $5, $9}'`);
      console.log(r.out.trim());
    }

    const which = await exec(conn, `command -v wp || ls -la ~/wp-cli.phar 2>/dev/null || echo NO_WPCLI`);
    console.log('\nwp-cli:', which.out.trim());

    const wp = await exec(
      conn,
      `cd '${e.SSH_REMOTE_PATH}' && (wp eval 'echo post_type_exists("ss_gift_promo") ? "CPT_OK" : "CPT_MISSING";' --skip-plugins --skip-themes 2>&1 || php wp-cli.phar eval 'echo post_type_exists("ss_gift_promo") ? "CPT_OK" : "CPT_MISSING";' 2>&1)`
    );
    console.log('wp eval:', wp.out.trim());

    const errlog = await exec(conn, `tail -n 30 '${e.SSH_REMOTE_PATH}/wp-content/debug.log' 2>/dev/null || echo NO_DEBUG_LOG`);
    console.log('\n--- debug.log tail ---\n' + errlog.out.trim());
  } catch (err) {
    console.error(err);
  } finally {
    conn.end();

    try {
      const url = `${e.SITE_URL}/?sscachebust=${Date.now()}`;
      const home = await httpGet(url);
      const fatal = /Fatal error|critical error|There has been a critical/i.test(home.body);
      console.log(`\nHome: HTTP ${home.status} · fatal=${fatal}`);
    } catch (err) {
      console.error('home fetch fallo:', err.message);
    }
  }
});

conn.connect({
  host: e.SSH_HOST,
  port: +(e.SSH_PORT || 22),
  username: e.SSH_USER,
  password: e.SSH_PASSWORD,
});
