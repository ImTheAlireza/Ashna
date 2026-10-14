<?php
/** Exercise actual AJAX handlers; OTP transport/lookup and JSON transport are fixtures. */
require __DIR__ . '/fixtures/session-bootstrap.php';
require $root . '/includes/core/class-ashna-storage.php';
require $root . '/includes/services/class-ashna-auth.php';
require $root . '/includes/core/class-ashna-passkey.php';
official_function($cache . '/wp-includes/formatting.php', '_deep_replace');
$missing_openssl = isset($argv[1]) && $argv[1] === 'no-openssl';
check(function_exists('openssl_verify') !== $missing_openssl, 'OpenSSL test configuration mismatch');
class Ashna_Helper {
    public static $settings = array('enable_passkey' => 1, 'allow_password_login' => 1);
    public static function get_option($key, $default = null) { return isset(self::$settings[$key]) ? self::$settings[$key] : $default; }
    public static function parse_identifier($value) { return array('type' => 'email', 'normalized' => $value); }
    public static function get_client_ip() { return '192.0.2.1'; }
}
class Ashna_Security {
    public static function verify_otp($identifier, $code) { return $code === '12345' ? true : new WP_Error('bad_otp', 'Invalid OTP'); }
    public static function check_lockout($identifier) { return true; }
    public static function clear_password_attempts($identifier) {}
    public static function record_failed_password_attempt($identifier) {}
}
class Ashna_Logger {
    public static function insert($data) { $GLOBALS['verified_logs'][] = $data; }
}
class WP_User_Query {
    private $users = array();
    public function __construct($args) {
        foreach ($GLOBALS['meta'] as $id => $meta) {
            if (isset($meta[$args['meta_key']]) && in_array($args['meta_value'], (array)$meta[$args['meta_key']], true)) { $this->users[] = new WP_User($id); }
        }
    }
    public function get_results() { return $this->users; }
}
class JsonResult extends RuntimeException {
    public $success; public $data;
    public function __construct($success, $data) { parent::__construct('JSON response'); $this->success = $success; $this->data = $data; }
}
function wp_send_json_success($data) { throw new JsonResult(true, $data); }
function wp_send_json_error($data) { throw new JsonResult(false, $data); }
function wp_die($message, $title = '', $args = array()) { throw new JsonResult(false, array('message' => $message)); }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, $args); }
function add_shortcode($tag, $callback) {}
function sanitize_text_field($value) { return trim($value); }
function sanitize_email($value) { return trim($value); }
function esc_url_raw($value) { return trim($value); }
function home_url($path = '') { return 'https://shop.example.test' . $path; }
function get_bloginfo($key) { return 'Fixture shop'; }
function wp_get_referer() { return false; }
function wp_kses_no_null($string, $options = array()) { return str_replace("\0", '', $string); }
function current_time($kind) { return '2026-10-14 12:00:00'; }
function as_guest($post) {
    wp_set_current_user(0);
    unset($_COOKIE[LOGGED_IN_COOKIE]);
    $_POST = $post;
    $_POST['nonce'] = wp_create_nonce('ashna_otp_nonce');
    $_REQUEST = $_POST;
}
function request_result($object, $method) {
    try { $object->$method(); } catch (JsonResult $result) { return $result; }
    throw new RuntimeException('Handler returned without JSON');
}
$user = fixture_user(7, 'customer');
fixture_user(8, 'customer');
$row = wp_cache_get(7, 'users'); $row->user_pass = wp_hash_password('fixture-password-only'); wp_cache_set(7, $row, 'users');
$GLOBALS['logins'] = 0;
add_action('wp_login', function($login, $user) { $GLOBALS['logins']++; check(get_current_user_id() === 7, 'Handler logged into the wrong account'); }, 10, 2);
$auth = Ashna_Auth::instance();
$passkey = new Ashna_Passkey();

if (!$missing_openssl) {
    as_guest(array('identifier' => 'user7@example.test', 'code' => '00000'));
    $response = request_result($auth, 'ajax_verify_otp');
    check(!$response->success && $GLOBALS['logins'] === 0 && get_current_user_id() === 0, 'Invalid OTP started a session');
    as_guest(array('identifier' => 'user7@example.test', 'code' => '12345', 'redirect_to' => 'https://shop.example.test/my-account'));
    $guest_nonce = $_POST['nonce'];
    $response = request_result($auth, 'ajax_verify_otp');
    check($response->success && $GLOBALS['logins'] === 1 && get_current_user_id() === 7, 'OTP handler did not use native session finalization');
    check(wp_verify_nonce($response->data['nonce'], 'ashna_otp_nonce') !== false && wp_verify_nonce($guest_nonce, 'ashna_otp_nonce') === false, 'OTP response returned the guest nonce');
    // The very next authenticated Passkey request uses the nonce returned by OTP.
    $_POST = $_REQUEST = array('nonce' => $response->data['nonce']);
    $enroll = request_result($passkey, 'ajax_register_options');
    check($enroll->success && isset($enroll->data['challenge']), 'Immediate post-OTP Passkey registration failed CSRF/session validation');
    $deny = function($user) { return new WP_Error('blocked_account', 'Blocked'); };
    add_filter('wp_authenticate_user', $deny);
    as_guest(array('identifier' => 'user7@example.test', 'code' => '12345'));
    $response = request_result($auth, 'ajax_verify_otp');
    remove_filter('wp_authenticate_user', $deny);
    check(!$response->success && $GLOBALS['logins'] === 1 && get_current_user_id() === 0, 'OTP ignored account denial');
    as_guest(array('identifier' => 'user7@example.test', 'password' => 'fixture-password-only'));
    $response = request_result($auth, 'ajax_password_login');
    check($response->success && $GLOBALS['logins'] === 2 && wp_verify_nonce($response->data['nonce'], 'ashna_otp_nonce') !== false, 'Password handler returned an invalid session nonce');
}

