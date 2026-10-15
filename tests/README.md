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

## Fast-failover engine (QC 3)

`tests/failover.php` exercises 29 scenarios against simulated gateways through
the real `pre_http_request` filter: short per-gateway timeouts, the total
budget, circuit breaker (skip, trial after cooldown, backoff, recovery), error
classification (timeout/connect/server/auth/account/api/config/input), learned
time slots, the background health probe (HEAD requests, early trial, switch),
channel fallback, "both" strategy, admin tests that bypass the breaker,
exceptions in custom gateways, and the exact request format of the current
provider APIs: Safir v3, Rahpayam (smart / messenger / SMS), FarazSMS
(iranpayamak), Melipayamak console (+ legacy fallback) and IPPanel Edge. A simulated "hang" really
sleeps for the timeout WordPress was given, so the timing assertions are real
(the run takes about 20 seconds).

It needs `class-wp-error.php` next to the hook files:

```sh
gh api 'repos/WordPress/WordPress/contents/wp-includes/class-wp-error.php?ref=6.8.3' --jq .content | base64 -d > .cache/license-tests/wp/wp-includes/class-wp-error.php
PHP_BINARY=/path/to/php python tests/run-failover.py
```

The same file also runs inside a disposable full WordPress install: require its
`wp-load.php` (with Ashna active), then include `tests/failover.php`.

## Full WordPress tests (QC 1 session, QC 2 security, QC 4 integrations)

`tests/run-full-wp.py <name>` copies the current source (no-license layout) into a
disposable WordPress install and runs `tests/<name>.php` there with real auth
cookies, `WP_Session_Tokens`, nonces, users and roles:

```sh
PHP=8.2 PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli python tests/run-full-wp.py session
```

`ASHNA_TEST_WP_SITE` selects the site (default `.cache/wp-cli-site`, WordPress 6.8.3
on SQLite with Ashna active). `tests/lib/wp-harness.php` turns `wp_send_json()` into
captured responses and replays captured auth cookies as the next "browser request";
everything it changes is rolled back at the end.

WooCommerce, Dokan and Paid Memberships Pro cannot be installed offline, so
`tests/fixtures/` reproduces the code Ashna integrates with. Dokan's
`dokan_after_login_redirect()`, the `Registration` class and helpers are copied
verbatim from getdokan/dokan (develop, v5.3.0); PMPro's `pmpro_login_redirect()` is
verbatim except its membership lookup. Storage/bootstrapping helpers are simplified
and marked as such. These fixtures are not a substitute for testing on a real store.

`tests/security.php` (QC 2) moves log rows back in time instead of sleeping, and
replays **every distinct interleaving** of three parallel requests through the
limiter's three steps (`precheck()` → `Ashna_Logger::reserve()` → `recount()`):
280 orders (1680 / 3!, since the requests in each check are interchangeable) for
the per-number cooldown, the per-IP minute limit and the site-wide daily cap. A real
multi-process burst was tried first and dropped: the SQLite test site serialized
the PHP processes, so it could not demonstrate concurrency either way.

`tests/integrations.php` (QC 4) runs Dokan's real sign-up code: the `Registration`
class, `dokan_after_login_redirect()`, the vendor setup wizard's
`woocommerce_registration_redirect` callback and helpers are verbatim excerpts of
getdokan/dokan v5.3.0 (`tests/fixtures/dokan-lite-excerpt.php`). Ultimate Member
(`tests/fixtures/ultimate-member-excerpt.php`), Paid Memberships Pro and MemberPress
are stand-ins with the upstream function names, statuses and shortcodes. A final
check on a site with the real plugins installed is still required.

## Scenario suite, version matrix and browser QA

`tests/scenarios.php` (190 checks) covers what the QC-specific suites do not:
- identifier parsing;
- the settings sanitizer (40 rounds of garbage), import/export/reset;
- the admin pages rendered in many states and the logs page with the CSV export;
- 331 login-form variants in 8 contexts, checked for valid HTML and duplicate IDs;
- every OTP flow and gateway driver, including broken JSON, HTML error pages and timeouts;
- special characters through every input path: quotes, backslashes, ZWNJ, emoji;
- password lockouts, including identifier rotation and per-IP limits;
- time zones, including PHP's default timezone changed by another plugin and clock changes;
- MySQL column limits;
- WooCommerce classic and block checkout, plus HPOS declarations;
- Elementor widgets, lifecycle/uninstall, and the `wp-login.php` takeover with its recovery constant.

The harness behaves like a real request:
- `ajax()` slashes `$_POST` the way `wp_magic_quotes()` does;
- `ashna_t_finish()` fails the suite if WordPress raised a `_doing_it_wrong` or deprecation notice while Ashna code was on the call stack.

### Building test sites

`tests/make-site.sh <wp-version> <dir> <official|old> <php> [theme] [permalinks]` builds a disposable site and activates Ashna. It expects these in `.cache/`:

