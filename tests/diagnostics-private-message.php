<?php
/** Consent/privacy tests with real WP hooks and stubbed user-meta/auth. */
$scenario = $argv[1];
define('ABSPATH', __DIR__ . '/../.cache/license-tests/wp/');
define('WP_PLUGIN_DIR', '/home/PRIVATE-PATH/wp-content/plugins');
define('WP_DEBUG', false);
require ABSPATH . 'wp-includes/plugin.php';
require __DIR__ . '/../tools/signa-activation-diagnostics/signa-activation-diagnostics.php';
$GLOBALS['meta'] = array();
$GLOBALS['user'] = 7;
$GLOBALS['allowed'] = true;
function current_user_can() { return $GLOBALS['allowed']; }
function get_current_user_id() { return $GLOBALS['user']; }
function get_user_meta($id, $key, $single) { return isset($GLOBALS['meta'][$id][$key]) ? $GLOBALS['meta'][$id][$key] : ''; }
function update_user_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; return true; }
function delete_user_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); }
function wp_unslash($s) { return $s; }
function wp_verify_nonce($nonce, $action) { return $nonce === $action; }
function wp_generate_uuid4() { return 'test-attempt-id'; }
function nocache_headers() {}
function esc_html($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($v) { return esc_html($v); }
function wp_json_encode($v, $flags) { return json_encode($v, $flags); }
function wp_nonce_field($action) { echo 'nonce-field'; }
function check_admin_referer($action) { if (!isset($_POST['_wpnonce']) || $_POST['_wpnonce'] !== $action) { throw new RuntimeException('bad_nonce'); } }
function wp_die($msg) { throw new RuntimeException('denied'); }
function check($value, $msg) { if (!$value) { throw new RuntimeException($msg); } }
$prefix = Signa_Activation_Diagnostics::META_PREFIX;
if ($scenario === 'uninstall') {
    define('WP_UNINSTALL_PLUGIN', true);
    $GLOBALS['deleted_keys'] = array();
    function delete_metadata($type, $id, $key, $value, $all) {
        check($type === 'user' && $id === 0 && $all === true, 'Bad uninstall scope');
        $GLOBALS['deleted_keys'][] = $key;
    }
    require __DIR__ . '/../tools/signa-activation-diagnostics/uninstall.php';
    $expected_keys = array_map(function ($kind) use ($prefix) { return $prefix . $kind; }, array('activate', 'error_scrape', 'capture_until', 'loader_message'));
    check($GLOBALS['deleted_keys'] === $expected_keys, 'Uninstall missed private data or touched unrelated keys');
    echo "PASS private uninstall\n";
    exit;
}

$pagenow = 'plugins.php';
$wp_version = '6.8.3';
$_POST = array();
$_GET = array('action' => 'activate', 'plugin' => 'signa-otp/index.php', '_wpnonce' => 'activate-plugin_signa-otp/index.php');
if (in_array($scenario, array('arm', 'arm_no_consent', 'arm_bad_nonce'), true)) {
    $_POST = array('signa_diag_arm' => '1', 'signa_diag_consent' => '1', '_wpnonce' => 'signa_diag_arm');
    if ($scenario === 'arm_no_consent') { unset($_POST['signa_diag_consent']); }
    if ($scenario === 'arm_bad_nonce') { $_POST['_wpnonce'] = 'bad'; }
    update_user_meta(7, $prefix . 'loader_message', array('message' => 'OLD-PRIVATE', 'time_utc' => 'old', 'attempt_id' => 'old'));
    ob_start();
    try {
        Signa_Activation_Diagnostics::page();
        check($scenario === 'arm', 'Arm should have been rejected');
    } catch (RuntimeException $e) {
        check($scenario !== 'arm' && in_array($e->getMessage(), array('bad_nonce', 'denied'), true), 'Wrong arm failure');
    }
    ob_end_clean();
    if ($scenario === 'arm') {
        check(get_user_meta(7, $prefix . 'capture_until', true) > time(), 'Consent missing');
        check(get_user_meta(7, $prefix . 'loader_message', true) === '', 'Stale message retained');
    } else {
        check(get_user_meta(7, $prefix . 'capture_until', true) === '', 'Invalid consent armed capture');
        check(get_user_meta(7, $prefix . 'loader_message', true)['message'] === 'OLD-PRIVATE', 'Invalid arm changed data');
    }
    echo "PASS private $scenario\n";
    exit;
}
if ($scenario === 'page_privacy') {
    update_user_meta(7, $prefix . 'loader_message', array('message' => '</textarea><script>PRIVATE-TOKEN</script>', 'time_utc' => 'now', 'attempt_id' => 'id'));
    $GLOBALS['user'] = 8;
    ob_start(); Signa_Activation_Diagnostics::page(); $html = ob_get_clean();
    check(strpos($html, 'PRIVATE-TOKEN') === false, 'Other admin saw message');
    $GLOBALS['user'] = 7;
    ob_start(); Signa_Activation_Diagnostics::page(); $html = ob_get_clean();
    check(strpos($html, '<script>PRIVATE-TOKEN') === false, 'Message XSS');
    check(strpos($html, '&lt;script&gt;PRIVATE-TOKEN') !== false, 'Owner cannot read escaped message');
    $GLOBALS['allowed'] = false;
    try { Signa_Activation_Diagnostics::page(); check(false, 'Unauthorized page allowed'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'denied', 'Wrong denial'); }
    $GLOBALS['allowed'] = true;
    update_user_meta(7, $prefix . 'capture_until', time() + 600);
    $_POST = array('signa_diag_clear' => '1', '_wpnonce' => 'signa_diag_clear');
    ob_start(); Signa_Activation_Diagnostics::page(); ob_end_clean();
    check(get_user_meta(7, $prefix . 'capture_until', true) === '', 'Consent not deleted');
    check(get_user_meta(7, $prefix . 'loader_message', true) === '', 'Message not deleted');
    echo "PASS private $scenario\n";
    exit;
}
if ($scenario !== 'default') { update_user_meta(7, $prefix . 'capture_until', time() + 600); }
if ($scenario === 'expired') { update_user_meta(7, $prefix . 'capture_until', time() - 1); }
if ($scenario === 'other_user') { $GLOBALS['user'] = 8; }
if ($scenario === 'bad_nonce') { $_GET['_wpnonce'] = 'bad'; }
if ($scenario === 'scrape') {
    $_GET['action'] = 'error_scrape';
    $_GET['_wpnonce'] = 'plugin-activation-error_signa-otp/index.php';
}
do_action('admin_init');
$error = array('type' => E_CORE_ERROR, 'message' => 'ionCube: The file ' . WP_PLUGIN_DIR . '/signa-otp/RTL_License.php cannot run. PRIVATE-TOKEN', 'file' => 'Unknown', 'line' => 0);
if ($scenario === 'not_fatal') { $error['type'] = E_NOTICE; }
if ($scenario === 'not_ioncube') { $error['message'] = 'Uncaught unrelated exception PRIVATE-TOKEN'; }
if ($scenario === 'long') { $error['message'] .= str_repeat('x', 14000); }
check(Signa_Activation_Diagnostics::fatal_screen('unchanged', $error) === 'unchanged', 'Fatal screen changed');
$stored = get_user_meta(get_current_user_id(), $prefix . 'loader_message', true);
$expected = in_array($scenario, array('capture', 'long', 'first_only'), true);
check((is_array($stored)) === $expected, 'Capture ignored consent/type restriction');
if ($expected) {
    check(strpos($stored['message'], 'PRIVATE-TOKEN') !== false, 'Real message not preserved');
    check(strpos($stored['message'], 'PRIVATE-PATH') === false, 'Known path not removed');
    check(strpos($stored['message'], '[plugins]/') !== false, 'Missing root replacement');
    check(strlen($stored['message']) <= 12000, 'Unbounded message');
    check($stored['truncated'] === ($scenario === 'long'), 'Bad truncation flag');
    check(get_user_meta(7, $prefix . 'capture_until', true) === '', 'Consent not consumed');
}
if ($scenario === 'first_only') {
    $error['message'] = 'ionCube SECOND-MESSAGE';
    Signa_Activation_Diagnostics::fatal_screen('unchanged', $error);
    check(get_user_meta(7, $prefix . 'loader_message', true) === $stored, 'First message overwritten');
}
foreach (array('activate', 'error_scrape') as $kind) {
    $report = get_user_meta(get_current_user_id(), $prefix . $kind, true);
    check(strpos(json_encode($report), 'PRIVATE-TOKEN') === false, 'Private error leaked to JSON report');
}
if ($scenario === 'scrape' || $scenario === 'bad_nonce' || $scenario === 'other_user') {
    check(get_user_meta(7, $prefix . 'capture_until', true) > time(), 'Unrelated request consumed consent');
}
echo "PASS private $scenario\n";
