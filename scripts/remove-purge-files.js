const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');
const e = Object.fromEntries(fs.readFileSync(path.resolve(__dirname,'..','.env.local'),'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];}));
(async ()=>{
  const c=new ftp.Client(60000);
  await c.access({host:e.FTP_HOST,port:+ (e.FTP_PORT||21),user:e.FTP_USER,password:e.FTP_PASSWORD,secure:true,secureOptions:{rejectUnauthorized:false}});
  const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
  const files = ['_purge-all.php','_purge-lscache.php','_tmp-purge-premio.php'];
  for(const f of files){
    try{ await c.remove(`${remoteBase}/${f}`); console.log('removed',f); }catch(err){console.log('skip',f,err.message); }
  }
  c.close();
})();
