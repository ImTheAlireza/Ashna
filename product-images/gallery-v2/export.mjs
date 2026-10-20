// Renders every frame of the Ashna gallery at 2x (800x900 css -> 1600x1800 px).
import fs from 'node:fs';
process.env.LD_LIBRARY_PATH = '/tmp/alx/lib';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');

const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args, '--font-render-hinting=none', '--force-color-profile=srgb'],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' },
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 900, height: 1000 }, deviceScaleFactor: 2 });
page.on('console', (m) => { if (m.type() === 'error') console.log('console:', m.text()); });
page.on('requestfailed', (r) => console.log('failed:', r.url()));
await page.goto('http://127.0.0.1:8124/product-images/gallery-v2/index.html', { waitUntil: 'networkidle', timeout: 120000 });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(800);

const names = {
  s1: '01-otp', s2: '02-gateways', s3: '03-limits', s4: '04-failover',
  s5: '05-sso', s6: '06-appearance', s7: '07-panel', s8: '08-form',
};
fs.mkdirSync('raw', { recursive: true });
for (const [id, name] of Object.entries(names)) {
  const buf = await page.locator('#' + id).screenshot();
  fs.writeFileSync(`raw/${name}.png`, buf);
  console.log(name, buf.length);
}
await browser.close();
