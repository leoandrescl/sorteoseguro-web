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
    const sh = `
cd /tmp
rm -f ss-c.txt ss-page.html ss-ajax.json ss-after.html ss-checkout-resp.json
curl -sS -c ss-c.txt -b ss-c.txt 'https://sorteoseguro.cl/comprar/jeep-avenger/' -o ss-page.html
NONCE=$(grep -oP '"nonce":"\\K[^"]+' ss-page.html | head -1)
curl -sS -c ss-c.txt -b ss-c.txt -X POST 'https://sorteoseguro.cl/wp-admin/admin-ajax.php' -H 'Referer: https://sorteoseguro.cl/comprar/jeep-avenger/' -d "action=ss_packs_select" -d "nonce=$NONCE" -d "product_id=45941" -d "campaign_id=44640" -o ss-ajax.json
REDIR=$(php -r '$j=json_decode(file_get_contents("/tmp/ss-ajax.json"),true); echo strtok($j["data"]["redirect"]??"","#");')
curl -sS -c ss-c.txt -b ss-c.txt "$REDIR" -o ss-after.html
PROC=$(grep -oP 'woocommerce-process-checkout-nonce" name="woocommerce-process-checkout-nonce" value="\\K[^"]+' ss-after.html | head -1)
TYCV='Acepto los Términos y condiciones y la Política de privacidad'
curl -sS -c ss-c.txt -b ss-c.txt -X POST 'https://sorteoseguro.cl/?wc-ajax=checkout' \\
  -H 'Referer: https://sorteoseguro.cl/comprar/jeep-avenger/' \\
  -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \\
  --data-urlencode "woocommerce-process-checkout-nonce=$PROC" \\
  --data-urlencode 'billing_first_name=Test' \\
  --data-urlencode 'billing_last_name=Diag' \\
  --data-urlencode 'billing_email=testdiag2@example.com' \\
  --data-urlencode 'billing_phone=+56912345678' \\
  --data-urlencode 'contact_first_name=Test' \\
  --data-urlencode 'contact_last_name=Diag' \\
  --data-urlencode 'contact_phone=+56912345678' \\
  --data-urlencode 'payment_method=woo-mercado-pago-basic' \\
  --data-urlencode "tyc[]=$TYCV" \\
  --data-urlencode 'woocommerce_checkout_place_order=1' \\
  -o ss-checkout-resp.json
cat ss-checkout-resp.json | head -c 4000
echo
grep -E 'wc-checkout|ss-checkout|checkout.js' ss-after.html | head -10
`;
    const r = await exec(conn, sh);
    console.log(r.out);
    conn.end();
  } catch (err) {
    console.error('ERR:', err.message);
    conn.end();
    process.exit(1);
  }
}).connect({ host: e.SSH_HOST, port: +e.SSH_PORT, username: e.SSH_USER, password: e.SSH_PASSWORD });
