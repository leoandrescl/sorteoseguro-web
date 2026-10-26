const fs = require('fs');
const ftp = require('basic-ftp');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
(async () => {
  const client = new ftp.Client(60000);
  try {
    await client.access({ host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    console.log('FTP connected. pwd:', await client.pwd());
    const list = await client.list('/wp-content/plugins');
    console.log(list.map((x) => `${x.type === 2 ? 'D' : '-'} ${x.name}`).join('\n'));
  } catch (err) {
    console.error('FTP ERR', err.message);
  } finally { client.close(); }
})();
