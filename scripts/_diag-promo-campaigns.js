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
$posts = get_posts(['post_type'=>'promo_campaign','post_status'=>'publish','numberposts'=>-1]);
foreach ($posts as $p) {
  $type = get_post_meta($p->ID,'_promo_type',true);
  $amount = get_post_meta($p->ID,'_promo_amount',true);
  $min = get_post_meta($p->ID,'_promo_min_total',true);
  $stack = get_post_meta($p->ID,'_promo_stackable',true);
  $show = get_post_meta($p->ID,'_promo_show_on_product',true);
  $buy = get_post_meta($p->ID,'_promo_bxpy_buy',true);
  $pay = get_post_meta($p->ID,'_promo_bxpy_pay',true);
  $active = class_exists('Promo_Engine_Stable') ? (new ReflectionClass('Promo_Engine_Stable')) : null;
  echo $p->ID." | ".$p->post_title." | type=$type amt=$amount min=$min stack=$stack show=$show buy=$buy pay=$pay\\n";
}
`;
    const remotePhp = '/tmp/ss-promo-list.php';
    // write via base64 to avoid quoting hell
    const b64 = Buffer.from(php).toString('base64');
    await exec(conn, `echo ${b64} | base64 -d > ${remotePhp} && php ${remotePhp}`);
    const r = await exec(conn, `php ${remotePhp}`);
    console.log(r.out);
  } catch (err) {
    console.error(err);
  } finally {
    conn.end();
  }
});
conn.connect({
  host: e.SSH_HOST,
  port: +(e.SSH_PORT || 22),
  username: e.SSH_USER,
  password: e.SSH_PASSWORD,
});
