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
This does not change or rebuild either existing Signa edition.

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
8.2 and 8.3 pass the version check without loading Signa or a license. This does
not certify the vendor-encoded license on all newer PHP releases. Direct-source
bootstrap tests on older PHP are not a declaration of licensed-package support.
