// Browser QA pass for Ashna: real Chromium against a WordPress Playground site (see tests/README.md).
// Not shipped with the plugin. The site needs tests/browser/ashna-browser-qa.php as a mu-plugin.
//
//   ASHNA_BROWSER_DEPS  folder with playwright-core, @sparticuz/chromium and axe-core (default .cache/browser)
//   ASHNA_QA_BASE       site URL (default http://localhost:9400 — WebAuthn needs a domain, not an IP)
//   QA_ONLY             comma-separated phases: crawl, mobile, desktop, admin
import { createRequire } from 'module'; import path from 'path'; import fs from 'fs'; import { fileURLToPath } from 'url';
const DEPS = process.env.ASHNA_BROWSER_DEPS || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../.cache/browser');
const require = createRequire(path.join(DEPS, 'package.json'));
const { chromium: playwright } = require('playwright-core'); const cm = require('@sparticuz/chromium'); const chromium = cm.default || cm;
const libs = await cm.inflate(path.resolve(path.dirname(require.resolve('@sparticuz/chromium')), '../bin/al2023.tar.br')); cm.setupLambdaEnvironment(path.join(libs, 'lib'));
const BASE = process.env.ASHNA_QA_BASE || 'http://localhost:9400';
const AXE = fs.readFileSync(path.join(DEPS, 'node_modules/axe-core/axe.min.js'), 'utf8');
const ONLY = (process.env.QA_ONLY || '').split(',').filter(Boolean);
const results = [];
const ok = (name, cond, extra = '') => { results.push(`${cond ? 'PASS' : 'FAIL'} ${name}${extra ? ' — ' + extra : ''}`); console.log(results[results.length - 1]); };
const issues = [];
const rnd = () => '0912' + String(Math.floor(1000000 + Math.random() * 8999999));
const fa = (s) => s.replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const codeIn = async (f) => ((await f.locator('.ashna-otp-alert').innerText().catch(() => '')).match(/(?<![#\d])(\d{5})(?!\d)/) || [])[1];
const loggedIn = async (ctx) => (await ctx.cookies()).some((c) => c.name.startsWith('wordpress_logged_in_'));
const isPost = (action) => (r) => (r.request().postData() || '').includes(action);

function watch(p, label) {
  p.on('console', (m) => { if (m.type() === 'error') issues.push(`${label} | console: ${m.text().slice(0, 300)}`); });
  p.on('pageerror', (e) => issues.push(`${label} | JS exception: ${e.message.slice(0, 300)}`));
  p.on('requestfailed', (r) => issues.push(`${label} | request failed: ${r.url().slice(0, 160)} (${(r.failure() || {}).errorText})`));
  p.on('response', (r) => {
    const u = r.url();
    if (u.startsWith(BASE) && r.status() >= 400 && !u.includes('no-such-page-qa') && !u.endsWith('favicon.ico')) issues.push(`${label} | HTTP ${r.status()} ${u}`);
  });
}

async function axeScan(p, label, scope) {
  await p.waitForTimeout(1500); // let entrance animations finish (axe would measure half-faded text)
  await p.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => {}))));
  await p.addScriptTag({ content: AXE });
  const v = await p.evaluate(async (sel) => {
    const r = await window.axe.run(sel ? { include: [sel] } : document, { resultTypes: ['violations'], runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } });
    return r.violations.map((x) => ({ id: x.id, impact: x.impact, nodes: x.nodes.length, sample: x.nodes.slice(0, 3).map((n) => n.target.join(' ') + ' :: ' + (n.failureSummary || '').split('\n').slice(1, 2).join(' ')).join(' | ') }));
  }, scope);
  const serious = v.filter((x) => x.impact === 'serious' || x.impact === 'critical');
  ok(`${label}: no serious/critical accessibility violations (axe ${scope || 'page'})`, serious.length === 0, JSON.stringify(serious).slice(0, 1500));
  const minor = v.filter((x) => !(x.impact === 'serious' || x.impact === 'critical'));
  if (minor.length) console.log(`   (minor a11y on ${label}: ${minor.map((x) => x.id + '×' + x.nodes).join(', ')})`);
}

