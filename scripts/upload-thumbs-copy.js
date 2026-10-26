const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');
const e = Object.fromEntries(fs.readFileSync(path.resolve(__dirname,'..','.env.local'),'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];}));
const projectRoot = path.resolve(__dirname,'..');
(async ()=>{
  const c=new ftp.Client(120000);
  await c.access({host:e.FTP_HOST,port:+ (e.FTP_PORT||21),user:e.FTP_USER,password:e.FTP_PASSWORD,secure:true,secureOptions:{rejectUnauthorized:false}});
  const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
  const thumbs = [
    'premio-2-150x150.png',
    'premio-2-300x249.png',
    'premio-2-300x300.png',
    'premio-2-600x498.png',
    'premio-2-768x637.png',
    'premio-2-100x100.png',
    'premio-3-150x150.png',
    'premio-3-300x249.png',
    'premio-3-300x300.png',
    'premio-3-600x498.png',
    'premio-3-768x637.png',
    'premio-3-100x100.png',
  ];
  for (const name of thumbs) {
    const is2 = name.startsWith('premio-2');
    const localFile = path.join(projectRoot, is2 ? 'premio-2.png' : 'premio-3.png');
    const remote = `${remoteBase}/wp-content/uploads/2025/07/${name}`;
    console.log('Uploading', name);
    await c.uploadFrom(localFile, remote);
  }
  c.close();
  console.log('Done');
})().catch(e=>{console.error(e);process.exit(1);});