```sh
# WordPress cores (github.com mirror; any tag)
curl -sSL https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/7.1.3 | tar -xz -C .cache/wpcore  # rename to .cache/wpcore/7.1.3
# Official SQLite driver (WP 6.1+): plugin package with the driver copied into wp-includes/database
git clone --depth 1 --branch v3.0.2 https://github.com/WordPress/sqlite-database-integration .cache/wpcore/sqlite
cp -R .cache/wpcore/sqlite/packages/plugin-sqlite-database-integration .cache/wpcore/sqlite-plugin
rm -rf .cache/wpcore/sqlite-plugin/wp-includes/database
cp -R .cache/wpcore/sqlite/packages/mysql-on-sqlite/src .cache/wpcore/sqlite-plugin/wp-includes/database
# WordPress 5.8 needs the older single-file driver ("old")
curl -sSL https://codeload.github.com/aaemnnosttv/wp-sqlite-db/tar.gz/refs/heads/master | tar -xz -C .cache/wpcore  # -> .cache/wpcore/wp-sqlite-db
# PHP 7.4–8.5 in WebAssembly
(cd .cache/phpwasm && npm install @php-wasm/cli)
```

Then point `ASHNA_TEST_WP_SITE` at the site:

```sh
ASHNA_TEST_WP_SITE=$PWD/.cache/matrix/wp71 PHP=8.5 PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli \
  python tests/run-full-wp.py session security integrations scenarios
```

Matrix of the QA pass (389 checks per combination):

| WordPress | PHP | Result |
|---|---|---|
| 6.8.3 | 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5 | all pass |
| 7.1.3 | 7.4, 8.3, 8.5 | all pass |
| 6.4.13 | 8.1 | all pass |
| 5.8.18 | 7.4 | 386/389: the three failures come from the old test driver (below) |

Known limits of the test tools (not of Ashna):
- php-wasm's PHP 7.4 build crashes inside libxml error handling. The HTML validity checks are therefore skipped on 7.4; the markup is identical on PHP 8.
- The old `wp-sqlite-db` driver used for WordPress 5.8 cannot store a string containing both a backslash and a quote, ignores `\_` in `LIKE` patterns, reports success for `DROP TABLE` but keeps the table, and leaks ~47 KB per write query. `make-site.sh` raises its memory limit. The three checks that depend on those SQL features fail there only: used captcha markers purge, the CSV round trip of a quote+backslash message, and the uninstall table drop. MySQL and the official SQLite driver handle them; both are verified on 6.4–7.1.

### Browser QA

`tests/browser/qa-browser.mjs` drives a real Chromium against WordPress Playground. Each phase gets its own browser, because this single-process Chromium does not isolate contexts, and every phase starts from a reset site state.

- **Crawl (desktop and mobile):** no PHP error in any page, no sideways scrolling on mobile, the bundled Vazirmatn is used.
- **Mobile journey:** bottom sheet, Persian digits, wrong code, cleared boxes, resend cooldown, exactly one verification request for the corrected code, session cookie.
- **Desktop journeys:** e-mail sign-up through a test inbox, change address, password sign-in, keyboard only, standalone page, the `wp-login.php` takeover with staff-only password sign-in.
- **Admin:** every tab, AJAX save and reload, logs, CSV download, the users column.
- **Accessibility:** axe-core (WCAG 2.1 AA) on the form, the code step, the password step and the mobile modal.

Result of the QA pass: 84/84. The only console messages are Gravatar requests (no internet in the sandbox) and the intentional 404 page.

```sh
tests/make-site.sh 6.8.3 $PWD/.cache/wp-site official 8.3
# Playground mounts the site at /wordpress: make the SQLite drop-in path relative and the URL localhost
mv .cache/wp-site/wp-content/plugins/plugin-sqlite-database-integration .cache/wp-site/wp-content/plugins/sqlite-database-integration
sed -i -e "s#^\$sqlite_plugin_implementation_folder_path = '.*';#\$sqlite_plugin_implementation_folder_path = __DIR__ . '/plugins/sqlite-database-integration';#" \
       -e "s#plugin-sqlite-database-integration/load.php#sqlite-database-integration/load.php#g" .cache/wp-site/wp-content/db.php
#   (set siteurl/home to http://localhost:9400 and remove the WP_DEBUG* line from wp-config.php)
mkdir -p .cache/wp-site/wp-content/mu-plugins && cp tests/browser/ashna-browser-qa.php .cache/wp-site/wp-content/mu-plugins/
(cd .cache/playground && npm install @wp-playground/cli)
(cd .cache/browser && npm install playwright-core @sparticuz/chromium axe-core)
.cache/playground/node_modules/.bin/wp-playground-cli server --mount=$PWD/.cache/wp-site:/wordpress \
  --wordpress-install-mode=do-not-attempt-installing --blueprint=tests/browser/blueprint.json --port=9400 --site-url=http://localhost:9400 &
node tests/browser/qa-browser.mjs
```

`tests/browser/ashna-browser-qa.php` adds an auto-login and a settings switch for the test. It answers loopback requests only, and must never be installed on a real site.
