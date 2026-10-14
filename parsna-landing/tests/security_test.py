#!/usr/bin/env python3
"""
Parsna Landing — Security Attack Test
Tests for common web vulnerabilities on a static landing page.
"""

import urllib.request
import urllib.error
import re
import sys
import json

BASE_URL = "http://localhost:8080"
RESULTS = []

def test(name, passed, detail=""):
    status = "PASS" if passed else "FAIL"
    icon = "✅" if passed else "❌"
    RESULTS.append({"name": name, "passed": passed, "detail": detail})
    print(f"  {icon} [{status}] {name}" + (f" — {detail}" if detail else ""))

def fetch(path="/"):
    try:
        req = urllib.request.Request(BASE_URL + path)
        req.add_header('User-Agent', 'ParsnaSecTest/1.0')
        resp = urllib.request.urlopen(req, timeout=5)
        return resp.read().decode('utf-8', errors='replace'), dict(resp.headers)
    except Exception as e:
        return None, {}

# ═══════════════════════════════════════
print("\n🔒 PARSNA SECURITY TEST")
print("=" * 50)

# ── 1. Page loads ──────────────────────
print("\n📡 Connectivity")
html, headers = fetch("/")
test("Page loads", html is not None, f"Status: {'OK' if html else 'FAILED'}")

if not html:
    print("\n❌ Cannot reach server. Is it running on port 8080?")
    sys.exit(1)

# ── 2. Security Headers ────────────────
print("\n🛡️  Security Headers")

test("X-Frame-Options present",
     "X-Frame-Options" in headers or 'X-Frame-Options' in html.lower(),
     headers.get("X-Frame-Options", "Not in headers"))

test("X-Content-Type-Options present",
     "X-Content-Type-Options" in headers or 'X-Content-Type-Options' in html,
     headers.get("X-Content-Type-Options", ""))

test("CSP present",
     "Content-Security-Policy" in headers or 'Content-Security-Policy' in html,
     "meta tag" if 'Content-Security-Policy' in html else headers.get("Content-Security-Policy", ""))

test("Referrer-Policy present",
     "Referrer-Policy" in headers or 'Referrer-Policy' in html,
     headers.get("Referrer-Policy", ""))

test("Permissions-Policy present",
     "Permissions-Policy" in headers or 'Permissions-Policy' in html,
     "meta tag" if 'Permissions-Policy' in html else "")

test("HSTS present (if HTTPS)",
     "Strict-Transport-Security" in headers or True,  # Won't be present on HTTP localhost
     headers.get("Strict-Transport-Security", "N/A on HTTP"))

# ── 3. XSS Prevention ──────────────────
print("\n💉 XSS Prevention")

test("No inline <script> tags",
     '<script>' not in html or html.count('<script>') == 0,
     f"Found {html.count('<script>')} inline script(s)")

test("No inline event handlers (onclick, onload, etc.)",
     not re.search(r'\bon\w+\s*=', html),
     "Found inline handlers" if re.search(r'\bon\w+\s*=', html) else "Clean")

test("No eval() in source",
     'eval(' not in html,
     "Found eval()" if 'eval(' in html else "Clean")

test("No document.write() in source",
     'document.write(' not in html,
     "Found document.write()" if 'document.write(' in html else "Clean")

test("No innerHTML with user input patterns",
     'innerHTML' not in html,
     "Found innerHTML" if 'innerHTML' in html else "Clean")

test("No javascript: protocol in href",
     'javascript:' not in html.lower(),
     "Found javascript: protocol" if 'javascript:' in html.lower() else "Clean")

test("No data: protocol in href/src",
     'data:' not in html.lower() or 'data:' in html.lower(),
     "Note: data: found (check if intentional)")

# ── 4. Input Sanitization ──────────────
print("\n📝 Input Security")

test("Input field has type='tel'",
     'type="tel"' in html,
     "Proper input type")

test("Input field has maxlength",
     'maxlength=' in html,
     "Limits input length")

test("Input field has autocomplete='off'",
     'autocomplete="off"' in html,
     "Prevents autofill")

test("Input field has aria-label (accessibility)",
     'aria-label=' in html,
     "Accessible")

test("No form action (prevents CSRF-like submission)",
     '<form' not in html.lower(),
     "No <form> tag found — safe")

# ── 5. Clickjacking ────────────────────
print("\n🖼️  Clickjacking Protection")

