const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const out = 'C:/Users/leoan/AppData/Local/Temp/opencode/flow';
(async () => {
  fs.mkdirSync(out, { recursive: true });
  const client = new ftp.Client(120000);
  try {
    await client.access({ host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21),
      user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    await client.downloadToDir(out + '/flowpaymentfl', '/wp-content/plugins/flowpaymentfl');
    console.log('downloaded flowpaymentfl');
    for (const f of ['debug.log', 'error_log', 'php_errorlog']) {
      try { await client.downloadTo(out + '/' + f, '/wp-content/' + f); console.log('downloaded', f); }
      catch (err) { console.log('no', f, err.message); }
    }
    try { await client.downloadTo(out + '/error_log_root', '/error_log'); console.log('downloaded root error_log'); } catch (err) { console.log('no root error_log', err.message); }
  } catch (err) { console.error('ERR', err.message); }
  finally { client.close(); }
})();
