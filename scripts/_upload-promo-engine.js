const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');

const env = Object.fromEntries(
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
const remoteBase = (env.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';

async function main() {
  const client = new ftp.Client(180000);
  try {
    await client.access({
      host: env.FTP_HOST || env.FTP_HOSTNAME,
      port: +(env.FTP_PORT || 21),
      user: env.FTP_USER,
      password: env.FTP_PASSWORD,
      secure: true,
      secureOptions: { rejectUnauthorized: false },
    });
    const local = 'C:/Proyectos/sorteoseguro/plugins/promo-engine/promo-engine.php';
    const remote = `${remoteBase}/wp-content/plugins/promo-engine/promo-engine.php`;
    try {
      await client.rename(remote, `${remote}.bak-${stamp}`);
      console.log('remote backup ok');
    } catch (_) {
      console.log('remote backup skip');
    }
    await client.uploadFrom(local, remote);
    console.log('uploaded promo-engine.php');
  } finally {
    client.close();
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
