const fs = require('fs');
const ftp = require('basic-ftp');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
(async () => {
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST, port: +e.FTP_PORT, user: e.FTP_USER,
      password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    const size = await client.size('/wp-content/ss-error.log');
    console.log('ss-error.log size', size);
    const start = Math.max(0, size - 300000);
    const dest = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow/ss-error-tail.log';
    fs.writeFileSync(dest, '');
    await client.downloadTo(dest, '/wp-content/ss-error.log', start);
    console.log('tail downloaded ->', dest, fs.statSync(dest).size);
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }
})();
