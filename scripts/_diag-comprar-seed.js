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
const root = JSON.stringify(e.SSH_REMOTE_PATH);
const conn = new Client();
conn.on('ready', async () => {
  try {
    console.log(await exec(conn, `cd ${root} && wp eval 'echo class_exists("SorteoSeguro_Comprar") ? "yes" : "no";' --allow-root 2>&1`));
    console.log(await exec(conn, `cd ${root} && wp eval 'delete_option("ss_comprar_pages_seeded_v2");' --allow-root 2>&1`));
    console.log(await exec(conn, `cd ${root} && wp eval 'SorteoSeguro_Comprar::maybe_seed_pages(); echo "done";' --allow-root 2>&1`));
    console.log(await exec(conn, `cd ${root} && wp post list --post_type=page --name=jeep-avenger --fields=ID,post_name,post_parent --allow-root 2>&1`));
    conn.end();
  } catch (err) {
    console.error(err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
