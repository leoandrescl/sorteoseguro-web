const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');

const e = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);

const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const remoteBase = (e.FTP_REMOTE_PATH || e.FTP_SERVER_PATH || '').replace(/\/$/, '');
const files = [
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-ingresos.php',
    remote: `${remoteBase}/wp-content/mu-plugins/sorteoseguro-ingresos.php`,
  },
  {
    local: 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-ingresos/assets/ingresos.css',
    remote: `${remoteBase}/wp-content/mu-plugins/sorteoseguro-ingresos/assets/ingresos.css`,
  },
];

async function main() {
  const client = new ftp.Client(60000);
  client.ftp.verbose = true;
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME,
      port: +(e.FTP_PORT || 21),
      user: e.FTP_USER,
      password: e.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });
    for (const f of files) {
      const bak = `${f.remote}.bak-${stamp}`;
      try {
        await client.downloadTo(path.join('C:/Proyectos/sorteoseguro', `._bak-${path.basename(f.remote)}`), f.remote);
        await client.uploadFrom(
          path.join('C:/Proyectos/sorteoseguro', `._bak-${path.basename(f.remote)}`),
          bak
        );
        console.log('backup ok', bak);
      } catch (err) {
        console.warn('backup skipped', path.basename(f.remote), err.message);
      }
      await client.uploadFrom(f.local, f.remote);
      console.log('uploaded', f.remote);
    }
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
