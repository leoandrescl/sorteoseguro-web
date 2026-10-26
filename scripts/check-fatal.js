const https=require('https');
https.get('https://sorteoseguro.cl/comprar/sorteo-vista-mar-dunares/',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    console.log(d.indexOf('Fatal error')>=0?'fatal':'no fatal');
    console.log(d.slice(0,2000));
  });
});
