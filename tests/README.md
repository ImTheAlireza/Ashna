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

## Full WordPress tests (QC 1 session, QC 2 security)

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
