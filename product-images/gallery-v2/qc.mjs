// Layout QC for every gallery frame: catches overflow past the 800x900 canvas,
// collisions with the footer strip and overlaps between stage blocks.
process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');

const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' },
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 900, height: 1000 } });
await page.goto('http://127.0.0.1:8124/product-images/gallery-v2/index.html', { waitUntil: 'networkidle', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(500);

const report = await page.evaluate(() => {
  const out = [];
  for (const slide of document.querySelectorAll('.slide')) {
    const id = slide.id;
    const s = slide.getBoundingClientRect();
    const foot = slide.querySelector('.foot')?.getBoundingClientRect();
    const stageEl = slide.querySelector('.stage');
    const stage = stageEl?.getBoundingClientRect();

    // anything spilling outside the canvas
    for (const el of slide.querySelectorAll('*')) {
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) continue;
      if (el.closest('.sig')) continue;
      if (r.left < s.left - 1 || r.right > s.right + 1 || r.top < s.top - 1 || r.bottom > s.bottom + 1) {
        out.push([id, 'overflow', (el.className || el.tagName).toString().slice(0, 40),
          Math.round(r.left - s.left), Math.round(r.top - s.top), Math.round(r.right - s.left), Math.round(r.bottom - s.top)]);
      }
    }

    if (stage && foot) {
      // stage blocks must not touch the footer band
      for (const el of [...stageEl.children]) {
        const r = el.getBoundingClientRect();
        if (r.bottom > foot.top - 8) {
          out.push([id, 'hits-footer', (el.className || el.tagName).toString().slice(0, 30),
            Math.round(r.bottom - s.top), Math.round(foot.top - s.top)]);
        }
      }
      // pairwise overlap of the direct stage blocks
      const kids = [...stageEl.children].map(e => [e, e.getBoundingClientRect()]);
      for (let i = 0; i < kids.length; i++) {
        for (let j = i + 1; j < kids.length; j++) {
          const [ea, a] = kids[i], [eb, b] = kids[j];
          const ox = Math.min(a.right, b.right) - Math.max(a.left, b.left);
          const oy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
          if (ox > 6 && oy > 6) {
            out.push([id, 'overlap', (ea.className || ea.tagName).toString().slice(0, 22) + ' x ' + (eb.className || eb.tagName).toString().slice(0, 22),
              Math.round(ox), Math.round(oy)]);
          }
        }
      }
    }
  }
  return out;
});

console.log(JSON.stringify(report, null, 0) === '[]' ? 'CLEAN' : JSON.stringify(report, null, 1));
await browser.close();
