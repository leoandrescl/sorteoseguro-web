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

function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream.on('close', (code) => resolve({ code, out })).on('data', (d) => (out += d)).stderr.on('data', (d) => (out += d));
    });
  });
}

function sftpPut(conn, local, remote) {
  return new Promise((resolve, reject) => {
    conn.sftp((err, sftp) => {
      if (err) return reject(err);
      sftp.fastPut(local, remote, (e2) => (e2 ? reject(e2) : resolve()));
    });
  });
}

function sftpMkdir(conn, remoteDir) {
  return new Promise((resolve, reject) => {
    conn.sftp((err, sftp) => {
      if (err) return reject(err);
      sftp.mkdir(remoteDir, (e2) => {
        // ignore already exists
        resolve();
      });
    });
  });
}

const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 12);
const root = e.SSH_REMOTE_PATH;
const files = [
  {
    local: 'C:/Proyectos/sorteoseguro/plugins/promo-engine/promo-engine.php',
    remote: `${root}/wp-content/plugins/promo-engine/promo-engine.php`,
    backup: true,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery.php',
    remote: `${root}/wp-content/mu-plugins/sorteoseguro-packs-lottery.php`,
    backup: true,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar/layout-comprar.php',
    remote: `${root}/wp-content/mu-plugins/sorteoseguro-comprar/layout-comprar.php`,
    backup: true,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar-bono.php',
    remote: `${root}/wp-content/mu-plugins/sorteoseguro-comprar-bono.php`,
    backup: false,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-comprar-bono/page-oferta.php',
    remote: `${root}/wp-content/mu-plugins/sorteoseguro-comprar-bono/page-oferta.php`,
    backup: false,
  },
];

const conn = new Client();
conn
  .on('ready', async () => {
    try {
      await sftpMkdir(conn, `${root}/wp-content/mu-plugins/sorteoseguro-comprar-bono`);
      for (const f of files) {
        if (f.backup) {
          console.log('backup', path.basename(f.remote));
          console.log((await exec(conn, `cp -a '${f.remote}' '${f.remote}.bak-${stamp}' 2>/dev/null || true`)).out);
        }
        await sftpPut(conn, f.local, f.remote);
        console.log('uploaded', f.remote.replace(root, ''));
      }

      const verify = await exec(
        conn,
        [
          `php -l '${root}/wp-content/mu-plugins/sorteoseguro-comprar-bono.php'`,
          `php -l '${root}/wp-content/plugins/promo-engine/promo-engine.php'`,
          `grep -n "stable-p13\\|get_preload_page_campaigns\\|SESSION_PRELOAD" '${root}/wp-content/plugins/promo-engine/promo-engine.php' | head -20`,
          `grep -n "SorteoSeguro_Comprar_Bono\\|oferta" '${root}/wp-content/mu-plugins/sorteoseguro-comprar-bono.php' | head -15`,
        ].join(' && ')
      );
      console.log(verify.out);

      const seed = await exec(
        conn,
        `cd '${root}' && wp eval 'if (class_exists("SorteoSeguro_Comprar_Bono")) { SorteoSeguro_Comprar_Bono::maybe_seed_pages(); echo "seeded\\n"; $p=get_page_by_path("oferta/jeep-avenger"); echo $p ? ("page=".$p->ID." url=".get_permalink($p->ID)."\\n") : "page=missing\\n"; } else { echo "class missing\\n"; }' --allow-root 2>&1`
      );
      console.log(seed.out);

      const purge = await exec(
        conn,
        `cd '${root}' && wp cache flush --allow-root 2>&1 | tail -3; php -r "require 'wp-load.php'; if (class_exists('LiteSpeed\\\\Purge')) { LiteSpeed\\\\Purge::purge_all(); echo \\"litespeed purged\\n\\"; }"`
      );
      console.log(purge.out);

      conn.end();
    } catch (err) {
      console.error(err);
      conn.end();
      process.exit(1);
    }
  })
  .on('error', (err) => {
    console.error(err);
    process.exit(1);
  })
  .connect({
    host: e.SSH_HOST,
    port: Number(e.SSH_PORT || 22),
    username: e.SSH_USER,
    password: e.SSH_PASSWORD,
  });
