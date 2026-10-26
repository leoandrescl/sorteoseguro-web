const fs = require('fs');
const f = 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery.php';
const s = fs.readFileSync(f, 'utf8');
const all = [...s.matchAll(/return <<<'CSS'\r?\n([\s\S]*?)\r?\nCSS;/g)].map((x) => x[1]);
if (!all.length) throw new Error('css not found');
const css = all.reduce((a, b) => (b.length > a.length ? b : a), '');

function card(n, qty, pay, price, list, save, pct, accent) {
  return `
  <div class="ss-pack-card ss-pack-card--promo ss-pack--1x1" role="button" tabindex="0" aria-pressed="false" style="--ss-pack-accent:${accent};--ss-pack-accent-soft:rgba(130,36,227,0.14);--ss-pack-glow:rgba(130,36,227,0.2)">
    <span class="ss-pack-banner__tab"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M13 2 4.5 13.2h6L9.2 22 19.5 9.8h-6.2L13 2z"/></svg><span>PACK CYBER ${n}</span></span>
    <span class="ss-pack-check" aria-hidden="true"></span>
    <span class="ss-pack-banner__tickets" aria-hidden="true"><img class="ss-pack-banner__tickets-img" src="file:///C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery/tickets-cyber.png" alt=""></span>
    <div class="ss-pack-info">
      <div class="ss-pack-label">${qty} DigiTickets</div>
      <div class="ss-pack-packname">PACK ${qty}x${pay}</div>
      <span class="ss-pack-tag"><svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M2.2 2.2h5.1c.3 0 .6.12.82.34l5.54 5.54a1.16 1.16 0 0 1 0 1.64l-4.1 4.1a1.16 1.16 0 0 1-1.64 0L2.38 8.28A1.16 1.16 0 0 1 2.04 7.46V2.2zm3.1 3.35a1.05 1.05 0 1 0 0-2.1 1.05 1.05 0 0 0 0 2.1z"/></svg><span class="ss-pack-tag-text">Pagas ${pay} y\nrecibes ${qty}</span></span>
    </div>
    <div class="ss-pack-metrics">
      <div class="ss-pack-metric"><div class="ss-pack-metric-label">PRECIO NORMAL</div><div class="ss-pack-list">${list}</div></div>
      <div class="ss-pack-metric ss-pack-metric--save"><div class="ss-pack-metric-label">AHORRAS</div><div class="ss-pack-save">${save}</div></div>
    </div>
    <div class="ss-pack-pricebox">
      <div class="ss-pack-price">${price}</div>
      <div class="ss-pack-per">por ${qty} DigiTickets</div>
      <div class="ss-pack-cta">SELECCIONAR</div>
    </div>
    <span class="ss-pack-banner__pct"><b>${pct}%<br>DCTO.</b></span>
  </div>`;
}

const head = `
  <div class="ss-pack-banners__head" aria-hidden="true">
    <span class="ss-pack-banners__brand"><span class="l1">PACK ESPECIAL</span><span class="l2">CYBER</span></span>
    <div class="ss-pack-banners__copy">
      <p class="ss-pack-banners__title">Aprovecha los Packs Cyber</p>
      <p class="ss-pack-banners__sub">Más de un <strong>80%</strong> de descuento<br>para tus DigiTickets</p>
    </div>
    <span class="ss-pack-banners__bag"><img class="ss-pack-banners__bag-img" src="file:///C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery/icono-bolsa-cyber.png" alt=""></span>
  </div>`;

const html = `<!doctype html><html><head><meta charset="utf-8"><style>
body{margin:0;padding:24px;background:#eef0f6;}
.wrap{background:#fff;padding:18px;border-radius:12px;margin:0 0 24px;}
.w600{width:600px;}.w420{width:420px;}
${css}
</style></head><body>
<div class="wrap w600"><div class="ss-packs-wrap"><div class="ss-pack-banners">${head}${card(1,60,10,'$50.000','$300.000','$250.000',83,'#8224e3')}${card(2,120,16,'$80.000','$600.000','$520.000',87,'#1e73be')}</div></div></div>
<div class="wrap w420"><div class="ss-packs-wrap"><div class="ss-pack-banners">${head}${card(1,60,10,'$50.000','$300.000','$250.000',83,'#8224e3')}</div></div></div>
</body></html>`;

fs.writeFileSync('C:/Users/leoan/AppData/Local/Temp/opencode/banner-preview.html', html);
console.log('preview written');
