<?php
/**
 * Real WP 6.8.3 cookie/token/nonce/role APIs + WC 9.9.5 session/permission code.
 * Only storage, environment, cache, transport observation and order rows are fixtures.
 * This is NOT a full browser/hosting/checkout integration test.
 */
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) {
    if (error_reporting() & $level) { throw new ErrorException($message, 0, $level, $file, $line); }
    return false;
});
ob_start();
$root = dirname(__DIR__, 2);
$cache = $root . '/.cache/session-tests';
define('ABSPATH', $cache . '/');
define('WPINC', 'wp-includes');
define('COOKIEHASH', 'ashna-fixture');
define('AUTH_COOKIE', 'wordpress_auth_fixture');
define('SECURE_AUTH_COOKIE', 'wordpress_sec_fixture');
define('LOGGED_IN_COOKIE', 'wordpress_logged_in_fixture');
define('COOKIEPATH', '/');
define('SITECOOKIEPATH', '/');
define('ADMIN_COOKIE_PATH', '/wp-admin');
define('PLUGINS_COOKIE_PATH', '/wp-content/plugins');
define('COOKIE_DOMAIN', '');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('YEAR_IN_SECONDS', 31536000);
define('WC_SESSION_CACHE_GROUP', 'woocommerce_sessions');
foreach (array('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT') as $key) { define($key, 'ISOLATED-TEST-ONLY-' . $key); }
$GLOBALS['options'] = array('home' => 'https://shop.example.test', 'siteurl' => 'https://shop.example.test');
$GLOBALS['meta'] = $GLOBALS['cache_store'] = $GLOBALS['wc_rows'] = $GLOBALS['wc_cookies'] = array();
$GLOBALS['ssl'] = true;
$GLOBALS['multisite'] = false;
$GLOBALS['wp_user_roles'] = array(
    'subscriber' => array('name' => 'Subscriber', 'capabilities' => array('read' => true)),
    'customer' => array('name' => 'Customer', 'capabilities' => array('read' => true)),
    'shop_manager' => array('name' => 'Shop manager', 'capabilities' => array('read' => true, 'manage_woocommerce' => true)),
    'administrator' => array('name' => 'Administrator', 'capabilities' => array('read' => true, 'manage_options' => true, 'manage_woocommerce' => true)),
);
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function wp_doing_ajax() { return true; }
function is_blog_admin() { return false; }
function is_network_admin() { return false; }
function is_multisite() { return $GLOBALS['multisite']; }
function get_current_blog_id() { return 1; }
function is_ssl() { return $GLOBALS['ssl']; }
function wp_installing() { return false; }
function get_option($key, $default = false) { return isset($GLOBALS['options'][$key]) ? $GLOBALS['options'][$key] : $default; }
function get_site_option($key, $default = false) { return get_option($key, $default); }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function get_transient($key) { return isset($GLOBALS['transients'][$key]) ? $GLOBALS['transients'][$key] : false; }
function set_transient($key, $value, $expiry = 0) { $GLOBALS['transients'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); return true; }
function get_metadata($type, $id, $key = '', $single = false) {
    if ($key === '') { return isset($GLOBALS['meta'][$id]) ? $GLOBALS['meta'][$id] : array(); }
    $value = isset($GLOBALS['meta'][$id][$key]) ? $GLOBALS['meta'][$id][$key] : null;
    return $single ? ($value === null ? '' : $value) : ($value === null ? array() : array($value));
}
function update_metadata($type, $id, $key, $value, $previous = '') { $GLOBALS['meta'][$id][$key] = $value; return true; }
function delete_metadata($type, $id, $key, $value = '', $all = false) { unset($GLOBALS['meta'][$id][$key]); return true; }
function metadata_exists($type, $id, $key) { return isset($GLOBALS['meta'][$id][$key]); }
function wp_cache_get($key, $group = '', $force = false, &$found = null) { $found = isset($GLOBALS['cache_store'][$group][$key]); return $found ? $GLOBALS['cache_store'][$group][$key] : false; }
function wp_cache_set($key, $value, $group = '', $expiry = 0) { $GLOBALS['cache_store'][$group][$key] = $value; return true; }
function wp_cache_add($key, $value, $group = '', $expiry = 0) { return wp_cache_get($key, $group) === false ? wp_cache_set($key, $value, $group, $expiry) : false; }
function wp_cache_delete($key, $group = '') { unset($GLOBALS['cache_store'][$group][$key]); return true; }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_user($value, $strict = false) { return $value; }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $key)); }
function __($value) { return $value; }
function _x($value, $context) { return $value; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function maybe_serialize($value) { return is_array($value) || is_object($value) ? serialize($value) : $value; }
function maybe_unserialize($value) { return is_string($value) && preg_match('/^[aObisdN]:/', $value) ? unserialize($value) : $value; }
function absint($value) { return abs((int) $value); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wc_site_is_https() { return true; }
function wc_setcookie($name, $value, $expiry, $secure, $http_only) { $GLOBALS['wc_cookies'][$name] = $value; check($http_only, 'WC session cookie lost HttpOnly'); }
function WC() { return isset($GLOBALS['wc']) ? $GLOBALS['wc'] : null; }
class WC_Cache_Helper { public static function get_cache_prefix($group) { return 'fixture-'; } }
eval('namespace Automattic\\Jetpack; class Constants { public static function is_defined($key) { return defined($key); } }');
class SessionTestDb {
    public $prefix = 'wp_'; public $users = 'wp_users';
    public function get_blog_prefix($id = null) { return 'wp_'; }
    public function prepare($sql, ...$args) { return array($sql, $args); }
    public function get_row($query) { return null; }
    public function get_var($query) { return isset($GLOBALS['wc_rows'][$query[1][0]]) ? $GLOBALS['wc_rows'][$query[1][0]] : null; }
    public function query($query) { $GLOBALS['wc_rows'][$query[1][0]] = $query[1][1]; return 1; }
    public function delete($table, $where) { unset($GLOBALS['wc_rows'][$where['session_key']]); return 1; }
    public function update($table, $data, $where) { return 1; }
}
$wpdb = new SessionTestDb();
require $cache . '/wp-includes/plugin.php';
require $cache . '/wp-includes/class-wp-error.php';
require $cache . '/wp-includes/class-wp-role.php';
require $cache . '/wp-includes/class-wp-roles.php';
require $cache . '/wp-includes/class-wp-user.php';
require $cache . '/wp-includes/capabilities.php';
require $cache . '/wp-includes/user.php';
require $cache . '/wp-includes/class-wp-session-tokens.php';
require $cache . '/wp-includes/class-wp-user-meta-session-tokens.php';
require $cache . '/wp-includes/pluggable.php';
require $cache . '/wc/abstract-wc-session.php';
require $cache . '/wc/class-wc-session-handler.php';
require $root . '/includes/core/class-ashna-compatibility.php';
require $root . '/includes/core/class-ashna-session.php';

// Load selected official WC functions verbatim, without booting the whole plugin.
function official_function($file, $name) {
    $tokens = token_get_all(file_get_contents($file));
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) { continue; }
        $code = ''; $depth = 0; $started = false;
        for (; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) { $depth++; $started = true; }
            if ($token === '}' && --$depth === 0 && $started) { eval($code); return; }
        }
    }
    throw new RuntimeException('Missing official function: ' . $name);
}
foreach (array('str_starts_with', 'str_ends_with', 'str_contains') as $polyfill) {
    if (!function_exists($polyfill)) { official_function($cache . '/wp-includes/compat.php', $polyfill); }
}
official_function($cache . '/wc/wc-user-functions.php', 'wc_user_logged_in');
official_function($cache . '/wc/wc-user-functions.php', 'wc_customer_has_capability');
function wc_update_user_last_active($id) { $GLOBALS['wc_last_active'] = $id; }
function wc_get_order($id) { return new class($id) {
    private $id; public function __construct($id) { $this->id = $id; }
    public function get_user_id() { return $this->id === 101 ? 7 : 8; }
}; }
add_action('wp_login', 'wc_user_logged_in', 10, 2);
add_filter('user_has_cap', 'wc_customer_has_capability', 10, 3);
function fixture_user($id, $role) {
    $row = (object) array('ID' => $id, 'user_login' => 'fixture' . $id, 'user_pass' => '$P$TEST-ONLY-HASH', 'user_email' => 'user' . $id . '@example.test', 'display_name' => 'Fixture', 'user_nicename' => 'fixture' . $id, 'spam' => 0, 'deleted' => 0);
    wp_cache_set($id, $row, 'users'); wp_cache_set($row->user_login, $id, 'userlogins'); wp_cache_set($row->user_email, $id, 'useremail');
    $GLOBALS['meta'][$id]['wp_capabilities'] = array($role => true);
    return new WP_User($id);
}