file_put_contents($cache . '/openssl.cnf', "[req]\ndistinguished_name=req_distinguished_name\n[req_distinguished_name]\n");
$key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, 'private_key_bits' => 2048, 'curve_name' => 'prime256v1', 'config' => $cache . '/openssl.cnf'));
check($key !== false, 'Cannot create fixture key');
$public = openssl_pkey_get_details($key)['key'];
$GLOBALS['meta'][7][Ashna_Storage::PASSKEYS] = array('fixture-credential' => array('public_key_pem' => $public, 'label' => 'Fixture'));
$GLOBALS['meta'][7][Ashna_Storage::PASSKEY_ID] = array('fixture-credential');
function assertion_fixture($key, $corrupt = false) {
    $challenge = Ashna_Passkey::base64url_encode(random_bytes(32));
    set_transient(Ashna_Storage::key('ashna_pk_auth_') . md5(Ashna_Helper::get_client_ip() . '_' . $challenge), $challenge, 120);
    $client = json_encode(array('type' => 'webauthn.get', 'challenge' => $challenge, 'origin' => 'https://shop.example.test'));
    $auth_data = hash('sha256', 'shop.example.test', true) . chr(1) . pack('N', 0);
    check(openssl_sign($auth_data . hash('sha256', $client, true), $signature, $key, OPENSSL_ALGO_SHA256), 'Cannot sign fixture');
    if ($corrupt) { $signature = str_repeat('x', strlen($signature)); }
    return array('credential_id' => 'fixture-credential', 'client_data_json' => Ashna_Passkey::base64url_encode($client), 'authenticator_data' => Ashna_Passkey::base64url_encode($auth_data), 'signature' => Ashna_Passkey::base64url_encode($signature), 'redirect_to' => 'https://evil.example.test/steal');
}
$before = $GLOBALS['logins'];
as_guest(assertion_fixture($key, !$missing_openssl));
$response = request_result($passkey, 'ajax_login_verify');
check(!$response->success && $GLOBALS['logins'] === $before && get_current_user_id() === 0, 'Unverified Passkey opened a session');
if ($missing_openssl) {
    check(strpos($response->data['message'], 'بررسی امضای') !== false, 'Missing OpenSSL did not fail closed');
    echo "PASS Passkey endpoint fails closed when openssl_verify is unavailable\n";
} else {
    as_guest(assertion_fixture($key));
    $response = request_result($passkey, 'ajax_login_verify');
    check($response->success && $GLOBALS['logins'] === $before + 1 && get_current_user_id() === 7, 'Valid signed assertion failed to create a native session');
    check(wp_verify_nonce($response->data['nonce'], 'ashna_otp_nonce') !== false, 'Passkey returned a nonce for the wrong session');
    check($response->data['redirect'] === home_url('/'), 'Passkey accepted an untrusted redirect');
    $repeat = request_result($passkey, 'ajax_login_verify');
    check(!$repeat->success && $GLOBALS['logins'] === $before + 1, 'Passkey assertion/challenge was reused');
    foreach (array('administrator', 'shop_manager', 'bogus', 'subscriber', 'customer') as $role) {
        Ashna_Helper::$settings['default_user_role'] = $role;
        check(Ashna_Auth::get_registration_role() === ($role === 'customer' ? 'customer' : 'subscriber'), 'Public signup could receive a privileged/unknown role');
    }
    wp_roles()->remove_role('customer');
    Ashna_Helper::$settings['default_user_role'] = 'customer';
    $GLOBALS['options']['default_role'] = 'administrator';
    check(Ashna_Auth::get_registration_role() === 'subscriber', 'Missing WC role fell back to a privileged site default');
    wp_roles()->remove_role('subscriber');
    check(is_wp_error(Ashna_Auth::get_registration_role()), 'Missing safe role did not deny signup');
    echo "PASS actual OTP/password/Passkey AJAX handlers: denial, native sessions, nonce handoff, signed/replayed assertions, redirect and registration-role safety\n";
}
ob_end_flush();
