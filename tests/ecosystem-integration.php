<?php
/** Actual pinned upstream policy functions + Ashna + native WP sessions; storage/services are fixtures. */
require __DIR__ . '/fixtures/session-bootstrap.php';
require $root . '/includes/integrations/class-ashna-integrations.php';
require $root . '/includes/services/class-ashna-auth.php';
official_function($cache . '/wp-includes/formatting.php', '_deep_replace');
class Ashna_Helper {
    public static $settings = array();
    public static function get_option($key, $default = null) { return isset(self::$settings[$key]) ? self::$settings[$key] : $default; }
    public static function get_client_ip() { return '192.0.2.1'; }
}
function wp_kses_no_null($string, $options = array()) { return str_replace("\0", '', $string); }
function esc_url($url) { return $url; }
function esc_url_raw($url) { return $url; }
function esc_sql($value) { return (string)$value; }
function get_permalink($id) { return 'https://shop.example.test/page-' . $id . '/'; }
function home_url($path = '') { return 'https://shop.example.test' . $path; }
function admin_url($path = '') { return home_url('/wp-admin/' . $path); }
function wp_get_referer() { return isset($GLOBALS['referer']) ? $GLOBALS['referer'] : false; }
function wc_get_page_permalink($page) { return home_url('/' . $page . '/'); }
function dokan_get_option($key, $section, $default = '') { return $key === 'dashboard' ? 42 : $default; }
function pmpro_url($page) { return home_url('/membership-' . $page . '/'); }
function um_get_core_page($page) { return home_url('/um-' . $page . '/'); }
function um_user_ip() { return '192.0.2.1'; }
class UMFixture {
    public $settings = array();
    public function options() { return $this; }
    public function common() { return $this; }
    public function users() { return $this; }
    public function get($key) { return isset($this->settings[$key]) ? $this->settings[$key] : ''; }
    public function get_status($id) { return get_user_meta($id, 'account_status', true); }
}
function UM() { return $GLOBALS['um']; }
$GLOBALS['um'] = new UMFixture();
class MeprOptions {
    public static function fetch() { return (object)array('account_page_id' => 47); }
}
$upstream = $root . '/.cache/ecosystem/';
foreach (array('dokan_is_user_seller', 'dokan_is_seller_enabled', 'dokan_get_navigation_url') as $fn) { official_function($upstream . 'dokan/includes/functions.php', $fn); }
official_function($upstream . 'pmpro/includes/login.php', 'pmpro_login_redirect');
official_function($upstream . 'um/includes/core/um-filters-login.php', 'um_wp_form_errors_hook_logincheck');
$GLOBALS['wp_user_roles']['seller'] = array('name'=>'Seller','capabilities'=>array('read'=>true,'dokandar'=>true));
$user = fixture_user(7, 'seller');
$GLOBALS['meta'][7]['dokan_enable_selling'] = 'yes';
Ashna_Integrations::register(); Ashna_Integrations::register();
check(count($wp_filter['ashna_authenticate_user']->callbacks[20]) === 1, 'Duplicate bridge hooks');
check(Ashna_Integrations::redirect(home_url('/'), $user) === home_url('/'), 'Disabled bridges changed destination');
Ashna_Helper::$settings['integrate_dokan'] = 1;
check(Ashna_Auth::resolve_redirect_url('', $user) === get_permalink(42), 'Enabled seller not sent to dashboard');
check(Ashna_Auth::resolve_redirect_url(home_url('/checkout/'), $user) === home_url('/checkout/'), 'Explicit checkout overridden');
$GLOBALS['referer'] = home_url('/checkout/');
check(Ashna_Auth::resolve_redirect_url('', $user) === home_url('/checkout/'), 'Automatic checkout overridden');
unset($GLOBALS['referer']);
Ashna_Helper::$settings['redirect_behavior'] = 'custom'; Ashna_Helper::$settings['custom_redirect_url'] = home_url('/chosen/');
check(Ashna_Auth::resolve_redirect_url('', $user) === home_url('/chosen/'), 'Manual destination overridden');
Ashna_Helper::$settings['redirect_behavior'] = 'auto';
$GLOBALS['meta'][7]['dokan_enable_selling'] = 'no';
foreach (array('otp','password','passkey') as $method) {
    check(!is_wp_error(Ashna_Session::login($user,$method)), 'Disabled selling incorrectly prevented account login');
    check(Ashna_Auth::resolve_redirect_url('', $user) === home_url('/myaccount/'), 'Disabled seller sent to dashboard');
    check(get_user_meta(7,'dokan_enable_selling',true) === 'no' && (new WP_User(7))->roles === array('seller'), 'Vendor status/role mutated');
}
check(is_wp_error(Ashna_Auth::register_user('new@example.test','email')), 'Managed signup created a generic account');
echo "PASS Dokan upstream role/status/navigation APIs: seller routes, disabled selling, role preservation, explicit/custom/checkout precedence and managed signup\n";
class MembershipDatabase {
    public $pmpro_memberships_users = 'wp_pmpro_memberships_users';
    public $active = true;
    public function get_var($sql) { return $this->active ? 1 : null; }
}
$session_database = $wpdb;
$wpdb = new MembershipDatabase();
Ashna_Helper::$settings = array('integrate_pmpro'=>1);
check(Ashna_Auth::resolve_redirect_url('', $user) === pmpro_url('account'), 'Active member destination');
$wpdb->active = false;
check(Ashna_Auth::resolve_redirect_url('', $user) === pmpro_url('levels'), 'Expired/non-member cannot reach renewal levels');
foreach (array('otp','password','passkey') as $method) { check(!is_wp_error(Ashna_Integrations::authorize($user,$method)), 'Plan expiry prevents renewal login'); }
check(is_wp_error(Ashna_Auth::register_user('new@example.test','email')), 'PMPro signup bypassed original checkout');
add_filter('pmpro_login_redirect_url', function($url) { return 'https://evil.example.test/'; });
check(Ashna_Auth::resolve_redirect_url('', $user) === home_url('/'), 'External membership redirect accepted');
echo "PASS native PMPro login redirect: active/expired destinations, renewal allowed, managed signup and external redirect rejection\n";
$wpdb = $session_database;
Ashna_Helper::$settings = array('integrate_um'=>1);
foreach (array('inactive','awaiting_admin_review','awaiting_email_confirmation','rejected') as $status) {
    $GLOBALS['meta'][7]['account_status'] = $status;
    foreach (array('otp','password','passkey') as $method) {
        $cookie = isset($_COOKIE[LOGGED_IN_COOKIE]) ? $_COOKIE[LOGGED_IN_COOKIE] : '';
        $result = Ashna_Session::login($user, $method);
        check(is_wp_error($result) && $result->get_error_code() === $status, 'UM restricted account bypassed: ' . $status . '/' . $method);
        check((isset($_COOKIE[LOGGED_IN_COOKIE]) ? $_COOKIE[LOGGED_IN_COOKIE] : '') === $cookie, 'Denied login issued a cookie');
    }
}
$GLOBALS['meta'][7]['account_status'] = 'approved';
foreach (array('otp','password','passkey') as $method) { check(!is_wp_error(Ashna_Session::login($user,$method)), 'Approved UM member denied'); }
check(Ashna_Auth::resolve_redirect_url('', $user) === um_get_core_page('account'), 'UM account destination');
$phone_only = clone $user; $phone_only->data = clone $user->data; $phone_only->user_email = '';
check(!is_wp_error(Ashna_Integrations::authorize($phone_only,'otp')), 'Empty block list denied a phone-only account');
$GLOBALS['um']->settings['blocked_emails'] = '*@example.test';
check(is_wp_error(Ashna_Session::login($user,'otp')), 'UM blocked domain bypassed');
$GLOBALS['um']->settings = array('blocked_ips'=>'192.0.2.*');
check(is_wp_error(Ashna_Session::login($user,'passkey')), 'UM blocked IP bypassed');
$GLOBALS['um']->settings = array('deny_admin_frontend_login'=>true);
$admin = fixture_user(10, 'administrator');
check(is_wp_error(Ashna_Session::login($admin,'password')), 'UM admin restriction bypassed');
check(is_wp_error(Ashna_Auth::register_user('new@example.test','email')), 'UM signup bypassed approval workflow');
echo "PASS actual UM account-status policy across all three methods: 12 denied logins issue no cookie; approved logins, email/IP/admin restrictions and managed signup\n";
Ashna_Helper::$settings = array('integrate_memberpress'=>1);
check(Ashna_Auth::resolve_redirect_url('', $user) === get_permalink(47), 'MemberPress fixture account page not selected');
check(is_wp_error(Ashna_Auth::register_user('new@example.test','email')), 'MemberPress signup bypass');
echo "PASS provisional MemberPress options-contract fixture ONLY: account route and managed signup; NOT a licensed-plugin compatibility certification\n";
