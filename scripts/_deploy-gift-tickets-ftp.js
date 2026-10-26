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
const ROOT = 'C:/Proyectos/sorteoseguro';

const files = [
  'sorteoseguro-gift-tickets.php',
  'sorteoseguro-home.php',
  'sorteoseguro-mis-sorteos.php',
  'sorteoseguro-cart.php',
  'sorteoseguro-thankyou.php',
  'sorteoseguro-ingresos.php',
  'sorteoseguro-packs-lottery.php',
].map((name) => ({
  local: `${ROOT}/mu-plugins/${name}`,
  remote: `${remoteBase}/wp-content/mu-plugins/${name}`,
}));

const pull = {
  remote: `${remoteBase}/wp-content/themes/hello-biz-child/functions.php`,
  local: `${ROOT}/scripts/_live-theme-functions.php`,
};

async function connect() {
  const client = new ftp.Client(90000);
  client.ftp.verbose = false;
  await client.access({
    host: e.FTP_HOST || e.FTP_HOSTNAME,
    port: +(e.FTP_PORT || 21),
    user: e.FTP_USER,
    password: e.FTP_PASSWORD,
    secure: true,
    secureOptions: { rejectUnauthorized: false },
  });
  return client;
}

async function remoteExists(client, remote) {
  try {
    await client.size(remote);
    return true;
  } catch (err) {
    return false;
  }
}

async function deployFile(f) {
  const client = await connect();
  try {
    if (await remoteExists(client, f.remote)) {
      const bak = `${f.remote}.bak-${stamp}`;
      const tmp = `${ROOT}/._bak-${path.basename(f.remote)}`;
      try {
        await client.downloadTo(tmp, f.remote);
        await client.uploadFrom(tmp, bak);
        console.log('backup ok   ', bak);
      } catch (err) {
        console.warn('backup fail ', path.basename(f.remote), '-', err.message);
      }
    } else {
      console.log('sin remoto  ', f.remote, '(nuevo, sin backup)');
    }
    await client.uploadFrom(f.local, f.remote);
    console.log('UPLOADED    ', f.remote);
    return true;
  } finally {
    client.close();
  }
}

async function main() {
  let ok = 0;
  for (const f of files) {
    try {
      await deployFile(f);
      ok++;
    } catch (err) {
      console.error('ERROR subiendo', f.remote, '-', err.message);
    }
  }
  console.log(`\n${ok}/${files.length} archivos subidos.`);

  try {
    const client = await connect();
    try {
      await client.downloadTo(pull.local, pull.remote);
      console.log('pulled tema ', pull.local);
    } finally {
      client.close();
    }
  } catch (err) {
    console.warn('pull tema fallo:', err.message);
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
