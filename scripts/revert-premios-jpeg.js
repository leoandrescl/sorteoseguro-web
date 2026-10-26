const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');
const e = Object.fromEntries(fs.readFileSync(path.resolve(__dirname,'..','.env.local'),'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];}));
const projectRoot = path.resolve(__dirname,'..');
(async ()=>{
  const c=new ftp.Client(120000);
  await c.access({host:e.FTP_HOST,port:+ (e.FTP_PORT||21),user:e.FTP_USER,password:e.FTP_PASSWORD,secure:true,secureOptions:{rejectUnauthorized:false}});
  const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
  const files = [
    {local:path.join(projectRoot,'premio-2.jpeg'), remote:`${remoteBase}/wp-content/uploads/2025/07/premio-2.png`},
    {local:path.join(projectRoot,'premio-3.jpeg'), remote:`${remoteBase}/wp-content/uploads/2025/07/premio-3.png`}
  ];
  for(const f of files){
    console.log('Uploading',f.local);
    await c.uploadFrom(f.local,f.remote);
  }
  c.close();
  console.log('Done');
})();
