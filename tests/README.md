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
temporary plugin copies**, including the loader, guard, generated runtime and
widgets. The production verifier hash is never altered. To test the actual
licensed archive (including direct loader-bypass and fake-guard scenarios):

```sh
python scripts/package-deliverables.py --without-license-file --output-dir .cache/license-release
ASHNA_TEST_PACKAGE=$PWD/.cache/license-release/ashna.zip PHP_BINARY=/path/to/php python tests/run-license-bootstrap.py
ASHNA_TEST_PACKAGE=$PWD/.cache/license-release/ashna.zip PHP_BINARY=/path/to/php python tests/run-protected-boundaries.py
```

The normal source/no-license layout remains testable without `ASHNA_TEST_PACKAGE`. Coverage includes normal early loading,
late activation from function scope, strict true/false results, missing/bad files,
runtime/parse/constructor errors, preloaded/conflicting/delegated classes,
inaccessible methods, missing or corrupt guard, a mismatched guard hash, service
initialization, activation/deactivation, idempotency, non-admin notices and the
no-license build. The guard is instrumented in fixtures to prove it is included
only after the license class is loaded.
Two scenarios feed an ionCube-encoded license or guard stub without the Loader
and expect `license_loader_missing`: the gate must refuse before `require`,
because an encoded stub can print a raw error and `exit(199)` the request.
Executing a real encoded license still requires a hosting PHP with ionCube.

`python3 tests/package-license.py` checks license mismatch, the four-file size
and path constraints, omission of raw runtime classes, exact equality of initial
protection inputs, separate no-license packaging, stale-source rejection and
byte-for-byte assembly of simulated encoded files. This cannot verify actual
vendor encoding. Fixtures are isolated under `.cache`; existing deliverables are
not overwritten.

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

`tests/run-full-wp.py [names...]` copies the current source (no-license layout) into
a disposable WordPress install and runs `tests/<name>.php` there with real auth
cookies, `WP_Session_Tokens`, nonces, users and roles. With no arguments it runs
every suite (`attacks session security sso integrations scenarios`):

```sh
PHP=8.2 PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli python tests/run-full-wp.py session
PHP=8.2 PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli python tests/run-full-wp.py   # all six suites
```

`ASHNA_TEST_WP_SITE` selects an isolated site inside `.cache` (default `.cache/wp-cli-site`, WordPress 6.8.3
on SQLite with Ashna active). Set `ASHNA_TEST_PACKAGE=/path/to/ashna.zip` to run
against the generated licensed layout with a fake local license and matching test
hashes; without it, the source/no-license layout is used. The encoded product and
real license are not exercised. `tests/run-wp-activation.py` additionally runs
WordPress's real activation sandbox for missing, inactive and active simulated
licenses, then checks the next normal request. It overwrites the plugin directory
and activation state of a site inside `.cache` only. WordPress can mark the plugin
active while an inactive/missing license prevents its features from booting.
`tests/lib/wp-harness.php` turns `wp_send_json()` into
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

`tests/sso.php` (114 checks, 12 scenarios) covers Google/Telegram sign-in end to end
without a network: a mock on `ashna_sso_http_response` serves discovery, JWKS and the
token endpoint, and an OpenSSL keypair signs real RS256 ID tokens. It attacks the
verifier (tampered payload, `alg=none`, HS256 confusion, wrong `aud`/`iss`, expired,
replayed nonce, unknown `kid`), then walks account outcomes (create, reuse by `sub`,
link only on a verified email or a provider-verified phone, staff refusal, no
auto-merge, `auto_register` off), single-use browser-bound `state`, the per-IP
callback ceiling, the phone-link AJAX endpoints (nonce, cooldown, ownership) and the
"add your phone" card. The last scenario reads the live schema to prove
`DB_VERSION` moved with the table.

`tests/integrations.php` (QC 4) runs Dokan's real sign-up code: the `Registration`
class, `dokan_after_login_redirect()`, the vendor setup wizard's
`woocommerce_registration_redirect` callback and helpers are verbatim excerpts of
getdokan/dokan v5.3.0 (`tests/fixtures/dokan-lite-excerpt.php`). Ultimate Member
(`tests/fixtures/ultimate-member-excerpt.php`), Paid Memberships Pro and MemberPress
are stand-ins with the upstream function names, statuses and shortcodes. A final
check on a site with the real plugins installed is still required.

