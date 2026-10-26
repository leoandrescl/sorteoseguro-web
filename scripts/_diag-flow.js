const fs = require('fs');
const { Client } = require('ssh2');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream.on('close', () => resolve(out)).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}
const conn = new Client();
conn.on('ready', async () => {
  const b = e.SSH_REMOTE_PATH;
  const cmds = [
    `ls -la ${b}/wp-content/plugins/ | grep -i -E "flow|woo"`,
    `ls -la ${b}/wp-content/plugins/flowpayment-woo-8/ 2>/dev/null | head -40`,
    `ls -la ${b}/wp-content/debug.log 2>/dev/null`,
    `tail -n 120 ${b}/wp-content/debug.log 2>/dev/null`,
    `ls -la ${b}/../logs 2>/dev/null | head`,
    `ls -la /home/${e.SSH_USER}/logs 2>/dev/null | head`,
    `find ${b}/wp-content -name "*.log" -mtime -3 2>/dev/null | head -20`,
    `php -v 2>/dev/null | head -2`,
  ];
  for (const c of cmds) {
    console.log('\n===== CMD: ' + c);
    try { console.log(await exec(conn, c)); } catch (err) { console.log('ERR', err.message); }
  }
  conn.end();
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
