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
$ids = [78771, 23581, 74487];
foreach ($ids as $id) {
  $p = get_post($id);
  echo "==== $id {$p->post_title} status={$p->post_status}\\n";
  foreach (['_promo_type','_promo_amount','_promo_min_total','_promo_stackable','_promo_start','_promo_end','_promo_show_on_product','_promo_segment_type','_promo_limit_total','_promo_limit_per_user','_promo_redemptions','_promo_target_products','_promo_target_lotteries'] as $k) {
    $v = get_post_meta($id, $k, true);
    if (is_array($v)) $v = wp_json_encode($v);
    echo "  $k = ".var_export($v,true)."\\n";
  }
  if (class_exists('Promo_Engine_Stable')) {
    $eng = new Promo_Engine_Stable();
    // can't easily - constructor hooks. Use reflection or just call static if public
    echo "  active_guest=";
    $r = new ReflectionMethod('Promo_Engine_Stable','campaign_is_active_for_user');
    // get instance via global? instantiate carefully
  }
  echo "\\n";
}
// recent fixed campaigns by modified
$posts = get_posts(['post_type'=>'promo_campaign','post_status'=>['publish','draft','private'],'numberposts'=>15,'orderby'=>'modified','order'=>'DESC']);
echo "=== recently modified ===\\n";
foreach ($posts as $p) {
  $type=get_post_meta($p->ID,'_promo_type',true);
  $amt=get_post_meta($p->ID,'_promo_amount',true);
  $min=get_post_meta($p->ID,'_promo_min_total',true);
  echo "{$p->ID} [{$p->post_status}] {$p->post_title} type=$type amt=$amt min=$min modified={$p->post_modified}\\n";
}
`;
    const b64 = Buffer.from(php).toString('base64');
    const r = await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-promo-meta.php && php /tmp/ss-promo-meta.php`);
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
