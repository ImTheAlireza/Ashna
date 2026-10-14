// Source-derived settings captures. No generative image model, live site, credentials or synthetic sales.
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import fs from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, 'product-images/gallery-design/package.json'));
const { chromium: playwright } = require('playwright-core');
const mod = require('@sparticuz/chromium'); const chromium = mod.default || mod;
const version=(await fs.readFile(path.join(root,'index.php'),'utf8')).match(/Version:\s*([0-9.]+)/)[1];
const faVersion=version.replace(/[0-9]/g,n=>'۰۱۲۳۴۵۶۷۸۹'[Number(n)]);
const work = path.join(root, '.cache/settings-capture'); await fs.mkdir(work, {recursive:true});
const font = path.join(work,'vazirmatn.woff2');
try { await fs.access(font); } catch {
  const source = JSON.parse(execFileSync('gh',['api','repos/rastikerdar/vazirmatn/contents/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2?ref=v33.003'],{maxBuffer:2e6}));
  await fs.writeFile(font,Buffer.from(source.content,'base64'));
}
const php = process.env.PHP_BINARY || path.join(root,'.cache/php-runtime/node_modules/.bin/php-wasm-cli');
const html = execFileSync(php,[path.join(root,'scripts/templates/settings-capture.php')],{cwd:root,maxBuffer:5e6,env:{...process.env,PHP:process.env.PHP||'8.2'}}).toString();
if (/Fatal error|Warning:|Deprecated:/.test(html)) throw new Error('PHP view capture failed: '+html.slice(-3000));
const view = path.join(work,'settings.html'); await fs.writeFile(view,html);
if (process.env.ASHNA_BUNDLED_LIBS === '1') {
  const libs = await mod.inflate(path.resolve(path.dirname(require.resolve('@sparticuz/chromium')),'../bin/al2023.tar.br'));
  mod.setupLambdaEnvironment(path.join(libs,'lib'));
}
const browser = await playwright.launch({executablePath:process.env.CHROME_PATH||await chromium.executablePath(),args:chromium.args,headless:true});
const sources = ['index.php','scripts/templates/settings-capture.php','includes/admin/views/settings-page.php','includes/core/class-ashna-helper.php','assets/css/admin.css','assets/js/admin.js', ...['auth-flow','sms-gateways','security','appearance'].map(x=>`includes/admin/views/partials/tab-${x}.php`)];
const report = {width:1000,version,provenance:'Actual PHP settings templates; offline default-data render, NOT installed WordPress screenshots.',externalReference:'User supplied a Wordfence vertical-layout reference; original Ashna blue styling and source-view captures, no copied artwork.',captures:[],sources:{}};
for (const source of sources) report.sources[source] = crypto.createHash('sha256').update(await fs.readFile(path.join(root,source))).digest('hex');
try {
  const page = await browser.newPage({viewport:{width:1300,height:7000},deviceScaleFactor:2,locale:'fa-IR'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://**/*',route=>route.abort());
  await page.goto(pathToFileURL(view).href,{waitUntil:'load'});
  await page.evaluate(async()=>{await document.fonts.ready;await Promise.all([...document.images].filter(i=>i.getAttribute('src')).map(i=>i.decode()));});
  await page.addStyleTag({content:'*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important} .ashna-topbar,.ashna-studio-preview-col{position:static!important}'});
  // Natural desktop layout; fit the main column at native CSS-pixel width, not enlarged bitmaps.
  for(let i=0;i<3;i++){
    const box=await page.locator('.ashna-main').boundingBox();
    const padding=await page.locator('.ashna-main').evaluate(e=>parseFloat(getComputedStyle(e).paddingLeft)+parseFloat(getComputedStyle(e).paddingRight));
    const width=page.viewportSize().width;
    await page.setViewportSize({width:Math.round(width+936-(box.width-padding)),height:7000});
  }
  async function tab(name){await page.locator(`.ashna-nav-item[data-tab="${name}"]`).click();await page.evaluate(()=>window.scrollTo(0,0));}
  async function capture(name,title,caption,box){
    if(box.width<500||box.height<100)throw new Error(`Invalid capture bounds: ${name} ${JSON.stringify(box)}`);
    const output=path.join(work,name+'.png');
    await page.screenshot({path:output,clip:{x:box.x,y:box.y,width:box.width,height:box.height},animations:'disabled'});
    report.captures.push({name,title,caption,cssWidth:box.width,cssHeight:box.height,file:output});
  }
  await tab('sms_gateways');
  const sms=await page.locator('#ashna-tab-sms_gateways > .ashna-card').first().boundingBox();
  const choices=await page.locator('.ashna-gw-selector-grid').boundingBox();
  await capture('01-routing','درگاه و زمان انتظار','تنظیم بودجهٔ هر تلاش و کل مسیر، همراه با انتخاب سامانهٔ پیامک.',{...sms,height:choices.y+choices.height-sms.y+20});
  await tab('auth_flow');
  const passkey=page.locator('#ashna-tab-auth_flow .ashna-card').filter({has:page.locator('#enable_passkey')});
  const passbox=await passkey.boundingBox();const controls=await passkey.locator('.ashna-fields-grid').first().boundingBox();
  await capture('02-passkey','ورود با Passkey','کنترل نمایش ورود بدون رمز، پیشنهاد ثبت و مدیریت دستگاه‌ها.',{x:passbox.x,y:controls.y-8,width:passbox.width,height:passbox.y+passbox.height-controls.y+8});
  await capture('03-authentication','کد تأیید و مسیر ورود','تنظیم طول و اعتبار کد، عضویت عمومی و مقصد کاربر پس از ورود.',await page.locator('#ashna-tab-auth_flow > .ashna-bento-row').last().boundingBox());
  await tab('security_firewall');
  await capture('04-security','محدودیت درخواست و کپچا','سقف ساعتی، تلاش ناموفق، قفل موقت و انتخاب کپچا؛ مقادیر پیش‌فرض.',await page.locator('#ashna-tab-security_firewall > .ashna-bento-row').first().boundingBox());
  await tab('appearance_studio');
  const trigger=page.locator('#ashna-tab-appearance_studio .ashna-accordion-trigger').first();
  const first=page.locator('#ashna-tab-appearance_studio .ashna-accordion-card').first();
  if(!(await first.evaluate(e=>e.classList.contains('is-open'))))await trigger.click();
  await page.waitForTimeout(300);
  const studio=await page.locator('.ashna-studio-layout').boundingBox();const firstbox=await first.boundingBox();
  const preview=await page.locator('.ashna-studio-preview-col').boundingBox();
  await capture('05-appearance','چیدمان فرم و پیش‌نمایش','کنترل چیدمان فرم در کنار پیش‌نمایش موجود در خود پنل.',{...studio,height:Math.max(firstbox.y+firstbox.height,preview.y+preview.height)-studio.y});
  if(errors.length)throw new Error(errors.join('\n'));
  const fileURL=p=>pathToFileURL(p).href;
  const sections=report.captures.map((c,i)=>`<section><div class="heading"><span>${['۰۱','۰۲','۰۳','۰۴','۰۵'][i]}</span><div><h2>${c.title}</h2><p>${c.caption}</p></div></div><img class="capture" src="${fileURL(c.file)}" alt="${c.title}"></section>`).join('');
  const poster=`<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><style>
@font-face{font-family:Vazirmatn;src:url('${fileURL(font)}');font-weight:100 900}
*{box-sizing:border-box}body{margin:0;width:1000px;background:radial-gradient(ellipse at 100% 8%,#102955 0,transparent 34%),#0a1020;font-family:Vazirmatn;color:#f8fafc}
header{position:relative;overflow:hidden;padding:42px 40px 72px;min-height:380px;background:radial-gradient(circle at 15% 30%,#5a95ff55,transparent 34%),linear-gradient(125deg,#101e51,#2563eb);border-bottom:4px solid #60a5fa;border-radius:0 0 48% 10% / 0 0 8% 4%}
header .wordmark{width:220px;height:auto;display:block;margin-bottom:24px}header .emblem{position:absolute;left:50px;top:95px;width:260px;height:260px;display:grid;place-items:center;background:#f8fbff;border:10px solid #9dc5ff50;background-clip:padding-box;border-radius:50%;box-shadow:0 0 70px #60a5fa70}header .emblem img{width:154px;height:154px}h1{font-size:46px;line-height:1.7;margin:0 0 14px;font-weight:900;letter-spacing:-1px;max-width:620px}header p{font-size:18px;color:#dbeafe;line-height:1.9;margin:0;max-width:710px}header b{font-size:14px;color:#dbeafe;font-weight:500;display:inline-block;margin-top:18px;padding:6px 14px;border:1px solid #93c5fd55;border-radius:40px;background:#0a236744}
section{padding:34px 32px 0}.heading{display:flex;gap:14px;align-items:center;margin-bottom:18px}.heading>span{background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:1px solid #60a5fa77;color:white;width:48px;height:48px;border-radius:14px;display:grid;place-items:center;font-size:20px;font-weight:700;box-shadow:0 0 30px #2563eb22}h2{font-size:26px;line-height:1.6;margin:0 0 4px;font-weight:800}p{font-size:15px;line-height:1.9;color:#b6c5dd;margin:0}.capture{display:block;width:936px;height:auto;border:2px solid #608ee64d;border-radius:18px;background:#fff;box-shadow:0 12px 36px #0004}footer{padding:34px 32px;background:linear-gradient(120deg,#162b61,#0f1d3d);margin-top:34px;border-top:2px solid #3b82f655;border-radius:45% 10% 0 0 / 8% 4% 0 0}footer p{font-size:14px;line-height:2;color:#c5d5ed}footer strong{color:#bfdbfe;font-size:18px;display:block;margin-bottom:10px}
</style><header><img class="wordmark" src="${fileURL(path.join(root,'assets/images/ashna-logo-white.png'))}" alt="آشنا"><div class="emblem"><img src="${fileURL(path.join(root,'assets/images/ashna-icon.svg'))}" alt="نشان آشنا"></div><h1>تنظیمات ورود،<br>در اختیار شما</h1><p>از روش ورود و مسیر ارسال کد<br>تا امنیت و طراحی ظاهر فرم</p><b>نمای تنظیمات آشنا • نسخهٔ ${faVersion}</b></header>${sections}<footer><strong>تصاویر از رابط تنظیمات کد محصول تهیه شده‌اند.</strong><p>رندر مستقیم قالب‌های PHP در محیط آزمایشی با مقادیر پیش‌فرض؛ نه اسکرین‌شات نصب زندهٔ وردپرس.<br>اطلاعات مشتری، کلید API یا آمار فروش واقعی در این تصویر وجود ندارد. بازبینی انسانی پیش از انتشار لازم است.</p></footer></html>`;
  const posterPath=path.join(work,'infographic.html');await fs.writeFile(posterPath,poster);
  const outputPage=await browser.newPage({viewport:{width:1000,height:1000},deviceScaleFactor:1,locale:'fa-IR'});
  await outputPage.goto(fileURL(posterPath),{waitUntil:'load'});
  const metrics=await outputPage.evaluate(async()=>{await document.fonts.ready;await Promise.all([...document.images].filter(i=>i.getAttribute('src')).map(i=>i.decode()));return {width:document.documentElement.scrollWidth,height:document.documentElement.scrollHeight,fontLoaded:document.fonts.check('700 20px Vazirmatn'),images:[...document.images].every(i=>i.naturalWidth>0)}});
  if(metrics.width!==1000||!metrics.fontLoaded||!metrics.images)throw new Error(JSON.stringify(metrics));
  await outputPage.screenshot({path:path.join(work,'infographic.png'),fullPage:true,animations:'disabled'});
  const finalPath=path.join(root,'product-images/ashna-settings-infographic.webp');
  const python=process.env.PYTHON_BINARY || path.join(root,'.venv/bin/python');
  // Lossless WebP keeps every screenshot pixel while meeting the upload size limit.
  execFileSync(python,['-c',`from PIL import Image
import sys
source=Image.open(sys.argv[1]).convert('RGB')
source.save(sys.argv[2],lossless=True,method=6)
assert Image.open(sys.argv[2]).convert('RGB').tobytes()==source.tobytes(), 'Pixel mismatch'`,path.join(work,'infographic.png'),finalPath]);
  const image=await fs.readFile(finalPath);
  if(image.length>=1000000)throw new Error(`Lossless image exceeds 1MB: ${image.length}`);
  report.output={file:'product-images/ashna-settings-infographic.webp',bytes:image.length,lossless:true,sha256:crypto.createHash('sha256').update(image).digest('hex')};
  report.metrics=metrics;await fs.writeFile(path.join(work,'capture-report.json'),JSON.stringify(report,null,2)+'\n');
  console.log(JSON.stringify(report,null,2));
}finally{await browser.close();}
