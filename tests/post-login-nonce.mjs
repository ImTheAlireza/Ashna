// Actual frontend.js + jQuery in Chromium; AJAX/WebAuthn transport is mocked.
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import assert from 'node:assert/strict';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, 'product-images/gallery-design/package.json'));
const { chromium: playwright } = require('playwright-core');
const chromiumModule = require('@sparticuz/chromium');
const chromium = chromiumModule.default || chromiumModule;
if (process.env.ASHNA_BUNDLED_LIBS === '1') {
  const libs = await chromiumModule.inflate(path.resolve(path.dirname(require.resolve('@sparticuz/chromium')), '../bin/al2023.tar.br'));
  chromiumModule.setupLambdaEnvironment(path.join(libs, 'lib'));
}
const browser = await playwright.launch({ executablePath: process.env.CHROME_PATH || await chromium.executablePath(), args: chromium.args, headless: true });
try {
  const page = await browser.newPage();
  for (const blocked of [false, true]) {
    await page.goto('about:blank');
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setContent(`<div class="ashna-otp-wrapper" data-otp-length="5" data-passkey-prompt="1"><div class="ashna-otp-card"><div class="ashna-otp-alert"></div><form class="ashna-step-request"><input class="ashna-identifier-input"></form><form class="ashna-step-verify">${[1,2,3,4,5].map(n => `<input class="ashna-digit-box" value="${n}">`).join('')}<input class="ashna-otp-hidden-code"><button class="ashna-submit-verify">Verify</button></form><form class="ashna-step-password"></form><div class="ashna-step-passkey-enroll" style="display:none"><button class="ashna-enroll-passkey-now">Enroll</button></div></div></div>`);
    await page.addScriptTag({ path: path.join(root, '.cache/frontend-tests/node_modules/jquery/dist/jquery.js') });
    await page.evaluate(({ blocked }) => {
      window.ashnaOtpParams = { ajaxUrl: '/admin-ajax.php', nonce: 'guest-nonce', i18n: { networkError: 'Network error' } };
      Object.defineProperty(window, 'localStorage', { configurable: true, get() { if (blocked) throw new Error('Storage blocked'); return { getItem() { return null; } }; } });
      Object.defineProperty(window, 'PublicKeyCredential', { configurable: true, value: function () {} });
      Object.defineProperty(navigator, 'credentials', { configurable: true, value: {} });
      window.sent = [];
      jQuery.fx.off = true;
      jQuery.ajax = options => {
        window.sent.push(options.data);
        return jQuery.Deferred().resolve({ success: true, data: { message: 'Logged in', nonce: 'authenticated-nonce', redirect_to: '/my-account' } }).promise();
      };
      jQuery.post = (url, data) => {
        window.sent.push(data);
        return jQuery.Deferred().resolve({ success: false, data: { message: 'Ceremony not exercised by this transport test' } }).promise();
      };
    }, { blocked });
    await page.addScriptTag({ path: path.join(root, 'assets/js/frontend.js') });
    await page.waitForFunction(() => jQuery('.ashna-otp-wrapper').data('ashna-initialized'));
    await page.evaluate(() => jQuery('.ashna-step-verify').trigger('submit'));
    assert.equal(await page.evaluate(() => ashnaOtpParams.nonce), 'authenticated-nonce');
    assert.equal(await page.locator('.ashna-step-passkey-enroll').isVisible(), true);
    await page.locator('.ashna-enroll-passkey-now').click();
    const requests = await page.evaluate(() => window.sent);
    assert.equal(requests[0].action, 'ashna_verify_otp');
    assert.equal(requests[0].nonce, 'guest-nonce');
    assert.equal(requests[1].action, 'ashna_passkey_register_options');
    assert.equal(requests[1].nonce, 'authenticated-nonce');
    assert.deepEqual(errors, []);
    console.log(`PASS browser nonce handoff: OTP guest → authenticated Passkey enrollment (storage ${blocked ? 'blocked' : 'available'})`);

  }
} finally { await browser.close(); }
