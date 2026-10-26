const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');
const projectRoot = path.resolve(__dirname, '..');
const e = Object.fromEntries(
  fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8')
    .split(/\r?\n/).filter(l=>l && !l.startsWith('#'))
    .map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];})
);
(async () => {
  const client = new ftp.Client(120000);
  await client.access({
    host: e.FTP_HOST,
    port: +(e.FTP_PORT || 21),
    user: e.FTP_USER,
    password: e.FTP_PASSWORD,
    secure: true,
    secureOptions: { rejectUnauthorized: false }
  });
  const remoteBase = (e.FTP_REMOTE_PATH || '/').replace(/\/$/,'');
  const files = [
    {local: path.join(projectRoot, 'premio-2.png'), remote: `${remoteBase}/wp-content/uploads/2025/07/premio-2.png`},
    {local: path.join(projectRoot, 'premio-3.png'), remote: `${remoteBase}/wp-content/uploads/2025/07/premio-3.png`}
  ];
  for (const f of files) {
    const stat = fs.statSync(f.local);
    console.log('Uploading', f.local, 'size', stat.size, 'to', f.remote);
    await client.uploadFrom(f.local, f.remote);
  }
  client.close();
  console.log('Done');
})().catch(e=>{console.error(e); process.exit(1);});
