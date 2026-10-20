process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const browser = await pw.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args], env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' }, headless: true });
const page = await browser.newPage({ viewport: { width: 2200, height: 1200 } });
await page.goto('http://127.0.0.1:8124/product-images/cover/index.html', { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
const report = await page.evaluate(() => {
  const cover = document.getElementById('cover').getBoundingClientRect();
  const out = [];
  for (const el of document.querySelectorAll('.cover *')) {
    const r = el.getBoundingClientRect();
    if (!r.width || !r.height) continue;
    const bleed = el.closest('.grain,.dots,.halo,.streak,.floor,.edge');
    if (bleed) continue;
    if (r.left < cover.left - 1 || r.top < cover.top - 1 || r.right > cover.right + 1 || r.bottom > cover.bottom + 1)
      out.push(['outside', el.className || el.tagName, Math.round(r.left), Math.round(r.top), Math.round(r.right), Math.round(r.bottom)]);
    if (el.scrollWidth > el.clientWidth + 2 && getComputedStyle(el).overflow === 'visible' && el.clientWidth)
      out.push(['h-overflow', el.className || el.tagName, el.scrollWidth, el.clientWidth]);
  }
  const feats = [...document.querySelectorAll('.feat p')].map(p => [p.textContent.trim().slice(0, 18), Math.round(p.getBoundingClientRect().left)]);
  const cards = [...document.querySelectorAll('.notif,.mini,.phone,.trust')].map(e => [e.className, Math.round(e.getBoundingClientRect().left), Math.round(e.getBoundingClientRect().right)]);
  return { out, feats, cards, minFeatLeft: Math.min(...feats.map(f => f[1])) };
});
console.log(JSON.stringify(report, null, 1));
await browser.close();
