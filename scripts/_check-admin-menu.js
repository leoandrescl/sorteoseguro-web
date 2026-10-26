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
  return Object.entries(jar)
    .map(([k, v]) => `${k}=${v}`)
    .join('; ');
}

function req(method, url, body) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const data = body ? Buffer.from(body) : null;
    const headers = {
      'User-Agent': 'Mozilla/5.0 ss-check',
      Cookie: cookieHeader(),
    };
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

(async () => {
  await req('GET', `${BASE}/wp-login.php?sscachebust=${Date.now()}`);

  const body = new URLSearchParams({
    log: e.WP_ADMIN_EMAIL,
    pwd: e.WP_ADMIN_PASSWORD,
    'wp-submit': 'Acceder',
    redirect_to: `${BASE}/wp-admin/`,
    testcookie: '1',
  }).toString();

  let login = await req('POST', `${BASE}/wp-login.php`, body);
  // follow one redirect
  if ([301, 302, 303].includes(login.status) && login.headers.location) {
    login = await req('GET', login.headers.location);
  }
  const loggedIn = /wordpress_logged_in/.test(cookieHeader());
  console.log('login status:', login.status, 'loggedInCookie=', loggedIn);

  const admin = await req('GET', `${BASE}/wp-admin/?sscachebust=${Date.now()}`);
  const hasMenuText = /Promos de regalo/.test(admin.body);
  const hasSlug = /ss_gift_promo/.test(admin.body);
  console.log('wp-admin status:', admin.status, 'menuText=', hasMenuText, 'slug=', hasSlug);

  const cpt = await req('GET', `${BASE}/wp-admin/edit.php?post_type=ss_gift_promo&sscachebust=${Date.now()}`);
  console.log('edit.php?post_type=ss_gift_promo:', cpt.status, /Promos de regalo|ss_gift_promo/.test(cpt.body));
  const m = cpt.body.match(/<h1[^>]*>(.*?)<\/h1>/i);
  if (m) console.log('   h1:', m[1].replace(/<[^>]+>/g, '').trim());

  const np = await req('GET', `${BASE}/wp-admin/post-new.php?post_type=ss_gift_promo&sscachebust=${Date.now()}`);
  const hasMetabox = /Configuraci[oó]n de la Promo de Regalo/.test(np.body);
  const hasFields = /name="ssgift_product_id"/.test(np.body) && /name="ssgift_count"/.test(np.body);
  const hasPhpError = /Fatal error|Warning:|Notice:/i.test(np.body);
  console.log('post-new status:', np.status, 'metabox=', hasMetabox, 'fields=', hasFields, 'phpNotice=', hasPhpError);
  if (hasPhpError) {
    const re = /.{0,80}(Fatal error|Warning:|Notice:).{0,180}/gi;
    let mm;
    let c = 0;
    while ((mm = re.exec(np.body)) && c < 8) {
      console.log('   >', mm[0].replace(/\s+/g, ' ').slice(0, 240));
      c++;
    }
  }

  if (!hasMenuText && !hasSlug) {
    console.log('   (no encontrado en el HTML de /wp-admin/; puede requerir navegar)');
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
