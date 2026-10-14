# License bootstrap regression tests

Tests use the real WordPress 6.8.3 `WP_Hook` dispatcher and plugin hook API.
Options, database schema calls, capabilities and pluggable functions are isolated
stubs. These are **not** tests against the customer's real license file, database,
RTL manager plugin or encoding loader.

Prepare the hook API inside the ignored test cache, from the repository root:

```sh
mkdir -p .cache/license-tests/wp/wp-includes
gh api 'repos/WordPress/WordPress/contents/wp-includes/plugin.php?ref=6.8.3' --jq .content | base64 -d > .cache/license-tests/wp/wp-includes/plugin.php
gh api 'repos/WordPress/WordPress/contents/wp-includes/class-wp-hook.php?ref=6.8.3' --jq .content | base64 -d > .cache/license-tests/wp/wp-includes/class-wp-hook.php
python tests/run-license-bootstrap.py
```

Python 3.9+ and PHP 7.4+ are required. Set `PHP_BINARY` to an alternate PHP CLI.
For the npm PHP-WASM CLI, set `PHP=7.4`, `PHP=8.2` or `PHP=8.3` as appropriate.

All license stubs and their matching fixture hashes are generated **only in
temporary plugin copies**. The production verifier hash is never altered.
Coverage includes normal early loading, late activation from function scope,
strict true/false results, missing/bad files, runtime/parse/constructor errors,
preloaded/conflicting classes, inaccessible methods, service initialization,
activation/deactivation, idempotency, non-admin notices and the no-license build.

## Temporary activation diagnostic tool

`PHP_BINARY=/path/to/php python tests/run-activation-diagnostics.py` exercises
13 scenarios using the same official WordPress hook dispatcher, with mocked
capabilities, nonces and user-meta storage: ordinary/unauthorized/unrelated/bulk
requests are ignored; activation hooks, error-scrape report separation, silent
exit, real PHP fatal shutdown, inactive-license status, sanitized error records,
page escaping and nonce-protected clearing are checked. The test never calls a
license verifier. This is not a live WordPress/hosting/ionCube integration test.

Build its independent ZIP with `python scripts/package-activation-diagnostics.py`.
This does not change or rebuild either existing Ashna edition.

`PHP_BINARY=/path/to/php python tests/run-activation-diagnostics.py` also verifies
14 synthetic ionCube message fixtures (PHP target mismatch, minimum Loader,
numeric encoding type, corruption, expiry, host restriction, absent Loader,
unknown messages and bounded numeric extraction). No raw Loader text, paths or
fixture secrets may enter these structured fields. These are message-parser
tests, not execution of ionCube or the vendor license.

Version 1.2 adds 15 consent/private-message scenarios: no capture by default,
expired consent, user isolation, invalid nonce, scrape isolation, fatal and
ionCube restrictions, bounded first-message capture, known path replacement,
explicit checkbox + nonce arming, escaped private display, clearing and scoped
uninstall. The real message is never inserted into the default JSON report.
Consented text may still be sensitive and requires human review before sharing;
these tests do not claim complete secret redaction.

## Licensed PHP minimum (8.2)

Cache the official WordPress admin API separately (do not use a live install):

```sh
mkdir -p .cache/license-tests/wp/wp-admin/includes
gh api 'repos/WordPress/WordPress/contents/wp-admin/includes/plugin.php?ref=6.8.3' --jq .content | base64 -d > .cache/license-tests/wp/wp-admin/includes/plugin.php
PHP_BINARY=/path/to/php python tests/run-php-requirement.py
```

This extracts the unmodified `validate_plugin_requirements()` function and uses
isolated metadata/UI/dependency stubs. PHP 7.4, 8.0 and 8.1 must be rejected;
8.2 and 8.3 pass the version check without loading Ashna or a license. This does
not certify the vendor-encoded license on all newer PHP releases. Direct-source
bootstrap tests on older PHP are not a declaration of licensed-package support.

## Ashna identity compatibility

The bootstrap runner also runs `rebrand` and `no_license_rebrand`. Both seed
legacy settings/DB version/cron/lockouts and exercise the actual storage, Passkey
repository (read, indexed lookup, save and deletion), class/interface/constant
aliases, shortcode callbacks, extension hooks and saved Elementor widget IDs.
The database/user APIs are stubs, not a live upgrade test. See
`README.md` (repository root) for the non-destructive storage contract and upgrade steps.

`python tests/check-ashna-brand.py` checks source identities, protocol vocabulary,
licensed verifier invariants and final ZIP contents after rebuilding artifacts.

`node tests/rebrand-browser.cjs` verifies browser preference/enrollment migration.
`PHP_BINARY=/path/to/php python tests/lint-php.py` lints every repository PHP source.

## Native authentication/session regression (2.7.8)

```sh
python tests/prepare-session-tests.py
PHP_BINARY=/path/to/php python tests/run-session-integration.py
```

