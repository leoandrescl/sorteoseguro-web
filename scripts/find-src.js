const https=require('https');
https.get('https://sorteoseguro.cl/producto/sorteo-vista-mar-dunares/',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    const matches=[...d.matchAll(/src="([^"]*premio-2[^"]*)"/g)];
    console.log(matches.map(m=>m[1]));
  });
});
