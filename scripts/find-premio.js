const https=require('https');
https.get('https://sorteoseguro.cl/producto/sorteo-vista-mar-dunares/',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    const matches=[...d.matchAll(/premio-2[^"'\s]+/g)];
    console.log(matches.map(m=>m[0]));
  });
});
