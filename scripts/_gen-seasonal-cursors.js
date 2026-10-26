const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

function crcTable() {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
}
const CRC = crcTable();
function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = CRC[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length, 0);
  const td = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(td), 0);
  return Buffer.concat([len, td, crc]);
}
function encodePNG(w, h, rgba) {
  const sig = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0);
  ihdr.writeUInt32BE(h, 4);
  ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;
  const raw = Buffer.alloc((w * 4 + 1) * h);
  for (let y = 0; y < h; y++) {
    raw[y * (w * 4 + 1)] = 0;
    rgba.copy(raw, y * (w * 4 + 1) + 1, y * w * 4, (y + 1) * w * 4);
  }
  const idat = zlib.deflateSync(raw, { level: 9 });
  return Buffer.concat([sig, chunk('IHDR', ihdr), chunk('IDAT', idat), chunk('IEND', Buffer.alloc(0))]);
}

function makeCanvas(w, h) {
  return { w, h, d: Buffer.alloc(w * h * 4) };
}
function setPx(c, x, y, col) {
  x = Math.round(x); y = Math.round(y);
  if (x < 0 || y < 0 || x >= c.w || y >= c.h) return;
  const i = (y * c.w + x) * 4;
  const a = (col[3] == null ? 255 : col[3]) / 255;
  const da = c.d[i + 3] / 255;
  const oa = a + da * (1 - a);
  if (oa === 0) {
    c.d[i] = c.d[i + 1] = c.d[i + 2] = c.d[i + 3] = 0;
    return;
  }
  c.d[i] = Math.round((col[0] * a + c.d[i] * da * (1 - a)) / oa);
  c.d[i + 1] = Math.round((col[1] * a + c.d[i + 1] * da * (1 - a)) / oa);
  c.d[i + 2] = Math.round((col[2] * a + c.d[i + 2] * da * (1 - a)) / oa);
  c.d[i + 3] = Math.round(oa * 255);
}
function fillEllipse(c, cx, cy, rx, ry, col) {
  for (let y = Math.floor(cy - ry); y <= Math.ceil(cy + ry); y++) {
    for (let x = Math.floor(cx - rx); x <= Math.ceil(cx + rx); x++) {
      const dx = (x - cx) / rx;
      const dy = (y - cy) / ry;
      if (dx * dx + dy * dy <= 1) setPx(c, x, y, col);
    }
  }
}
function fillRect(c, x0, y0, w, h, col) {
  for (let y = y0; y < y0 + h; y++) for (let x = x0; x < x0 + w; x++) setPx(c, x, y, col);
}
function inPoly(px, py, pts) {
  let inside = false;
  for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
    const xi = pts[i][0], yi = pts[i][1], xj = pts[j][0], yj = pts[j][1];
    const hit = yi > py !== yj > py && px < ((xj - xi) * (py - yi)) / (yj - yi) + xi;
    if (hit) inside = !inside;
  }
  return inside;
}
function fillPoly(c, pts, col) {
  let minx = Infinity, maxx = -Infinity, miny = Infinity, maxy = -Infinity;
  pts.forEach((p) => { minx = Math.min(minx, p[0]); maxx = Math.max(maxx, p[0]); miny = Math.min(miny, p[1]); maxy = Math.max(maxy, p[1]); });
  for (let y = Math.floor(miny); y <= Math.ceil(maxy); y++)
    for (let x = Math.floor(minx); x <= Math.ceil(maxx); x++)
      if (inPoly(x, y, pts)) setPx(c, x, y, col);
}
function strokeLine(c, x0, y0, x1, y1, t, col) {
  const steps = Math.ceil(Math.hypot(x1 - x0, y1 - y0) * 2) || 1;
  for (let i = 0; i <= steps; i++) {
    const x = x0 + ((x1 - x0) * i) / steps;
    const y = y0 + ((y1 - y0) * i) / steps;
    fillEllipse(c, x, y, t / 2, t / 2, col);
  }
}

const outDir = path.join(__dirname, '..', 'mu-plugins', 'sorteoseguro-seasonal', 'assets', 'halloween');
fs.mkdirSync(outDir, { recursive: true });

/* Pumpkin cursor 32x32 */
(function () {
  const c = makeCanvas(32, 32);
  fillRect(c, 14, 3, 4, 6, [95, 140, 46, 255]);
  fillEllipse(c, 16, 19, 13, 11, [255, 122, 26, 255]);
  fillEllipse(c, 9, 19, 4, 10.5, [196, 84, 10, 150]);
  fillEllipse(c, 23, 19, 4, 10.5, [196, 84, 10, 150]);
  fillPoly(c, [[9, 14], [13, 20], [6, 20]], [40, 20, 12, 255]);
  fillPoly(c, [[23, 14], [19, 20], [26, 20]], [40, 20, 12, 255]);
  fillPoly(c, [[8, 23], [11, 26], [14, 23], [17, 26], [20, 23], [23, 26], [24, 27], [8, 27]], [40, 20, 12, 255]);
  fs.writeFileSync(path.join(outDir, 'cursor-pumpkin.png'), encodePNG(32, 32, c.d));
})();

/* Spider pointer cursor 32x32 (con contorno claro para fondos oscuros) */
(function () {
  const c = makeCanvas(32, 32);
  const legs = [[6, 7], [10, 5], [22, 5], [26, 7], [5, 17], [27, 17], [7, 26], [25, 26]];
  legs.forEach((p) => strokeLine(c, 16, 17, p[0], p[1], 2.4, [235, 235, 240, 255]));
  fillEllipse(c, 16, 19, 8.5, 9, [235, 235, 240, 255]);
  fillEllipse(c, 16, 11, 6, 6, [235, 235, 240, 255]);
  legs.forEach((p) => strokeLine(c, 16, 17, p[0], p[1], 1.4, [24, 24, 30, 255]));
  fillEllipse(c, 16, 19, 7, 7.5, [24, 24, 30, 255]);
  fillEllipse(c, 16, 11, 4.6, 4.6, [24, 24, 30, 255]);
  fillEllipse(c, 14, 10.5, 1, 1, [255, 80, 60, 255]);
  fillEllipse(c, 18, 10.5, 1, 1, [255, 80, 60, 255]);
  fs.writeFileSync(path.join(outDir, 'cursor-pointer.png'), encodePNG(32, 32, c.d));
})();

console.log('cursors written to', outDir);
