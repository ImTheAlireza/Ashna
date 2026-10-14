<?php
require __DIR__ . '/fixtures/session-bootstrap.php';
$user = fixture_user(7, 'customer');
fixture_user(8, 'customer');
fixture_user(9, 'shop_manager');
fixture_user(10, 'administrator');
add_filter('determine_current_user', 'wp_validate_logged_in_cookie');
$GLOBALS['logins'] = 0;
add_action('wp_login', function($login, $user) { check(get_current_user_id() === $user->ID, 'wp_login saw a stale identity'); $GLOBALS['logins']++; }, 20, 2);
$guest_nonce = wp_create_nonce('ashna_otp_nonce');
$previous_token = '';
foreach (array('otp', 'password', 'passkey') as $method) {
    $result = Ashna_Session::login($user, $method);
    check(!is_wp_error($result), $method . ' session failed');
    check(array_keys($result) === array('nonce'), 'Session token/JWT leaked into response');
    $token = wp_get_session_token();
    check($token && $token !== $previous_token, 'Login reused the old session token');
    check(WP_Session_Tokens::get_instance(7)->verify($token), 'Native token manager cannot verify new session');
    check(wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in') === 7, 'Cookie invalid on the next request');
    check(wp_verify_nonce($result['nonce'], 'ashna_otp_nonce') !== false, 'Post-login nonce mismatched new session');
    check(wp_verify_nonce($guest_nonce, 'ashna_otp_nonce') === false, 'Guest nonce still authorizes authenticated requests');
    $current_user = null;
    check(get_current_user_id() === 7, 'Cookie did not rehydrate identity');
    check(current_user_can('read') && !current_user_can('manage_options') && !current_user_can('manage_woocommerce'), 'Customer gained permissions');
    check(current_user_can('view_order', 101) && !current_user_can('view_order', 102), 'WooCommerce order ownership permissions mismatched');
    check(get_user_meta(7, 'wp_capabilities', true) === array('customer' => true), 'Login rewrote roles');
    $previous_token = $token;
}
check($GLOBALS['logins'] === 3, 'wp_login did not fire once per login');
check(get_user_meta(7, '_woocommerce_load_saved_cart_after_login', true) === 1, 'WC persistent-cart marker not set');
check($GLOBALS['no_cache'], 'Login response not marked uncacheable');
echo "PASS native sessions: OTP/password/Passkey, fresh tokens, nonce refresh, next-request identity, customer permissions and order ownership\n";

// An existing guest cart must migrate via the real WC handler, not a plugin copy.
wp_set_current_user(0); unset($_COOKIE[LOGGED_IN_COOKIE]);
$GLOBALS['wc'] = (object) array('session' => new WC_Session_Handler());
$session = WC()->session;
$session->init_session_cookie();
$guest_id = $session->get_customer_id();
$session->set('cart', array('line-one' => array('product_id' => 42, 'quantity' => 2)));
$session->set('chosen_shipping_methods', array('flat_rate:1'));
$session->set_customer_session_cookie(true);
$session->save_data();
$_COOKIE['wp_woocommerce_session_' . COOKIEHASH] = $GLOBALS['wc_cookies']['wp_woocommerce_session_' . COOKIEHASH];
$result = Ashna_Session::login($user, 'otp');
check(!is_wp_error($result) && $session->get_customer_id() === '7', 'Guest session was not migrated to WP user');
check($session->get('cart')['line-one']['quantity'] === 2, 'Guest cart was lost');
check($session->get('chosen_shipping_methods') === array('flat_rate:1'), 'Guest checkout selection lost');
check(!isset($GLOBALS['wc_rows'][$guest_id]) && isset($GLOBALS['wc_rows']['7']), 'WC migration did not replace guest storage');
$_COOKIE['wp_woocommerce_session_' . COOKIEHASH] = $GLOBALS['wc_cookies']['wp_woocommerce_session_' . COOKIEHASH];
$next_session = new WC_Session_Handler(); $next_session->init_session_cookie();
check($next_session->get_customer_id() === '7' && $next_session->get('cart')['line-one']['quantity'] === 2, 'Cart was lost on next request');
echo "PASS real WooCommerce session migration: guest cart/shipping, user session cookie and next-request cart\n";

// Same-browser repeat login and privileged existing accounts keep their own roles.
$GLOBALS['wc'] = null;
foreach (array(9, 10) as $id) {
    $result = Ashna_Session::login(get_user_by('id', $id), 'otp');
    check(!is_wp_error($result) && current_user_can('manage_woocommerce'), 'Existing staff permissions lost');
    check(current_user_can('manage_options') === ($id === 10), 'Staff role gained/lost admin rights');
    $current_user = null; check(get_current_user_id() === $id, 'Repeat-login cookie identified wrong account');
}
echo "PASS role preservation: customer, shop manager, administrator and account switching\n";

// Account policies and cookie suppression must deny authentication without success hooks.
$before = $GLOBALS['logins']; $before_tokens = get_user_meta(7, 'session_tokens', true);
$deny = function($user, $password) { return new WP_Error('fixture_denied', 'Account denied'); };
add_filter('wp_authenticate_user', $deny, 10, 2);
check(is_wp_error(Ashna_Session::login($user, 'otp')), 'Account-policy rejection bypassed');
remove_filter('wp_authenticate_user', $deny, 10);
$swap = function($user) { return get_user_by('id', 10); };
add_filter('ashna_authenticate_user', $swap);
check(is_wp_error(Ashna_Session::login($user, 'passkey')), 'Policy substituted an administrator account');
remove_filter('ashna_authenticate_user', $swap);
$deny_cookies = function($send) { return false; };
add_filter('send_auth_cookies', $deny_cookies);
$result = Ashna_Session::login($user, 'otp');
remove_filter('send_auth_cookies', $deny_cookies);
check(is_wp_error($result) && get_current_user_id() === 10, 'Blocked cookie claimed success or changed current user');
check(get_user_meta(7, 'session_tokens', true) === $before_tokens, 'Blocked cookie left a new active session behind');
check($GLOBALS['logins'] === $before, 'Denied authentication emitted wp_login');
check(!has_filter('send_auth_cookies') && !has_action('set_logged_in_cookie'), 'Temporary cookie observers leaked');
echo "PASS policy rejection, account-substitution rejection, cookie suppression and token rollback\n";

$scheme = '';
$capture_scheme = function($cookie, $expire, $expiration, $id, $auth_scheme) use (&$scheme) { $scheme = $auth_scheme; };
$short_expiry = function($seconds, $id, $remember) { check($remember === true, 'Remember-login behavior changed'); return 180; };
add_action('set_auth_cookie', $capture_scheme, 10, 5);
add_filter('auth_cookie_expiration', $short_expiry, 10, 3);
$result = Ashna_Session::login(get_user_by('id', 10), 'otp');
remove_action('set_auth_cookie', $capture_scheme, 10);
remove_filter('auth_cookie_expiration', $short_expiry, 10);
check(!is_wp_error($result) && $scheme === 'secure_auth', 'HTTPS login failed to issue the secure authentication scheme');
$cookie = wp_parse_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
check((int)$cookie['expiration'] <= time() + 181, 'Core expiration policy was ignored');
check(wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE] . 'tampered', 'logged_in') === false, 'Tampered cookie accepted');
$expired_cookie = wp_generate_auth_cookie(10, time() - DAY_IN_SECONDS, 'logged_in', wp_get_session_token());
check(wp_validate_auth_cookie($expired_cookie, 'logged_in') === false, 'Expired cookie accepted');
echo "PASS HTTPS cookie scheme, core expiration policy, tampered and expired cookie rejection\n";

$token = wp_get_session_token();
wp_destroy_current_session();
check(!WP_Session_Tokens::get_instance(10)->verify($token), 'Native logout did not revoke session');
check(wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in') === false, 'Revoked cookie was accepted');
echo "PASS native session revocation/logout\n";
ob_end_flush();

$before = get_user_meta(7, 'session_tokens', true);
$result = Ashna_Session::login($user, 'otp');
check(is_wp_error($result) && $result->get_error_code() === 'ashna_session_headers_sent', 'Headers-sent login claimed success');
check(get_user_meta(7, 'session_tokens', true) === $before, 'Headers-sent rejection created a session');
echo "PASS headers-sent rejection without creating a session\n";