## Adversarial suite (hardcore security campaign)

`tests/attacks.php` (199 checks, 18 scenarios) attacks the running plugin: it is
written as an attacker, not as a feature test. Run it like any other suite:

```sh
PHP=8.3 PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli python tests/run-full-wp.py attacks session security integrations scenarios
```

- nonce gate on all six public endpoints, nonce swapping/replay, and the two
  endpoints that are deliberately nonce-free (`ashna_refresh_session` only returns a
  fresh nonce; the passkey endpoints carry their own challenge);
- 25 hostile payloads (arrays, objects, `null`, 100 KB strings, format strings,
  null bytes, SQL comments, invalid UTF-8, emoji, ZWNJ, prototype-pollution keys)
  posted to 7 public and 9 admin endpoints, plus a `$_GET` render fuzz and a
  database-integrity check after every round — 1,788 requests in total, because
  every endpoint is also crossed with the high-risk parameter names (`nonce`,
  `identifier`, `redirect_to`, `code`, `shopurl`, `credential_id`, `target`,
  `ip`, `json_data`) that *other* endpoints consume, in the session state each
  endpoint really exists in (guest, member, administrator);
- user enumeration: identical answers for real and ghost accounts, masked
  identifiers, no code/hash in any response, password oracle parity;
- OTP guessing, replay of a used code, expiry, wrong recipient, Persian digits;
- lockout escape: 9 identifier rewrites (`+98`, `0098`, ZWNJ, uppercase, e-mail
  case) and 5 spoofed proxy headers (`X-Forwarded-For`, `X-Real-IP`, …) must all end
  in the same lockout;
- privilege escalation: 9 admin endpoints × 6 roles × 3 nonce states, a passkey
  IDOR, and `subscriber`→`administrator` role escalation attempts;
- WebAuthn forgery: bit-flipped signatures, foreign origin, wrong `rpId`, cleared
  user-presence flag, replayed assertion;
- session forgery, including a hand-made `wordpress_logged_in` cookie;
- open redirect: 16 payloads (`//evil`, `javascript:`, encoded, backslash,
  protocol-relative) through every redirect option;
- rate-limit escape: parallel bursts, forged and stale captcha tickets, 60-guess
  brute force, and the visitor captcha gate after attack mode starts;
- stored XSS: 9 payloads × 6 render paths (form, modal, `wp-login`, account pages,
  admin pages, logs);
- import abuse (huge/foreign/typed payloads), a static audit of the shipped tree
  and SQL injection traps through the logs page;
- two scenarios for the case where `wp_die()` comes back instead of ending the
  request (another plugin replaced the `wp_die_ajax_handler`, which the harness can
  emulate): a refusal must not be swallowed, and must not fall through into the
  action it was guarding (5 public endpoints × 25 payloads with and without a
  nonce, plus 9 admin endpoints with a bad nonce) — no code sent, no session, no
  new user, no setting changed, no lockout cleared.

The suite found and closed three real defects in shipped code: two PHP 8
`TypeError` 500s (`strlen()`/`ltrim()` on an array from a request) and an off-site
`redirect_url`/`role_redirects` value that survived the settings sanitizer, plus
the hardening it forced on every refusal path (see the live runner below). It runs
against the no-license build like the other suites and removes the roles it creates
before finishing (otherwise session tests see them).

### Live HTTP + browser attacks

`tests/browser/qa-attack.mjs` (84 checks) attacks a real WordPress over the wire
and then drives a real Chromium against it:

```sh
node tests/browser/qa-attack.mjs http://localhost:9400
```

- reconnaissance: front page, PHP error text in output, `admin-ajax.php` without an
  action, a nonce-less `request_otp`;
- 12 hostile URLs (array parameters, invalid UTF-8, `javascript:`/off-site
  `redirect_to`, forged query actions) must never produce a 500;
