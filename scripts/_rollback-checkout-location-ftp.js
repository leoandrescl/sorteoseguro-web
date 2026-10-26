/* Rollback Región+Comuna (checkout) por FTPS.
 * - Restaura los 3 archivos editados desde sus .bak-<stamp> en el servidor.
 * - Elimina el mu-plugin nuevo y su carpeta de datos.
 * - Purge LiteSpeed.
 * Uso: node scripts/_rollback-checkout-location-ftp.js --stamp=YYYYMMDD-HHmm
 */
const fs = require('fs');
const path = require('path');
const { Writable } = require('stream');
const ftp = require('basic-ftp');
const https = require('https');

const ROOT = 'C:/Proyectos/sorteoseguro';
const e = Object.fromEntries(
  fs.readFileSync(path.join(ROOT, '.env.local'), 'utf8')
    .split(/\r?\n/).filter((l) => l && !l.startsWith('#'))
    .map((l) => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)]; })
);
const arg = process.argv.find((a) => a.startsWith('--stamp='));
const stamp = arg ? arg.split('=')[1] : '';
if (!stamp) { console.error('Falta --stamp=YYYYMMDD-HHmm'); process.exit(1); }

const MU = '/wp-content/mu-plugins';
const EDITED = [
  'sorteoseguro-checkout/templates/checkout/form-checkout.php',
  'sorteoseguro-checkout/assets/checkout.js',
  'sorteoseguro-checkout/assets/checkout.css',
];
const ADDED = [
  'sorteoseguro-checkout-location.php',
  'sorteoseguro-checkout-location/data/chile-regiones-comunas.php',
];

function pull(c, remote) {
  return new Promise((res, rej) => {
    const chunks = [];
    const w = new Writable({ write(ch, enc, cb) { chunks.push(ch); cb(); } });
    c.downloadTo(w, remote).then(() => res(Buffer.concat(chunks))).catch(rej);
  });
}

function fetchText(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0' }, rejectUnauthorized: false }, (res) => {
      let body = ''; res.on('data', (d) => (body += d)); res.on('end', () => resolve({ status: res.statusCode, body }));
    }).on('error', reject);
  });
}

(async () => {
  const c = new ftp.Client(120000);
  try {
    await c.access({ host: e.FTP_HOST || e.FTP_HOSTNAME, port: +(e.FTP_PORT || 21), user: e.FTP_USER, password: e.FTP_PASSWORD, secure: true, secureOptions: { rejectUnauthorized: false } });
    console.log('ROLLBACK stamp', stamp);

    // 1) Restaurar editados desde .bak remoto.
    for (const rel of EDITED) {
      const bak = MU + '/' + rel + '.bak-' + stamp;
      const buf = await pull(c, bak);
      const tmp = path.join(ROOT, '_tmp-restore-' + rel.replace(/[\/]/g, '__'));
      fs.writeFileSync(tmp, buf);
      await c.uploadFrom(tmp, MU + '/' + rel);
      fs.unlinkSync(tmp);
      console.log('restaurado', rel, buf.length + 'b');
    }

    // 2) Borrar archivos nuevos.
    for (const rel of ADDED) {
      try { await c.remove(MU + '/' + rel); console.log('eliminado', rel); }
      catch (err) { console.log('no existe/err', rel, err.message); }
    }
    try { await c.removeDir(MU + '/sorteoseguro-checkout-location/data'); } catch (_) {}
    try { await c.removeDir(MU + '/sorteoseguro-checkout-location'); } catch (_) {}

    // 3) Purge LiteSpeed.
    const key = 'sslocrb' + stamp;
    const php = `<?php $k='${key}'; if((string)($_GET['k']??'')!==$k){http_response_code(403);exit('no');} require __DIR__.'/wp-load.php'; if(function_exists('litespeed_purge_all')){litespeed_purge_all();echo 'purged';}else{do_action('litespeed_purge_all');echo 'action';} if(function_exists('wp_cache_flush'))wp_cache_flush();`;
    const tmpRel = '_tmp-ss-loc-rb-' + stamp + '.php';
    const tmpLocal = path.join(ROOT, tmpRel);
    fs.writeFileSync(tmpLocal, php);
    await c.cd('/');
    await c.uploadFrom(tmpLocal, '/' + tmpRel);
    const hit = await fetchText('https://sorteoseguro.cl/' + tmpRel + '?k=' + key);
    console.log('purge', hit.status, hit.body.trim());
    try { await c.remove('/' + tmpRel); } catch (_) {}
    try { fs.unlinkSync(tmpLocal); } catch (_) {}

    console.log('\nROLLBACK OK', stamp);
  } catch (err) {
    console.error('ROLLBACK ERROR:', err.message);
    process.exit(1);
  } finally {
    c.close();
  }
})();
