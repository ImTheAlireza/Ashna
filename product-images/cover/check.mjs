process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const browser = await pw.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args], env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' }, headless: true });
const page = await browser.newPage({ viewport: { width: 2200, height: 1200 } });
await page.goto('http://127.0.0.1:8124/product-images/cover/index.html', { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
const data = await page.evaluate(() => {
  const sel = ['.chip-sms','.chip-shield','.chip-sso','.phone','.gw','.gw-label','.features','.identity','.feat:nth-child(5)','.brand'];
  const out = {};
  for (const s of sel) { const e = document.querySelector(s); if (!e) { out[s]='MISSING'; continue; } const r = e.getBoundingClientRect(); out[s] = [Math.round(r.x),Math.round(r.y),Math.round(r.right),Math.round(r.bottom)]; }
  const tiles = [...document.querySelectorAll('.gw b')].map(b => { const r=b.getBoundingClientRect(); return [b.textContent.trim(), Math.round(r.x),Math.round(r.y),Math.round(r.right),Math.round(r.bottom)]; });
  const over = [];
  const items = [...document.querySelectorAll('.chip, .phone, .gw, .features, .identity')];
  for (let i=0;i<items.length;i++) for (let j=i+1;j<items.length;j++){
    const a=items[i].getBoundingClientRect(), b=items[j].getBoundingClientRect();
    const w=Math.min(a.right,b.right)-Math.max(a.left,b.left), h=Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top);
    if (w>1 && h>1) over.push([items[i].className, items[j].className, Math.round(w), Math.round(h)]);
  }
  return { out, tiles, over };
});
console.log(JSON.stringify(data, null, 1));
await browser.close();
