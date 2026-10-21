// node scripts/render-help-pdf.mjs
// Local: npm i --prefix .cache playwright-core @sparticuz/chromium
// Bundled libs: SIGNA_BUNDLED_LIBS=1 node scripts/render-help-pdf.mjs
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, '.cache/package.json'));
const { chromium: playwright } = require('playwright-core');
const chromiumModule = require('@sparticuz/chromium');
const chromium = chromiumModule.default || chromiumModule; // v131 exports the class directly
// CHROME_PATH + LD_LIBRARY_PATH already set → use them; otherwise let the package
// unpack its own binary and shared libraries (bundled-libs mode).
const exe = process.env.CHROME_PATH || await chromium.executablePath();
const env = { ...process.env };
if (!process.env.LD_LIBRARY_PATH) {
  const libs = path.join(path.dirname(exe), 'libs');
  env.LD_LIBRARY_PATH = [libs, path.join(libs, 'lib'), process.env.LD_LIBRARY_PATH].filter(Boolean).join(':');
}
const browser = await playwright.launch({ executablePath: exe, args: chromium.args, headless: true, env });
try {
  const page = await browser.newPage({ viewport: { width: 900, height: 1200 }, locale: 'fa-IR' });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('requestfailed', r => errors.push(r.url()));
  await page.goto(pathToFileURL(path.join(root, 'docs/help-fa.html')).href, { waitUntil: 'networkidle' });
  await page.waitForFunction(() => document.body.dataset.paginated === '1', null, { timeout: 15000 });
  await page.waitForTimeout(400); // توزیع فاصله‌ها در فریم بعدی اعمال می‌شود
  await page.emulateMedia({ media: 'print' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all([...document.images].map(image => image.decode()));
  });
  const check = await page.evaluate(() => {
    const toFa = n => String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
    const lists = [...document.querySelectorAll('ol.steps')];
    const badSteps = [];
    lists.forEach(list => [...list.querySelectorAll(':scope > li')].forEach((li, i) => {
      const label = li.querySelector('.st')?.textContent.trim();
      if (label !== toFa(i + 1)) badSteps.push(`${label}≠${toFa(i + 1)}`);
    }));
    const text = document.body.innerText;
    return {
      figures: document.querySelectorAll('figure img').length,
      brokenImages: [...document.images].filter(i => !i.naturalWidth).map(i => i.getAttribute('src')),
      steps: document.querySelectorAll('ol.steps > li').length,
      badSteps,
      latinDigits: (text.replace(/[\u0600-\u06FF]/g, '').match(/[0-9]/g) || []).length,
      fontLoaded: document.fonts.check('900 24pt Estedad'),
      phpMinimum: text.includes('۸.۲'),
    };
  });
  const layout = await page.evaluate(() => [...document.querySelectorAll('.page')].map((p, i) => {
    const footer = p.querySelector('.footer').getBoundingClientRect();
    const contents = [...p.children].filter(e => !e.classList.contains('footer'));
    const bottom = Math.max(...contents.map(e => e.getBoundingClientRect().bottom));
    const items = [...p.querySelectorAll('.step, .hero, .chapter, table, .requirements, h3')].map(e => ({
      cls: e.className.split(' ')[0] || e.tagName.toLowerCase(),
      h: Math.round(e.getBoundingClientRect().height),
    }));
    return { page: i + 1, footerGap: Math.round(footer.top - bottom), overflow: p.scrollHeight - p.clientHeight, items };
  }));
  console.log(JSON.stringify({ check, layout }, null, 2));
  await fs.mkdir(path.join(root, 'deliverables'), { recursive: true });
  await page.pdf({ path: path.join(root, 'deliverables/help.pdf'), preferCSSPageSize: true, printBackground: true, tagged: true, outline: true });
  console.log('errors:', errors.filter(u => !/fonts\.googleapis|wp\.com/.test(u)).slice(0, 5));
} finally { await browser.close(); }
