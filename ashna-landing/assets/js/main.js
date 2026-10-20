/* ============================================================
   آشنا — landing interactions
   Vanilla JS, no dependencies. All demo data is synthetic.
   ============================================================ */
(function () {
  "use strict";

  /* --------------------------------------------------------
     Site configuration — edit these three lines before deploy
     -------------------------------------------------------- */
  var ASHNA_CONFIG = {
    // Replace with the final Rightchin product URL when published.
    purchaseUrl: "https://www.rtl-theme.com/?s=%D8%A2%D8%B4%D9%86%D8%A7",
    // Leave empty until demo.parsena.ir is live; buttons then point to #lab.
    demoUrl: "",
    // Optional: support / ticket URL, e.g. the Rightchin Q&A tab.
    supportUrl: ""
  };

  window.__ashnaReady = true;

  var FA = new Intl.NumberFormat("fa-IR");
  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
  var faNum = function (n) { return FA.format(n); };

  /* ---------------- Toasts ---------------- */
  var toastStack = $("#toast-stack");
  function toast(message, ms) {
    if (!toastStack) return;
    var el = document.createElement("div");
    el.className = "toast";
    el.textContent = message;
    toastStack.appendChild(el);
    window.setTimeout(function () {
      el.classList.add("is-out");
      window.setTimeout(function () { el.remove(); }, 260);
    }, ms || 3200);
  }

  /* ---------------- Config links ---------------- */
  $$("[data-buy]").forEach(function (el) {
    if (ASHNA_CONFIG.purchaseUrl) {
      el.href = ASHNA_CONFIG.purchaseUrl;
      el.target = "_blank";
      el.rel = "noopener";
    }
  });
  if (!ASHNA_CONFIG.demoUrl) {
    $$("[data-demo-link]").forEach(function (el) {
      el.href = "#lab";
      el.addEventListener("click", function () {
        toast("دموی زندهٔ وردپرس در حال آماده‌سازی است؛ فعلاً شبیه‌ساز همین صفحه را امتحان کن.");
      });
    });
  }

  /* ---------------- Header + mobile nav ---------------- */
  var header = $("#site-header");
  var nav = $("#main-nav");
  var navToggle = $("#nav-toggle");
  function onScroll() {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 8);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  function closeNav() {
    if (!nav || !navToggle) return;
    nav.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded", "false");
    navToggle.setAttribute("aria-label", "باز کردن منو");
    document.body.classList.remove("menu-open");
  }
  if (navToggle && nav) {
    navToggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      navToggle.setAttribute("aria-expanded", open ? "true" : "false");
      navToggle.setAttribute("aria-label", open ? "بستن منو" : "باز کردن منو");
      document.body.classList.toggle("menu-open", open);
    });
    $$("a", nav).forEach(function (a) { a.addEventListener("click", closeNav); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") closeNav();
    });
  }

  /* ---------------- Scroll reveal ---------------- */
  var reveals = $$(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-in");
        obs.unobserve(entry.target);
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.08 });
    reveals.forEach(function (el, i) {
      el.style.transitionDelay = (Math.min(i % 3, 2) * 70) + "ms";
      io.observe(el);
    });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-in"); });
  }

  /* ---------------- Proof counters ---------------- */
  var counters = $$(".counter");
  function animateCounter(el) {
    var target = parseInt(el.getAttribute("data-count"), 10);
    if (isNaN(target)) return;
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      el.textContent = faNum(target);
      return;
    }
    var start = null;
    var duration = 1250;
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = faNum(Math.round(target * eased));
      if (p < 1) window.requestAnimationFrame(step);
    }
    window.requestAnimationFrame(step);
  }
  if ("IntersectionObserver" in window && counters.length) {
    var cio = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        animateCounter(entry.target);
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.5 });
    counters.forEach(function (el) { cio.observe(el); });
  }

  /* ---------------- Hero demo (ورود با کد یک‌بارمصرف) ---------------- */
  var demoRoot = $("#demo-auth-live");
  if (demoRoot) {
    var demoCard = $("[data-demo-card='otp']", demoRoot);
    var demoSuccess = $("[data-demo-card='success']", demoRoot);
    var demoPhone = $("#demo-phone");
    var stepRequest = $(".demo-step-request", demoRoot);
    var stepVerify = $(".demo-step-verify", demoRoot);
    var identifierInput = $("#demo-identifier", demoRoot);
    var recipientEl = $(".demo-recipient-display", demoRoot);
    var successNumber = $(".demo-success-number", demoRoot);
    var digitInputs = $$(".demo-digit-box", demoRoot);
    var alertBox = $(".demo-otp-alert", demoRoot);
    var countdownEl = $(".demo-timer-countdown", demoRoot);
    var progressFill = $(".demo-timer-progress-fill", demoRoot);
    var timerHead = $(".demo-timer-head", demoRoot);
    var resendBtn = $(".demo-resend-btn", demoRoot);
    var codeChips = $(".demo-notif-code");
    var restartBtn = $(".demo-restart", demoRoot);
    var DEMO_SECONDS = 60;
    var demoCode = "";
    var secondsLeft = DEMO_SECONDS;
    var demoTimerId = null;
    var verifyId = null;
    var reduceMotion = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);

    function randomCode() {
      var out = "";
      for (var i = 0; i < 5; i++) out += String(Math.floor(Math.random() * 10));
      return out;
    }
    function faDigits(value) {
      return String(value).replace(/[0-9]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹"[Number(d)]; });
    }
    function maskContact(value) {
      var raw = (value || "").trim();
      if (!raw) return "0912***6789";
      if (raw.indexOf("@") > -1) return raw.charAt(0) + "***" + raw.slice(raw.indexOf("@"));
      var digits = raw.replace(/\D/g, "");
      if (digits.length < 8) return raw;
      return digits.slice(0, 4) + "***" + digits.slice(-4);
    }
    function setAlert(show) { if (alertBox) alertBox.hidden = !show; }
    function clearDigits() {
      digitInputs.forEach(function (input) {
        input.value = "";
        input.classList.remove("is-filled");
      });
    }
    function paintCode() {
      if (!codeChips) return;
      codeChips.textContent = "";
      faDigits(demoCode).split("").forEach(function (digit) {
        var chip = document.createElement("span");
        chip.textContent = digit;
        codeChips.appendChild(chip);
      });
    }
    function stopDemoTimer() {
      if (demoTimerId) window.clearInterval(demoTimerId);
      demoTimerId = null;
    }
    function paintTimer() {
      var minutes = Math.floor(secondsLeft / 60);
      var seconds = secondsLeft % 60;
      if (countdownEl) countdownEl.textContent = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
      if (progressFill) progressFill.style.width = (secondsLeft / DEMO_SECONDS * 100) + "%";
    }
    function runDemoTimer() {
      stopDemoTimer();
      secondsLeft = DEMO_SECONDS;
      if (timerHead) timerHead.hidden = false;
      if (resendBtn) resendBtn.hidden = true;
      paintTimer();
      demoTimerId = window.setInterval(function () {
        secondsLeft -= 1;
        if (secondsLeft <= 0) {
          stopDemoTimer();
          secondsLeft = 0;
          paintTimer();
          if (timerHead) timerHead.hidden = true;
          if (resendBtn) resendBtn.hidden = false;
          return;
        }
        paintTimer();
      }, 1000);
    }
    function requestCode() {
      demoCode = randomCode();
      paintCode();
      var masked = maskContact(identifierInput ? identifierInput.value : "");
      if (recipientEl) recipientEl.textContent = masked;
      if (successNumber) successNumber.textContent = masked;
      clearDigits();
      setAlert(false);
      if (stepRequest) stepRequest.hidden = true;
      if (stepVerify) stepVerify.hidden = false;
      if (demoPhone) {
        demoPhone.hidden = false;
        demoPhone.classList.remove("is-in");
        if (!reduceMotion) {
          void demoPhone.offsetWidth;
          demoPhone.classList.add("is-in");
        }
      }
      runDemoTimer();
      window.setTimeout(function () { if (digitInputs[0]) digitInputs[0].focus(); }, reduceMotion ? 0 : 280);
    }
    function resetDemo() {
      stopDemoTimer();
      window.clearTimeout(verifyId);
      setAlert(false);
      clearDigits();
      demoCode = "";
      if (demoPhone) { demoPhone.hidden = true; demoPhone.classList.remove("is-in"); }
      if (stepVerify) stepVerify.hidden = true;
      if (stepRequest) stepRequest.hidden = false;
      if (demoSuccess) demoSuccess.hidden = true;
      if (demoCard) demoCard.hidden = false;
    }
    function showSuccess() {
      stopDemoTimer();
      if (demoPhone) demoPhone.hidden = true;
      if (demoCard) demoCard.hidden = true;
      if (demoSuccess) demoSuccess.hidden = false;
    }
    function verifyCode() {
      var value = digitInputs.map(function (input) { return input.value; }).join("");
      if (value.length !== 5) return;
      if (value === demoCode) { setAlert(false); showSuccess(); return; }
      setAlert(true);
    }

    if (stepRequest) {
      stepRequest.addEventListener("submit", function (event) { event.preventDefault(); requestCode(); });
    }
    if (stepVerify) {
      stepVerify.addEventListener("submit", function (event) { event.preventDefault(); verifyCode(); });
    }
    $$(".demo-change-identifier", demoRoot).forEach(function (btn) {
      btn.addEventListener("click", resetDemo);
    });
    if (resendBtn) {
      resendBtn.addEventListener("click", function () {
        demoCode = randomCode();
        paintCode();
        clearDigits();
        setAlert(false);
        runDemoTimer();
        if (digitInputs[0]) digitInputs[0].focus();
      });
    }
    if (restartBtn) restartBtn.addEventListener("click", resetDemo);

    digitInputs.forEach(function (input, index) {
      input.addEventListener("input", function () {
        var value = input.value
          .replace(/[۰-۹]/g, function (d) { return String("۰۱۲۳۴۵۶۷۸۹".indexOf(d)); })
          .replace(/[٠-٩]/g, function (d) { return String("٠١٢٣٤٥٦٧٨٩".indexOf(d)); })
          .replace(/\D/g, "")
          .slice(0, 1);
        input.value = value;
        input.classList.toggle("is-filled", !!value);
        setAlert(false);
        if (value && digitInputs[index + 1]) digitInputs[index + 1].focus();
        if (digitInputs.every(function (box) { return !!box.value; })) {
          window.clearTimeout(verifyId);
          verifyId = window.setTimeout(verifyCode, 240);
        }
      });
      input.addEventListener("keydown", function (event) {
        if (event.key === "Backspace" && !input.value && digitInputs[index - 1]) {
          var prev = digitInputs[index - 1];
          prev.value = "";
          prev.classList.remove("is-filled");
          prev.focus();
          event.preventDefault();
        }
        if (event.key === "ArrowLeft" && digitInputs[index + 1]) digitInputs[index + 1].focus();
        if (event.key === "ArrowRight" && digitInputs[index - 1]) digitInputs[index - 1].focus();
      });
      input.addEventListener("paste", function (event) {
        var text = ((event.clipboardData || window.clipboardData).getData("text") || "")
          .replace(/[۰-۹]/g, function (d) { return String("۰۱۲۳۴۵۶۷۸۹".indexOf(d)); })
          .replace(/\D/g, "")
          .slice(0, 5);
        if (!text) return;
        event.preventDefault();
        text.split("").forEach(function (digit, i) {
          if (digitInputs[i]) {
            digitInputs[i].value = digit;
            digitInputs[i].classList.add("is-filled");
          }
        });
        if (text.length === 5) verifyCode();
        else if (digitInputs[text.length]) digitInputs[text.length].focus();
      });
    });

    $$("[data-demo-alt]", demoRoot).forEach(function (btn) {
      btn.addEventListener("click", function () {
        var passkey = btn.getAttribute("data-demo-alt") === "passkey";
        toast(passkey
          ? "این نمایش جریان ورود با کد را شبیه‌سازی می‌کند؛ Passkey در نسخهٔ واقعی افزونه با اثر انگشت یا چهره کار می‌کند."
          : "این نمایش جریان ورود با کد را شبیه‌سازی می‌کند؛ ورود با رمز عبور در نسخهٔ واقعی افزونه فعال است.");
      });
    });
  }

  /* ---------------- Lab tabs ---------------- */
  var labTabs = $$("[data-lab-tab]");
  var labPanels = $$(".lab-panel");
  function activateLab(name) {
    $$(".lab-tab[data-lab-tab]").forEach(function (tab) {
      var active = tab.getAttribute("data-lab-tab") === name;
      tab.classList.toggle("is-active", active);
      tab.setAttribute("aria-selected", active ? "true" : "false");
      tab.tabIndex = active ? 0 : -1;
    });
    labPanels.forEach(function (panel) {
      var active = panel.id === "panel-" + name;
      panel.classList.toggle("is-active", active);
      panel.hidden = !active;
    });
    if (name === "bomb") updateBomb();
  }
  labTabs.forEach(function (el) {
    el.addEventListener("click", function (e) {
      var name = el.getAttribute("data-lab-tab");
      if (el.classList.contains("lab-tab")) {
        e.preventDefault();
        activateLab(name);
      } else {
        activateLab(name);
      }
    });
    el.addEventListener("keydown", function (e) {
      if (!el.classList.contains("lab-tab")) return;
      if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
      e.preventDefault();
      var tabs = $$(".lab-tab[data-lab-tab]");
      var idx = tabs.indexOf(el);
      var next = e.key === "ArrowLeft" ? (idx + 1) % tabs.length : (idx - 1 + tabs.length) % tabs.length;
      tabs[next].focus();
      activateLab(tabs[next].getAttribute("data-lab-tab"));
    });
  });

  /* ---------------- Failover simulator ---------------- */
  var breakBtn = $("#break-gateway");
  var resetFlowBtn = $("#reset-flow");
  var flowStatus = $("#flow-status");
  var flowLog = $("#flow-log");
  var flowResult = $("#flow-result");
  var flowResultSub = $("#flow-result-sub");
  var flowResultTime = $("#flow-result-time");
  var breakerCard = $("#breaker-card");
  var flowTimers = [];
  var flowRunning = false;
  var flowBroken = false;

  function nowFa() {
    return new Date().toLocaleTimeString("fa-IR", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
  }
  function clearFlowTimers() {
    flowTimers.forEach(function (t) { window.clearTimeout(t); });
    flowTimers = [];
  }
  function addLog(type, text) {
    if (!flowLog) return;
    var line = document.createElement("div");
    line.className = "log-line";
    line.innerHTML = "<time>" + nowFa() + "</time><span class=\"log-" + type + "\">" + text + "</span>";
    flowLog.appendChild(line);
    flowLog.scrollTop = flowLog.scrollHeight;
  }
  function setGwState(name, cls, label) {
    var row = $(".flow-row[data-gw=\"" + name + "\"]");
    if (!row) return;
    row.classList.remove("is-active", "is-failed", "is-trying");
    if (cls) row.classList.add(cls);
    var state = $("[data-gw-state]", row);
    if (state) state.textContent = label;
  }
  function resetFlow() {
    clearFlowTimers();
    flowRunning = false;
    flowBroken = false;
    if (breakBtn) {
      breakBtn.disabled = false;
      breakBtn.innerHTML = '<svg class="icon icon-18"><use href="#i-bolt"/></svg> قطعی پنل اصلی را شبیه‌سازی کن';
    }
    setGwState("primary", "", "سالم · ۰.۴ ثانیه");
    setGwState("backup1", "", "آماده");
    setGwState("backup2", "", "آماده");
    if (flowStatus) {
      flowStatus.className = "status-pill";
      flowStatus.innerHTML = '<span class="pulse-dot"></span> همه‌چیز سالم';
    }
    if (breakerCard) breakerCard.hidden = true;
    if (flowResult) {
      flowResult.classList.remove("is-pending");
      var t = $("b", flowResult);
      if (t) t.textContent = "کد به کاربر رسید";
    }
    if (flowResultSub) flowResultSub.textContent = "مسیر: درگاه اصلی · ۰.۴ ثانیه";
    if (flowResultTime) flowResultTime.textContent = "۰.۴s";
    if (flowLog) {
      flowLog.innerHTML = "";
    }
    addLog("info", "درخواست کد ثبت شد");
    addLog("ok", "SMS.ir پاسخ داد (۰.۴s)");
    addLog("ok", "کد به کاربر تحویل شد");
  }
  function breakGateway() {
    if (flowRunning) return;
    if (flowBroken) { resetFlow(); return; }
    flowRunning = true;
    flowBroken = true;
    if (breakBtn) {
      breakBtn.disabled = true;
      breakBtn.innerHTML = "قطعی فعال است — برای شروع دوباره بازنشانی کن";
    }
    if (flowStatus) {
      flowStatus.className = "status-pill is-danger";
      flowStatus.innerHTML = '<span class="pulse-dot" style="background:#F87171"></span> قطعی شبیه‌سازی شد';
    }
    setGwState("primary", "is-failed", "خطا ۵۰۰ · قطع");
    if (flowResult) {
      flowResult.classList.add("is-pending");
      var title = $("b", flowResult);
      if (title) title.textContent = "در حال ارسال از مسیر پشتیبان…";
    }
    if (flowResultSub) flowResultSub.textContent = "کاربر منتظر است؛ مسیر جایگزین انتخاب می‌شود";
    if (flowResultTime) flowResultTime.textContent = "…";
    addLog("bad", "SMS.ir: خطای ۵۰۰ — ارسال ناموفق (۰.۵s)");

    flowTimers.push(window.setTimeout(function () {
      addLog("warn", "Circuit Breaker برای SMS.ir فعال شد");
      setGwState("primary", "is-failed", "Circuit Breaker · کنار گذاشته شد");
      if (breakerCard) breakerCard.hidden = false;
      if (flowStatus) {
        flowStatus.className = "status-pill is-warn";
        flowStatus.innerHTML = '<span class="pulse-dot" style="background:#FCD34D"></span> انتقال به پشتیبان';
      }
    }, 700));

    flowTimers.push(window.setTimeout(function () {
      addLog("info", "تلاش با پشتیبان ۱: کاوه‌نگار");
      setGwState("backup1", "is-trying", "در حال ارسال…");
    }, 1300));

    flowTimers.push(window.setTimeout(function () {
      setGwState("backup1", "is-active", "تحویل شد · ۱.۸ ثانیه");
      addLog("ok", "کاوه‌نگار پاسخ داد (۱.۸s)");
      if (flowResult) {
        flowResult.classList.remove("is-pending");
        var t = $("b", flowResult);
        if (t) t.textContent = "کد از مسیر پشتیبان رسید";
      }
      if (flowResultSub) flowResultSub.textContent = "مسیر: پشتیبان ۱ (کاوه‌نگار) · بدون دخالت کاربر";
      if (flowResultTime) flowResultTime.textContent = "۱.۸s";
      addLog("ok", "کد به کاربر تحویل شد · مسیر پشتیبان ۱");
      if (flowStatus) {
        flowStatus.className = "status-pill";
        flowStatus.innerHTML = '<span class="pulse-dot"></span> کاربر وارد شد';
      }
      flowRunning = false;
    }, 2700));
  }
  if (breakBtn) breakBtn.addEventListener("click", breakGateway);
  if (resetFlowBtn) resetFlowBtn.addEventListener("click", resetFlow);

  /* ---------------- Anti-SMS-bomb calculator ---------------- */
  var bombRange = $("#bomb-range");
  var bombCount = $("#bomb-count");
  var bombAllowed = $("#bomb-allowed");
  var bombBlocked = $("#bomb-blocked");
  var bombState = $("#bomb-state");
  var bombStateSub = $("#bomb-state-sub");
  var bombBadge = $("#bomb-badge");
  var bombStatus = $("#bomb-status");
  var bombBarOk = $("#bomb-bar-ok");
  var bombBarBad = $("#bomb-bar-bad");
  var attackBanner = $("#attack-banner");
  var attackSwitch = $("#attack-switch");
  var segs = $$(".seg[data-limit]");
  var bombLimit = 5;
  var attackMode = false;

  function updateBomb() {
    if (!bombRange) return;
    var requests = parseInt(bombRange.value, 10);
    var allowed = Math.min(requests, bombLimit);
    var blocked = Math.max(0, requests - bombLimit);
    var limitHit = blocked > 0;

    if (bombCount) bombCount.textContent = faNum(requests);
    if (bombAllowed) bombAllowed.textContent = faNum(allowed);
    if (bombBlocked) bombBlocked.textContent = faNum(blocked);
    if (bombState) bombState.textContent = attackMode ? "کپچا اجباری" : (limitHit ? "محدود" : "مجاز");
    if (bombStateSub) bombStateSub.textContent = attackMode ? "حالت حمله" : (limitHit ? "تا پایان پنجره" : "بدون قفل");
    if (bombBarOk) bombBarOk.style.width = ((allowed / 60) * 100) + "%";
    if (bombBarBad) bombBarBad.style.width = ((blocked / 60) * 100) + "%";

    if (bombBadge) {
      bombBadge.className = "result-badge" + (attackMode ? " is-danger" : (limitHit ? " is-warn" : ""));
      bombBadge.textContent = attackMode ? "حالت حمله فعال" : (limitHit ? "خارج از سقف" : "در محدودهٔ مجاز");
    }
    if (bombStatus) {
      bombStatus.className = "status-pill" + (attackMode ? " is-danger" : (limitHit ? " is-warn" : ""));
      bombStatus.innerHTML = '<span class="pulse-dot"' + (attackMode ? ' style="background:#F87171"' : (limitHit ? ' style="background:#FCD34D"' : "")) + "></span> " +
        (attackMode ? "حمله" : (limitHit ? "محدودسازی" : "عادی"));
    }
    if (attackBanner) attackBanner.hidden = !attackMode;
    if (attackSwitch) attackSwitch.setAttribute("aria-checked", attackMode ? "true" : "false");

    var pct = ((requests - 1) / 59) * 100;
    bombRange.style.setProperty("--fill", pct + "%");
  }
  if (bombRange) {
    bombRange.addEventListener("input", updateBomb);
    updateBomb();
  }
  segs.forEach(function (seg) {
    seg.addEventListener("click", function () {
      segs.forEach(function (s) { s.classList.toggle("is-active", s === seg); });
      bombLimit = parseInt(seg.getAttribute("data-limit"), 10) || 5;
      updateBomb();
    });
  });
  if (attackSwitch) {
    attackSwitch.addEventListener("click", function () {
      attackMode = !attackMode;
      updateBomb();
    });
  }

  /* ---------------- Admin tour ---------------- */
  var viewButtons = $$(".wptab[data-view], .wpsub[data-view], .wpmenu-item[data-view]");
  var views = $$(".admin-view");

  function showView(name) {
    views.forEach(function (v) {
      var active = v.getAttribute("data-view") === name;
      v.classList.toggle("is-active", active);
      v.hidden = !active;
    });
    viewButtons.forEach(function (b) {
      var active = b.getAttribute("data-view") === name;
      b.classList.toggle("is-active", active);
      if (b.classList.contains("wptab") || b.classList.contains("wpsub")) b.setAttribute("aria-selected", active ? "true" : "false");
    });
  }
  viewButtons.forEach(function (btn) {
    btn.addEventListener("click", function () { showView(btn.getAttribute("data-view")); });
  });
  $$("[data-admin-view]").forEach(function (link) {
    link.addEventListener("click", function () { showView(link.getAttribute("data-admin-view")); });
  });

  /* Studio controls */
  var studioPreview = $("#studio-preview");
  if (studioPreview) {
    $$(".swatch[data-color]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        $$(".swatch[data-color]").forEach(function (s) { s.classList.toggle("is-active", s === btn); });
        studioPreview.style.setProperty("--sp-accent", btn.getAttribute("data-color"));
      });
    });
    $$(".tpl[data-tpl]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        $$(".tpl[data-tpl]").forEach(function (t) { t.classList.toggle("is-active", t === btn); });
        studioPreview.setAttribute("data-tpl", btn.getAttribute("data-tpl"));
        if (btn.getAttribute("data-tpl") === "dark") studioPreview.classList.add("is-dark");
        else if (!studioPreview.classList.contains("is-dark-forced")) studioPreview.classList.remove("is-dark");
      });
    });
    $$("[data-studio-dark]").forEach(function (toggle) {
      toggle.addEventListener("click", function () {
        var isDark = studioPreview.classList.toggle("is-dark");
        toggle.classList.toggle("on", isDark);
        studioPreview.classList.toggle("is-dark-forced", isDark);
      });
    });
  }

  /* ---------------- Buy checklist ---------------- */
  var checklist = $("#buy-checklist");
  var scoreNum = $("#score-num");
  var scoreText = $("#score-text");
  var ringFill = $("#ring-fill");
  var CIRC = 2 * Math.PI * 52;
  var scoreMessages = {
    0: "اولین نیازت را انتخاب کن تا ببینیم آشنا کجای کارت می‌آید.",
    1: "آشنا یکی از این نیازها را پوشش می‌دهد؛ بقیهٔ امکانات هم در نسخهٔ ۳.۱.۳ فعال‌اند.",
    2: "آشنا برای این ترکیب مناسب است؛ در آزمایشگاه بالای صفحه رفتارش را ببین.",
    3: "نیمی از مسیر؛ زنجیرهٔ پشتیبان، فایروال و یکپارچگی‌ها دقیقاً برای همین‌ها ساخته شده‌اند.",
    4: "تقریباً همهٔ موارد با آشنا پوشش داده می‌شود؛ فقط پیش‌نیازهای PHP و ionCube را بررسی کن.",
    5: "آشنا برای سایت تو طراحی شده است. پیش‌نیازها را ببین، بعد از راست‌چین خرید کن."
  };
  function updateChecklist() {
    if (!checklist) return;
    var checked = $$("input[type=checkbox]:checked", checklist).length;
    if (scoreNum) scoreNum.textContent = faNum(checked);
    if (scoreText) scoreText.textContent = scoreMessages[checked] || scoreMessages[0];
    if (ringFill) ringFill.style.strokeDashoffset = String(CIRC * (1 - checked / 5));
    if (ringFill) ringFill.style.stroke = checked >= 4 ? "#34D399" : checked >= 2 ? "#60A5FA" : "#94A3B8";
  }
  if (checklist) {
    if (ringFill) ringFill.style.strokeDasharray = String(CIRC);
    $$("input[type=checkbox]", checklist).forEach(function (input) {
      input.addEventListener("change", updateChecklist);
    });
    updateChecklist();
  }

  /* ---------------- FAQ accordion ---------------- */
  var faqItems = $$(".faq-item");
  faqItems.forEach(function (item) {
    item.addEventListener("toggle", function () {
      if (!item.open) return;
      faqItems.forEach(function (other) {
        if (other !== item) other.open = false;
      });
    });
  });

  /* ---------------- Anchor focus / active nav (small polish) ---------------- */
  $$('a[href^="#"]').forEach(function (link) {
    link.addEventListener("click", function () {
      var id = link.getAttribute("href");
      if (id.length < 2) return;
      var target = document.getElementById(id.slice(1));
      if (target) {
        window.setTimeout(function () {
          target.setAttribute("tabindex", "-1");
          target.focus({ preventScroll: true });
        }, 400);
      }
    });
  });
})();
