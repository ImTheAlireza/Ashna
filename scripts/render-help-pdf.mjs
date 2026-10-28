// node scripts/render-help-pdf.mjs
// Local: npm i --prefix .cache playwright-core @sparticuz/chromium
// On minimal Linux, set LD_LIBRARY_PATH to the compatible Chromium libraries.
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
  await page.waitForFunction(() => document.body.dataset.paginated === '1' || document.body.dataset.paginationError, null, { timeout: 15000 });
  const paginationError = await page.evaluate(() => document.body.dataset.paginationError);
  if (paginationError) throw new Error(`Help pagination failed: ${paginationError}`);
  await page.waitForTimeout(400); // توزیع فاصله‌ها در فریم بعدی اعمال می‌شود
  await page.emulateMedia({ media: 'print' });
  await page.evaluate(async () => {
    await Promise.all([400, 500, 700, 900].map(weight => document.fonts.load(`${weight} 12pt Estedad`)));
    await document.fonts.ready;
    await Promise.all([...document.images].map(image => image.decode()));
  });
  const check = await page.evaluate(() => {
    const toFa = n => String(n).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
    const labels = [...document.querySelectorAll('.step h4 .num')].map(el => el.textContent.trim());
    const expectedLabels = [1, 2, 3, 1, 2, 3, 1, 2, 3, 4, 5, 6, 7, 8, 9].map(toFa);
    const badSteps = labels.length === expectedLabels.length
      ? labels.flatMap((label, i) => label === expectedLabels[i] ? [] : [`${label}≠${expectedLabels[i]}`])
      : [`Expected ${expectedLabels.length} numbered headings, found ${labels.length}`];
    const text = document.body.innerText;
    const successFigure = document.querySelector('img[src$="install-3-done.webp"]').getBoundingClientRect();
    return {
      figures: document.querySelectorAll('figure img').length,
      brokenImages: [...document.images].filter(i => !i.naturalWidth).map(i => i.getAttribute('src')),
      missingAlt: [...document.querySelectorAll('figure img')].filter(i => !i.getAttribute('alt')?.trim()).map(i => i.getAttribute('src')),
      steps: labels.length,
      badSteps,
      installImageWidthMm: Math.round(successFigure.width / (96 / 25.4) * 10) / 10,
      latinDigits: (text.replace(/[\u0600-\u06FF]/g, '').match(/[0-9]/g) || []).length,
      fontLoaded: [400, 500, 700, 900].every(weight => document.fonts.check(`${weight} 12pt Estedad`)),
      phpMinimum: text.includes('۸.۲'),
      licenseDisclosed: text.includes('در ZIP فعلی وجود ندارد') && text.includes('لایسنس شبیه‌سازی‌شده'),
    };
  });
  const layout = await page.evaluate(() => [...document.querySelectorAll('.page')].map((p, i) => {
    const footer = p.querySelector('.footer').getBoundingClientRect();
    const contents = [...p.querySelector('.pbody').children];
    const bottom = Math.max(...contents.map(e => e.getBoundingClientRect().bottom));
    const items = [...p.querySelectorAll('.step, .hero, .chapter, table, .requirements, h3')].map(e => ({
      cls: e.className.split(' ')[0] || e.tagName.toLowerCase(),
      h: Math.round(e.getBoundingClientRect().height),
    }));
    const body = p.querySelector('.pbody');
    return {
      page: i + 1,
      footerGap: Math.round(footer.top - bottom),
      overflow: p.scrollHeight - p.clientHeight,
      bodyOverflow: body.scrollHeight - body.clientHeight,
      horizontalOverflow: body.scrollWidth - body.clientWidth,
      items,
    };
  }));
  const relevantErrors = errors.filter(u => !/fonts\.googleapis|wp\.com/.test(u));
  console.log(JSON.stringify({ check, layout, errors: relevantErrors }, null, 2));
  const invalidLayout = layout.filter(p => p.overflow > 1 || p.bodyOverflow > 1 || p.horizontalOverflow > 1 || p.footerGap < 0);
  if (check.figures !== 14 || check.brokenImages.length || check.missingAlt.length || check.badSteps.length ||
      check.installImageWidthMm > 115 || !check.fontLoaded || !check.phpMinimum || !check.licenseDisclosed ||
      invalidLayout.length || relevantErrors.length) {
    throw new Error('Help PDF validation failed; inspect the report above before publishing.');
  }
  await fs.mkdir(path.join(root, 'deliverables'), { recursive: true });
  await page.pdf({ path: path.join(root, 'deliverables/help.pdf'), preferCSSPageSize: true, printBackground: true, tagged: true, outline: true });
} finally { await browser.close(); }
