const https=require('https');
const opts={hostname:'sorteoseguro.cl',path:'/wp-content/uploads/2025/07/premio-2.png',headers:{'Cache-Control':'no-cache','Pragma':'no-cache'}};
https.get(opts,{rejectUnauthorized:false},r=>{
  console.log(r.statusCode,r.headers['cache-control']);
  let len=0;
  r.on('data',d=>len+=d.length);
  r.on('end',()=>console.log('bytes',len));
});