- enumeration, CSRF (9 state-changing actions posted cross-site with `Origin`/
  `Referer` of an attacker page and no nonce), brute force and a parallel flood with
  spoofed proxy headers;
- forged `Host`, cache headers, reflected XSS through four URL payloads;
- direct hits on plugin files (`index.php`, `ashna.php`, `uninstall.php`, class and
  template files), the plugin directory listing, `admin-post.php` twins of the AJAX
  handlers, a valid visitor nonce against an admin handler, XML-RPC and REST;
- browser phase: dialog watch on a hostile URL, a real sign-in through the form,
  cookie flags (`HttpOnly`, path, `SameSite`), fresh-context isolation, and a scan of
  the rendered page for leaked gateway keys;
- finally the server's `WP_DEBUG` log is read from the byte offset captured before
  the campaign: no line may mention the plugin, and no fatal may appear at all.

The runner calls `?qa_reset=1` (the loopback-only reset in
`tests/browser/ashna-browser-qa.php`) at the start and between phases, because the
flood and brute-force phases leave real lockouts and rate-limit windows behind.

### What the campaign changed in the plugin

Every response ends with `wp_send_json_*()`, which calls `wp_die()`. WordPress
guarantees that `wp_die()` ends the request — but only as long as nothing replaces
the `wp_die_ajax_handler` filter. The campaign emulated a site where something
does, and found that a refusal could then fall through into the action it was
guarding (the settings save was the clearest case). The shipped code is now
fail-closed:

- `Ashna_Session::verify_request()` returns `false` instead of "continue anyway",
  and all nine public handlers stop on anything other than a strict `true`;
- the eight administrator handlers that use core's `check_ajax_referer()` first
  check the *shape* of `$_REQUEST['nonce']` (core casts its argument, and an array
  casts with a PHP warning on PHP 8), then refuse with `wp_die( -1, 403 )` and
  return if it comes back;
- every `wp_send_json_*()` response — 73 call sites across the admin, auth, passkey,
  session and Dokan code — is followed by `return;`, so a refusal can never continue
  into the handler body;
- the test harness now emulates `_ajax_wp_die_handler()` faithfully (echo the
  message, then stop), so WordPress's own `-1` refusals are visible to assertions.

None of this changes behaviour on a normal site: `wp_die()` never returns there.

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

Matrix of the campaign (per combination: attacks 199, session 66, security 72,
integrations 62, scenarios 190; `sso` was added after the campaign and runs on
6.8.3 only):

| WordPress | PHP | Result |
|---|---|---|
| 6.8.3 | 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5 | all pass |
| 7.1.3 | 8.5 (7.4, 8.3 in the QA pass) | all pass |
| 6.4.13 | 8.1 | all pass |
| 5.8.18 | 7.4 | 584/587: three checks fail and two are skipped, all of them from the old test driver (below) |

```sh
ASHNA_TEST_WP_SITE=$PWD/.cache/matrix/wp71 PHP=8.5 PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli \
  python tests/run-full-wp.py            # no names: every suite
```

Known limits of the test tools (not of Ashna):
- php-wasm's PHP 7.4 build aborts the process inside `libxml_clear_errors()`. On 7.4 the DOM pass of the XSS scanner is therefore skipped (the literal-needle pass still runs; the markup is identical on PHP 8, where the parsed scan runs).
- The old `wp-sqlite-db` driver used for WordPress 5.8 cannot store a string containing both a backslash and a quote, ignores `\_` in `LIKE` patterns, reports success for `DROP TABLE` but keeps the table, and leaks ~47 KB per write query. `make-site.sh` raises its memory limit. Only checks that depend on those SQL features fail there: the used-captcha-marker purge (security), the CSV round trip of a quote+backslash message and the uninstall table drop (scenarios). The attacks suite detects the driver and skips its two wildcard-search checks with a printed note instead of failing, which is why it reports 197 of 199 there. MySQL and the official SQLite driver handle all of them; both are verified on 6.4–7.1.
- A suite that crashes midway leaves its fixture roles (`customer`, `seller`) in the site database, which makes the session suite's "no WooCommerce → subscriber" check fail. Remove them (or rebuild the site) before the next run.

