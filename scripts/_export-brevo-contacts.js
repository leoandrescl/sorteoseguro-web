const fs = require('fs');
const path = require('path');
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

const php = `<?php
if (!defined('ABSPATH')) { echo 'NO_WP'; return; }

function ss_brevo_csv_field($v) {
  $v = preg_replace('/[\\r\\n]+/', ' ', (string) $v);
  $v = trim($v);
  return '"' . str_replace('"', '""', $v) . '"';
}

$skip_roles = array('administrator', 'shop_manager', 'editor', 'author');
$contacts = array();
$from = '2026-05-15';
$to = wp_date('Y-m-d');
$after_gmt = get_gmt_from_date($from . ' 00:00:00');
$before_gmt = get_gmt_from_date($to . ' 23:59:59');

$users = get_users(array(
  'number' => -1,
  'fields' => 'all',
  'role__not_in' => $skip_roles,
  'date_query' => array(
    array(
      'after' => $after_gmt,
      'before' => $before_gmt,
      'inclusive' => true,
      'column' => 'user_registered',
    ),
  ),
));

foreach ($users as $u) {
  $email = strtolower(trim((string) $u->user_email));
  if ($email === '' || !is_email($email)) {
    continue;
  }
  $fn = trim((string) get_user_meta($u->ID, 'first_name', true));
  $ln = trim((string) get_user_meta($u->ID, 'last_name', true));
  if ($fn === '') {
    $fn = trim((string) get_user_meta($u->ID, 'billing_first_name', true));
  }
  if ($ln === '') {
    $ln = trim((string) get_user_meta($u->ID, 'billing_last_name', true));
  }
  $contacts[$email] = array(
    'EMAIL' => $email,
    'FIRSTNAME' => $fn,
    'LASTNAME' => $ln,
    'ORIGEN' => 'registrado',
  );
}

$buyer_count = 0;
$page = 1;

do {
  $batch = wc_get_orders(array(
    'limit' => 200,
    'page' => $page,
    'date_created' => $from . '...' . $to,
    'status' => array('completed', 'processing'),
    'type' => 'shop_order',
    'return' => 'objects',
  ));
  if (!is_array($batch)) {
    $batch = array();
  }
  foreach ($batch as $order) {
    $email = strtolower(trim((string) $order->get_billing_email()));
    if ($email === '' || !is_email($email)) {
      continue;
    }
    $fn = trim((string) $order->get_billing_first_name());
    $ln = trim((string) $order->get_billing_last_name());
    $buyer_count++;
    if (!isset($contacts[$email])) {
      $contacts[$email] = array(
        'EMAIL' => $email,
        'FIRSTNAME' => $fn,
        'LASTNAME' => $ln,
        'ORIGEN' => 'comprador',
      );
    } else {
      if ($contacts[$email]['FIRSTNAME'] === '' && $fn !== '') {
        $contacts[$email]['FIRSTNAME'] = $fn;
      }
      if ($contacts[$email]['LASTNAME'] === '' && $ln !== '') {
        $contacts[$email]['LASTNAME'] = $ln;
      }
      if ($contacts[$email]['ORIGEN'] === 'registrado') {
        $contacts[$email]['ORIGEN'] = 'registrado+comprador';
      }
    }
  }
  $page++;
} while (count($batch) === 200);

ksort($contacts);

$lines = array('EMAIL,FIRSTNAME,LASTNAME');
foreach ($contacts as $row) {
  $lines[] = ss_brevo_csv_field($row['EMAIL']) . ',' . ss_brevo_csv_field($row['FIRSTNAME']) . ',' . ss_brevo_csv_field($row['LASTNAME']);
}

$csv = implode("\\n", $lines) . "\\n";
$out = '/tmp/ss-brevo-contactos.csv';
file_put_contents($out, "\\xEF\\xBB\\xBF" . $csv);

$n_reg = 0;
$n_buy = 0;
$n_both = 0;
foreach ($contacts as $row) {
  if ($row['ORIGEN'] === 'registrado') { $n_reg++; }
  elseif ($row['ORIGEN'] === 'comprador') { $n_buy++; }
  else { $n_both++; }
}

echo json_encode(array(
  'ok' => 1,
  'from' => $from,
  'to' => $to,
  'users_query' => count($users),
  'paid_orders' => $buyer_count,
  'unique_emails' => count($contacts),
  'solo_registrado' => $n_reg,
  'solo_comprador_guest' => $n_buy,
  'registrado_y_comprador' => $n_both,
  'file' => $out,
  'bytes' => strlen($csv),
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
`;

function execCmd(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream
        .on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out)))
        .on('data', (d) => {
          out += d;
        });
      stream.stderr.on('data', (d) => {
        out += d;
      });
    });
  });
}

function sftpWrite(conn, remote, contents) {
  return new Promise((resolve, reject) => {
    conn.sftp((err, sftp) => {
      if (err) return reject(err);
      const buf = Buffer.from(contents, 'utf8');
      sftp.writeFile(remote, buf, (e2) => {
        sftp.end();
        if (e2) return reject(e2);
        resolve();
      });
    });
  });
}

function sftpRead(conn, remote) {
  return new Promise((resolve, reject) => {
    conn.sftp((err, sftp) => {
      if (err) return reject(err);
      sftp.readFile(remote, (e2, data) => {
        sftp.end();
        if (e2) return reject(e2);
        resolve(data);
      });
    });
  });
}

const conn = new Client();
conn
  .on('ready', async () => {
    try {
      const base = e.SSH_REMOTE_PATH;
      const remotePhp = '/tmp/ss-export-brevo.php';
      const remoteCsv = '/tmp/ss-brevo-contactos.csv';
      await sftpWrite(conn, remotePhp, php);
      const meta = await execCmd(
        conn,
        `cd ${JSON.stringify(base)} && wp eval-file ${JSON.stringify(remotePhp)} --allow-root 2>&1`
      );
      console.log(meta);
      const csv = await sftpRead(conn, remoteCsv);
      const local = path.join('C:/Proyectos/sorteoseguro', 'brevo-contactos-reg-y-compra-2026-05-15.csv');
      fs.writeFileSync(local, csv);
      console.log('LOCAL=' + local);
      await execCmd(conn, `rm -f ${JSON.stringify(remotePhp)} ${JSON.stringify(remoteCsv)}`);
      conn.end();
    } catch (err) {
      console.error('ERR:', err.message);
      conn.end();
      process.exit(1);
    }
  })
  .connect({
    host: e.SSH_HOST,
    port: +e.SSH_PORT,
    username: e.SSH_USER,
    password: e.SSH_PASSWORD,
  });
