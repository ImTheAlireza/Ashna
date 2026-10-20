process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const fs = await import('node:fs');
const scale = Number(process.argv[2] || 1);
const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args, '--font-render-hinting=none', '--force-color-profile=srgb'],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' }, headless: true });
const page = await browser.newPage({ viewport: { width: 1300, height: 1300 }, deviceScaleFactor: scale });
await page.goto('http://127.0.0.1:8124/product-images/cover/mobile.html', { waitUntil: 'networkidle', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(700);
const buf = await page.locator('#cover').screenshot({ scale: 'device' });
fs.writeFileSync(process.argv[3] || '/tmp/mobile.png', buf);
console.log('ok', buf.length);
await browser.close();
