const https=require('https');
https.get('https://sorteoseguro.cl/comprar/sorteo-vista-mar-dunares/',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    const idx = d.indexOf('ss-pdp-marquee');
    console.log('found', idx>=0);
    if(idx>=0) console.log(d.slice(idx, idx+800));
  });
});
