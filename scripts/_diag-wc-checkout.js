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
rm -f ss-c.txt ss-page.html ss-ajax.json ss-after.html
curl -sS -c ss-c.txt -b ss-c.txt 'https://sorteoseguro.cl/comprar/jeep-avenger/' -o ss-page.html
NONCE=$(grep -oP '"nonce":"\\K[^"]+' ss-page.html | head -1)
curl -sS -c ss-c.txt -b ss-c.txt -X POST 'https://sorteoseguro.cl/wp-admin/admin-ajax.php' \\
  -H 'Referer: https://sorteoseguro.cl/comprar/jeep-avenger/' \\
  -d "action=ss_packs_select" -d "nonce=$NONCE" -d "product_id=45941" -d "campaign_id=44640" -o ss-ajax.json
REDIR=$(php -r '$j=json_decode(file_get_contents("/tmp/ss-ajax.json"),true); echo strtok($j["data"]["redirect"]??"","#");')
curl -sS -c ss-c.txt -b ss-c.txt "$REDIR" -o ss-after.html
echo "checkout_url:" $(grep -oP 'checkout_url":"\\K[^"]+' ss-after.html | head -1)
echo "form action:" $(grep -oP 'form[^>]*checkout[^>]*action="\\K[^"]+' ss-after.html | head -1)
echo "process nonce:" $(grep -oP 'woocommerce-process-checkout-nonce" name="woocommerce-process-checkout-nonce" value="\\K[^"]+' ss-after.html | head -1)
echo "payment methods:" $(grep -oP 'payment_method_[^"]+' ss-after.html | sort -u | tr '\\n' ' ')
echo "wc-checkout enqueued:" $(grep -c 'wc-checkout' ss-after.html)
grep -oP 'wc_checkout_params = \\{[^}]+\\}' ss-after.html | head -1
# simulate checkout POST via AJAX like wc-checkout.js
PROC=$(grep -oP 'woocommerce-process-checkout-nonce" name="woocommerce-process-checkout-nonce" value="\\K[^"]+' ss-after.html | head -1)
curl -sS -c ss-c.txt -b ss-c.txt -X POST 'https://sorteoseguro.cl/?wc-ajax=checkout' \\
  -H 'Referer: https://sorteoseguro.cl/comprar/jeep-avenger/' \\
  -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \\
  --data-urlencode "woocommerce-process-checkout-nonce=$PROC" \\
  --data-urlencode 'billing_first_name=Test' \\
  --data-urlencode 'billing_last_name=Diag' \\
  --data-urlencode 'billing_email=testdiag@example.com' \\
  --data-urlencode 'billing_phone=+56912345678' \\
  --data-urlencode 'contact_first_name=Test' \\
  --data-urlencode 'contact_last_name=Diag' \\
  --data-urlencode 'contact_phone=+56912345678' \\
  --data-urlencode 'payment_method=woo-mercado-pago-basic' \\
  --data-urlencode 'woocommerce_checkout_place_order=1' \\
  -o ss-checkout-resp.json
echo '--- checkout ajax response ---'
head -c 2500 ss-checkout-resp.json; echo
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
