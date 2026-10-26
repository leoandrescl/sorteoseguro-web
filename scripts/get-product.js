const https=require('https');
https.get('https://sorteoseguro.cl/wp-json/wc/v3/products/1091?context=view',{rejectUnauthorized:false},r=>{
  let d='';
  r.on('data',c=>d+=c);
  r.on('end',()=>{
    try{ const j=JSON.parse(d); console.log('images', j.images.map(i=>i.src)); }catch(e){console.log('err',e.message); console.log(d.slice(0,500)); }
  });
});
