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
    const php = `<?php
require '${e.SSH_REMOTE_PATH}/wp-load.php';
$pid = 45941;
$p = wc_get_product($pid);
$reserve = method_exists($p,'get_reserved_tickets_data') ? $p->get_reserved_tickets_data() : [];
echo "reserved_keys=".(is_array($reserve)?count($reserve):0)."\\n";
$hold = get_post_meta($pid,'_lty_hold_tickets',true);
echo "hold_count=".(is_array($hold)?count($hold):0)." hold_sample=".wp_json_encode(is_array($hold)?array_slice($hold,0,5):$hold)."\\n";
$mins = (int) get_option('lty_settings_reserve_ticket_time_in_min', 30);
echo "reserve_minutes=$mins\\n";
// count active reserves
$now=time(); $active=0; $expired=0; $by_customer=[];
if (is_array($reserve)) {
  foreach ($reserve as $ticket=>$rows) {
    if (!is_array($rows)) continue;
    foreach ($rows as $cid=>$ts) {
      if (absint($ts)+(60*$mins) >= $now) { $active++; $by_customer[(string)$cid]=($by_customer[(string)$cid]??0)+1; }
      else $expired++;
    }
  }
}
echo "active_reserves=$active expired_entries=$expired customers=".count($by_customer)."\\n";
arsort($by_customer);
echo "top_customers=".wp_json_encode(array_slice($by_customer,0,10,true))."\\n";

// Simulate pick_and_claim via reflection
if (!class_exists('SorteoSeguro_Packs_Lottery')) { echo "NO CLASS\\n"; exit; }
$ref = new ReflectionClass('SorteoSeguro_Packs_Lottery');
$m = $ref->getMethod('pick_and_claim_tickets');
$m->setAccessible(true);
$cust = 'diag-'.wp_generate_password(8,false);
foreach ([1,3,5,10,20] as $qty) {
  $t0=microtime(true);
  $got = $m->invoke(null, $pid, $qty, $cust);
  $ms = (int)((microtime(true)-$t0)*1000);
  echo "pick qty=$qty got=".count($got)." ms=$ms sample=".wp_json_encode(array_slice($got,0,3))."\\n";
  if ($got) {
    $rel = $ref->getMethod('release_claimed_tickets');
    $rel->setAccessible(true);
    $rel->invoke(null, $pid, $got, $cust);
  }
}

// check MAX_PICK_TRIES and claim method existence
echo "MAX_PICK_TRIES=".$ref->getConstant('MAX_PICK_TRIES')."\\n";
echo "file=". (new ReflectionClass('SorteoSeguro_Packs_Lottery'))->getFileName()."\\n";
`;
    const b64 = Buffer.from(php).toString('base64');
    console.log((await exec(conn, `echo ${b64} | base64 -d > /tmp/ss-jeep-claim.php && php /tmp/ss-jeep-claim.php`)).out);
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