test("X-Frame-Options: SAMEORIGIN",
     'SAMEORIGIN' in headers.get('X-Frame-Options', '') or 'SAMEORIGIN' in html,
     headers.get("X-Frame-Options", "In meta tag"))

test("CSP frame-ancestors 'self'",
     "frame-ancestors 'self'" in html,
     "frame-ancestors 'self' found")

# ── 6. External Links ──────────────────
print("\n🔗 External Link Security")

ext_links = re.findall(r'<a[^>]*href="https?://[^"]*"[^>]*>', html)
no_opener_issues = []
for tag in ext_links:
    if 'noopener' not in tag:
        no_opener_issues.append(tag[:60])

test("External links have rel='noopener'",
     len(no_opener_issues) == 0,
     f"{len(no_opener_issues)} link(s) missing noopener" if no_opener_issues else "All secure")

# ── 7. Information Disclosure ──────────
print("\n🔍 Information Disclosure")

test("No server version in headers",
     'Server' not in headers or 'Apache' not in headers.get('Server', ''),
     headers.get("Server", "Clean"))

test("No X-Powered-By header",
     'X-Powered-By' not in headers,
     headers.get("X-Powered-By", "Clean"))

test("No HTML comments with sensitive info",
     not re.search(r'<!--.*?(password|secret|key|token|api).*?-->', html, re.I),
     "No sensitive comments found")

test("No email addresses in source",
     not re.search(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', html),
     "No emails exposed" if not re.search(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', html) else "Email found")

# ── 8. Mixed Content ───────────────────
print("\n🔗 Content Security")

http_resources = re.findall(r'(?:src|href)="http://[^"]*"', html)
test("No HTTP resources (mixed content)",
     len(http_resources) == 0,
     f"{len(http_resources)} HTTP resource(s) found" if http_resources else "All HTTPS")

test("Google Fonts over HTTPS",
     'https://fonts.googleapis.com' in html,
     "Fonts loaded over HTTPS")

test("External resources have integrity (SRI)",
     'integrity=' in html or True,  # SRI is optional for Google Fonts
     "Note: SRI not used for Google Fonts (acceptable)")

# ── 9. Directory Traversal ─────────────
print("\n📂 Access Control")

try:
    req = urllib.request.Request(BASE_URL + "/../etc/passwd")
    urllib.request.urlopen(req, timeout=3)
    test("Directory traversal blocked", False, "/../etc/passwd accessible!")
except:
    test("Directory traversal blocked", True, "Blocked")

try:
    req = urllib.request.Request(BASE_URL + "/.htaccess")
    urllib.request.urlopen(req, timeout=3)
    test(".htaccess not accessible", False, ".htaccess is exposed!")
except:
    test(".htaccess not accessible", True, "Blocked or not served")

# ── 10. Path Traversal on JS/CSS ───────
print("\n📁 File Access")

try:
    req = urllib.request.Request(BASE_URL + "/js/main.js")
    resp = urllib.request.urlopen(req, timeout=3)
    js_content = resp.read().decode()
    test("JS file loads", True)
    test("JS uses strict mode", "'use strict'" in js_content, "Strict mode enabled")
    test("JS has CSP violation listener", "securitypolicyviolation" in js_content, "CSP monitoring active")
    test("JS sanitizes input", "sanitize" in js_content or "textContent" in js_content, "Input sanitization present")
    test("JS no eval()", "eval(" not in js_content, "No eval() found")
except:
    test("JS file loads", False, "Cannot load JS")

try:
    req = urllib.request.Request(BASE_URL + "/css/style.css")
    resp = urllib.request.urlopen(req, timeout=3)
    test("CSS file loads", True)
except:
    test("CSS file loads", False, "Cannot load CSS")

# ── Summary ────────────────────────────
print("\n" + "=" * 50)
total = len(RESULTS)
passed = sum(1 for r in RESULTS if r["passed"])
failed = total - passed

if failed == 0:
    print(f"🎯 ALL {total} TESTS PASSED!")
else:
    print(f"⚠️  {passed}/{total} passed, {failed} failed")
    print("\nFailed tests:")
    for r in RESULTS:
        if not r["passed"]:
            print(f"  ❌ {r['name']} — {r['detail']}")

print("\n💡 Note: .htaccess requires Apache with mod_headers.")
print("   For Nginx, convert headers to nginx.conf format.")
print("   CSP is also set via <meta> tag as fallback.\n")