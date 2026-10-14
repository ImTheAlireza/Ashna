<?php
/** Offline capture of the real PHP settings view; NOT an installed WordPress site. */
error_reporting( E_ALL );
set_error_handler( static function ( $level, $message, $file, $line ) {
	if ( error_reporting() & $level ) { throw new ErrorException( $message, 0, $level, $file, $line ); }
	return false;
} );
$root = dirname( __DIR__, 2 );
define( 'ABSPATH', $root . '/.cache/settings-capture/' );
define( 'ASHNA_OTP_PATH', $root . '/' );
define( 'ASHNA_OTP_URL', 'file://' . $root . '/' );
preg_match( '/Version:\s*([0-9.]+)/', file_get_contents( $root . '/index.php' ), $version );
define( 'ASHNA_OTP_VERSION', $version[1] );
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_textarea( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function checked( $value, $expected = true, $echo = true ) { $result = (string) $value === (string) $expected ? ' checked="checked"' : ''; if ( $echo ) { echo $result; } return $result; }
function selected( $value, $expected = true, $echo = true ) { $result = (string) $value === (string) $expected ? ' selected="selected"' : ''; if ( $echo ) { echo $result; } return $result; }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $key, $default = false ) { return $default; }
function get_bloginfo( $key ) { return 'name' === $key ? 'محیط آزمایشی آشنا' : 'demo@example.test'; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function do_action( $hook, ...$args ) {}
function did_action( $hook ) { return 0; }
function get_pages( $args = array() ) { return array(); }
function number_format_i18n( $value ) { return strtr( number_format( $value ), array_combine( range( '0', '9' ), preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY ) ) ); }
function admin_url( $path = '' ) { return 'https://capture.invalid/wp-admin/' . $path; }
function wp_create_nonce( $action ) { return 'OFFLINE-CAPTURE-NOT-A-VALID-NONCE'; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="OFFLINE-CAPTURE-NOT-A-VALID-NONCE">'; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
require $root . '/includes/class-ashna-autoloader.php';
Ashna_Autoloader::register();
$settings = Ashna_Helper::get_settings();
$sms_gateways = Ashna_Gateway_Manager::get_sms_gateways();
$stats = array_fill_keys( array( 'total', 'today', 'verified', 'conversion_rate', 'otp_users', 'failed' ), 0 );
$chart_data = $recent_logs = $active_lockouts = array();
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>رندر آزمایشی تنظیمات آشنا</title>
<link rel="stylesheet" href="<?php echo ASHNA_OTP_URL; ?>assets/css/admin.css">
<style>
@font-face{font-family:Vazirmatn;src:url('<?php echo ASHNA_OTP_URL; ?>.cache/settings-capture/vazirmatn.woff2') format('woff2');font-weight:100 900;font-display:block}
body{margin:0;background:#f1f5f9}input,button,select,textarea{font-family:inherit}
*,*::before,*::after{box-sizing:border-box}
</style>
<script src="<?php echo ASHNA_OTP_URL; ?>.cache/frontend-tests/node_modules/jquery/dist/jquery.js"></script>
<script>window.ashnaAdminParams={ajaxUrl:'https://capture.invalid/blocked',nonce:'OFFLINE-CAPTURE-NOT-A-VALID-NONCE'};jQuery.fx.off=true;</script>
</head><body>
<?php require $root . '/includes/admin/views/settings-page.php'; ?>
<script src="<?php echo ASHNA_OTP_URL; ?>assets/js/admin.js"></script>
</body></html>
