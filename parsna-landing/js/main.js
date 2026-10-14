/**
 * Parsna Landing — Main JavaScript
 * Security-hardened, no inline code
 */

(function () {
  'use strict';

  /* ═══════════════════════════════════════
     Security: Input Sanitization
     ═══════════════════════════════════════ */

  /**
   * Sanitize user input — prevent XSS
   * Uses textContent trick: safe, no innerHTML with user data
   */
  function sanitize(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  /**
   * Validate phone number format (Iranian)
   * Only allows digits, max 11 chars
   */
  function isValidPhone(input) {
    var cleaned = input.replace(/[^0-9۰-۹]/g, '');
    return cleaned.length >= 10 && cleaned.length <= 11;
  }

  /* ═══════════════════════════════════════
     Theme Toggle
     ═══════════════════════════════════════ */

  var toggle = document.getElementById('themeToggle');
  var html = document.documentElement;

  // Restore saved theme (safe: only reads from localStorage)
  var saved = null;
  try {
    saved = localStorage.getItem('parsna-theme');
  } catch (e) {
    // localStorage may be blocked — fail silently
  }
  if (saved === 'light' || saved === 'dark') {
    html.setAttribute('data-theme', saved);
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      try {
        localStorage.setItem('parsna-theme', next);
      } catch (e) {
        // Fail silently
      }
    });
  }

  /* ═══════════════════════════════════════
     Cursor Glow (throttled)
     ═══════════════════════════════════════ */

  var glow = document.getElementById('glow');
  var mx = 0, my = 0, gx = 0, gy = 0;

  document.addEventListener('mousemove', function (e) {
    mx = e.clientX;
    my = e.clientY;
  });

  function animateGlow() {
    gx += (mx - gx) * 0.08;
    gy += (my - gy) * 0.08;
    if (glow) {
      glow.style.left = gx + 'px';
      glow.style.top = gy + 'px';
    }
    requestAnimationFrame(animateGlow);
  }
  requestAnimationFrame(animateGlow);

  /* ═══════════════════════════════════════
     Particles (safe DOM creation)
     ═══════════════════════════════════════ */

  var particleContainer = document.getElementById('particles');
  var colors = ['#93c5fd', '#fda4af', '#c4b5fd', '#86efac'];

  if (particleContainer) {
    for (var i = 0; i < 20; i++) {
      var p = document.createElement('div');
      p.className = 'particle';
      p.style.left = (Math.random() * 100) + '%';
      p.style.background = colors[i % 4];
      p.style.animationDuration = (10 + Math.random() * 15) + 's';
      p.style.animationDelay = (Math.random() * 10) + 's';
      var size = (2 + Math.random() * 3) + 'px';
      p.style.width = size;
      p.style.height = size;
      particleContainer.appendChild(p);
    }
  }

  /* ═══════════════════════════════════════
     Scroll Reveal (IntersectionObserver)
     ═══════════════════════════════════════ */

  if ('IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    document.querySelectorAll('.reveal').forEach(function (el) {
      revealObserver.observe(el);
    });
  }

  /* ═══════════════════════════════════════
     Demo Input — Safe event handling
     ═══════════════════════════════════════ */

  var demoInput = document.querySelector('.demo-input');
  var demoBtn = document.querySelector('.demo-btn');

  if (demoBtn && demoInput) {
    demoBtn.addEventListener('click', function () {
      var val = demoInput.value.trim();
      if (val && isValidPhone(val)) {
        // In production: send to API with CSRF token
        // For demo: just show sanitized feedback
        demoBtn.textContent = '✓ ارسال شد';
        demoBtn.style.color = '#86efac';
        demoBtn.style.borderColor = 'rgba(134,239,172,.3)';
        setTimeout(function () {
          demoBtn.textContent = 'دریافت کد';
          demoBtn.style.color = '';
          demoBtn.style.borderColor = '';
        }, 2000);
      }
    });

    // Prevent form submission if wrapped in form
    demoInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        demoBtn.click();
      }
    });
  }

  /* ═══════════════════════════════════════
     Security: External links — noopener
     ═══════════════════════════════════════ */

  document.querySelectorAll('a[target="_blank"]').forEach(function (link) {
    if (!link.getAttribute('rel') || link.getAttribute('rel').indexOf('noopener') === -1) {
      link.setAttribute('rel', 'noopener noreferrer');
    }
  });

  /* ═══════════════════════════════════════
     Security: Disable drag on sensitive elements
     ═══════════════════════════════════════ */

  document.querySelectorAll('.signa-logo-img').forEach(function (img) {
    img.setAttribute('draggable', 'false');
  });

  /* ═══════════════════════════════════════
     Security: CSP violation reporting
     ═══════════════════════════════════════ */

  document.addEventListener('securitypolicyviolation', function (e) {
    console.warn('[CSP Violation]', e.blockedURI, e.violatedDirective);
  });

})();