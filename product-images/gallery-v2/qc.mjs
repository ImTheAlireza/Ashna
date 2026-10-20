// Layout QC for every gallery frame: catches overflow past the 800x900 canvas,
// text running into the footer strip, and stage blocks colliding with each other.
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
    const artEl = slide.querySelector('.art');
    const art = artEl?.getBoundingClientRect();

    // anything spilling outside the canvas
    for (const el of slide.querySelectorAll('*')) {
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) continue;
      if (el.closest('svg') || el.classList.contains('bleed')) continue;
      if (r.left < s.left - 1 || r.right > s.right + 1 || r.top < s.top - 1 || r.bottom > s.bottom + 1) {
        out.push([id, 'overflow', (el.className || el.tagName).toString().slice(0, 34),
          Math.round(r.left - s.left), Math.round(r.top - s.top), Math.round(r.right - s.left), Math.round(r.bottom - s.top)]);
      }
    }

    if (art && foot) {
      for (const el of [...artEl.children]) {
        if (el.classList.contains('bleed')) continue;
        const r = el.getBoundingClientRect();
        if (r.bottom > foot.top - 6) {
          out.push([id, 'hits-footer', (el.className || el.tagName).toString().slice(0, 30),
            Math.round(r.bottom - s.top), Math.round(foot.top - s.top)]);
        }
      }
    }

    // title and subtitle must stay on the canvas and not collide
    const t = slide.querySelector('.t')?.getBoundingClientRect();
    const sub = slide.querySelector('.s')?.getBoundingClientRect();
    if (t && sub && t.bottom > sub.top + 1) out.push([id, 'title-overlap', Math.round(t.bottom - sub.top)]);
    if (t && art && t.top < s.top) out.push([id, 'title-clipped', Math.round(t.top - s.top)]);
  }
  return out;
});

console.log(JSON.stringify(report) === '[]' ? 'CLEAN' : JSON.stringify(report, null, 1));
await browser.close();
