const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');
const e = Object.fromEntries(fs.readFileSync(path.resolve(__dirname,'..','.env.local'),'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];}));
const projectRoot = path.resolve(__dirname,'..');
(async ()=>{
  const c=new ftp.Client(120000);
  await c.access({host:e.FTP_HOST,port:+ (e.FTP_PORT||21),user:e.FTP_USER,password:e.FTP_PASSWORD,secure:true,secureOptions:{rejectUnauthorized:false}});
  const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
  const remote = `${remoteBase}/wp-content/uploads/2025/07/premio-2.png`;
  console.log('Removing',remote);
  try{await c.remove(remote);console.log('removed');}catch(err){console.log('remove error',err.message);}
  // wait
  await new Promise(r=>setTimeout(r,1000));
  const local = path.join(projectRoot,'premio-2.png');
  console.log('Uploading',local);
  await c.uploadFrom(local, remote);
  console.log('uploaded');
  c.close();
})().catch(e=>{console.error(e);process.exit(1);});
