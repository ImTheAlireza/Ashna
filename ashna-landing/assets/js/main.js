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
    // Leave empty until demo.parsena.ir is live; buttons then point to the hero demo.
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
      el.href = "#top";
      el.addEventListener("click", function () {
        toast("دموی زندهٔ وردپرس در حال آماده‌سازی است؛ فعلاً همان دموی ورود بالای صفحه را ببین.");
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

  /* ---------------- Hero demo (ورود با کد یک‌بارمصرف / Passkey) ----------------
     Mirrors the stage machine of the original landing demo:
       form (request → verify → password) · biometric (scan → verified) · success
     Every step is local: no request leaves the page.
     -------------------------------------------------------------------------- */
  var demoStage = $("#hero-demo");
  if (demoStage) {
    var scenes = {
      form: $("[data-scene='form']", demoStage),
      biometric: $("[data-scene='biometric']", demoStage),
      success: $("[data-scene='success']", demoStage)
    };
    var cardRoot = $("#demo-auth-live", demoStage);
    var steps = {
      request: $(".demo-step-request", demoStage),
      verify: $(".demo-step-verify", demoStage),
      password: $(".demo-step-password", demoStage)
    };
    var identifierInput = $("#demo-identifier", demoStage);
    var recipientEl = $(".demo-recipient-display", demoStage);
    var successNumberEl = $(".demo-success-number", demoStage);
    var alertBox = $(".demo-otp-alert", demoStage);
    var digitInputs = $$(".demo-digit-box", demoStage);
    var digitWrap = $(".demo-otp-digits", demoStage);
    var phoneCol = $("#demo-phone", demoStage);
    var cardCol = $(".demo-card-col", demoStage);
    var phoneBody = $(".demo-phone", demoStage);
    var notifEl = $(".demo-notif", demoStage);
    var codeChips = $(".demo-notif-code", demoStage);
    var countdownEl = $(".demo-timer-countdown", demoStage);
    var progressFill = $(".demo-timer-progress-fill", demoStage);
    var timerWrap = $("[data-timer]", demoStage);
    var resendBtn = $(".demo-resend-btn", demoStage);
    var requestBtn = $(".demo-submit-request", demoStage);
    var requestSpinner = $(".demo-spinner", demoStage);
    var bioTitle = $("[data-bio-title]", demoStage);
    var bioTiles = $$(".demo-bio-tile", demoStage);
    var restartBtn = $(".demo-restart", demoStage);
    var successCheck = $(".demo-success-check", demoStage);

    var DEMO_SECONDS = 60;
    var demoCode = "";
    var secondsLeft = DEMO_SECONDS;
    var timerId = null;
    var timers = [];
    var currentScene = scenes.form;
    var isBusy = false;

    function later(fn, ms) {
      var id = window.setTimeout(fn, ms);
      timers.push(id);
      return id;
    }
    function clearLater() {
      timers.forEach(function (id) { window.clearTimeout(id); });
      timers = [];
    }
    function stopCountdown() {
      if (timerId) window.clearInterval(timerId);
      timerId = null;
    }
    function clearAllTimers() {
      clearLater();
      stopCountdown();
    }
    function reducedMotion() {
      return !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
    }
    function replayAnimation(el) {
      if (!el || reducedMotion()) return;
      el.style.animation = "none";
      void el.offsetWidth;
      el.style.animation = "";
    }

    /* ---------- stage machine ---------- */
    function showScene(name) {
      var next = scenes[name];
      if (!next || next === currentScene) return;
      var prev = currentScene;
      prev.classList.add("is-out");
      window.setTimeout(function () {
        prev.hidden = true;
        prev.classList.remove("is-out");
        next.hidden = false;
        replayAnimation(next);
      }, 140);
      currentScene = next;
    }

    function setStep(name) {
      Object.keys(steps).forEach(function (key) {
        if (steps[key]) steps[key].hidden = key !== name;
      });
      hideAlert();
      if (name !== "verify") hidePhone();
      if (name === "request" && identifierInput) identifierInput.value = identifierInput.value || "09123456789";
    }

    /* ---------- helpers ---------- */
    function randomCode() {
      return String(Math.floor(10000 + Math.random() * 90000));
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
    function hideAlert() {
      if (!alertBox) return;
      alertBox.hidden = true;
      alertBox.textContent = "";
    }
    function showAlert(message) {
      if (!alertBox) return;
      alertBox.textContent = message;
      alertBox.hidden = false;
    }
    function shakeDigits() {
      if (!digitWrap || reducedMotion()) return;
      digitWrap.classList.remove("is-shake");
      void digitWrap.offsetWidth;
      digitWrap.classList.add("is-shake");
      window.setTimeout(function () { digitWrap.classList.remove("is-shake"); }, 680);
    }
    function clearDigits() {
      digitInputs.forEach(function (input) {
        input.value = "";
        input.classList.remove("is-filled");
      });
      paintMatches();
    }
    function paintMatches() {
      if (!codeChips) return;
      var typed = digitInputs.map(function (input) { return input.value; }).join("");
      Array.prototype.forEach.call(codeChips.children, function (chip, index) {
        chip.classList.toggle("is-match", !!typed[index] && typed[index] === demoCode[index]);
      });
    }

    /* ---------- phone + notification ---------- */
    function nudgeCard(direction) {
      if (!cardCol || reducedMotion() || !window.matchMedia("(min-width: 1000px)").matches) return;
      var cls = direction === "in" ? "shift-in" : "shift-out";
      cardCol.classList.remove("shift-in", "shift-out");
      void cardCol.offsetWidth;
      cardCol.classList.add(cls);
      window.setTimeout(function () { cardCol.classList.remove(cls); }, 560);
    }
    function hidePhone(quiet) {
      if (!phoneCol || phoneCol.hidden) return;
      if (reducedMotion()) {
        phoneCol.hidden = true;
      } else {
        phoneCol.classList.add("is-out");
        window.setTimeout(function () {
          if (!phoneCol.classList.contains("is-out")) return;
          phoneCol.classList.remove("is-out");
          phoneCol.hidden = true;
        }, 250);
      }
      if (phoneBody) phoneBody.classList.remove("is-notch-open");
      if (notifEl) {
        notifEl.hidden = true;
        notifEl.classList.remove("is-in");
      }
      if (!quiet) nudgeCard("out");
    }
    function showPhone() {
      if (!phoneCol) return;
      var fresh = phoneCol.hidden;
      phoneCol.classList.remove("is-out");
      if (fresh) {
        phoneCol.hidden = false;
        replayAnimation(phoneCol);
        nudgeCard("in");
        later(function () {
          if (phoneBody) phoneBody.classList.add("is-notch-open");
        }, 300);
      }
      later(function () {
        if (phoneBody) phoneBody.classList.remove("is-notch-open");
        if (notifEl) {
          notifEl.hidden = false;
          notifEl.classList.remove("is-in");
          replayAnimation(notifEl);
          notifEl.classList.add("is-in");
        }
      }, fresh ? 700 : 60);
    }

    /* ---------- code chips ---------- */
    function paintCode() {
      if (!codeChips) return;
      codeChips.textContent = "";
      faDigits(demoCode).split("").forEach(function (digit) {
        var chip = document.createElement("span");
        chip.textContent = digit;
        codeChips.appendChild(chip);
      });
    }

    /* ---------- countdown ---------- */
    function paintTimer() {
      var minutes = Math.floor(secondsLeft / 60);
      var seconds = secondsLeft % 60;
      if (countdownEl) countdownEl.textContent = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
      if (progressFill) progressFill.style.width = (secondsLeft / DEMO_SECONDS * 100) + "%";
    }
    function runCountdown() {
      stopCountdown();
      secondsLeft = DEMO_SECONDS;
      if (timerWrap) timerWrap.hidden = false;
      if (resendBtn) resendBtn.hidden = true;
      paintTimer();
      timerId = window.setInterval(function () {
        secondsLeft -= 1;
        if (secondsLeft <= 0) {
          secondsLeft = 0;
          paintTimer();
          stopCountdown();
          if (timerWrap) timerWrap.hidden = true;
          if (resendBtn) resendBtn.hidden = false;
          return;
        }
        paintTimer();
      }, 1000);
    }

    /* ---------- actions ---------- */
    function requestCode(event) {
      if (event) event.preventDefault();
      if (isBusy) return;
      var value = identifierInput ? identifierInput.value.trim() : "";
      if (!value) {
        showAlert("شماره موبایل یا ایمیل را وارد کنید.");
        if (identifierInput) identifierInput.focus();
        return;
      }
      clearAllTimers();
      hideAlert();
      demoCode = randomCode();
      paintCode();
      var masked = maskContact(value);
      if (recipientEl) recipientEl.textContent = masked;
      if (successNumberEl) successNumberEl.textContent = masked;
      isBusy = true;
      if (requestBtn) requestBtn.disabled = true;
      if (requestSpinner) requestSpinner.hidden = false;
      later(function () {
        isBusy = false;
        if (requestBtn) requestBtn.disabled = false;
        if (requestSpinner) requestSpinner.hidden = true;
        clearDigits();
        setStep("verify");
        runCountdown();
        showPhone();
        later(function () { if (digitInputs[0]) digitInputs[0].focus(); }, 700);
      }, 300);
    }

    function verifyCode() {
      hideAlert();
      var value = digitInputs.map(function (input) { return input.value; }).join("");
      if (value.length !== 5) {
        showAlert("کد ۵ رقمی را کامل وارد کنید.");
        shakeDigits();
        return;
      }
      if (value !== demoCode) {
        showAlert("کد وارد شده اشتباه است.");
        shakeDigits();
        clearDigits();
        if (digitInputs[0]) digitInputs[0].focus();
        return;
      }
      showSuccess();
    }

    function showSuccess() {
      clearAllTimers();
      hidePhone(true);
      showScene("success");
      replayAnimation(successCheck);
    }

    function startPasskey() {
      clearAllTimers();
      hideAlert();
      hidePhone(true);
      if (bioTitle) bioTitle.textContent = "در حال اسکن...";
      bioTiles.forEach(function (tile) { tile.classList.remove("is-verified"); });
      showScene("biometric");
      later(function () {
        if (bioTitle) bioTitle.textContent = "تایید شد";
        bioTiles.forEach(function (tile) {
          tile.classList.remove("is-verified");
          void tile.offsetWidth;
          tile.classList.add("is-verified");
        });
      }, 950);
      later(showSuccess, 2150);
    }

    function resetDemo() {
      clearAllTimers();
      hideAlert();
      hidePhone(true);
      clearDigits();
      demoCode = "";
      if (bioTitle) bioTitle.textContent = "در حال اسکن...";
      bioTiles.forEach(function (tile) { tile.classList.remove("is-verified"); });
      setStep("request");
      showScene("form");
      if (identifierInput) identifierInput.focus();
    }

    /* ---------- wiring ---------- */
    if (steps.request) steps.request.addEventListener("submit", requestCode);
    if (steps.verify) steps.verify.addEventListener("submit", function (event) { event.preventDefault(); verifyCode(); });
    if (steps.password) steps.password.addEventListener("submit", function (event) { event.preventDefault(); showSuccess(); });

    $$(".demo-trigger-passkey", demoStage).forEach(function (btn) {
      btn.addEventListener("click", startPasskey);
    });
    $$(".demo-switch-to-password", demoStage).forEach(function (btn) {
      btn.addEventListener("click", function () { hideAlert(); hidePhone(); setStep("password"); });
    });
    $$(".demo-switch-to-otp", demoStage).forEach(function (btn) {
      btn.addEventListener("click", function () { hideAlert(); setStep("request"); });
    });
    $$(".demo-change-identifier", demoStage).forEach(function (btn) {
      btn.addEventListener("click", function () {
        clearAllTimers();
        hidePhone();
        setStep("request");
      });
    });
    if (resendBtn) {
      resendBtn.addEventListener("click", function () {
        clearAllTimers();
        demoCode = randomCode();
        paintCode();
        clearDigits();
        hideAlert();
        runCountdown();
        showPhone();
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
        hideAlert();
        if (value && digitInputs[index + 1]) digitInputs[index + 1].focus();
        paintMatches();
        if (digitInputs.every(function (box) { return !!box.value; })) {
          later(verifyCode, 240);
        }
      });
      input.addEventListener("keydown", function (event) {
        if (event.key === "Backspace" && !input.value && digitInputs[index - 1]) {
          var prev = digitInputs[index - 1];
          prev.value = "";
          prev.classList.remove("is-filled");
          prev.focus();
          paintMatches();
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
        paintMatches();
        if (text.length === 5) verifyCode();
        else if (digitInputs[text.length]) digitInputs[text.length].focus();
      });
    });

    // Prime the card with its default state
    if (cardRoot) cardRoot.setAttribute("data-ready", "1");
    setStep("request");
    showScene("form");
  }

  /* ---------------- Admin panel (the real dashboard, in a scroll window) ----------------
     The panel is laid out at the width the plugin's dashboard is designed for
     (1520px, the shell of a 1920px WordPress screen) and then zoomed down to the
     space the frame has. Below 1200px of frame width we stop zooming and let the
     plugin's own responsive rules take over. */
  var panelScroll = $("#panel-scroll");
  var panelShell = $("#ashna-app-shell");
  var PANEL_DESIGN_W = 1520;
  function fitPanel() {
    if (!panelScroll || !panelShell) return;
    var w = panelScroll.clientWidth;
    if (!w) return;
    if (w >= 1100 && typeof panelShell.style.zoom !== "undefined") {
      var z = Math.min(1, w / PANEL_DESIGN_W);
      panelShell.style.width = PANEL_DESIGN_W + "px";
      panelShell.style.zoom = z.toFixed(4);
    } else {
      panelShell.style.width = "";
      panelShell.style.zoom = "";
    }
  }
  fitPanel();
  var fitTimer = null;
  window.addEventListener("resize", function () {
    window.clearTimeout(fitTimer);
    fitTimer = window.setTimeout(fitPanel, 120);
  });
  if (window.ResizeObserver) {
    new ResizeObserver(fitPanel).observe(panelScroll);
  }
  var panelHint = $("#panel-hint");
  if (panelScroll && panelHint) {
    var hidePanelHint = function () { panelHint.classList.add("is-hidden"); };
    panelScroll.addEventListener("scroll", function () {
      if (panelScroll.scrollTop > 24 || panelScroll.scrollLeft > 24) hidePanelHint();
    }, { passive: true });
    window.setTimeout(hidePanelHint, 9000);
  }

  var themeToggle = $("#ashna-theme-toggle");
  if (panelShell && themeToggle) {
    themeToggle.addEventListener("click", function () {
      var isDark = panelShell.classList.toggle("is-dark");
      themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
      themeToggle.setAttribute("title", isDark ? "برگشت به حالت روشن" : "تغییر حالت روشن / تاریک (Dark Mode)");
    });
  }

  var copyShortcode = $("#ashna-copy-shortcode");
  if (copyShortcode) {
    var copyLabel = copyShortcode.innerHTML;
    var copyTimer = null;
    copyShortcode.addEventListener("click", function () {
      var code = copyShortcode.getAttribute("data-copy") || "";
      var done = function () {
        copyShortcode.textContent = "کپی شد";
        window.clearTimeout(copyTimer);
        copyTimer = window.setTimeout(function () { copyShortcode.innerHTML = copyLabel; }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done, done);
      } else {
        done();
      }
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
    2: "آشنا برای این ترکیب مناسب است؛ تب پیشخوانش را پایین‌تر ببین.",
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
