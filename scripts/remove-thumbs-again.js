const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');
const e = Object.fromEntries(fs.readFileSync(path.resolve(__dirname,'..','.env.local'),'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];}));
(async ()=>{
  const c=new ftp.Client(60000);
  await c.access({host:e.FTP_HOST,port:+ (e.FTP_PORT||21),user:e.FTP_USER,password:e.FTP_PASSWORD,secure:true,secureOptions:{rejectUnauthorized:false}});
  const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
  const thumbs = [
    'premio-2-150x150.png','premio-2-300x249.png','premio-2-300x300.png','premio-2-600x498.png','premio-2-768x637.png','premio-2-100x100.png',
    'premio-3-150x150.png','premio-3-300x249.png','premio-3-300x300.png','premio-3-600x498.png','premio-3-768x637.png','premio-3-100x100.png'
  ];
  for(const name of thumbs){
    try{ await c.remove(`${remoteBase}/wp-content/uploads/2025/07/${name}`); console.log('removed',name);}catch(err){console.log('skip',name,err.message);}
  }
  c.close();
})();
