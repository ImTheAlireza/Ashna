<?php
/**
 * Isolated bootstrap integration test using WordPress's real hook dispatcher.
 * Options, users and the database are stubs; this is NOT a live-site test.
 * Run via tests/run-license-bootstrap.py.
 */
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) {
	if (error_reporting() & $level) {
		throw new ErrorException($message, 0, $level, $file, $line);
	}
	return false;
});
$plugin_dir = $argv[1];
$scenario = $argv[2];
$expected = $argv[3];
define('ABSPATH', rtrim($argv[4], '/') . '/');
define('WP_PLUGIN_DIR', dirname($plugin_dir));
define('WPMU_PLUGIN_DIR', dirname($plugin_dir) . '/mu-plugins');
define('HOUR_IN_SECONDS', 3600);
define('WP_DEBUG', false);
$wp_plugin_paths = array();
require ABSPATH . 'wp-includes/plugin.php';
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function plugins_url($path = '', $file = '') { return 'https://example.test/wp-content/plugins/ashna/' . $path; }
$GLOBALS['options'] = $GLOBALS['cron'] = $GLOBALS['schema'] = $GLOBALS['shortcodes'] = array();
$GLOBALS['license_calls'] = 0;
$GLOBALS['can_manage'] = true;
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function get_option($key, $default = false) { return isset($GLOBALS['options'][$key]) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; return true; }
function add_option($key, $value = '', $deprecated = '', $autoload = 'yes') { if (isset($GLOBALS['options'][$key])) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function wp_json_encode($data, $flags = 0, $depth = 512) { return json_encode($data, $flags, $depth); }
function wp_next_scheduled($hook) { return isset($GLOBALS['cron'][$hook]) ? $GLOBALS['cron'][$hook] : false; }
function wp_schedule_event($time, $repeat, $hook) { $GLOBALS['cron'][$hook] = $time; return true; }
function wp_unschedule_event($time, $hook) { unset($GLOBALS['cron'][$hook]); return true; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function get_bloginfo($key) { return $key === 'name' ? 'Test Site' : 'admin@example.test'; }
function is_admin() { return true; }
function wp_doing_ajax() { return false; }
function current_user_can($cap) { return $GLOBALS['can_manage']; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
class TestWpdb {
	public $prefix = 'wp_';
	public $usermeta = 'wp_usermeta';
	public $options = 'wp_options';
	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function prepare($query) { return $query; }
	public function get_var($query = null) { return null; }
	public function get_results($query = null) { return array(); }
	public function query($query = null) { return 0; }
}
$wpdb = new TestWpdb();
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
// Declare only when the simulated WordPress pluggable phase is reached.
function load_pluggable_functions() {
	function wp_get_current_user() { return (object) array('ID' => 1); }
}
$late = strpos($scenario, 'activation') !== false;
$unlicensed = strpos($scenario, 'no_license') === 0;
if ($late) {
	load_pluggable_functions();
	do_action('plugins_loaded');
	define('WP_SANDBOX_SCRAPING', true);
}
if ('conflict' === $scenario) { require $plugin_dir . '/conflicting-class.php'; }
if ('preloaded' === $scenario) { require $plugin_dir . '/RTL_License_c1595a7238d64f3a.php'; }
ob_start();
if ('direct_then_index' === $scenario) { require_once $plugin_dir . '/ashna.php'; }
// WordPress's activation sandbox includes the plugin from function scope.
(function ($entry) { include_once $entry; })($plugin_dir . '/index.php');
check(ob_get_clean() === '', 'Unexpected output during inclusion');
if (!$late) {
	if (!$unlicensed) { check(!function_exists('ashna_otp'), 'License check ran before WordPress was ready'); }
	load_pluggable_functions();
	do_action('plugins_loaded');
}
if (!$unlicensed) {
	check(Ashna_License_Bootstrap::get_status() === $expected, 'Unexpected status: ' . Ashna_License_Bootstrap::get_status());
	Ashna_License_Bootstrap::run();
	check($GLOBALS['license_calls'] <= 1, 'License check ran twice');
}
if ('active' === $expected) {
	check(ashna_otp() instanceof Ashna_Plugin, 'Kernel not booted');
	if ($late) {
		check(empty($GLOBALS['schema']), 'DB initialization ran before activation hook');
		check(!has_action('admin_menu'), 'Services booted during activation sandbox');
		do_action('activate_' . ASHNA_OTP_BASENAME);
		$schema_sql = implode("\n", $GLOBALS['schema']);
		check(count($GLOBALS['schema']) === 2, 'Activation did not initialize both tables exactly once');
		check(false !== strpos($schema_sql, 'ashna_otp_logs') && false !== strpos($schema_sql, 'ashna_sso_identities'), 'Activation created the wrong tables');
	} else {
		check(has_action('admin_menu') !== false, 'Admin service missing after delayed boot');
		check(has_action('wp_ajax_nopriv_ashna_request_otp') !== false, 'OTP endpoints missing');
		check(isset($GLOBALS['shortcodes']['ashna_otp_login']), 'Login shortcode missing');
		$count = count($wp_filter['admin_menu']->callbacks[10]);
		ashna_otp()->on_plugins_loaded();
		check(count($wp_filter['admin_menu']->callbacks[10]) === $count, 'Services registered twice');
		check(count(Ashna_Gateway_Manager::get_sms_gateways()) === 7, 'Gateways failed to load');
	}
	check(is_array(get_option('ashna_otp_settings')), 'Default settings missing');
	check(wp_next_scheduled('ashna_otp_daily_cleanup') !== false, 'Cron missing');
	check(wp_next_scheduled('ashna_otp_gateway_probe') !== false, 'Gateway probe cron missing');
	check(has_filter('cron_schedules') !== false, 'Probe schedule not registered');
	do_action('deactivate_' . ASHNA_OTP_BASENAME);
	check(wp_next_scheduled('ashna_otp_daily_cleanup') === false, 'Cron not cleaned on deactivation');
	check(wp_next_scheduled('ashna_otp_gateway_probe') === false, 'Gateway probe cron not cleaned on deactivation');
} else {
	check(!function_exists('ashna_otp'), 'License failure booted the kernel');
	check(!class_exists('Ashna_Auth', false), 'License failure loaded auth service');
	check(empty($GLOBALS['schema']), 'License failure changed the database');
	ob_start(); do_action('admin_notices'); $notice = ob_get_clean();
	check(strpos($notice, $expected) !== false, 'Diagnostic notice missing');
	check(strpos($notice, 'fixture-secret') === false, 'Raw exception leaked a secret');
	$GLOBALS['can_manage'] = false;
	ob_start(); do_action('admin_notices'); check(ob_get_clean() === '', 'Diagnostic visible to non-admin');
}
echo 'PASS ' . $scenario . ' / PHP ' . PHP_VERSION . "\n";
