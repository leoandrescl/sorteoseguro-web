const https=require('https');
https.get('https://sorteoseguro.cl/comprar/sorteo-vista-mar-dunares/',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    const idx = d.indexOf('purchase-panel');
    console.log('found', idx>=0);
    console.log(d.slice(Math.max(0,idx-200), idx+400));
  });
});
