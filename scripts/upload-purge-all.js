const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const projectRoot = path.resolve(__dirname, '..');
const e = Object.fromEntries(fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8').split(/\r?\n/).filter(l=>l && !l.startsWith('#')).map(l=>{const i=l.indexOf('=');return [l.slice(0,i), l.slice(i+1)];}));
const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
const key='purgeall20260917';
const php = `<?php
if(($_GET['k']??'')!='${key}'){http_response_code(403);exit('no');}
require __DIR__.'/wp-load.php';
if(function_exists('litespeed_purge_all')){ litespeed_purge_all(); echo 'purged all';}
elseif(function_exists('litespeed_purge_ex')){ litespeed_purge_ex('url','https://sorteoseguro.cl/wp-content/uploads/2025/07/premio-2.png'); echo 'purged ex';}
else{ echo 'no function'; }
`;
const local = path.join(projectRoot,'scripts','_purge-all.php');
fs.writeFileSync(local, php);
(async ()=>{
  const client = new ftp.Client(120000);
  await client.access({host:e.FTP_HOST,port:+ (e.FTP_PORT||21),user:e.FTP_USER,password:e.FTP_PASSWORD,secure:true,secureOptions:{rejectUnauthorized:false}});
  await client.uploadFrom(local, `${remoteBase}/_purge-all.php`);
  client.close();
  console.log('uploaded');
})();
