const fs = require('fs');
const ftp = require('basic-ftp');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const out = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow';
(async () => {
  const client = new ftp.Client(60000);
  try {
    await client.access({ host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    const root = await client.list('/');
    console.log('ROOT:'); console.log(root.map((x) => `${x.type === 2 ? 'D' : '-'} ${x.name} ${x.size}`).join('\n'));
    const mu = await client.list('/wp-content/mu-plugins');
    console.log('\nMU-PLUGINS:'); console.log(mu.map((x) => `${x.type === 2 ? 'D' : '-'} ${x.name}`).join('\n'));
    for (const f of ['wp-config.php', '.htaccess', 'error_log', 'php.ini', '.user.ini']) {
      try { await client.downloadTo(out + '/root__' + f.replace(/[^a-z0-9._-]/gi, '_'), '/' + f); console.log('downloaded', f); }
      catch (err) { console.log('no', f); }
    }
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }
})();
