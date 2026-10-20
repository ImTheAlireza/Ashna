/**
 * گرفتن اسکرین‌شات‌های اینفوگرافیک از پنل واقعی آشنا.
 *
 *   node shoot-clean.mjs http://127.0.0.1:8881
 *
 * پیش‌نیاز: یک وردپرس در حال اجرا با افزونهٔ فعال (نسخهٔ بدون لایسنس هم کافی است).
 * اگر آدرس داده نشود، http://127.0.0.1:8881 استفاده می‌شود.
 *
 * چرا این‌طور گرفته می‌شود؟
 *  • viewport پهن (۱۴۴۰) و deviceScaleFactor=2 → خروجی ۲× و بدون زوم مرورگر.
 *  • نوار وردپرس و نوار چسبان پنل پنهان می‌شوند تا بالای کارت‌ها چیزی نیفتد.
 *  • هر کارت با element.screenshot() گرفته می‌شود، پس لبه‌ها روی خود کارت است
 *    و هیچ متن یا منوی نصفه‌ای در کادر نمی‌ماند.
 *  • قبل از عکس، کارت و پنج والدش از قید max-height/overflow آزاد می‌شوند.
 *  • آدرس لوکال در متن کارت گوگل با یک دامنهٔ نمونه عوض می‌شود.
 *
 * خروجی: /tmp/caps3/*.png  (بعد به WebP تبدیل و در img/ گذاشته می‌شود)
 */
import fs from 'node:fs';

const BASE = (process.argv[2] || 'http://127.0.0.1:8881').replace(/\/$/, '');
const CHROME = '/home/user/Ashna/.cache/browser/node_modules/@sparticuz/chromium/build/index.js';
const PW = '/home/user/Ashna/.cache/browser/node_modules/playwright-core/index.mjs';
const OUT = '/tmp/caps3';

const { chromium: pw } = await import(PW);
const chromium = (await import(CHROME)).default;

const browser = await pw.launch({
  executablePath: await chromium.executablePath(),
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
  env: { ...process.env, LD_LIBRARY_PATH: '/tmp/alx/lib' },
});
const ctx = await browser.newContext({
  viewport: { width: 1440, height: 1100 },
  deviceScaleFactor: 2,
  locale: 'fa-IR',
});
const page = await ctx.newPage();

fs.mkdirSync(OUT, { recursive: true });

// ورود مدیر (کاربر پیش‌فرض وردپرس پلی‌گراند)
await page.goto(`${BASE}/wp-login.php`);
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'password');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

await page.goto(`${BASE}/wp-admin/admin.php?page=ashna-otp`, { waitUntil: 'networkidle' });
await page.addStyleTag({
  content: `
    .notice, #wpbody-content > .notice, .update-nag { display: none !important; }
    #wpadminbar, .ashna-topbar { display: none !important; }
    html { margin-top: 0 !important; }
  `,
});

async function tab(name) {
  await page.click(`.ashna-nav-item[data-tab="${name}"]`);
  await page.waitForTimeout(700);
}

async function shot(selector, file, relax = true) {
  try {
    if (relax) {
      await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (!el) return;
        el.style.maxHeight = 'none';
        el.style.overflow = 'visible';
        let n = el.parentElement;
        for (let i = 0; i < 4 && n; i++, n = n.parentElement) {
          n.style.maxHeight = 'none';
          n.style.overflow = 'visible';
        }
      }, selector);
      await page.waitForTimeout(250);
    }
    const el = await page.$(selector);
    if (!el) return console.log('MISS', file);
    await el.scrollIntoViewIfNeeded();
    await page.waitForTimeout(200);
    await el.screenshot({ path: `${OUT}/${file}.png`, animations: 'disabled', timeout: 15000 });
    const box = await el.boundingBox();
    console.log('OK', file, `${Math.round(box.width)}x${Math.round(box.height)}`);
  } catch (e) {
    console.log('FAIL', file, e.message.split('\n')[0]);
  }
}

// ۰۱ روش ورود
await tab('auth_flow');
await shot('#ashna-tab-auth_flow .ashna-choice-grid', 'methods');

// ۰۲ گوگل و تلگرام (با دامنهٔ نمونه)
await tab('social');
await page.evaluate(() => {
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let n;
  while ((n = w.nextNode())) {
    if (n.nodeValue && n.nodeValue.includes('127.0.0.1')) {
      n.nodeValue = n.nodeValue.replace(/https?:\/\/127\.0\.0\.1(:\d+)?/g, 'https://shop.example.com');
    }
  }
});
await shot('.ashna-sso-columns', 'sso');

// ۰۳ درگاه‌ها
await tab('sms_gateways');
await shot('.ashna-gw-selector-grid', 'gateways');
await shot('#ashna-tab-sms_gateways .ashna-card', 'failover');

// ۰۴ امنیت
await tab('security_firewall');
await shot('#ashna-rate-limit-card', 'security');

// ۰۵ ووکامرس
await tab('woocommerce');
await shot('#ashna-tab-woocommerce .ashna-card', 'shop');

// ۰۶ طراح ظاهر
await tab('appearance_studio');
await page.waitForTimeout(600);
await shot('#ashna-tab-appearance_studio .ashna-studio-layout', 'appearance', false);

// پیشخوان (اختیاری)
await tab('dashboard');
await shot('.ashna-kpi-grid', 'dashboard');

// فرم واقعی روی سایت، بدون کوکی ورود
const anon = await browser.newContext({
  viewport: { width: 1440, height: 1100 },
  deviceScaleFactor: 2,
  locale: 'fa-IR',
});
const front = await anon.newPage();
await front.goto(`${BASE}/ashna-login/`, { waitUntil: 'networkidle' });
await front.waitForTimeout(1200);
try {
  await front.locator('.ashna-otp-wrapper').screenshot({ path: `${OUT}/login-card.png` });
  console.log('OK login-card');
} catch (e) {
  console.log('FAIL login-card', e.message.split('\n')[0]);
}

await browser.close();
console.log('done → /tmp/caps3');
