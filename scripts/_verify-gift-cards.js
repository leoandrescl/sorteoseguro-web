const fs = require('fs');
const https = require('https');

const e = Object.fromEntries(
  fs
    .readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);

const BASE = e.SITE_URL;
const jar = {};

function storeCookies(res) {
  const sc = res.headers['set-cookie'];
  if (!sc) return;
  for (const c of sc) {
    const pair = c.split(';')[0];
    const i = pair.indexOf('=');
    if (i > 0) jar[pair.slice(0, i)] = pair.slice(i + 1);
  }
}
function cookieHeader() {
  return Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
}
function req(method, url, body) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const data = body ? Buffer.from(body) : null;
    const headers = { 'User-Agent': 'Mozilla/5.0 ss-verify', Cookie: cookieHeader() };
    if (data) {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      headers['Content-Length'] = data.length;
    }
    const r = https.request(
      { method, hostname: u.hostname, path: u.pathname + u.search, headers },
      (res) => {
        storeCookies(res);
        let out = '';
        res.on('data', (d) => (out += d));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: out }));
      }
    );
    r.on('error', reject);
    if (data) r.write(data);
    r.end();
  });
}
function field(html, name) {
  const re = new RegExp(`name="${name}"[^>]*value="([^"]*)"`, 'i');
  const m = html.match(re);
  return m ? m[1] : '';
}
function formAction(html) {
  const m = html.match(/<form[^>]*id="post"[^>]*>/i);
  if (!m) return '';
  const a = m[0].match(/action="([^"]*)"/i);
  return a ? a[1] : '';
}

(async () => {
  await req('GET', `${BASE}/wp-login.php?sscachebust=${Date.now()}`);
  await req('POST', `${BASE}/wp-login.php`, new URLSearchParams({
    log: e.WP_ADMIN_EMAIL,
    pwd: e.WP_ADMIN_PASSWORD,
    'wp-submit': 'Acceder',
    redirect_to: `${BASE}/wp-admin/`,
    testcookie: '1',
  }).toString());

  // 1) Producto de prueba
  const home = await req('GET', `${BASE}/?sscachebust=${Date.now()}`);
  const pm = home.body.match(/https:\/\/[^"' ]+\/producto\/[a-z0-9-]+\//i);
  if (!pm) throw new Error('No encontré link /producto/ en la home');
  const productUrl = pm[0];
  const pp = await req('GET', `${productUrl}?sscachebust=${Date.now()}`);
  const pidm = pp.body.match(/postid-(\d+)/i);
  const pid = pidm ? pidm[1] : '';
  console.log('producto:', productUrl, 'pid=', pid);
  console.log('packs config presente:', /ss-packs-lottery-config/.test(pp.body), 'giftTag presente:', /"giftTag"/.test(pp.body));

  if (!pid) throw new Error('No pude obtener product_id');

  // 2) Crear promo de prueba (editor clásico)
  const np = await req('GET', `${BASE}/wp-admin/post-new.php?post_type=ss_gift_promo&sscachebust=${Date.now()}`);
  let action = formAction(np.body) || `${BASE}/wp-admin/post.php`;
  if (!/^https?:/i.test(action)) {
    action = `${BASE}/wp-admin/${action.replace(/^\//, '')}`;
  }
  const formBlock = (np.body.match(/<form[^>]*id="post"[^>]*>[\s\S]*?<\/form>/i) || [''])[0];
  const wpnonce = field(formBlock, '_wpnonce') || field(np.body, '_wpnonce');
  const ssgiftNonce = field(formBlock, 'ssgift_nonce') || field(np.body, 'ssgift_nonce');
  console.log('form action=', action, '_wpnonce?', !!wpnonce, 'ssgift_nonce?', !!ssgiftNonce);

  const create = await req('POST', action, new URLSearchParams({
    action: 'editpost',
    post_ID: '',
    post_type: 'ss_gift_promo',
    post_title: 'TEST REGALO (ss-verify)',
    post_status: 'publish',
    _wpnonce: wpnonce,
    _wp_http_referer: '/wp-admin/post-new.php?post_type=ss_gift_promo',
    ssgift_nonce: ssgiftNonce,
    ssgift_product_id: pid,
    ssgift_buy: '1',
    ssgift_count: '9',
    ssgift_prefix: '',
    ssgift_badge: 'REGALA 9',
    ssgift_color: '#e91e8c',
    ssgift_active: 'yes',
  }).toString());
  const newIdMatch = (create.headers.location || '').match(/post=(\d+)/);
  const newId = newIdMatch ? newIdMatch[1] : '';
  const ttl = (create.body.match(/<title>(.*?)<\/title>/is) || [])[1] || '';
  console.log('crear promo: status=', create.status, 'id=', newId, 'title=', ttl.trim().slice(0, 80), 'loc=', create.headers.location || create.body.slice(0, 100));

  // 3) Verificar inyección en el producto
  const pp2 = await req('GET', `${productUrl}?sscachebust=${Date.now()}`);
  const cfgMatch = pp2.body.match(/window\.ssPacksLottery = (\{.*?\});<\/script>/s);
  let ok = false;
  if (cfgMatch) {
    try {
      const cfg = JSON.parse(cfgMatch[1]);
      const gifts = (cfg.campaigns || []).filter((c) => Number(c.gift) > 0);
      ok = gifts.length > 0;
      console.log('campaigns:', (cfg.campaigns || []).map((c) => `${c.id}(buy${c.buy}${c.gift ? ',gift' + c.gift : ''})`).join(' '));
      console.log('tarjetas de regalo inyectadas:', gifts.length, JSON.stringify(gifts[0] || {}));
    } catch (err) {
      console.log('no pude parsear config JSON:', err.message);
    }
  } else {
    console.log('no encontré el JSON de config en la página');
  }
  console.log(ok ? 'RESULTADO: OK (tarjeta de regalo presente)' : 'RESULTADO: NO se ve la tarjeta');

  // 4) Desactivar la promo de prueba (status draft + active no)
  if (newId) {
    const edit = await req('GET', `${BASE}/wp-admin/post.php?post=${newId}&action=edit&sscachebust=${Date.now()}`);
    const eNonce = field(edit.body, '_wpnonce');
    const eSsgift = field(edit.body, 'ssgift_nonce');
    const dis = await req('POST', action, new URLSearchParams({
      action: 'editpost',
      post_ID: newId,
      post_type: 'ss_gift_promo',
      post_title: 'TEST REGALO (ss-verify)',
      post_status: 'draft',
      _wpnonce: eNonce,
      ssgift_nonce: eSsgift,
      ssgift_product_id: pid,
      ssgift_buy: '1',
      ssgift_count: '9',
    }).toString());
    console.log('desactivar promo:', dis.status, dis.headers.location || '');
  }
})().catch((err) => {
  console.error('ERROR', err.message);
  process.exit(1);
});
