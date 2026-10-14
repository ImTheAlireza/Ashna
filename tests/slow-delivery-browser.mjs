// Real frontend.js + jQuery. AJAX transport is controlled, not a carrier benchmark.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import assert from 'node:assert/strict';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, 'product-images/gallery-design/package.json'));
const { chromium: playwright } = require('playwright-core');
const mod = require('@sparticuz/chromium'); const chromium = mod.default || mod;
if (process.env.ASHNA_BUNDLED_LIBS === '1') {
  const libs = await mod.inflate(path.resolve(path.dirname(require.resolve('@sparticuz/chromium')), '../bin/al2023.tar.br'));
  mod.setupLambdaEnvironment(path.join(libs, 'lib'));
}
const browser = await playwright.launch({ executablePath: await chromium.executablePath(), args: chromium.args, headless: true });
try {
  const page = await browser.newPage(); const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.setContent(`<div class="ashna-otp-wrapper"><div class="ashna-otp-alert" role="alert" style="display:none"></div><form class="ashna-step-request"><input class="ashna-identifier-input" value="09123456789"><button class="ashna-submit-request"><span class="ashna-spinner" style="display:none"></span>Send</button></form><form class="ashna-step-verify" style="display:none"><input class="ashna-digit-box"><input class="ashna-otp-hidden-code"></form><form class="ashna-step-password" style="display:none"></form></div>`);
  await page.addScriptTag({ path: path.join(root,'.cache/frontend-tests/node_modules/jquery/dist/jquery.js') });
  await page.evaluate(() => {
    window.ashnaOtpParams = { ajaxUrl:'/ajax', nonce:'guest', requestTimeoutMs:39000, i18n:{ networkError:'NETWORK_ERROR' } };
    window.sent = []; jQuery.fx.off = true;
    jQuery.ajax = options => { sent.push(options); window.pending = jQuery.Deferred(); return pending.promise(); };
  });
  await page.addScriptTag({ path:path.join(root,'assets/js/frontend.js') });
  await page.waitForFunction(() => jQuery('.ashna-otp-wrapper').data('ashna-initialized'));
  await page.clock.install();
  await page.evaluate(() => { jQuery('.ashna-step-request').trigger('submit'); jQuery('.ashna-step-request').trigger('submit'); });
  assert.equal(await page.evaluate(() => sent.length),1, 'Repeated Enter created duplicate sends');
  assert.equal(await page.locator('.ashna-submit-request').isDisabled(),true);
  await page.clock.runFor(3100);
  assert.match(await page.locator('.ashna-otp-alert').textContent(),/هنوز در حال بررسی/);
  await page.evaluate(() => { pending.reject({},'timeout'); });
  assert.match(await page.locator('.ashna-otp-alert').textContent(),/ممکن است انجام شده باشد/);
  assert.equal(await page.locator('.ashna-submit-request').isDisabled(),false);
  assert.equal(await page.locator('.ashna-spinner').isVisible(),false);
  await page.clock.runFor(10000);
  assert.equal(await page.evaluate(() => sent.length),1, 'Timeout auto-retried a billable send');
  await page.evaluate(() => { jQuery('.ashna-step-request').trigger('submit'); pending.resolve({success:false,data:{message:'SERVER_ERROR'}}); });
  await page.clock.runFor(4000);
  assert.equal(await page.locator('.ashna-otp-alert').textContent(),'SERVER_ERROR','Stale slow timer overwrote response');
  await page.evaluate(() => { jQuery('.ashna-step-request').trigger('submit'); pending.reject({},'error'); });
  assert.equal(await page.locator('.ashna-otp-alert').textContent(),'NETWORK_ERROR');
  await page.evaluate(() => {
    jQuery('.ashna-step-request').trigger('submit');
    pending.resolve({success:true,data:{identifier:'09123456789',masked:'0912***6789',otp_length:5,resend_cooldown:60}});
  });
  assert.equal(await page.locator('.ashna-step-verify').isVisible(),true);
  await page.clock.runFor(4000);
  assert(!/هنوز در حال بررسی/.test(await page.locator('.ashna-otp-alert').textContent()));
  assert.equal(await page.evaluate(() => sent.every(o => o.timeout===39000)),true);
  assert.equal(await page.evaluate(() => sent.length),4);
  assert.deepEqual(errors,[]);
  console.log('PASS: 3s patient feedback, duplicate-submit guard, timeout without automatic retry, error/success timer cleanup, spinner/button recovery and OTP transition.');
} finally { await browser.close(); }
