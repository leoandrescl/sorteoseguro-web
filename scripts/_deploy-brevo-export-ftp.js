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
const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const local = 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-brevo-export.php';
const remote = `${remoteBase}/wp-content/mu-plugins/sorteoseguro-brevo-export.php`;

async function main() {
  const client = new ftp.Client(60000);
  try {
    await client.access({
      host: e.FTP_HOST || e.FTP_HOSTNAME,
      port: +(e.FTP_PORT || 21),
      user: e.FTP_USER,
      password: e.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });
    const bak = `${remote}.bak-${stamp}`;
    const tmpBak = path.join('C:/Proyectos/sorteoseguro', '._bak-sorteoseguro-brevo-export.php');
    try {
      await client.downloadTo(tmpBak, remote);
      await client.uploadFrom(tmpBak, bak);
      console.log('backup', bak);
    } catch (err) {
      console.warn('backup skipped', err.message);
    }
    await client.uploadFrom(local, remote);
    console.log('uploaded', remote);
    try {
      fs.unlinkSync(tmpBak);
    } catch (_) {}
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
