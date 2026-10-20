// Clean panel screenshots: topbar + sidebar hidden so the cards fill the frame.
process.env.LD_LIBRARY_PATH = '/tmp/alx';
const chromium = (await import('/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js')).default;
const { chromium: pw } = await import('/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs');
const out = '/home/user/Ashna/product-images/infographic/raw';

const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: [...chromium.args], env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx' }, headless: true,
});
const ctx = await browser.newContext({ viewport: { width: 1400, height: 1500 }, deviceScaleFactor: 2, locale: 'fa-IR' });
const page = await ctx.newPage();
await page.goto('http://127.0.0.1:9400/?qa_login_as=admin', { waitUntil: 'networkidle' });
await page.waitForTimeout(900);

await page.addStyleTag({ content: `
  .ashna-topbar{display:none !important}
  #wpadminbar{display:none !important}
  html{margin-top:0 !important}
  #wpcontent{padding-top:0 !important}
`});

async function p(tab, name, h) {
  await page.locator(`button[data-tab="${tab}"]`).first().click({ force: true }).catch(() => {});
  await page.waitForTimeout(1200);
  const sel = 'section.ashna-panel:visible';
  const el = page.locator(sel).first();
  const box = await el.boundingBox();
  await el.screenshot({ path: `${out}/c-${name}.png`, clip: { x: 0, y: 0, width: box.width, height: Math.min(h, box.height) } });
  console.log(name, Math.round(box.width), Math.round(box.height), '->', h);
}

await p('auth_flow', 'auth', 820);
await p('sms_gateways', 'gateways', 1000);
await p('security_firewall', 'security', 640);
await p('woocommerce', 'shop', 620);
await p('social', 'sso', 640);
await p('appearance_studio', 'appearance', 830);
await p('bale_email', 'messengers', 600);
await p('tools_backup', 'tools', 560);
await p('dashboard', 'dashboard', 700);

// dark mode: toggle lives in the topbar, so click it via JS then re-shoot
await page.evaluate(() => {
  const t = document.querySelector('#ashna-theme-toggle');
  if (t) t.dispatchEvent(new MouseEvent('click', { bubbles: true }));
});
await page.waitForTimeout(1000);
const el2 = page.locator('section.ashna-panel:visible').first();
const b2 = await el2.boundingBox();
await el2.screenshot({ path: `${out}/c-dashboard-dark.png`, clip: { x: 0, y: 0, width: b2.width, height: Math.min(700, b2.height) } });
console.log('dark', Math.round(b2.width), Math.round(b2.height));

await browser.close();
