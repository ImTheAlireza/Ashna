process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const browser = await pw.launch({ executablePath: await chromium.executablePath(), args: [...chromium.args],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' }, headless: true });
const page = await browser.newPage({ viewport: { width: 900, height: 1000 } });
await page.goto('http://127.0.0.1:8124/product-images/gallery-v2/index.html', { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(400);

const out = await page.evaluate(() => {
  const res = {};
  const S = (id) => document.querySelector('#' + id).getBoundingClientRect();
  // 1. step order inside frame 1
  const st = [...document.querySelectorAll('#s1 .step')].map(s => {
    const k = s.querySelector('.k').getBoundingClientRect();
    const ico = s.querySelector('.ico').getBoundingClientRect();
    return { k: Math.round(k.left), ico: Math.round(ico.left) };
  });
  res.steps = st;
  // 2. every leaf text node whose box overlaps another leaf text box
  const overlaps = [];
  for (const slide of document.querySelectorAll('.slide')) {
    const leaves = [...slide.querySelectorAll('div,span,p,h2')]
      .filter(e => e.children.length === 0 && e.textContent.trim().length > 1);
    const boxes = leaves.map(e => [e, e.getBoundingClientRect()]);
    for (let i = 0; i < boxes.length; i++) for (let j = i + 1; j < boxes.length; j++) {
      const [a, ra] = boxes[i], [b, rb] = boxes[j];
      if (a.contains(b) || b.contains(a)) continue;
      const ox = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left);
      const oy = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
      if (ox > 5 && oy > 5) overlaps.push([slide.id, a.textContent.trim().slice(0, 22), b.textContent.trim().slice(0, 22), Math.round(ox), Math.round(oy)]);
    }
  }
  res.textOverlaps = overlaps.slice(0, 20);
  // 3. distance from each slide's lowest stage block to the footer
  res.gaps = [...document.querySelectorAll('.slide')].map(s => {
    const f = s.querySelector('.foot').getBoundingClientRect();
    let low = 0, who = '';
    for (const el of s.querySelectorAll('.art *')) {
      if (el.closest('svg')) continue;
      const r = el.getBoundingClientRect();
      if (r.height && r.bottom > low && r.bottom < f.top) { low = r.bottom; who = (el.className || '') + '|' + el.textContent.trim().slice(0,12); }
    }
    return [s.id, Math.round(f.top - low), who.slice(0, 40)];
  });
  // 4. tiny font sizes
  const tiny = {};
  for (const slide of document.querySelectorAll('.slide')) {
    const sizes = [...slide.querySelectorAll('*')].filter(e => e.children.length === 0 && e.textContent.trim())
      .map(e => parseFloat(getComputedStyle(e).fontSize));
    tiny[slide.id] = sizes.length ? Math.min(...sizes) : 0;
  }
  res.tiny = tiny;
  return res;
});
console.log(JSON.stringify(out, null, 1));
await browser.close();
