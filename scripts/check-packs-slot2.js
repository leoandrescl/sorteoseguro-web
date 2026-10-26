const https=require('https');
https.get('https://sorteoseguro.cl/comprar/sorteo-vista-mar-dunares/',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    const idx = d.indexOf('ss-pdp-packs-slot');
    const snippet = d.slice(Math.max(0,idx-500), idx+500);
    const count = (d.match(/ss-pdp-packs-slot/g)||[]).length;
    console.log('count',count);
    console.log(snippet.slice(0,1000));
  });
});
