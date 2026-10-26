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
  const sh = `cd /tmp && curl -sS -c ss-c.txt -b ss-c.txt 'https://sorteoseguro.cl/comprar/jeep-avenger/' -o ss-page.html && NONCE=$(grep -oP '"nonce":"\\K[^"]+' ss-page.html | head -1) && curl -sS -c ss-c.txt -b ss-c.txt -X POST 'https://sorteoseguro.cl/wp-admin/admin-ajax.php' -H 'Referer: https://sorteoseguro.cl/comprar/jeep-avenger/' -d "action=ss_packs_select" -d "nonce=$NONCE" -d "product_id=45941" -d "campaign_id=44640" -o ss-ajax.json && REDIR=$(php -r '$j=json_decode(file_get_contents("/tmp/ss-ajax.json"),true); echo strtok($j["data"]["redirect"]??"","#");') && curl -sS -c ss-c.txt -b ss-c.txt "$REDIR" -o ss-after.html && grep -o 'ss-comprar critical v[0-9.]*' ss-after.html | head -1 && grep -o 'frontend/checkout[^\" ]*' ss-after.html | head -3 && tail -c 8000 ss-after.html | tr '>' '\\n' | grep script | head -15`;
  console.log(await exec(conn, sh));
  conn.end();
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
