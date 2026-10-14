<?php
/** Real WP hooks; stubbed auth/user metadata. No live WordPress/database. */
$scenario = $argv[1];
define('ABSPATH', __DIR__ . '/../.cache/license-tests/wp/');
define('WP_PLUGIN_DIR', __DIR__ . '/../.cache/diagnostic-fixture');
define('WP_DEBUG', false);
require ABSPATH . 'wp-includes/plugin.php';
$GLOBALS['reports'] = array();
$GLOBALS['can_manage'] = 'unauthorized' !== $scenario;
function current_user_can($cap, $plugin = '') { return $GLOBALS['can_manage']; }
function wp_unslash($s) { return $s; }
function wp_verify_nonce($n, $action) { return $n === $action; }
function get_current_user_id() { return 7; }
function wp_generate_uuid4() { return 'test-request-id'; }
function update_user_meta($id, $key, $value) {
    if ($id !== 7) { throw new RuntimeException('Wrong report owner'); }
    $GLOBALS['reports'][$key] = $value;
    return true;
}
function check($v, $message) { if (!$v) { throw new RuntimeException($message); } }
function esc_textarea($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($v, $flags) { return json_encode($v, $flags); }
function get_user_meta($id, $key, $single) { return isset($GLOBALS['reports'][$key]) ? $GLOBALS['reports'][$key] : ''; }
function delete_user_meta($id, $key) { unset($GLOBALS['reports'][$key]); }
function nocache_headers() {}
function wp_nonce_field($action) { echo '<input value="safe-nonce">'; }
function check_admin_referer($action) { check(isset($_POST['_wpnonce']) && $_POST['_wpnonce'] === $action, 'Bad clear nonce'); }
function wp_die($message) { throw new RuntimeException('access_denied'); }
require __DIR__ . '/../tools/ashna-activation-diagnostics/ashna-activation-diagnostics.php';
$pagenow = 'plugins.php';
$wp_version = '6.8.3';
$_GET = array('action' => 'activate', 'plugin' => 'ashna-otp/index.php', '_wpnonce' => 'activate-plugin_ashna-otp/index.php');
if ($scenario === 'bad_nonce') { $_GET['_wpnonce'] = 'bad'; }
if ($scenario === 'array_nonce') { $_GET['_wpnonce'] = array('bad'); }
if ($scenario === 'wrong_target') { $_GET['plugin'] = '../ashna-otp/index.php'; }
if ($scenario === 'bulk') { $_GET['action'] = 'activate-selected'; }
if ($scenario === 'ordinary_page') { $pagenow = 'index.php'; }
if ($scenario === 'scrape') { $_GET['action'] = 'error_scrape'; $_GET['_wpnonce'] = 'plugin-activation-error_ashna-otp/index.php'; }
$ignored = in_array($scenario, array('unauthorized', 'bad_nonce', 'array_nonce', 'wrong_target', 'bulk', 'ordinary_page'), true);
if ($scenario === 'scrape') {
    $GLOBALS['reports']['_signa_activation_diagnostics_activate'] = array('attempt_id' => 'original-request');
}
ob_start();
do_action('admin_init');
check(ob_get_clean() === '', 'Observer emitted output');
if ($ignored) {
    check(count($GLOBALS['reports']) === 0, 'Unauthorized/unrelated request stored a report');
    if ($scenario === 'unauthorized') {
        try { Ashna_Activation_Diagnostics::page(); throw new RuntimeException('Page allowed unauthorized user'); }
        catch (RuntimeException $e) { check($e->getMessage() === 'access_denied', 'Unexpected page exception'); }
    }
    echo "PASS $scenario\n";
    exit;
}
$key = '_signa_activation_diagnostics_' . $_GET['action'];
check(count($GLOBALS['reports']) === ($scenario === 'scrape' ? 2 : 1), 'Initial checkpoint missing');
if ($scenario === 'scrape') {
    check($GLOBALS['reports']['_signa_activation_diagnostics_activate']['attempt_id'] === 'original-request', 'Error iframe overwrote original report');
}
check($GLOBALS['reports'][$key]['phase'] === 'before_plugin_include', 'Bad first phase');
check($GLOBALS['reports'][$key]['files']['license_present'] === false, 'Unexpected fixture license');
check(Ashna_Activation_Diagnostics::is_target('Ashna/index.php'), 'Windows folder variation rejected');
check(!Ashna_Activation_Diagnostics::is_target('ashna-otp/../index.php'), 'Traversal accepted');
class Ashna_License_Bootstrap {
    public static $status = 'checking';
    public static function get_status() { return self::$status; }
    public static function isActive() { throw new RuntimeException('Verifier must NEVER be called by diagnostics'); }
}
register_shutdown_function(function () use ($key, $scenario) {
    $report = $GLOBALS['reports'][$key];
    check($report['capture_event'] === 'shutdown', 'Shutdown report missing');
    check(strpos(json_encode($report), 'PRIVATE-TOKEN') === false, 'Secret leaked');
    if ($scenario === 'success') {
        check($report['phase'] === 'wordpress_activated_plugin_hook', 'Activation hooks not observed');
        check($report['bootstrap_status'] === 'active', 'Active status not read');
    } elseif ($scenario === 'silent_exit') {
        check($report['phase'] === 'before_plugin_include', 'Wrong phase on exit');
        check($report['bootstrap_status'] === 'checking', 'Wrong exit status');
    } elseif ($scenario === 'fatal') {
        check($report['last_php_error']['is_fatal'] === true, 'Fatal not captured');
        check($report['last_php_error']['category'] === 'uncaught_error_or_exception', 'Wrong fatal category');
    } elseif ($scenario === 'inactive') {
        check($report['bootstrap_status'] === 'license_inactive', 'Inactive state lost');
    }
    echo "PASS $scenario\n";
});
if ($scenario === 'silent_exit') { exit; }
if ($scenario === 'fatal') { throw new RuntimeException('PRIVATE-TOKEN'); }
if ($scenario === 'inactive') { Ashna_License_Bootstrap::$status = 'license_inactive'; }
if ($scenario === 'success' || $scenario === 'scrape') {
    Ashna_License_Bootstrap::$status = 'active';
    if ($scenario !== 'scrape') { do_action('activate_plugin', 'ashna-otp/index.php'); }
    do_action('activate_ashna-otp/index.php');
    if ($scenario !== 'scrape') { do_action('activated_plugin', 'ashna-otp/index.php'); }
}
if ($scenario === 'redaction') {
    $error = array('type' => E_ERROR, 'message' => 'ionCube failure PRIVATE-TOKEN', 'file' => 'C:\\private\\RTL_License.php', 'line' => 14);
    $message = apply_filters('wp_php_error_message', 'original-message', $error);
    check($message === 'original-message', 'Changed fatal screen');
    $safe = $GLOBALS['reports'][$key]['last_php_error'];
    check($safe['file'] === 'RTL_License.php' && $safe['line'] === 14, 'Wrong source location');
    check($safe['category'] === 'ioncube', 'Wrong loader category');
    check(strpos(json_encode($GLOBALS['reports']), 'PRIVATE-TOKEN') === false, 'Secret stored');
    $notice = Ashna_Activation_Diagnostics::safe_error(array('type' => E_NOTICE, 'message' => 'translation notice'));
    check(!$notice['is_fatal'], 'Notice incorrectly classified fatal');
}
if ($scenario === 'page') {
    $GLOBALS['reports'][$key]['test_escape'] = '</textarea><script>alert(1)</script>';
    ob_start(); Ashna_Activation_Diagnostics::page(); $html = ob_get_clean();
    check(strpos($html, '<script>alert(1)</script>') === false, 'Report XSS');
    check(strpos($html, '&lt;script&gt;') !== false, 'Escaped report missing');
    $_POST = array('ashna_diag_clear' => '1', '_wpnonce' => 'bad');
    try { Ashna_Activation_Diagnostics::page(); throw new RuntimeException('Clear allowed bad nonce'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'Bad clear nonce', 'Unexpected clear exception'); }
    check(count($GLOBALS['reports']) === 1, 'Bad nonce deleted reports');
    $_POST = array('ashna_diag_clear' => '1', '_wpnonce' => 'ashna_diag_clear');
    ob_start(); Ashna_Activation_Diagnostics::page(); ob_end_clean();
    check(count($GLOBALS['reports']) === 0, 'Clear did not remove reports');
}
