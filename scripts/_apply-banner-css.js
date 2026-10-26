const fs = require('fs');
const f = 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-packs-lottery.php';
const src = fs.readFileSync(f, 'utf8');
const lines = src.split('\n');

const startIdx = lines.findIndex((l) => l.includes('Banners de packs') || (l.includes('Banner de pack') && l.includes('ficha normal')));
if (startIdx < 0) throw new Error('start not found');
let endIdx = -1;
for (let i = startIdx; i < lines.length; i++) {
  if (lines[i].replace(/\r$/, '') === 'CSS;') { endIdx = i - 1; break; }
}
if (endIdx < 0) throw new Error('end not found');

const banner = `/* ---------- Banners de packs en ficha normal (/producto/) ---------- */
.ss-pack-banners {
	display: flex;
	flex-direction: column;
	gap: 4cqw;
	margin: 0 0 4cqw;
}

.ss-pack-banners__head {
	position: relative;
	display: flex;
	align-items: center;
	gap: 3cqw;
	padding: 2.8cqw 3.4cqw;
	border-radius: 3.2cqw;
	background: linear-gradient(180deg, #3b0bb0 0%, #2a0794 100%);
	color: #ffffff;
	overflow: hidden;
	box-shadow: 0 12px 30px rgba(40, 10, 120, 0.25);
}
.ss-pack-banners__head::after {
	content: "";
	position: absolute;
	inset: 0;
	background: radial-gradient(70% 140% at 90% 50%, rgba(124, 58, 237, 0.55), rgba(124, 58, 237, 0) 60%);
	pointer-events: none;
}
.ss-pack-banners__head > * {
	position: relative;
	z-index: 1;
}
.ss-pack-banners__brand {
	flex: 0 0 auto;
	display: flex;
	flex-direction: column;
	align-items: center;
	line-height: 0.92;
	font-weight: 900;
	text-transform: uppercase;
	text-align: center;
	background: linear-gradient(160deg, #ff6a2b 0%, #e0123d 100%);
	color: #ffffff;
	padding: 2cqw 3.2cqw;
	border-radius: 2cqw;
	transform: rotate(-6deg);
	box-shadow: 0 10px 22px rgba(0, 0, 0, 0.3);
}
.ss-pack-banners__brand .l1 {
	font-size: 2.4cqw;
	letter-spacing: 0.04em;
}
.ss-pack-banners__brand .l2 {
	font-size: 6cqw;
	letter-spacing: 0.01em;
	margin-top: 0.2cqw;
}
.ss-pack-banners__copy {
	flex: 1 1 auto;
	min-width: 0;
}
.ss-pack-banners__title {
	margin: 0;
	font-size: 3.4cqw;
	font-weight: 700;
	line-height: 1.05;
}
.ss-pack-banners__sub {
	margin: 0.6cqw 0 0;
	font-size: 2.7cqw;
	font-weight: 600;
	line-height: 1.15;
}
.ss-pack-banners__sub strong {
	color: #ffd21f;
	font-weight: 900;
}
.ss-pack-banners__bag {
	flex: 0 0 auto;
	width: 8.5cqw;
	height: 9.5cqw;
	color: rgba(255, 255, 255, 0.45);
}
.ss-pack-banners__bag-img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: contain;
}

.ss-pack-card--promo {
	margin-top: 8px;
}

.ss-pack-banner__tab {
	position: absolute;
	z-index: 5;
	top: -2.4cqw;
	left: 3.6cqw;
	display: inline-flex;
	align-items: center;
	gap: 1.2cqw;
	background: linear-gradient(180deg, #ffe15a, #ffcf12);
	color: #231000;
	font-weight: 900;
	font-size: 2.3cqw;
	letter-spacing: 0.04em;
	text-transform: uppercase;
	padding: 1.2cqw 3cqw;
	border-radius: 1.2cqw;
	transform: skewX(-16deg);
	box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}
.ss-pack-banner__tab > * {
	transform: skewX(16deg);
}
.ss-pack-banner__tab svg {
	width: 1.2em;
	height: 1.2em;
	color: #4c0cdf;
}

.ss-pack-banner__tickets {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	align-self: center;
	width: 72px;
	height: 52px;
}
.ss-pack-banner__tickets-img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: contain;
}

.ss-pack-banner__pct {
	position: absolute;
	z-index: 6;
	top: -4.4cqw;
	right: -1.5cqw;
	display: flex;
	align-items: center;
	justify-content: center;
	width: 10cqw;
	height: 10cqw;
	background: #e8213f;
	color: #ffffff;
	font-weight: 700;
	line-height: 1;
	text-align: center;
	transform: rotate(-8deg);
	box-shadow: 0 2cqw 4cqw rgba(232, 33, 63, 0.4);
	clip-path: polygon(50% 0%, 61% 9%, 75% 5%, 80% 18%, 95% 22%, 92% 36%, 100% 50%, 92% 64%, 95% 78%, 80% 82%, 75% 95%, 61% 91%, 50% 100%, 39% 91%, 25% 95%, 20% 82%, 5% 78%, 8% 64%, 0% 50%, 8% 36%, 5% 22%, 20% 18%, 25% 5%, 39% 9%);
}
.ss-pack-banner__pct b {
	font-size: 2.2cqw;
	font-weight: 700;
}`;

const out = [...lines.slice(0, startIdx), banner, ...lines.slice(endIdx + 1)];
fs.writeFileSync(f, out.join('\n'));
console.log('replaced banner CSS block, lines', startIdx + 1, '..', endIdx + 1);