Requires PHP with OpenSSL (including EC key generation), `gh` for initial cache
preparation and the ability to use `-d disable_functions=openssl_verify` for the
separate fail-closed scenario. Official WP 6.8.3 cookie/token/nonce/user/role code
and WooCommerce 9.9.5 session-handler, login and order-ownership functions are
cached in `.cache/session-tests`; selected WC functions/polyfills are evaluated
verbatim in the isolated fixture. Storage, environment, cache, order rows and
JSON/OTP transport are fixtures; this does not boot a whole WP/WC site.

The tests run actual plugin AJAX handlers for valid/invalid OTP and password,
signed/invalid/replayed Passkey assertions, OpenSSL unavailability, immediate
post-OTP Passkey registration and safe signup roles. They verify native token
rotation/validation/revocation, rehydration of user roles on a simulated next
request, ownership permissions, real WC guest-session migration, expiration,
tampering, headers already sent, account policy rejection and cookie suppression.

Browser nonce handoff test (actual frontend.js and jQuery, mocked transport):

```sh
npm ci --prefix product-images/gallery-design
npm install --prefix .cache/frontend-tests jquery@3.7.1
ASHNA_BUNDLED_LIBS=1 node tests/post-login-nonce.mjs
```

It verifies the guest nonce in OTP verification, then the freshly returned
nonce in immediate authenticated Passkey enrollment, with browser storage both
available and blocked. No real SMS, browser authenticator, production HTTP cookie
transport, checkout/payment, vendor license or external Redis server is tested.

## Delivery budgets and ecosystem bridges (2.7.9)

```sh
python tests/prepare-session-tests.py
python tests/prepare-ecosystem-tests.py
python tests/run-delivery-ecosystem.py
ASHNA_BUNDLED_LIBS=1 node tests/slow-delivery-browser.mjs
```

The runner rejects WASM's silent fatal/zero-exit behavior and expects 10 delivery
scenarios plus 4 ecosystem groups. It uses actual WP hook/session APIs, the actual
manager/dispatcher, and an actual SMS.ir request/parser with fixture transport.
Slow transport is simulated with sleeps clamped to the provided timeout. Probe
contention is modeled by a pre-existing lease, **not** a multi-worker load test.
Dokan/PMPro/UM policy functions are extracted verbatim from SHA256-checked pinned
sources; database/services are fixtures. MemberPress is an options-contract stub
only; no proprietary source or compatibility certification is provided.
The browser test exercises real frontend/jQuery with controlled AJAX and clock:
3s feedback, duplicate-submit exclusion, timeout with no retry, timer cleanup,
button/spinner recovery and transition to OTP. It is not a carrier benchmark.
See `README.md` (repository root) for setup, scope and remaining staging tests.

## Product description and documentation checks

`rtl-description.md` is the single marketplace-description draft. It follows the
linked RTL guide's section structure, but the guide explicitly prohibits AI text
and images: **human rewriting/review are still needed**. The included infographic is an automated
composition of actual PHP settings-view renders, not a live-site screenshot or
AI-generated artwork; marketplace acceptance is not certified.
No marketplace acceptance or image-compliance certification is implied.

```sh
python tests/check-rtl-description.py
python tests/check-technical-description.py
```

The first check enforces two explicit intro lines, exactly five single-line main
features, title-only flat feature bullets, no unverified MemberPress advertising
and a real linked lossless 1000px/<1MB settings infographic. The renderer checks
source-view capture, fonts/images, no browser errors and PNG/WebP pixel equality.
Screenshots use default offline fixture data; they do not certify a live install.
The second retains the code-default and limitation checks against the root README;
superseded report files are no longer needed. Build/guide instructions are also
in the root README. `docs/` retains only the guide source and changelog.


### لندینگ معرفی محصول

راهنمای اجرا، بسته‌بندی و انتشار در بخش «پیش‌نمایش معرفی آشنا» در README اصلی است. پس از اجرای `python parsna-landing/serve.py 8080`، تست‌های `python parsna-landing/tests/security_test.py` و `ASHNA_BUNDLED_LIBS=1 node tests/landing-preview.mjs` اجرا می‌شوند. این تست‌ها رابط استاتیک و شبیه‌سازی آموزشی را بررسی می‌کنند، نه ورود واقعی یا نصب وردپرس. دارایی‌ها محلی و تصاویر از رندر آفلاین قالب‌های تنظیمات‌اند. اسکرین‌شات‌های تست فقط در `.cache/landing-preview/` نگهداری می‌شوند.


### بررسی استاندارد تحویل سه‌فایلی

`python tests/check-submission.py` ساختار دقیق product ZIP (فقط افزونه+PDF)، دقیقاً سه فایل اصلی داخل تحویل یکجا، برابری بایتی updater/PDF/installer، manifest و CRC بسته‌های تو‌در‌تو را می‌سنجد. PDF با `scripts/render-help.mjs` به هشت صفحه و پنج capture واقعی تنظیمات گسترش یافته؛ نصب قبل از راه‌اندازی، شماره‌های فارسی و نبود برخورد با پاورقی بررسی می‌شود. چک‌لیست وضعیت انسانی در `docs/submission-checklist.md` است. این بررسی جای آزمون واقعی لایسنس، تحویل پیامک، الزامات دقیقه‌ای/هم‌زمان و پذیرش بازار نیست.
