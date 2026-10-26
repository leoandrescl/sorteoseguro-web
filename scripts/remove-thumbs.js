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
  const client = new ftp.Client();
  await client.access({
    host: e.FTP_HOST,
    port: +(e.FTP_PORT || 21),
    user: e.FTP_USER,
    password: e.FTP_PASSWORD,
    secure: true,
    secureOptions: { rejectUnauthorized: false }
  });
  const list = await client.list('wp-content/uploads/2025/07');
  const thumbs2 = list.filter(f=>f.name.startsWith('premio-2-') && f.name.endsWith('.png')).map(f=>f.name);
  const thumbs3 = list.filter(f=>f.name.startsWith('premio-3-') && f.name.endsWith('.png')).map(f=>f.name);
  const all = [...thumbs2, ...thumbs3];
  for (const n of all) {
    try { await client.remove(`wp-content/uploads/2025/07/${n}`); console.log('removed', n); } catch(e){ console.log('fail', n, e.message); }
  }
  client.close();
})().catch(e=>{console.error(e); process.exit(1);});
