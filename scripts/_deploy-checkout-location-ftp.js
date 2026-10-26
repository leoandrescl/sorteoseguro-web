/* Deploy Región+Comuna (checkout) por FTPS.
 * - Respalda las versiones ORIGINALES de producción en bkp-checkout-region-<stamp>/ (local)
 *   y en <remoto>.bak-<stamp>.
 * - Sube los archivos nuevos/editados.
 * - Verifica por hash y purge LiteSpeed.
 * Uso: node scripts/_deploy-checkout-location-ftp.js [--dry]
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const ftp = require('basic-ftp');
const https = require('https');

const ROOT = 'C:/Proyectos/sorteoseguro';
const e = Object.fromEntries(
  fs.readFileSync(path.join(ROOT, '.env.local'), 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const DRY = process.argv.includes('--dry');
const stamp = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '').slice(0, 12);
const BKP = path.join(ROOT, 'bkp-checkout-region-' + stamp);
const MU = '/wp-content/mu-plugins';

const EDITED = [
  ['mu-plugins/sorteoseguro-checkout/templates/checkout/form-checkout.php', 'sorteoseguro-checkout/templates/checkout/form-checkout.php'],
  ['mu-plugins/sorteoseguro-checkout/assets/checkout.js', 'sorteoseguro-checkout/assets/checkout.js'],
  ['mu-plugins/sorteoseguro-checkout/assets/checkout.css', 'sorteoseguro-checkout/assets/checkout.css'],
];
const ADDED = [
  ['mu-plugins/sorteoseguro-checkout-location.php', 'sorteoseguro-checkout-location.php'],
  ['mu-plugins/sorteoseguro-checkout-location/data/chile-regiones-comunas.php', 'sorteoseguro-checkout-location/data/chile-regiones-comunas.php'],
];

const md5 = (b) => crypto.createHash('md5').update(b).digest('hex');
const localP = (rel) => path.join(ROOT, rel);
const remoteP = (rel) => MU + '/' + rel;
const bkpName = (rel) => rel.replace(/[\/]/g, '__');

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0', 'Cache-Control': 'no-cache' }, rejectUnauthorized: false }, (res) => {
      let body = ''; res.on('data', (d) => (body += d)); res.on('end', () => resolve({ status: res.statusCode, body }));
    }).on('error', reject);
  });
}

(async () => {
  const c = new ftp.Client(120000);
  try {
    fs.mkdirSync(BKP, { recursive: true });
    await c.access({ host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21), user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });

    console.log('STAMP', stamp, DRY ? '(DRY)' : '');

    // 1) Bajar originales de producción (respaldo local autoritativo).
    for (const [local, rel] of EDITED) {
      const dest = path.join(BKP, bkpName(rel));
      await c.downloadTo(dest, remoteP(rel));
      console.log('backup local:', path.relative(ROOT, dest), md5(fs.readFileSync(dest)));
    }

    // 2) Crear .bak en el servidor ANTES de sobreescribir.
    if (!DRY) {
      for (const [local, rel] of EDITED) {
        const src = path.join(BKP, bkpName(rel));
        await c.uploadFrom(src, remoteP(rel) + '.bak-' + stamp);
        console.log('backup remoto:', rel + '.bak-' + stamp);
      }
    }

    // 3) Subir archivos nuevos + editados.
    for (const [local, rel] of ADDED.concat(EDITED)) {
      console.log(DRY ? 'would upload ' + rel : 'upload ' + rel);
      if (DRY) continue;
      const dir = path.posix.dirname(remoteP(rel));
      await c.ensureDir(dir);
      await c.uploadFrom(localP(local), remoteP(rel));
    }

    // 4) Verificar por hash.
    if (!DRY) {
      for (const [local, rel] of ADDED.concat(EDITED)) {
        const buf = await new Promise((res, rej) => {
          const { Writable } = require('stream'); const chunks = [];
          const w = new Writable({ write(ch, enc, cb) { chunks.push(ch); cb(); } });
          c.downloadTo(w, remoteP(rel)).then(() => res(Buffer.concat(chunks))).catch(rej);
        });
        const ok = md5(buf) === md5(fs.readFileSync(localP(local)));
        console.log(ok ? 'OK  ' : 'FALLA', rel, md5(buf).slice(0, 8));
        if (!ok) throw new Error('hash mismatch ' + rel);
      }
    }

    // 5) Purge LiteSpeed.
    if (!DRY) {
      const key = 'ssloc' + stamp;
      const php = `<?php $k='${key}'; if((string)($_GET['k']??'')!==$k){http_response_code(403);exit('no');} require __DIR__.'/wp-load.php'; if(function_exists('litespeed_purge_all')){litespeed_purge_all();echo 'purged';}else{do_action('litespeed_purge_all');echo 'action';} if(function_exists('wp_cache_flush'))wp_cache_flush();`;
      const tmpRel = '_tmp-ss-loc-purge-' + stamp + '.php';
      const tmpLocal = path.join(ROOT, tmpRel);
      fs.writeFileSync(tmpLocal, php);
      await c.cd('/');
      await c.uploadFrom(tmpLocal, '/' + tmpRel);
      const hit = await fetchText('https://sorteoseguro.cl/' + tmpRel + '?k=' + key);
      console.log('purge', hit.status, hit.body.trim());
      try { await c.remove('/' + tmpRel); } catch (_) {}
      try { fs.unlinkSync(tmpLocal); } catch (_) {}
    }

    console.log('\nDEPLOY OK', stamp, DRY ? '(dry, sin cambios)' : '');
  } catch (err) {
    console.error('DEPLOY ERROR:', err.message);
    process.exit(1);
  } finally {
    c.close();
  }
})();