### Load tests (thousands of sign-ins at once)

`tests/load/` runs complete OTP sign-in flows — request a code, read it, verify it, then prove with
`?qa_whoami=1` that the session belongs to the expected account — from many independent PHP
processes at once. WordPress Playground serves one PHP request at a time, so it cannot stand in for
simultaneous visitors; this driver can, because every worker is its own process writing to the same
database (sessions, transients, counters, OTP log).

```sh
export PHP_BINARY="$PWD/.cache/phpwasm/node_modules/.bin/php-wasm-cli" PHP=8.3
# the whole 8-phase campaign (baseline, crowd, burst, hostile mix, session storm, attack mode, soak)
bash tests/load/run-campaign.sh                                        # JSON per phase in .cache/load/
# one custom phase
python tests/load/load-multiproc.py --workers=150 --concurrency=8 --flows=4 --seed=1200 \
  --settings="attack_mode_threshold=0,resend_cooldown=0,captcha_type=none,active_sms_gateway=sandbox,show_debug_code_in_toast=1" \
  --reset --label=crowd --out=.cache/load/crowd.json
# same account over and over (session storm); helpers: --purge, --seed-only, --reset [--keep-logs], --mode=same-user, --hostile=N
python tests/load/load-multiproc.py --workers=40 --concurrency=10 --flows=4 --mode=same-user --reset …
# end-to-end HTTP path (real cookies + nonces, one request at a time, shared guest nonce)
node tests/load/load-login.mjs --users=20 --concurrency=5 --repeat=1
```

Notes:
- `max_requests_per_hour`/`max_requests_per_day` are clamped to at least 1 and count a rolling 24 h,
  so a phase that reuses one phone number needs high limits (`max_requests_per_day=100000`) or unique
  numbers per flow. A refusal with `ashna_rate_limit_recipient_day` is the plugin working, not a fault.
- The fixtures (`tests/load/ashna-load-fixtures.php`) answer loopback requests only and require the
  seeder; install them in the sandbox site only.
- Results of the campaign: `docs/load-report-FA.md`. Numbers are correctness-under-concurrency
  evidence; the sandbox (SQLite, one web worker) is not a capacity benchmark.

### Golden transcript (proof that a refactor changed nothing)

`tests/load/golden.php` replays a fixed script of sign-in situations — first request for a new
number, resend ticket, cooldown, wrong code until the attempts run out, successful sign-in, replay,
blocklists, per-minute and daily limits, honeypot, captcha, e-mail and phone-only modes — and prints
every AJAX answer with only the run-dependent values (code, tokens, nonce, user id, countdown
numbers) replaced by placeholders. `tests/load/golden-compare.sh` runs it twice against the baseline
build (`git archive <ref>`, default `HEAD`) and once against the working tree, then diffs all three:

```sh
bash tests/load/golden-compare.sh .cache/matrix/wp64 8.1         # HEAD as the baseline
bash tests/load/golden-compare.sh .cache/matrix/wp71 8.5 3.0.0    # any ref: a tag, a branch, a commit
```

The two baseline runs must be identical (the transcript is deterministic) and the working tree must
match them byte for byte — both are asserted, and the script fails loudly if the transcript is short
instead of "passing" empty. 29 answers are compared on each environment.

### The lockout transient that could silently not engage

Found by a rare, order-dependent failure of `tests/security.php` (the "10 wrong answers lock the
IP" check): a `_transient_timeout_<key>` row can outlive its value row when something deletes
`_transient_*` rows but leaves the paired timeout rows behind. `set_transient()` then takes its
"create" path, writes the lock **with the old, already passed timeout**, and the next
`get_transient()` deletes the lock again — so the block never happens. `Ashna_Security::write_lock_transient()`
now reads the key before writing, which makes WordPress drop that stale pair itself; the IP lock is
written rarely, so the extra read only happens on the path that already blocks somebody.
`tests/load/reset-state.php` deletes both halves of every transient for the same reason (that is how
the sandbox kept recreating the state).

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
