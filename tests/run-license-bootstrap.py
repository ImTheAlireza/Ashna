"""Run isolated licensing tests with real WordPress hooks and stubbed services.

PHP_BINARY may point to php-wasm-cli. PHP selects the WASM PHP version.
Requires wp-includes/plugin.php and class-wp-hook.php in ASHNA_TEST_WP_PATH,
which defaults to .cache/license-tests/wp (see tests/README.md).
Production hashes/files are never altered: all fixture edits use temporary copies.
"""
from pathlib import Path
import hashlib
import os
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
WP = Path(os.environ.get('ASHNA_TEST_WP_PATH', ROOT / '.cache/license-tests/wp')).resolve()
PHP = os.environ.get('PHP_BINARY', 'php')
for file in ['plugin.php', 'class-wp-hook.php']:
    if not (WP / 'wp-includes' / file).is_file():
        raise SystemExit('WordPress hook files missing; see tests/README.md')
upgrade = WP / 'wp-admin/includes/upgrade.php'
# Only write fixtures inside the repository's ignored test cache.
if not WP.is_relative_to(ROOT / '.cache'):
    raise SystemExit('ASHNA_TEST_WP_PATH must be an isolated copy inside .cache, never a real WordPress installation.')
upgrade.parent.mkdir(parents=True, exist_ok=True)
upgrade.write_text("<?php function dbDelta($sql) { $GLOBALS['schema'][] = $sql; }\n")
license_name = 'RTL_License_c1595a7238d64f3a'
active = "public function isActive() { ++$GLOBALS['license_calls']; return wp_get_current_user()->ID === 1; }"
fixtures = {
    'active': (active, 'active'),
    'activation': (active, 'active'),
    'direct_then_entry': (active, 'active'),
    'preloaded': (active, 'active'),
    'inactive': ('public function isActive() { return false; }', 'license_inactive'),
    'truthy': ('public function isActive() { return 1; }', 'license_inactive'),
    'missing': ('', 'license_file_missing'),
    'activation_missing': ('', 'license_file_missing'),
    'bad_hash': ('public function __construct() { throw new RuntimeException("MUST NOT LOAD"); }', 'license_hash_mismatch'),
    'missing_class': ('', 'license_class_missing'),
    'missing_method': ('', 'license_method_invalid'),
    'private_method': ('private function isActive() { return true; }', 'license_method_invalid'),
    'constructor_error': ('public function __construct() { throw new RuntimeException("fixture-secret"); } public function isActive() { return true; }', 'license_runtime_error'),
    'method_error': ('public function isActive() { ashna_missing_fixture_function(); }', 'license_runtime_error'),
    'require_error': ('', 'license_runtime_error'),
    'parse_error': ('', 'license_runtime_error'),
    'encoded_no_loader': (active, 'license_loader_missing'),
    'conflict': (active, 'license_class_conflict'),
    'delegated_class': (active, 'license_class_conflict'),
    'guard_missing': (active, 'license_runtime_error'),
    'guard_encoded_no_loader': (active, 'license_loader_missing'),
    'guard_parse_error': (active, 'license_runtime_error'),
    'guard_bad_hash': (active, 'license_inactive'),
    'core_error': (active, 'propagate_core_error'),
    'no_license': ('', 'active'),
    'no_license_activation': ('', 'active'),
}
original = (ROOT / 'ashna.php').read_bytes()
guard_original = (ROOT / 'includes/class-ashna-license-guard.php').read_bytes()
expected_hash = '3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc'
assert original.decode().count(expected_hash) == 1
assert guard_original.decode().count(expected_hash) == 1
assert b"require_once __DIR__ . '/class-ashna-autoloader.php';" in guard_original
for scenario, (body, expected) in fixtures.items():
    with tempfile.TemporaryDirectory(prefix='fixture-', dir=ROOT / '.cache/license-tests') as temp:
        plugin = Path(temp) / 'ashna-otp'
        plugin.mkdir()
        for name in ['index.php', 'ashna.php']:
            shutil.copyfile(ROOT / name, plugin / name)
        shutil.copytree(ROOT / 'includes', plugin / 'includes')
        fixture = '<?php class ' + license_name + ' { ' + body + ' }'
        if scenario == 'missing_class': fixture = '<?php // No class.'
        if scenario == 'require_error': fixture = '<?php throw new RuntimeException("fixture-secret");'
        if scenario == 'parse_error': fixture = '<?php class !!! INVALID !!!'
        if scenario == 'encoded_no_loader': fixture = '<?php //ICB0 81:0 82:503b ?><?php class ' + license_name + ' { ' + body + ' }'
        if scenario == 'delegated_class':
            (plugin / 'delegated-class.php').write_text(fixture)
            fixture = "<?php require __DIR__ . '/delegated-class.php';"
        license_file = plugin / (license_name + '.php')
        if 'missing' not in [scenario, scenario.removeprefix('activation_')]:
            license_file.write_text(fixture)
        if scenario == 'conflict':
            (plugin / 'conflicting-class.php').write_text('<?php class ' + license_name + ' { }')
        fixture_hash = hashlib.sha1(fixture.encode()).hexdigest()
        source = (plugin / 'ashna.php').read_text()
        if scenario != 'bad_hash':
            source = source.replace(expected_hash, fixture_hash)
        (plugin / 'ashna.php').write_text(source)
        guard_file = plugin / 'includes/class-ashna-license-guard.php'
        guard_source = guard_file.read_text()
        if scenario != 'guard_bad_hash':
            guard_source = guard_source.replace(expected_hash, fixture_hash)
        # An encoded guard may need the vendor class as soon as it is included.
        # Simulate that condition in the isolated copy, for every scenario.
        marker = "if ( ! defined( 'ABSPATH' ) ) {"
        assert marker in guard_source
        guard_source = guard_source.replace(marker,
            "if (!class_exists('" + license_name + "', false)) { throw new RuntimeException('guard-loaded-before-license'); }\n" + marker, 1)
        guard_file.write_text(guard_source)
        if scenario == 'guard_encoded_no_loader': guard_file.write_text('<?php //ICB0 81:0 82:503b ?>' + guard_source)
        if scenario == 'guard_missing': guard_file.unlink()
        if scenario == 'guard_parse_error': guard_file.write_text('<?php INVALID !!!')
        if scenario.startswith('no_license'):
            shutil.copyfile(ROOT / 'scripts/templates/ashna-no-license.php', plugin / 'ashna.php')
        if scenario == 'core_error':
            (plugin / 'includes/class-ashna-autoloader.php').write_text('<?php throw new RuntimeException("core-fixture-failure");')
        result = subprocess.run([PHP, str(ROOT / 'tests/license-bootstrap.php'), str(plugin), scenario, expected, str(WP)], capture_output=True, text=True, cwd=ROOT)
        output = result.stdout + result.stderr
        # Some WASM CLI versions return exit code 0 even for PHP fatal errors.
        # Assert the PHP success marker as well as the process status.
        if scenario == 'core_error':
            assert 'Fatal error' in output and 'core-fixture-failure' in output, output
            assert 'license_runtime_error' not in output, output
            print('PASS core errors propagate; not swallowed by the license verifier', flush=True)
        else:
            assert result.returncode == 0 and ('PASS ' + scenario + ' / PHP ') in output and 'Fatal error' not in output, output
            print(result.stdout.strip(), flush=True)
assert (ROOT / 'ashna.php').read_bytes() == original
assert (ROOT / 'includes/class-ashna-license-guard.php').read_bytes() == guard_original
print(f'PASS {len(fixtures)} scenarios; production license hash and source unchanged by tests.')
