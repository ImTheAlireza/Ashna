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
