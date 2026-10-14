/* Ashna product preview. Local-only educational simulation, no authentication API. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var theme = $('themeToggle');
  function applyTheme(value) {
    document.documentElement.dataset.theme = value;
    theme.setAttribute('aria-pressed', String(value === 'dark'));
    theme.setAttribute('aria-label', value === 'dark' ? 'تغییر به حالت روشن' : 'تغییر به حالت تاریک');
  }
  try {
    var saved = localStorage.getItem('ashna-preview-theme');
    if (saved === 'light' || saved === 'dark') applyTheme(saved);
  } catch (_) { /* Storage is optional; demo input is never persisted. */ }
  theme.addEventListener('click', function () {
    var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    try { localStorage.setItem('ashna-preview-theme', next); } catch (_) { /* Optional. */ }
  });
  $('menuToggle').addEventListener('click', function () {
    var open = $('menuToggle').getAttribute('aria-expanded') !== 'true';
    $('menuToggle').setAttribute('aria-expanded', String(open));
    $('navLinks').classList.toggle('open', open);
  });
  function closeMenu() {
    $('menuToggle').setAttribute('aria-expanded', 'false');
    $('navLinks').classList.remove('open');
  }
  $('navLinks').querySelectorAll('a').forEach(function (a) { a.addEventListener('click', closeMenu); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && $('menuToggle').getAttribute('aria-expanded') === 'true') {
      closeMenu(); $('menuToggle').focus();
    }
  });

  // Roving tab focus; arrow keys follow the visual RTL tab order.
  function setupTabs(selector, onSelect) {
    var tabs = Array.from(document.querySelectorAll(selector));
    function select(tab, focus) {
      tabs.forEach(function (item) {
        item.setAttribute('aria-selected', String(item === tab));
        item.tabIndex = item === tab ? 0 : -1;
      });
      onSelect(tab);
      if (focus) tab.focus();
    }
    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () { select(tab, false); });
      tab.addEventListener('keydown', function (event) {
        var target;
        if (event.key === 'ArrowLeft') target = tabs[(index + 1) % tabs.length];
        if (event.key === 'ArrowRight') target = tabs[(index + tabs.length - 1) % tabs.length];
        if (event.key === 'Home') target = tabs[0];
        if (event.key === 'End') target = tabs[tabs.length - 1];
        if (target) { event.preventDefault(); select(target, true); }
      });
    });
  }
  var channel = 'sms';
  var phase = 'identify';
  var timer = null;
  var resendAt = 0;
  function digits(value) {
    return value.replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
      .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); });
  }
  function status(message, error) {
    $('demoStatus').textContent = message;
    $('demoStatus').classList.toggle('error', Boolean(error));
  }
  function stopTimer() { clearInterval(timer); timer = null; }
  function startTimer() {
    stopTimer();
    resendAt = Date.now() + 60000;
    function tick() {
      var remaining = Math.max(0, Math.ceil((resendAt - Date.now()) / 1000));
      $('resendCode').disabled = remaining > 0;
      $('resendCode').textContent = remaining ? 'ارسال مجدد نمایشی · ' + remaining + ' ثانیه' : 'ارسال مجدد نمایشی';
      if (!remaining) stopTimer();
    }
    tick(); timer = setInterval(tick, 1000);
  }
  function render() {
    ['sms', 'email', 'passkey'].forEach(function (name) {
      $('panel-' + name).hidden = name !== channel || phase !== 'identify';
    });
    $('requestCode').hidden = phase !== 'identify' || channel === 'passkey';
    $('requestCode').textContent = channel === 'sms' ? 'ادامه با موبایل' : 'ادامه با ایمیل';
    $('verifyPanel').hidden = phase !== 'verify';
    $('successPanel').hidden = phase !== 'success';
  }
  function reset(clear) {
    stopTimer(); phase = 'identify';
    $('demoCode').value = '';
    $('recipientText').textContent = '';
    $('demoCode').removeAttribute('aria-invalid');
    ['demoPhone', 'demoEmail'].forEach(function (id) {
      $(id).removeAttribute('aria-invalid');
      if (clear) $(id).value = '';
    });
    status(''); render();
  }
  setupTabs('.demo-tabs [role=tab]', function (tab) {
    channel = tab.dataset.channel; reset(true);
  });
  $('resetDemo').addEventListener('click', function () {
    reset(true);
    if (channel !== 'passkey') $(channel === 'sms' ? 'demoPhone' : 'demoEmail').focus();
    else $('tab-passkey').focus();
  });
  $('requestCode').addEventListener('click', function () {
    if (phase !== 'identify' || channel === 'passkey') return;
    var input = $(channel === 'sms' ? 'demoPhone' : 'demoEmail');
    var value = digits(input.value.trim());
    var valid = channel === 'sms' ? /^09\d{9}$/.test(value) : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    input.setAttribute('aria-invalid', String(!valid));
    if (!valid) {
      status(channel === 'sms' ? 'یک شمارهٔ نمایشی ۱۱ رقمی با شروع 09 وارد کنید.' : 'یک ایمیل نمایشی معتبر مثل hello@example.com وارد کنید.', true);
      input.focus(); return;
    }
    // textContent only: input is not injected into markup and never leaves this page.
    $('recipientText').textContent = 'مرحلهٔ بررسی نمایشی برای ' + value;
    phase = 'verify'; render(); startTimer();
    status('هیچ پیامی ارسال نشد. برای ادامه کد نمایشی 123456 را وارد کنید.');
    $('demoCode').focus();
  });
  $('verifyCode').addEventListener('click', function () {
    if (phase !== 'verify') return;
    var correct = digits($('demoCode').value.trim()) === '123456';
    $('demoCode').setAttribute('aria-invalid', String(!correct));
    if (!correct) { status('کد نمونه درست نیست. از 123456 استفاده کنید.', true); $('demoCode').focus(); return; }
    phase = 'success'; stopTimer(); render();
    $('demoPhone').value = ''; $('demoEmail').value = ''; $('demoCode').value = '';
    $('recipientText').textContent = '';
    status('تأیید نمایشی انجام شد؛ هیچ حساب یا نشست واقعی ایجاد نشده است.');
    $('successPanel').tabIndex = -1; $('successPanel').focus();
  });
  $('resendCode').addEventListener('click', function () {
    if (phase !== 'verify' || Date.now() < resendAt) return;
    startTimer(); status('ارسال مجدد فقط شبیه‌سازی شد؛ کد نمونه همچنان 123456 است.');
  });
  ['demoPhone', 'demoEmail', 'demoCode'].forEach(function (id) {
    $(id).setAttribute('aria-describedby', 'demoStatus');
    $(id).addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); $(id === 'demoCode' ? 'verifyCode' : 'requestCode').click(); }
    });
  });
  // Avoid restoring possibly sensitive demo values through back/forward page cache.
  window.addEventListener('pagehide', function () { reset(true); });

  var captions = {
    '01-routing': 'تنظیم درگاه اصلی، ترتیب پشتیبان‌ها و بودجهٔ انتظار',
    '02-passkey': 'فعال‌سازی Passkey و کنترل گزینه‌های ورود بدون رمز',
    '03-authentication': 'تنظیم کد یکبارمصرف، ثبت‌نام و مقصد پس از ورود',
    '04-security': 'محدودیت درخواست و تلاش تأیید، قفل موقت و کپچا',
    '05-appearance': 'تنظیم ظاهر فرم همراه پیش‌نمایش زنده در پنل محصول'
  };
  setupTabs('.settings-tabs [role=tab]', function (tab) {
    var src = 'assets/settings/' + tab.dataset.shot + '.webp';
    $('settingsImage').src = src;
    $('settingsImage').alt = captions[tab.dataset.shot] + ' در آشنا';
    $('shotCaption').textContent = captions[tab.dataset.shot];
    $('shotLink').href = src;
    $('settingsPanel').setAttribute('aria-labelledby', tab.id);
    $('shotLink').scrollTop = 0;
  });
  var dialog = $('imageDialog');
  $('zoomShot').addEventListener('click', function () {
    if (!dialog.showModal) { window.open($('shotLink').href, '_blank', 'noopener'); return; }
    $('dialogImage').src = $('settingsImage').src;
    $('dialogImage').alt = $('settingsImage').alt;
    $('imageDialogTitle').textContent = $('shotCaption').textContent;
    dialog.showModal(); dialog.scrollTop = 0;
    document.body.classList.add('dialog-open');
  });
  $('closeDialog').addEventListener('click', function () { dialog.close(); });
  dialog.addEventListener('close', function () { document.body.classList.remove('dialog-open'); });
  dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
  $('copyShortcode').addEventListener('click', async function () {
    try {
      if (!navigator.clipboard) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText('[ashna_otp_login]');
      $('copyStatus').textContent = 'شورت‌کد کپی شد.';
    } catch (_) {
      $('copyStatus').textContent = 'کپی خودکار در این مرورگر ممکن نیست؛ شورت‌کد را انتخاب و دستی کپی کنید.';
      var range = document.createRange(); range.selectNodeContents(document.querySelector('.code-sample code'));
      var selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    }
  });
})();
