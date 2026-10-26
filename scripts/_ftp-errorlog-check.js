const fs = require('fs');
const ftp = require('basic-ftp');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const out = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow';
(async () => {
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST, port: +e.FTP_PORT, user: e.FTP_USER,
      password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.downloadTo(out + '/mu-error-log.php', '/wp-content/mu-plugins/sorteoseguro-error-log.php');
    console.log('downloaded mu-error-log.php');
    const size = await client.size('/wp-content/ss-error.log');
    const start = Math.max(0, size - 6000);
    fs.writeFileSync(out + '/ss-error-latest.log', '');
    await client.downloadTo(out + '/ss-error-latest.log', '/wp-content/ss-error.log', start);
    console.log('ss-error.log size now', size);
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }
})();
