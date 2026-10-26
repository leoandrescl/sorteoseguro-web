const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const projectRoot = path.resolve(__dirname, '..');
const e = Object.fromEntries(
  fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8')
    .split(/\r?\n/).filter(l=>l&&!l.startsWith('#'))
    .map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];})
);
const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
const files = [
  { local: path.join(projectRoot, 'premio-2.jpeg'), remote: `${remoteBase}/wp-content/uploads/2025/07/premio-2.png` },
  { local: path.join(projectRoot, 'premio-3.jpeg'), remote: `${remoteBase}/wp-content/uploads/2025/07/premio-3.png` },
];
(async()=>{
  const client = new ftp.Client(120000);
  try {
    await client.access({ host:e.FTP_HOST, port:+ (e.FTP_PORT||21), user:e.FTP_USER, password:e.FTP_PASSWORD, secure:true, secureOptions:{rejectUnauthorized:false}});
    for(const f of files){
      await client.ensureDir(path.posix.dirname(f.remote));
      await client.uploadFrom(f.local, f.remote);
      console.log('uploaded', f.remote);
    }
  } finally { client.close(); }
})().catch(e=>{console.error(e);process.exit(1);});