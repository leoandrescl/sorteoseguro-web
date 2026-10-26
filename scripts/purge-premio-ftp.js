const fs = require('fs');
const path = require('path');
const ftp = require('basic-ftp');
const projectRoot = path.resolve(__dirname);
const e = Object.fromEntries(
  fs.readFileSync(path.join(projectRoot, '.env.local'), 'utf8')
    .split(/\r?\n/).filter(l=>l&&!l.startsWith('#'))
    .map(l=>{const i=l.indexOf('=');return [l.slice(0,i),l.slice(i+1)];})
);
const remoteBase = (e.FTP_REMOTE_PATH||'/').replace(/\/$/,'');
(async()=>{
  const client = new ftp.Client(120000);
  await client.access({ host:e.FTP_HOST, port:+ (e.FTP_PORT||21), user:e.FTP_USER, password:e.FTP_PASSWORD, secure:true, secureOptions:{rejectUnauthorized:false}});
  const key='purge-premio-20260916';
  const bootLocal = path.join(projectRoot,'scripts','_tmp-purge-premio.php');
  const bootRemote = `${remoteBase}/_tmp-purge-premio.php`;
  fs.writeFileSync(bootLocal, `<?php header('Content-Type:text/plain'); if(($_GET['k']??'')!='${key}'){http_response_code(403);echo 'no';exit;} require __DIR__.'/wp-load.php'; if(function_exists('wp_cache_flush')) wp_cache_flush(); echo 'ok';`);
  await client.uploadFrom(bootLocal, bootRemote);
  console.log('uploaded purge');
  client.close();
})().catch(e=>{console.error(e);process.exit(1);});