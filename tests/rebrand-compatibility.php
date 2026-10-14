<?php
/** Included by license-bootstrap.php for licensed and unencoded rebrand fixtures. */
check(empty($GLOBALS['schema']), 'Rebrand recreated the existing logs schema');
check(Ashna_Logger::table_name() === 'wp_signa_otp_logs', 'Existing log table disconnected');
check(Ashna_Helper::get_option('primary_color') === '#123456', 'Saved settings were replaced');
check(isset($GLOBALS['options']['signa_active_lockouts']['09123456789']), 'Existing lockouts lost');
check(!isset($GLOBALS['options']['ashna_otp_settings']), 'Created an empty parallel settings store');
check($GLOBALS['cron']['signa_otp_daily_cleanup'] === 123456789 && count($GLOBALS['cron']) === 1, 'Scheduled cleanup duplicated or reset');
check(SIGNA_OTP_PATH === ASHNA_OTP_PATH && signa_otp() === ashna_otp(), 'Legacy bootstrap aliases missing');
check(class_exists('Signa_Helper') && Signa_Helper::get_option('primary_color') === '#123456', 'Legacy class alias missing');
check(interface_exists('Signa_Gateway_Interface'), 'Legacy interface alias missing');

// Published shortcode content remains untouched and calls the new renderer.
$GLOBALS['shortcode_tags'] = $GLOBALS['shortcodes'];
do_action('init');
foreach (array('otp_login', 'otp_form', 'otp_button', 'passkey_manager') as $name) {
    check($GLOBALS['shortcodes']['signa_' . $name] === $GLOBALS['shortcodes']['ashna_' . $name], 'Saved shortcode disconnected');
}
check(Ashna_Compatibility::markup('.signa-btn{color:var(--signa-primary)} signature signal') === '.ashna-btn{color:var(--ashna-primary)} signature signal', 'Selector translation corrupted non-brand words');

add_filter('signa_otp_login_redirect', function ($value) { return $value . '/old'; });
add_filter('ashna_otp_login_redirect', function ($value) { return $value . '/new'; });
check(Ashna_Compatibility::filter('ashna_otp_login_redirect', 'https://example.test') === 'https://example.test/old/new', 'Extension filters lost');
$GLOBALS['rebrand_hook_calls'] = 0;
add_action('signa_otp_sent', function () { ++$GLOBALS['rebrand_hook_calls']; });
add_action('ashna_otp_sent', function () { ++$GLOBALS['rebrand_hook_calls']; });
Ashna_Compatibility::action('ashna_otp_sent');
check($GLOBALS['rebrand_hook_calls'] === 2, 'Extension actions not dispatched exactly once each');

// Exercise the actual Passkey repository over legacy usermeta, including deletion.
$GLOBALS['test_meta'] = array(7 => array(
    '_signa_passkeys' => array('existing-id' => array('id' => 'existing-id', 'public_key_pem' => 'fixture-public-key', 'label' => 'Old device')),
    '_signa_passkey_id' => array('existing-id'),
    'signa_phone' => '09123456789',
));
function get_user_meta($id, $key, $single = false) {
    $value = isset($GLOBALS['test_meta'][$id][$key]) ? $GLOBALS['test_meta'][$id][$key] : ($single ? '' : array());
    return $value;
}
function update_user_meta($id, $key, $value) { $GLOBALS['test_meta'][$id][$key] = $value; return true; }
function add_user_meta($id, $key, $value, $unique = false) { $GLOBALS['test_meta'][$id][$key][] = $value; return true; }
function delete_user_meta($id, $key, $value = '') {
    $GLOBALS['test_meta'][$id][$key] = array_values(array_filter($GLOBALS['test_meta'][$id][$key], function ($v) use ($value) { return $v !== $value; }));
    return true;
}
class WP_User { public $ID = 7; }
function get_users($args) {
    check($args['meta_key'] === '_signa_passkey_id', 'Passkey index lookup changed');
    return in_array($args['meta_value'], $GLOBALS['test_meta'][7]['_signa_passkey_id'], true) ? array(new WP_User()) : array();
}
function sanitize_text_field($v) { return trim($v); }
function current_time($kind) { return '2026-10-14 12:00:00'; }
check(isset(Ashna_Passkey::get_user_passkeys(7)['existing-id']), 'Old credential unreadable');
check(Ashna_Passkey::find_user_by_credential_id('existing-id')->ID === 7, 'Credential index disconnected');
check(get_user_meta(7, Ashna_Storage::key('ashna_phone'), true) === '09123456789', 'Phone disconnected');
check(Ashna_Passkey::save_user_passkey(7, array('id' => 'new-id', 'public_key_pem' => 'new-fixture', 'label' => 'New device')), 'New credential save failed');
check(isset($GLOBALS['test_meta'][7]['_signa_passkeys']['new-id']), 'Credential written into a parallel store');
check(Ashna_Passkey::delete_user_passkey(7, 'existing-id'), 'Old credential cannot be deleted');
check(!isset(Ashna_Passkey::get_user_passkeys(7)['existing-id']) && Ashna_Passkey::find_user_by_credential_id('existing-id') === null, 'Deleted credential resurrected');
foreach (array('lock_', 'lock_ip_', 'pw_try_', 'pk_auth_', 'pk_reg_', 'u_') as $suffix) {
    check(Ashna_Storage::key('ashna_' . $suffix) === 'signa_' . $suffix, 'Security/WebAuthn storage contract changed');
}

// Elementor aliases render old saved widget IDs, but stay out of the new panel.
eval('namespace Elementor; class Widget_Base {}');
$manager = new class {
    public $widgets = array();
    public function register($widget) { $this->widgets[$widget->get_name()] = $widget; }
};
Ashna_Elementor::instance()->register_widgets($manager);
check(count($manager->widgets) === 4, 'Native or legacy Elementor widgets missing');
check($manager->widgets['signa_otp_login_widget'] instanceof Ashna_Elementor_Login_Widget, 'Old login widget lost its new renderer');
check($manager->widgets['signa_otp_modal_button_widget']->show_in_panel() === false, 'Legacy widget exposed in new panel');
echo "PASS rebrand: saved settings/logs/cron/lockouts, credentials/index/deletion, shortcodes, hooks, classes and Elementor aliases\n";
