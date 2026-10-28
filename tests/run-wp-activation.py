"""Test WordPress's real plugin activation sandbox with a fake license, never production.

Set ASHNA_TEST_WP_SITE to a disposable WordPress+SQLite site under .cache and
ASHNA_TEST_PACKAGE to the unencoded licensed plugin ZIP. This overwrites the
site's Ashna plugin directory and toggles its activation state.
"""
from pathlib import Path
from zipfile import ZipFile
import hashlib
import os
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
SITE = Path(os.environ.get('ASHNA_TEST_WP_SITE', ROOT / '.cache/wp-cli-site')).resolve()
PACKAGE = Path(os.environ.get('ASHNA_TEST_PACKAGE', ROOT / 'deliverables/ashna.zip')).resolve()
PHP = os.environ.get('PHP_BINARY', 'php')
if not SITE.is_relative_to(ROOT / '.cache') or not (SITE / 'wp-load.php').is_file():
    raise SystemExit('A disposable WordPress site inside .cache is required')
EXPECTED = '3fac0395eb82fa65036a1e9aabcf0aac9c2bcedc'
LICENSE = 'RTL_License_c1595a7238d64f3a'
paths = ['ashna.php', 'includes/class-ashna-license-guard.php', 'includes/class-ashna-protected-core.php',
         'includes/integrations/elementor/class-ashna-elementor-login-widget.php',
         'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php']
boot = """<?php
$_SERVER['HTTP_HOST'] = '127.0.0.1:9400'; $_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = '127.0.0.1'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require __DIR__ . '/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php';
function qa_assert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
"""

def run_php(code, marker):
    file = SITE / '_ashna_license_activation_fixture.php'
    if file.exists():
        raise SystemExit('Refusing to overwrite existing test fixture: ' + str(file))
    file.write_text(boot + code)
    try:
        result = subprocess.run([PHP, file.name], cwd=SITE, capture_output=True, text=True)
    finally:
        file.unlink()
    output = result.stdout + result.stderr
    if result.returncode != 0 or marker not in output or 'Fatal error' in output:
        raise RuntimeError(output)
    print(output.strip(), flush=True)

for scenario, expected in [('missing', 'license_file_missing'), ('inactive', 'license_inactive'), ('active', 'active')]:
    run_php("deactivate_plugins('ashna/ashna.php'); qa_assert(!is_plugin_active('ashna/ashna.php'), 'deactivation failed'); echo 'PASS deactivated';\n", 'PASS deactivated')
    dest = SITE / 'wp-content/plugins/ashna'
    if dest.exists():
        shutil.rmtree(dest)
    with ZipFile(PACKAGE) as archive:
        archive.extractall(dest.parent)
    if scenario != 'missing':
        val = 'true' if scenario == 'active' else 'false'
        fake = ('<?php class ' + LICENSE + ' { public function isActive() { $GLOBALS["qa_license_calls"] = ($GLOBALS["qa_license_calls"] ?? 0) + 1; return ' + val + '; } }\n').encode()
        (dest / (LICENSE + '.php')).write_bytes(fake)
        fake_hash = hashlib.sha1(fake).hexdigest()
        for relative in paths:
            path = dest / relative
            content = path.read_text()
            if content.count(EXPECTED) != 1:
                raise SystemExit('Expected one license hash in ' + relative)
            path.write_text(content.replace(EXPECTED, fake_hash))
    run_php("""
$result = activate_plugin('ashna/ashna.php');
qa_assert(!is_wp_error($result), 'WordPress activation returned an error');
qa_assert(is_plugin_active('ashna/ashna.php'), 'WordPress did not record plugin as active');
qa_assert(Ashna_License_Bootstrap::get_status() === '%s', 'unexpected license status');
qa_assert(false === has_action('wp_ajax_nopriv_ashna_request_otp'), 'services ran in activation sandbox');
if ('%s' === 'active') {
    qa_assert((isset($GLOBALS['qa_license_calls']) ? $GLOBALS['qa_license_calls'] : 0) === 2, 'guard/core must check independently');
    qa_assert(false !== has_action('activate_ashna/ashna.php'), 'activation callback missing');
    qa_assert(get_option('ashna_otp_db_version') !== false, 'activation did not record schema');
} else {
    qa_assert(!function_exists('ashna_otp') && !class_exists('Ashna_Plugin', false), 'inactive license loaded services');
    qa_assert(false === has_action('activate_ashna/ashna.php'), 'inactive license registered activation callback');
}
echo 'PASS %s real WordPress activation sandbox / PHP ' . PHP_VERSION . "\\n";
""" % (expected, scenario, scenario), 'PASS ' + scenario + ' real WordPress activation sandbox')
    if scenario == 'active':
        run_php("""
qa_assert(is_plugin_active('ashna/ashna.php'), 'plugin not active on next request');
qa_assert(Ashna_License_Bootstrap::get_status() === 'active', 'next boot not active');
qa_assert(false !== has_action('wp_ajax_nopriv_ashna_request_otp'), 'OTP service not booted on next request');
qa_assert((isset($GLOBALS['qa_license_calls']) ? $GLOBALS['qa_license_calls'] : 0) === 2, 'unexpected next-request checks');
echo 'PASS active next WordPress request / PHP ' . PHP_VERSION . "\\n";
""", 'PASS active next WordPress request')
print('PASS real WordPress activation scenarios with isolated simulated licenses')
