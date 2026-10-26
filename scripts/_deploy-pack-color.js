const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');

const projectRoot = path.resolve(__dirname, '..');
const e = Object.fromEntries(
  fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);
const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const base = (e.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';

const files = [
  ['plugins/promo-engine/promo-engine.php', '/wp-content/plugins/promo-engine/promo-engine.php'],
  ['mu-plugins/sorteoseguro-packs-lottery.php', '/wp-content/mu-plugins/sorteoseguro-packs-lottery.php'],
  ['mu-plugins/sorteoseguro-comprar-bono.php', '/wp-content/mu-plugins/sorteoseguro-comprar-bono.php'],
  ['mu-plugins/sorteoseguro-comprar-cyber.php', '/wp-content/mu-plugins/sorteoseguro-comprar-cyber.php'],
];

(async () => {
  const client = new ftp.Client(180000);
  client.ftp.verbose = false;
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME,
      port: +(e.FTP_PORT || 21),
      user: e.FTP_USER,
      password: e.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });

    for (const [rel, remoteRel] of files) {
      const local = path.join(projectRoot, rel);
      const remote = base + remoteRel;
      await client.ensureDir(path.posix.dirname(remote));
      try {
        await client.rename(remote, `${remote}.bak-${stamp}`);
        console.log('backup ', remoteRel);
      } catch (_) {
        console.log('backup skip (nuevo)', remoteRel);
      }
      await client.uploadFrom(local, remote);
      console.log('upload ', remoteRel);
    }

    const purgeLocal = path.join(projectRoot, 'scripts', '_purge-all.php');
    const purgeRemote = `${base}/_purge-all.php`;
    await client.uploadFrom(purgeLocal, purgeRemote);
    console.log('upload  /_purge-all.php');

    console.log('OK deploy stamp', stamp);
  } finally {
    client.close();
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
