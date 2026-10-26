const fs = require('fs');
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
const remoteBase = (env.FTP_REMOTE_PATH || '/').replace(/\/$/, '') || '';
const remote = `${remoteBase}/wp-content/plugins/promo-engine/promo-engine.php`;
const local = 'C:/Proyectos/sorteoseguro/plugins/promo-engine/promo-engine.php';

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
    const ls = await client.list(require('path').posix.dirname(remote));
    const entries = ls.filter((e) => e.name.startsWith('promo-engine.php'));
    entries.forEach((e) => console.log('REMOTE', e.name, e.size));
    const localSize = fs.statSync(local).size;
    console.log('LOCAL size', localSize);
    const remoteSize = (entries.find((e) => e.name === 'promo-engine.php') || {}).size;
    console.log('DIFF?', remoteSize !== localSize);

    const dst = 'C:/Proyectos/sorteoseguro/scripts/_tmp-remote-pe.php';
    await client.downloadTo(dst, remote);
    const rb = fs.readFileSync(dst, 'utf8');
    console.log('remote head:', JSON.stringify(rb.slice(0, 80)));
    console.log('remote has class:', rb.includes('final class Promo_Engine_Stable'));
    console.log('remote has bulk meta:', rb.includes("'bulk_qty'"));
    try { fs.unlinkSync(dst); } catch (_) {}
  } finally {
    client.close();
  }
}
main().catch((e) => {
  console.error(e);
  process.exit(1);
});