async function crawl(ctx, label, mobile) {
  const p = await ctx.newPage(); watch(p, label);
  for (const u of ['/', '/login-test/', '/modal-test/', '/account-test/', '/ashna-login/', '/wp-login.php', '/?p=1', '/no-such-page-qa/']) {
    await p.goto(BASE + u, { waitUntil: 'load', timeout: 120000 });
    await p.waitForTimeout(500);
    const html = await p.content();
    const phpErr = html.match(/<b>(Fatal error|Warning|Notice|Deprecated|Parse error)<\/b>:[^<]{0,200}/);
    ok(`${label} ${u}: no PHP error printed in the page`, !phpErr, phpErr ? phpErr[0] : '');
    if (mobile) {
      const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      ok(`${label} ${u}: no sideways scrolling`, overflow <= 1, `+${overflow}px`);
    }
    if (['/login-test/', '/ashna-login/', '/wp-login.php'].includes(u)) {
      ok(`${label} ${u}: sign-in form visible`, await p.locator('.ashna-otp-wrapper').first().isVisible().catch(() => false));
    }
    if (u === '/login-test/') {
      const font = await p.evaluate(async () => { await document.fonts.ready; const el = document.querySelector('.ashna-otp-wrapper .ashna-otp-title') || document.querySelector('.ashna-otp-wrapper'); return { family: getComputedStyle(el).fontFamily.slice(0, 40), loaded: document.fonts.check('700 16px Vazirmatn'), local: [...document.fonts].some((f) => f.family.replace(/["']/g, '') === 'Vazirmatn' && f.status === 'loaded') }; });
      ok(`${label}: form renders in the bundled Vazirmatn (no CDN)`, font.loaded && font.local && font.family.includes('Vazirmatn'), JSON.stringify(font));
    }
  }
  await p.close();
}

async function mobileModalJourney(ctx) {
  const p = await ctx.newPage(); watch(p, 'mobile journey');
  await p.goto(BASE + '/modal-test/', { waitUntil: 'load', timeout: 120000 });
  await p.locator('.ashna-open-modal').first().tap();
  const overlay = p.locator('.ashna-modal-overlay').first();
  await overlay.waitFor({ state: 'visible', timeout: 15000 });
  await p.waitForTimeout(700); // open animation
  const sheet = await overlay.evaluate((el) => {
    const box = el.querySelector('.ashna-modal-content, .ashna-modal-dialog, .ashna-otp-card') || el.firstElementChild;
    const r = box.getBoundingClientRect();
    return { bottomGap: Math.round(window.innerHeight - r.bottom), width: Math.round(r.width), vw: window.innerWidth, box: box.className.slice(0, 60) };
  });
  ok('mobile: modal opens as a bottom sheet (full width, docked to the bottom)', sheet.bottomGap <= 2 && sheet.width >= sheet.vw - 2, JSON.stringify(sheet));
  await axeScan(p, 'mobile modal', '.ashna-modal-overlay');
  const f = overlay.locator('.ashna-otp-wrapper').first();
  const phone = rnd();
  await f.locator('.ashna-identifier-input').fill(fa(phone));
  await f.locator('.ashna-submit-request').tap();
  await f.locator('.ashna-step-verify').waitFor({ state: 'visible', timeout: 45000 });
  const code1 = await codeIn(f);
  ok('mobile: code requested with a number typed in Persian digits', !!code1);
  const inputMode = await f.locator('.ashna-digit-box').first().getAttribute('inputmode');
  ok('mobile: code boxes open the numeric keypad', inputMode === 'numeric', String(inputMode));
  const wrong = code1 === '11111' ? '22222' : '11111';
  const wrongResp = p.waitForResponse(isPost('ashna_verify_otp'), { timeout: 45000 });
  await f.locator('.ashna-digit-box').first().tap();
  await p.keyboard.type(wrong, { delay: 40 });
  const wr = await (await wrongResp).json();
  await p.waitForTimeout(600);
  const alertText = await f.locator('.ashna-otp-alert').innerText().catch(() => '');
  ok('mobile: a wrong code is refused with a message, still on the code step', wr.success === false && (await f.locator('.ashna-step-verify').isVisible()) && alertText.trim().length > 0, alertText.slice(0, 80));
  const boxes = await f.locator('.ashna-digit-box').evaluateAll((els) => els.map((e) => e.value).join(''));
  ok('mobile: code boxes are cleared for a new try', boxes === '', boxes);
  const resend = f.locator('.ashna-resend-btn');
  ok('mobile: resend is locked during the cooldown', !(await resend.isEnabled().catch(() => false)) || !(await resend.isVisible()));
  await p.waitForTimeout(9500);
  await resend.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  ok('mobile: resend unlocks after the cooldown', await resend.isEnabled().catch(() => false));
  const resendResp = p.waitForResponse(isPost('ashna_request_otp'), { timeout: 45000 });
  await resend.tap();
  const rr = await (await resendResp).json();
  await p.waitForTimeout(600);
  const code2 = await codeIn(f);
  ok('mobile: resend delivers a new code', rr.success === true && !!code2, JSON.stringify(rr.data || {}).slice(0, 120));
  let verifyCount = 0;
  p.on('request', (rq) => { if ((rq.postData() || '').includes('ashna_verify_otp')) verifyCount++; });
  const verifyResp = p.waitForResponse(isPost('ashna_verify_otp'), { timeout: 45000 });
  await f.locator('.ashna-digit-box').first().tap();
  await p.keyboard.type(code2, { delay: 15 }); // fast typing
  const vr = await (await verifyResp).json();
  await p.waitForTimeout(1500);
  ok('mobile: typing the corrected code sends exactly one check (no extra wrong attempts)', verifyCount === 1, String(verifyCount));
  ok('mobile: new code signs the visitor up and in', vr.success === true, JSON.stringify(vr.data || {}).slice(0, 160));
  await p.waitForTimeout(1500);
  ok('mobile: WordPress session cookie set', await loggedIn(ctx));
  if (await f.locator('.ashna-step-passkey-enroll').isVisible().catch(() => false)) {
    await f.locator('.ashna-skip-passkey-enroll').tap();
    await p.waitForTimeout(2500);
  }
  await p.goto(BASE + '/modal-test/', { waitUntil: 'load', timeout: 120000 });
  ok('mobile: signed-in visitors no longer get the sign-in button', !(await p.locator('.ashna-open-modal').first().isVisible().catch(() => false)));
  await ctx.clearCookies();
  await p.close();
}

async function desktopJourneys(ctx) {
  const p = await ctx.newPage(); watch(p, 'desktop journeys');
  const form = () => p.locator('.ashna-otp-wrapper:visible').first();
  const inbox = async () => (await (await p.request.get(BASE + '/?qa_last_mail=1')).json());
  // E-mail sign-up.
  await p.goto(BASE + '/login-test/', { waitUntil: 'load', timeout: 120000 });
  await axeScan(p, 'desktop form', '.ashna-otp-wrapper');
  const email = `qa.browser.${Date.now()}@example.com`;
  await form().locator('.ashna-identifier-input').fill(email);
  const eResp = p.waitForResponse(isPost('ashna_request_otp'), { timeout: 45000 });
  await form().locator('.ashna-submit-request').click();
  const er = await (await eResp).json();
  ok('desktop: e-mail code request accepted', er.success === true, JSON.stringify(er.data || {}).slice(0, 300));
  await form().locator('.ashna-step-verify').waitFor({ state: 'visible', timeout: 45000 });
  const mail1 = await inbox();
  ok('desktop: the code e-mail goes to the typed address', mail1.to === email || (Array.isArray(mail1.to) && mail1.to.includes(email)), JSON.stringify(mail1.to));
  ok('desktop: e-mail body contains a 5-digit code and no escaping problems', /(?<![#\d])\d{5}(?!\d)/.test(String(mail1.message)) && !/&amp;amp;|&lt;\/?(p|div)/.test(String(mail1.message)), String(mail1.subject).slice(0, 80));
  await axeScan(p, 'desktop code step', '.ashna-otp-wrapper');
  const shown = await form().locator('.ashna-recipient-display').innerText().catch(() => '');
  ok('desktop: code step shows where the code went (masked)', shown.includes('@'), shown);
  await form().locator('.ashna-change-identifier').click();
  await form().locator('.ashna-step-request').waitFor({ state: 'visible', timeout: 10000 });
  ok('desktop: "change" returns to the first step with the address kept', (await form().locator('.ashna-identifier-input').inputValue()) === email);
  await p.waitForTimeout(8500); // resend cooldown
  await form().locator('.ashna-submit-request').click();
  await form().locator('.ashna-step-verify').waitFor({ state: 'visible', timeout: 45000 });
  const code = (String((await inbox()).message).match(/(?<![#\d])(\d{5})(?!\d)/) || [])[1];
  const vResp = p.waitForResponse(isPost('ashna_verify_otp'), { timeout: 45000 });
  await form().locator('.ashna-digit-box').first().click();
  await p.keyboard.type(code, { delay: 30 });
  const v = await (await vResp).json();
  ok('desktop: e-mail code signs up and in', v.success === true, JSON.stringify(v.data || {}).slice(0, 160));
  await p.waitForTimeout(2500);
  ok('desktop: session cookie set after e-mail sign-up', await loggedIn(ctx));
  await ctx.clearCookies();

  // Password sign-in (wrong, then right by phone in Persian digits).
  await p.goto(BASE + '/login-test/', { waitUntil: 'load', timeout: 120000 });
  await form().locator('.ashna-switch-to-password').click();
  await form().locator('.ashna-step-password').waitFor({ state: 'visible', timeout: 10000 });
  await axeScan(p, 'desktop password step', '.ashna-otp-wrapper');
  await form().locator('.ashna-pw-identifier').fill('qa_pw_user');
  await form().locator('.ashna-pw-input').fill('wrong-password');
  const bad = p.waitForResponse(isPost('ashna_password_login'), { timeout: 45000 });
  await form().locator('.ashna-submit-password').click();
  const br = await (await bad).json();
  await p.waitForTimeout(500);
  ok('desktop: wrong password refused with a message', br.success === false && (await form().locator('.ashna-otp-alert').innerText()).trim().length > 0);
  await form().locator('.ashna-pw-identifier').fill(fa('09351112233'));
  await form().locator('.ashna-pw-input').fill('Qa-Pass-123!');
  const good = p.waitForResponse(isPost('ashna_password_login'), { timeout: 45000 });
  await form().locator('.ashna-submit-password').click();
  const gr = await (await good).json();
  ok('desktop: password sign-in with the phone number (Persian digits)', gr.success === true, JSON.stringify(gr.data || {}).slice(0, 160));
  await p.waitForTimeout(2500);
  ok('desktop: session cookie set after password sign-in', await loggedIn(ctx));
  await ctx.clearCookies();

  // Keyboard only.
  await p.goto(BASE + '/login-test/', { waitUntil: 'load', timeout: 120000 });
  await form().locator('.ashna-identifier-input').focus();
  await p.keyboard.type(rnd());
  const kResp = p.waitForResponse(isPost('ashna_request_otp'), { timeout: 45000 });
  await p.keyboard.press('Enter');
  const kr = await (await kResp).json();
  ok('desktop: Enter in the number field requests the code', kr.success === true);
  await form().locator('.ashna-step-verify').waitFor({ state: 'visible', timeout: 45000 });
  await p.waitForTimeout(700); // Ashna focuses the first box 220 ms after the step appears
  const focused = await p.evaluate(() => document.activeElement && document.activeElement.className);
  ok('desktop: focus moves to the first code box', String(focused).includes('ashna-digit-box'), String(focused));
  await ctx.clearCookies();

  // Standalone page and wp-login.php.
  await p.goto(BASE + '/ashna-login/', { waitUntil: 'load', timeout: 120000 });
  ok('standalone sign-in page renders the form', await form().isVisible());
  await p.goto(BASE + '/wp-login.php', { waitUntil: 'load', timeout: 120000 });
  ok('wp-login.php shows the Ashna form', await p.locator('.ashna-otp-wrapper').first().isVisible());

  // Password sign-in switched off: staff keep a way in on wp-login.php, customers do not.
  await p.request.get(BASE + '/?qa_set=allow_password_login:0');
  try {
    await p.goto(BASE + '/wp-login.php?redirect_to=' + encodeURIComponent(BASE + '/wp-admin/'), { waitUntil: 'load', timeout: 120000 });
    const staffLink = form().locator('.ashna-switch-to-password');
    const linkText = await staffLink.innerText();
    ok('wp-login.php (password sign-in off): "staff sign-in with password" offered', linkText.includes('مدیران'), linkText);
    await staffLink.click();
    await form().locator('.ashna-pw-identifier').fill('qa_pw_user');
    await form().locator('.ashna-pw-input').fill('Qa-Pass-123!');
    const c1 = p.waitForResponse(isPost('ashna_password_login'), { timeout: 45000 });
    await form().locator('.ashna-submit-password').click();
    const a1 = await (await c1).json();
    ok('…a customer cannot use it, and is told to use the one-time code', a1.success === false && String(a1.data && a1.data.message).includes('کد یکبارمصرف'), String(a1.data && a1.data.message));
    await form().locator('.ashna-pw-identifier').fill('qa_admin');
    await form().locator('.ashna-pw-input').fill('Qa-Admin-123!');
    const c2 = p.waitForResponse(isPost('ashna_password_login'), { timeout: 45000 });
    await form().locator('.ashna-submit-password').click();
    const a2 = await (await c2).json();
    ok('…an administrator signs in and is sent to the dashboard', a2.success === true && String(a2.data && a2.data.redirect_to).includes('/wp-admin'), JSON.stringify(a2.data || {}).slice(0, 160));
    await p.waitForURL((u) => u.toString().includes('/wp-admin'), { timeout: 30000 }).catch(() => {});
    ok('…and lands in wp-admin', p.url().includes('/wp-admin'), p.url());
  } finally {
    await p.request.get(BASE + '/?qa_set=allow_password_login:1');
    await ctx.clearCookies();
  }
  await p.close();
}

async function adminPass(ctx) {
  const p = await ctx.newPage(); watch(p, 'admin');
  await p.goto(BASE + '/?qa_login_as=admin', { waitUntil: 'load', timeout: 120000 });
  await p.locator('#ashna-app-shell').waitFor({ state: 'visible', timeout: 60000 });
  ok('admin: settings app loads', true);
  const tabs = await p.locator('.ashna-nav-item[data-tab]').evaluateAll((els) => els.map((e) => e.getAttribute('data-tab')));
  for (const t of tabs) {
    await p.locator(`.ashna-nav-item[data-tab="${t}"]`).click();
    await p.waitForTimeout(350);
    ok(`admin: tab "${t}" opens`, await p.locator('#ashna-tab-' + t).isVisible());
  }
  const openTexts = async () => {
    await p.locator('.ashna-nav-item[data-tab="appearance_studio"]').click();
    const body = p.locator('[name="ashna[form_title]"]').first().locator('xpath=ancestor::div[contains(@class,"ashna-accordion-body")]');
    if (!(await body.isVisible())) {
      await body.locator('xpath=preceding-sibling::div[contains(@class,"ashna-accordion-trigger")]').click();
      await p.waitForTimeout(500);
    }
    ok('admin: accordion section opens', await p.locator('[name="ashna[form_title]"]').first().isVisible());
  };
  await openTexts();
  const title = p.locator('[name="ashna[form_title]"]').first();
  const before = await title.inputValue();
  const marker = 'ورود QA ' + Date.now();
  await title.fill(marker);
  const save = p.waitForResponse(isPost('ashna_admin_save_settings'), { timeout: 60000 });
  await p.locator('#ashna-ajax-save-btn').click();
  const sr = await (await save).json();
  ok('admin: save via AJAX succeeds', sr.success === true, JSON.stringify(sr.data && sr.data.message));
  await p.reload({ waitUntil: 'load', timeout: 120000 });
  await p.locator('.ashna-nav-item[data-tab="appearance_studio"]').click();
  ok('admin: saved value is there after reload', (await p.locator('[name="ashna[form_title]"]').first().inputValue()) === marker);
  await openTexts();
  await p.locator('[name="ashna[form_title]"]').first().fill(before);
  const save2 = p.waitForResponse(isPost('ashna_admin_save_settings'), { timeout: 60000 });
  await p.locator('#ashna-ajax-save-btn').click();
  ok('admin: original title restored', (await (await save2).json()).success === true);

  await p.goto(BASE + '/wp-admin/admin.php?page=ashna-otp-logs', { waitUntil: 'load', timeout: 120000 });
  const rows = await p.locator('table tbody tr').count();
  ok('admin: logs page lists the codes sent in this run', rows > 0, String(rows));
  const csvLink = p.locator('a[href*="ashna_export_logs_csv"]').first();
  if (await csvLink.count()) {
    const href = await csvLink.getAttribute('href');
    const res = await p.request.get(new URL(href, BASE).toString());
    const body = await res.body();
    ok('admin: CSV export downloads (UTF-8 BOM + header)', res.status() === 200 && (res.headers()['content-type'] || '').includes('text/csv') && body[0] === 0xef && body.toString('utf8').includes('Recipient'), `${res.status()} ${res.headers()['content-type']}`);
  } else {
    ok('admin: CSV export link present', false);
  }
  await p.goto(BASE + '/wp-admin/users.php', { waitUntil: 'load', timeout: 120000 });
  ok('admin: users list has the mobile column', (await p.locator('th#ashna_phone, th.column-ashna_phone').count()) > 0);
  await p.goto(BASE + '/wp-admin/plugins.php', { waitUntil: 'load', timeout: 120000 });
  ok('admin: plugins screen lists Ashna without errors', (await p.content()).includes('ashna-otp'));
  await ctx.clearCookies();
  await p.close();
}

// This single-process Chromium does not isolate browser contexts (cookies leak between them),
// so every phase gets its own browser and starts from a reset site state.
const DESKTOP = { viewport: { width: 1280, height: 900 }, locale: 'fa-IR' };
const MOBILE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fa-IR', userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1' };
async function phase(name, opts, fn) {
  if (ONLY.length && !ONLY.includes(name)) return;
  const br = await playwright.launch({ executablePath: await chromium.executablePath(), args: chromium.args, headless: true });
  try {
    const ctx = await br.newContext(opts);
    await ctx.request.get(BASE + '/?qa_reset=1');
    await fn(ctx);
  } catch (e) {
    ok(name + ': no exception', false, e.stack.slice(0, 1200));
  } finally {
    await br.close();
  }
}
await phase('crawl', DESKTOP, (ctx) => crawl(ctx, 'desktop', false));
await phase('crawl', MOBILE, (ctx) => crawl(ctx, 'mobile', true));
await phase('mobile', MOBILE, mobileModalJourney);
await phase('desktop', DESKTOP, desktopJourneys);
await phase('admin', DESKTOP, adminPass);
const uniq = [...new Set(issues)];
console.log('\n--- browser console / network issues (' + uniq.length + ') ---\n' + uniq.join('\n'));
console.log(`\n${results.filter((r) => r.startsWith('PASS')).length}/${results.length} passed`);
