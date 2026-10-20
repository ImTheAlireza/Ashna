<?php
/**
 * Broad sandbox QA for Ashna (tests/run-full-wp.py scenarios).
 *
 * Every PHP notice, warning or deprecation raised by Ashna code fails the run (see
 * tests/lib/wp-harness.php). Rendered HTML is parsed and must have no mismatched tags,
 * unescaped ampersands or duplicate IDs.
 */

require_once __DIR__ . '/lib/wp-harness.php';
require_once __DIR__ . '/fixtures/third-party.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$GLOBALS['ashna_base']  = array(
	'active_sms_gateway'       => 'sandbox',
	'backup_sms_gateway_1'     => 'none',
	'backup_sms_gateway_2'     => 'none',
	'backup_sms_gateway_3'     => 'none',
	'mobile_delivery_channel'  => 'sms',
	'login_mode'               => 'phone_and_email',
	'auto_register'            => 1,
	'whitelisted_identifiers'  => '203.0.113.7',
	'captcha_type'             => 'none',
	'redirect_behavior'        => 'home',
	'require_name_on_register' => 'optional',
	'otp_length'               => 5,
	'allow_password_login'     => 0,
	'wp_login_integration'     => 0,
);
ashna_t_settings( $GLOBALS['ashna_base'] );
add_filter( 'pre_wp_mail', '__return_true' );
Ashna_Admin::instance(); // Admin hooks (admin-ajax.php loads them via is_admin()).

/** Problems a browser would silently "fix": mismatched tags, raw &, duplicate IDs. */
function html_problems( $html ) {
	if ( PHP_VERSION_ID < 80000 ) {
		// php-wasm's PHP 7.4 build reports no libxml errors and crashes when clearing them. The
		// markup does not depend on the PHP version and is checked on every PHP 8 run.
		return array();
	}
	// libxml's HTML4 parser ends <script>/<style> at the first "</x" — browsers don't, and
	// inline JS legitimately builds markup strings. Blank those bodies (keeping line numbers).
	$html = preg_replace_callback(
		'#(<(script|style)\b[^>]*>)(.*?)(</\2>)#is',
		function ( $m ) {
			return $m[1] . str_repeat( "\n", substr_count( $m[3], "\n" ) ) . $m[4];
		},
		$html
	);
	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="ashna-qa-root">' . $html . '</div>', LIBXML_NOWARNING );
	$problems = array();
	foreach ( libxml_get_errors() as $e ) {
		if ( 801 === $e->code ) { // HTML5 / SVG element names unknown to libxml.
			continue;
		}
		$problems[] = trim( $e->message ) . ' (line ' . $e->line . ')';
	}
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$seen = array();
	foreach ( ( new DOMXPath( $doc ) )->query( '//*[@id]' ) as $node ) {
		$id = $node->getAttribute( 'id' );
		if ( isset( $seen[ $id ] ) ) {
			$problems[] = 'duplicate id "' . $id . '"';
		}
		$seen[ $id ] = true;
	}
	return array_slice( array_unique( $problems ), 0, 5 );
}

function as_admin() {
	static $admin = null;
	if ( null === $admin ) {
		$admin = make_user( 'qa_admin', 'administrator' );
	}
	wp_set_current_user( $admin->ID );
	return $admin;
}

function http_ok( $body, $code = 200 ) {
	return array(
		'headers'  => array(),
		'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
		'response' => array( 'code' => $code, 'message' => '' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

/* =========================================================================
 * A. Identifier parsing
 * ======================================================================= */
scenario(
	'Identifier parsing (phones in every format, e-mails, junk)',
	function () {
		$cases = array(
			'09123456789'                 => array( 'phone', '09123456789' ),
			'9123456789'                  => array( 'phone', '09123456789' ),
			'+989123456789'               => array( 'phone', '09123456789' ),
			'00989123456789'              => array( 'phone', '09123456789' ),
			'989123456789'                => array( 'phone', '09123456789' ),
			'۰۹۱۲۳۴۵۶۷۸۹'                 => array( 'phone', '09123456789' ),
			'٠٩١٢٣٤٥٦٧٨٩'                 => array( 'phone', '09123456789' ),
			' 0912 345 6789 '             => array( 'phone', '09123456789' ),
			'0912-345-6789'               => array( 'phone', '09123456789' ),
			'+98 912 345 6789'            => array( 'phone', '09123456789' ),
			'Test.User+tag@Example.COM'   => array( 'email', 'test.user+tag@example.com' ),
			'0912345678'                  => array( 'invalid', null ),
			'091234567890'                => array( 'invalid', null ),
			'02112345678'                 => array( 'invalid', null ),
			'test@example'                => array( 'invalid', null ),
			'<script>alert(1)</script>'   => array( 'invalid', null ),
			''                            => array( 'invalid', null ),
		);
		$bad = array();
		foreach ( $cases as $in => $expected ) {
			$got = Ashna_Helper::parse_identifier( $in );
			if ( $got['type'] !== $expected[0] || ( null !== $expected[1] && $got['normalized'] !== $expected[1] ) ) {
				$bad[] = $in . ' → ' . $got['type'] . ':' . $got['normalized'];
			}
		}
		check( count( $cases ) . ' identifier formats parsed as expected', empty( $bad ), $bad );
		check( 'masking', '0912***6789' === Ashna_Helper::mask_identifier( '09123456789' ) && 'te***@example.com' === Ashna_Helper::mask_identifier( 'test@example.com' ) );
	}
);

/* =========================================================================
 * B. Settings: sanitize, import, reset
 * ======================================================================= */
scenario(
	'Settings sanitizer: idempotent on defaults, safe on garbage',
	function () {
		$admin    = Ashna_Admin::instance();
		$sanitize = new ReflectionMethod( $admin, 'sanitize_settings_payload' );
		$sanitize->setAccessible( true );
		$defaults = Ashna_Helper::default_settings();

		$once    = $sanitize->invoke( $admin, $defaults );
		$changed = array();
		foreach ( $defaults as $k => $v ) {
			if ( ! array_key_exists( $k, $once ) || $once[ $k ] != $v ) { // phpcs:ignore Universal.Operators.StrictComparisons
				$changed[ $k ] = array( $v, isset( $once[ $k ] ) ? $once[ $k ] : null );
			}
		}
		check( 'saving the defaults does not change any value', empty( $changed ), $changed );
		check( 'every setting is kept (no key lost)', array_keys( $defaults ) === array_keys( $once ) );

		mt_srand( 20261015 );
		$garbage = array( array( 'x' => array( 'y' ) ), '<script>alert(1)</script>', '-5', '99999999999', '۱۲۳', ' ', 'javascript:alert(1)', "a\0b", 'true', '0' );
		$types   = array();
		for ( $round = 0; $round < 40; $round++ ) {
			$raw = array();
			foreach ( $defaults as $k => $v ) {
				$raw[ $k ] = $garbage[ mt_rand( 0, count( $garbage ) - 1 ) ];
			}
			$clean = $sanitize->invoke( $admin, $raw );
			foreach ( $clean as $k => $v ) {
				if ( 'role_redirects' === $k ) {
					if ( ! is_array( $v ) ) {
						$types[ $k ] = 'not array';
					}
					continue;
				}
				if ( is_array( $v ) || is_object( $v ) ) {
					$types[ $k ] = 'array leaked';
				} elseif ( is_string( $v ) && ( false !== stripos( $v, '<script' ) || false !== stripos( $v, 'javascript:' ) && false !== strpos( (string) $defaults[ $k ], 'http' ) ) ) {
					$types[ $k ] = 'unsafe value kept: ' . $v;
				}
			}
			if ( $clean['otp_length'] < 4 || $clean['otp_length'] > 8 || $clean['session_days'] < 1 || $clean['gateway_timeout'] < 2 || $clean['gateway_timeout'] > 15 ) {
				$types['bounds'] = array( $clean['otp_length'], $clean['session_days'], $clean['gateway_timeout'] );
			}
		}
		check( '40 rounds of garbage: no arrays/scripts leak, numbers stay in range', empty( $types ), $types );
		$clean = $sanitize->invoke( $admin, array( 'default_user_role' => 'administrator' ) + $defaults );
		check( 'an administrator role cannot be stored as the sign-up role', 'administrator' !== $clean['default_user_role'] );
	}
);

scenario(
	'Import / export / reset',
	function () {
		as_admin();
		$export = Ashna_Helper::get_settings();
		$nonce  = wp_create_nonce( 'ashna_admin_nonce' );

		$res = ajax( 'ashna_admin_import_settings', array( 'nonce' => $nonce, 'json_data' => wp_json_encode( array_merge( $export, array( 'form_title' => 'عنوان وارد شده' ) ) ) ) );
		check( 'full export re-imports', ok_json( $res ) && 'عنوان وارد شده' === Ashna_Helper::get_option( 'form_title' ), $res['raw'] );

		// Export of an older version: newer keys are missing.
		$old = $export;
		foreach ( array( 'session_remember', 'respect_login_gates', 'respect_plugin_redirects', 'gateway_probe', 'gateway_adaptive', 'login_url_to_ashna', 'dokan_vendor_signup', 'replace_member_forms', 'trust_proxy_headers' ) as $new_key ) {
			unset( $old[ $new_key ] );
		}
		$old['enable_passkey'] = 0;
		$res                   = ajax( 'ashna_admin_import_settings', array( 'nonce' => $nonce, 'json_data' => wp_json_encode( $old ) ) );
		check( 'older export: missing on/off settings keep their defaults (not switched off)', ok_json( $res ) && 1 === (int) Ashna_Helper::get_option( 'session_remember' ) && 1 === (int) Ashna_Helper::get_option( 'respect_login_gates' ) && 1 === (int) Ashna_Helper::get_option( 'gateway_probe' ) && 1 === (int) Ashna_Helper::get_option( 'trust_proxy_headers' ) );
		check( '…while values present in the file are applied', 0 === (int) Ashna_Helper::get_option( 'enable_passkey' ) );

		$res = ajax( 'ashna_admin_import_settings', array( 'nonce' => $nonce, 'json_data' => '{not json' ) );
		check( 'broken JSON refused', ! ok_json( $res ) );
		$res = ajax( 'ashna_admin_import_settings', array( 'nonce' => $nonce, 'json_data' => wp_json_encode( array( 'form_title' => '<img src=x onerror=alert(1)>', 'custom_css' => '</style><script>alert(1)</script>' ) ) ) );
		check( 'scripts in imported values are stripped', ok_json( $res ) && false === strpos( Ashna_Helper::get_option( 'form_title' ), '<img' ) && false === strpos( Ashna_Helper::get_option( 'custom_css' ), '<script' ) );

		$res = ajax( 'ashna_admin_reset_settings', array( 'nonce' => $nonce ) );
		check( 'reset restores factory defaults', ok_json( $res ) && Ashna_Helper::default_settings() == get_option( 'ashna_otp_settings' ) ); // phpcs:ignore Universal.Operators.StrictComparisons
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

/* =========================================================================
 * C. Admin screens
 * ======================================================================= */
function render_admin_page( $method ) {
	$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_ashna-otp' );
	ob_start();
	Ashna_Admin::instance()->$method();
	$html = ob_get_clean();
	unset( $GLOBALS['current_screen'] );
	return $html;
}

scenario(
	'Admin settings page renders cleanly in many states',
	function () {
		as_admin();
		$states = array(
			'defaults'      => array(),
			'everything on' => array_fill_keys( array( 'allow_password_login', 'enable_passkey', 'show_terms_checkbox', 'glassmorphism', 'wp_login_integration', 'show_debug_code_in_toast', 'send_welcome_message' ), 1 ),
			'gateways set'  => array( 'active_sms_gateway' => 'kavenegar', 'backup_sms_gateway_1' => 'smsir', 'backup_sms_gateway_2' => 'msgway', 'mobile_delivery_channel' => 'messenger_fallback_sms', 'bale_mode' => 'bot', 'captcha_type' => 'arcaptcha' ),
			'split layout'  => array( 'form_layout' => 'split_left', 'canvas_bg_style' => 'custom_image', 'canvas_bg_image' => 'https://example.test/bg.jpg?a=1&b=2', 'logo_url' => 'https://example.test/l.png?x=1&y=2' ),
		);
		foreach ( $states as $label => $changes ) {
			ashna_t_settings( array_merge( $GLOBALS['ashna_base'], $changes ) );
			$html = render_admin_page( 'render_settings_page' );
			check( "settings page ({$label}): rendered, every tab present", false !== strpos( $html, 'ashna-tab-security_firewall' ) && false !== strpos( $html, 'ashna-tab-woocommerce' ) && strlen( $html ) > 50000, strlen( $html ) );
			$p = html_problems( $html );
			check( "settings page ({$label}): well-formed HTML", empty( $p ), $p );
		}

		// Live data on the panel: attack mode, lockouts, proxy hint, refused requests, logs.
		update_option( Ashna_Rate_Limiter::ATTACK_OPTION, array( 'since' => time(), 'until' => time() + 600, 'peak' => 99 ), false );
		Ashna_Security::trigger_ip_lockout( '198.51.100.66' );
		update_option( 'ashna_otp_proxy_hint', array( 'remote' => '203.0.113.200', 'headers' => array( 'X-FORWARDED-FOR' ), 'arvan' => true, 'seen' => time() ), false );
		Ashna_Rate_Limiter::record_block( 'ashna_rate_limit_ip_minute', '09121112233', '198.51.100.66' );
		Ashna_Logger::insert( array( 'recipient' => '<b>x</b>@example.com', 'channel' => 'email', 'gateway' => 'email', 'otp_code' => '•••••', 'status' => 'sent', 'response_message' => 'خطا <script>alert(1)</script> & co' ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
		$html = render_admin_page( 'render_settings_page' );
		check( 'panel with live security data renders', false !== strpos( $html, 'ashna-attack-mode-note' ) && false !== strpos( $html, 'ashna-proxy-hint' ) && false !== strpos( $html, '198.51.100.66' ) );
		check( 'no raw HTML from stored data is printed', false === strpos( $html, '<script>alert(1)</script>' ) && false === strpos( $html, '<b>x</b>' ) );
		$p = html_problems( $html );
		check( 'live panel: well-formed HTML', empty( $p ), $p );
		Ashna_Rate_Limiter::end_attack_mode();
		delete_option( 'ashna_active_lockouts' );
		delete_option( 'ashna_otp_proxy_hint' );
		delete_option( Ashna_Rate_Limiter::STATS_OPTION );
	}
);

scenario(
	'Logs page: filters, search, paging; CSV export is formula-safe',
	function () {
		as_admin();
		foreach ( array( 'sent', 'verified', 'failed', 'expired', 'pending' ) as $i => $status ) {
			Ashna_Logger::insert( array( 'recipient' => '0912000000' . $i, 'channel' => 'sms', 'gateway' => 'sandbox', 'otp_code' => '12345', 'status' => $status ) );
		}
		Ashna_Logger::insert( array( 'recipient' => '=1+1@x.co', 'channel' => 'email', 'gateway' => 'email', 'status' => 'sent', 'response_message' => '-cmd|calc' ) );
		foreach ( array( array(), array( 'status' => 'verified' ), array( 'channel' => 'email' ), array( 'search' => '0912' ), array( 'paged' => '2' ), array( 'paged' => '999' ), array( 'search' => '"><script>' ) ) as $get ) {
			$_GET = wp_slash( $get );
			$html = render_admin_page( 'render_logs_page' );
			$p    = html_problems( $html );
			check( 'logs page ' . wp_json_encode( $get ) . ' renders cleanly', '' !== $html && empty( $p ) && false === strpos( $html, '"><script>' ), $p );
		}
		$_GET   = array();
		$tricky = "line one\nhe said \"hi\" in C:\\temp\\";
		Ashna_Logger::insert( array( 'recipient' => '09120000009', 'channel' => 'sms', 'gateway' => 'sandbox', 'status' => 'failed', 'response_message' => $tricky ) );
		$stream = fopen( 'php://memory', 'w+' );
		Ashna_Admin::write_logs_csv( $stream );
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		check( 'CSV has a UTF-8 BOM and a header row', 0 === strpos( $csv, "\xEF\xBB\xBF" ) && false !== strpos( $csv, 'Recipient' ) );
		check( 'CSV neutralizes formula cells (=, -)', false !== strpos( $csv, "'=1+1@x.co" ) && false !== strpos( $csv, "'-cmd|calc" ) );
		fseek( $stream, 3 );
		$parsed = array();
		while ( false !== ( $cells = fgetcsv( $stream, 0, ',', '"', '' ) ) ) {
			$parsed[] = $cells;
		}
		fclose( $stream );
		$found = null;
		foreach ( $parsed as $cells ) {
			if ( isset( $cells[1] ) && '09120000009' === $cells[1] ) {
				$found = $cells;
			}
		}
		check( 'CSV parses back with standard rules: every row has 11 cells', count( $parsed ) > 1 && 1 === count( array_unique( array_map( 'count', $parsed ) ) ) && 11 === count( $parsed[0] ) );
		check( '…and quotes, backslashes and new lines come back unchanged', $found && $tricky === $found[8], $found );
	}
);

/* =========================================================================
 * D. Login form template matrix
 * ======================================================================= */
scenario(
	'Login form: every option value + 250 random combinations render cleanly',
	function () {
		$dims     = array(
			'form_layout'              => array( 'card', 'split_right', 'split_left' ),
			'card_position'            => array( 'center', 'right', 'left' ),
			'login_mode'               => array( 'phone_only', 'phone_and_email', 'email_only' ),
			'input_addon_style'        => array( 'icon', 'ir_flag', 'none' ),
			'input_style'              => array( 'filled', 'outlined', 'underlined', 'soft_pill' ),
			'digit_box_style'          => array( 'box', 'underline', 'pill' ),
			'timer_style'              => array( 'progress_bar', 'circular_ring', 'minimal_badge', 'simple_text' ),
			'form_animation'           => array( 'fade_up', 'zoom_spring', 'slide_rtl', 'none' ),
			'card_shadow'              => array( 'none', 'soft', 'medium', 'deep', 'glow' ),
			'card_border_style'        => array( 'subtle', 'none', 'glow', 'top_accent' ),
			'bg_pattern'               => array( 'none', 'dots', 'grid', 'waves', 'geometric' ),
			'font_family'              => array( 'vazirmatn', 'iransans', 'yekanbakh', 'dana', 'estedad', 'theme_inherit' ),
			'button_bg_mode'           => array( 'solid', 'gradient' ),
			'glassmorphism'            => array( 0, 1 ),
			'captcha_type'             => array( 'none', 'math', 'arcaptcha', 'recaptcha_v3', 'turnstile' ),
			'allow_password_login'     => array( 0, 1 ),
			'enable_passkey'           => array( 0, 1 ),
			'passkey_prompt_after_otp' => array( 0, 1 ),
			'show_terms_checkbox'      => array( 0, 1 ),
			'otp_length'               => array( 4, 5, 6, 7, 8 ),
			'logo_url'                 => array( '', 'https://example.test/logo.png?a=1&b=2' ),
			'card_bg_color'            => array( '#ffffff', '#0f172a', 'not-a-color' ),
			'canvas_bg_style'          => array( 'mesh_light', 'mesh_dark', 'brand_gradient', 'custom_image', 'solid' ),
			'terms_url'                => array( '', 'https://example.test/terms?a=1&b=2' ),
		);
		$contexts = array( 'shortcode', 'modal', 'wc-checkout', 'standalone-page', 'wp-login', 'member-pmpro_login', 'elementor', 'wc-myaccount' );
		$combos   = array();
		foreach ( $dims as $dim => $values ) {
			foreach ( $values as $v ) {
				$combos[] = array( $dim => $v );
			}
		}
		mt_srand( 1015 );
		for ( $i = 0; $i < 250; $i++ ) {
			$c = array();
			foreach ( $dims as $dim => $values ) {
				$c[ $dim ] = $values[ mt_rand( 0, count( $values ) - 1 ) ];
			}
			$combos[] = $c;
		}

		$failures = array();
		browser_guest();
		foreach ( $combos as $n => $combo ) {
			ashna_t_settings( array_merge( $GLOBALS['ashna_base'], array( 'captcha_site_key' => 'site-key', 'captcha_secret_key' => 'secret' ), $combo ) );
			$context = $contexts[ $n % count( $contexts ) ];
			$html    = Ashna_Frontend::get_login_form_html( array( 'context' => $context, 'redirect' => home_url( '/x/?a=1&b=2' ) ) );
			$s       = Ashna_Helper::get_settings();
			$issues  = html_problems( $html );
			if ( substr_count( $html, 'class="ashna-digit-box"' ) !== (int) $s['otp_length'] ) {
				$issues[] = 'digit boxes ' . substr_count( $html, 'class="ashna-digit-box"' ) . ' != ' . $s['otp_length'];
			}
			$expect_pw = (bool) $s['allow_password_login'] || ( 'wp-login' === $context && Ashna_Auth::login_takeover_active() );
			if ( $expect_pw !== ( false !== strpos( $html, 'ashna-step-password' ) ) ) {
				$issues[] = 'password step mismatch';
			}
			if ( (bool) $s['enable_passkey'] !== ( false !== strpos( $html, 'ashna-trigger-passkey-login' ) ) ) {
				$issues[] = 'passkey button mismatch';
			}
			$captcha_markup = array(
				'math'         => 'ashna-captcha-box is-math is-active',
				'arcaptcha'    => 'class="arcaptcha"',
				'turnstile'    => 'class="cf-turnstile"',
				'recaptcha_v3' => '',
				'none'         => '',
			);
			if ( '' !== $captcha_markup[ $s['captcha_type'] ] && false === strpos( $html, $captcha_markup[ $s['captcha_type'] ] ) ) {
				$issues[] = 'captcha markup missing for ' . $s['captcha_type'];
			}
			if ( false === strpos( $html, 'name="ashna_website"' ) ) {
				$issues[] = 'honeypot missing';
			}
			if ( preg_match( '/style="[^"]*not-a-color/', $html ) && false !== strpos( $html, '"not-a-color' ) ) {
				$issues[] = 'unescaped color';
			}
			if ( $issues ) {
				$failures[] = wp_json_encode( $combo ) . ' @' . $context . ': ' . implode( '; ', $issues );
			}
		}
		check( count( $combos ) . ' form variants (8 contexts) render with valid HTML and the right parts', empty( $failures ), array_slice( $failures, 0, 3 ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );

		$shortcodes = array( '[ashna_otp_login]', '[ashna_otp_form title="T&C <b>x</b>" button_text="بفرست" redirect="https://example.test/?a=1&b=2"]', '[ashna_otp_button text="ورود"]', '[ashna_otp_login layout="split_right" position="left"]' );
		foreach ( $shortcodes as $sc ) {
			$html = do_shortcode( $sc );
			$p    = html_problems( $html );
			check( 'shortcode ' . $sc . ' renders cleanly for guests', '' !== trim( $html ) && empty( $p ) && false === strpos( $html, '<b>x</b>' ), $p );
		}
		$u = make_user( 'sc_user', 'subscriber' );
		wp_set_current_user( $u->ID );
		check( 'logged-in: login shortcode shows the account box', false !== strpos( do_shortcode( '[ashna_otp_login]' ), 'ashna-logged-in-box' ) );
		check( 'logged-in: button shortcode links to the account', false !== strpos( do_shortcode( '[ashna_otp_button]' ), 'href=' ) );
		check( 'passkey manager shortcode renders for members', false !== strpos( do_shortcode( '[ashna_passkey_manager]' ), 'ashna-passkey-account-card' ) );
		browser_guest();
		check( 'passkey manager is empty for guests', '' === trim( do_shortcode( '[ashna_passkey_manager]' ) ) );

		ob_start();
		Ashna_Frontend::instance()->render_global_modal();
		$modal = ob_get_clean();
		check( 'global popup renders for guests', false !== strpos( $modal, 'id="ashna-otp-modal"' ) && empty( html_problems( $modal ) ) );
		ashna_t_settings( array( 'enable_global_modal' => 0 ) );
		ob_start();
		Ashna_Frontend::instance()->render_global_modal();
		check( 'global popup can be switched off', '' === trim( ob_get_clean() ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

/* =========================================================================
 * E. OTP flows
 * ======================================================================= */
function verify( $identifier, $code, array $extra = array() ) {
	$res = ajax( 'ashna_verify_otp', array_merge( array( 'identifier' => $identifier, 'code' => (string) $code, 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ), $extra ) );
	track_user_id( data_of( $res, 'user_id' ) );
	return $res;
}

scenario(
	'OTP flows: modes, code formats, attempts, expiry, reuse, resend',
	function () {
		browser_guest();
		ashna_t_settings( array( 'login_mode' => 'phone_only' ) );
		list( $r ) = request_code( 'user@example.com' );
		check( 'phone-only mode refuses e-mails', ! ok_json( $r ) );
		ashna_t_settings( array( 'login_mode' => 'email_only' ) );
		list( $r ) = request_code( random_phone() );
		check( 'e-mail-only mode refuses phones', ! ok_json( $r ) );
		ashna_t_settings( array( 'login_mode' => 'phone_and_email', 'auto_register' => 0 ) );
		list( $r ) = request_code( random_phone() );
		check( 'sign-up off: unknown number refused, no code sent', ! ok_json( $r ) );
		ashna_t_settings( array( 'auto_register' => 1 ) );

		foreach ( array( 4, 6, 8 ) as $len ) {
			ashna_t_settings( array( 'otp_length' => $len ) );
			$phone            = random_phone();
			list( $r, $code ) = request_code( $phone );
			check( "{$len}-digit code generated", ok_json( $r ) && strlen( (string) $code ) === $len && $len === (int) data_of( $r, 'otp_length' ) );
		}
		ashna_t_settings( array( 'otp_length' => 5 ) );

		$phone            = random_phone();
		list( , $code )   = request_code( $phone );
		$persian          = strtr( (string) $code, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		$r                = verify( '+98' . substr( $phone, 1 ), ' ' . $persian . ' ' );
		check( 'Persian digits + spaces in the code, +98 form of the number → accepted', ok_json( $r ), $r['raw'] );
		browser_guest();
		$r = verify( $phone, $code );
		check( 'a used code cannot be reused', ! ok_json( $r ) && 'ashna_already_used' === data_of( $r, 'code' ) );

		$phone          = random_phone();
		list( , $code ) = request_code( $phone );
		$wrong          = (string) ( ( (int) $code + 1 ) % 100000 );
		$codes          = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$codes[] = data_of( verify( $phone, str_pad( $wrong, 5, '1' ) ), 'code' );
		}
		check( '4 wrong codes report remaining tries, the 5th kills the code', array( 'ashna_wrong_code', 'ashna_wrong_code', 'ashna_wrong_code', 'ashna_wrong_code', 'ashna_max_attempts' ) === $codes, $codes );
		$r = verify( $phone, $code );
		check( 'after too many tries even the right code is refused (locked)', ! ok_json( $r ) );
		Ashna_Security::unlock_target( $phone );

		$phone          = random_phone();
		list( , $code ) = request_code( $phone );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Ashna_Logger::table_name() . " SET expires_at = %s WHERE recipient = %s AND status = 'sent'", gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 5 ), $phone ) ); // phpcs:ignore
		$r = verify( $phone, $code );
		check( 'expired code refused', 'ashna_expired_code' === data_of( $r, 'code' ), $r['raw'] );

		$phone           = random_phone();
		list( , $first ) = request_code( $phone );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Ashna_Logger::table_name() . ' SET created_at = %s WHERE recipient = %s', gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 120 ), $phone ) ); // phpcs:ignore
		list( , $second ) = request_code( $phone );
		$r                = verify( $phone, $first );
		check( 'after a resend the old code is dead', ! ok_json( $r ) || $first === $second );
		$r = verify( $phone, $second );
		check( '…and the new one works', ok_json( $r ), $r['raw'] );

		browser_guest();
		$r = verify( random_phone(), '12345' );
		check( 'verify without a requested code → clear error', ! ok_json( $r ) && 'ashna_no_otp' === data_of( $r, 'code' ) );
		$r = verify( 'not-an-id', '12345' );
		check( 'verify with junk identifier → error', ! ok_json( $r ) );
		$r = ajax( 'ashna_request_otp', array( 'identifier' => '', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'empty identifier → error', ! ok_json( $r ) );
	}
);

scenario(
	'Sign-up options: name/e-mail required, duplicate e-mail, username prefix, e-mail accounts',
	function () {
		browser_guest();
		ashna_t_settings( array( 'require_name_on_register' => 'required', 'require_email_on_register' => 'required' ) );
		$phone          = random_phone();
		list( , $code ) = request_code( $phone );
		$r              = verify( $phone, $code );
		check( 'name required → refused before the code is used', 'لطفاً نام و نام خانوادگی خود را وارد کنید.' === data_of( $r, 'message' ) );
		$r = verify( $phone, $code, array( 'full_name' => 'علی محمدی' ) );
		check( 'e-mail required → refused', false !== strpos( (string) data_of( $r, 'message' ), 'ایمیل' ) );
		$taken = make_user( 'emailowner', 'subscriber' );
		$r     = verify( $phone, $code, array( 'full_name' => 'علی محمدی', 'user_email' => $taken->user_email ) );
		$u     = get_user_by( 'id', (int) data_of( $r, 'user_id' ) );
		check( 'someone else\'s e-mail is not attached to the new account', ok_json( $r ) && $u && $u->user_email !== $taken->user_email && 'علی' === $u->first_name && 'محمدی' === $u->last_name, $r['raw'] );

		ashna_t_settings( array( 'require_name_on_register' => 'optional', 'require_email_on_register' => 'disabled', 'username_prefix' => 'shop_' ) );
		browser_guest();
		$phone          = random_phone();
		list( , $code ) = request_code( $phone );
		$r              = verify( $phone, $code );
		$u              = get_user_by( 'id', (int) data_of( $r, 'user_id' ) );
		check( 'username prefix applied', $u && 'shop_' . $phone === $u->user_login, $u ? $u->user_login : $r['raw'] );
		ashna_t_settings( array( 'username_prefix' => '' ) );

		browser_guest();
		$email          = 'new.member' . wp_rand( 1000, 9999 ) . '@example.test';
		list( , $code ) = request_code( $email );
		$r              = verify( strtoupper( $email ), $code );
		$u              = get_user_by( 'id', (int) data_of( $r, 'user_id' ) );
		check( 'e-mail sign-up (any letter case) creates the account', ok_json( $r ) && $u && strtolower( $email ) === $u->user_email, $r['raw'] );
		browser_guest();
		list( , $code ) = request_code( $email );
		$r              = verify( $email, $code );
		check( 'same e-mail later signs into the same account', ok_json( $r ) && (int) data_of( $r, 'user_id' ) === ( $u ? $u->ID : -1 ) );
	}
);

/* =========================================================================
 * F. Gateways: configuration errors and broken provider answers
 * ======================================================================= */
scenario(
	'Every gateway driver: missing settings, HTML error pages, broken JSON, timeouts',
	function () {
		$drivers = array(
			'smsir'       => array( array( 'smsir_api_key' => 'k', 'smsir_template_id' => 123 ), array( 'status' => 1, 'message' => 'ok' ) ),
			'kavenegar'   => array( array( 'kavenegar_api_key' => 'k', 'kavenegar_template' => 't' ), array( 'return' => array( 'status' => 200, 'message' => 'ok' ), 'entries' => array() ) ),
			'melipayamak' => array( array( 'melipayamak_api_key' => 'k', 'melipayamak_body_id' => 5 ), array( 'recId' => 4815, 'status' => 'ok' ) ),
			'farazsms'    => array( array( 'farazsms_api_key' => 'k', 'farazsms_pattern_code' => 'p' ), array( 'status' => 'success', 'data' => array( 'message_id' => 1 ) ) ),
			'ippanel'     => array( array( 'ippanel_api_key' => 'k', 'ippanel_pattern_code' => 'p' ), array( 'meta' => array( 'status' => true ), 'data' => array() ) ),
			'msgway'      => array( array( 'msgway_api_key' => 'k', 'msgway_sms_template_id' => 3 ), array( 'status' => 'success', 'referenceID' => '1' ) ),
		);
		$responses = array(
			'html 502' => http_ok( '<html><body><h1>502 Bad Gateway</h1></body></html>', 502 ),
			'html 200' => http_ok( '<!DOCTYPE html><html>maintenance</html>', 200 ),
			'bad json' => http_ok( '{"status":', 200 ),
			'empty'    => http_ok( '', 200 ),
			'timeout'  => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 5000 milliseconds' ),
		);
		$current = null;
		$filter  = function () use ( &$current ) {
			return $current;
		};
		add_filter( 'pre_http_request', $filter );
		$problems = array();
		foreach ( $drivers as $id => $spec ) {
			list( $config, $ok_body ) = $spec;
			Ashna_Failover::reset_all();
			ashna_t_settings( array_merge( $GLOBALS['ashna_base'], array( 'active_sms_gateway' => $id ), array_fill_keys( array_keys( $config ), '' ) ) );
			$current = http_ok( $ok_body );
			$r       = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'sms', $id );
			if ( ! is_wp_error( $r ) || '' === trim( $r->get_error_message() ) ) {
				$problems[] = "{$id}: missing settings not reported";
			}
			ashna_t_settings( $config );
			$current = http_ok( $ok_body );
			Ashna_Failover::reset_all();
			$r = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'sms', $id );
			if ( is_wp_error( $r ) ) {
				$problems[] = "{$id}: valid answer treated as error: " . $r->get_error_message();
			}
			foreach ( $responses as $label => $resp ) {
				Ashna_Failover::reset_all();
				$current = $resp;
				$r       = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'sms', $id );
				if ( ! is_wp_error( $r ) ) {
					$problems[] = "{$id}: {$label} accepted as success";
				} elseif ( '' === trim( $r->get_error_message() ) ) {
					$problems[] = "{$id}: {$label} without an error message";
				}
			}
		}
		remove_filter( 'pre_http_request', $filter );
		Ashna_Failover::reset_all();
		check( count( $drivers ) . ' SMS drivers × 7 cases: correct result, readable error, no PHP warnings', empty( $problems ), $problems );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

scenario(
	'Messenger channels (Bale Safir v3, Bale bot, Rahpayam) and e-mail',
	function () {
		$current = null;
		$filter  = function () use ( &$current ) {
			return $current;
		};
		add_filter( 'pre_http_request', $filter );

		ashna_t_settings( array( 'bale_mode' => 'safir', 'bale_api_key' => 'k', 'bale_bot_id' => 12345 ) );
		$current = http_ok( array( 'message_id' => 'abc' ) );
		$r       = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'bale' );
		check( 'Bale Safir v3 success', ! is_wp_error( $r ) && 'bale' === $r['channel'], is_wp_error( $r ) ? $r->get_error_message() : $r );
		$current = http_ok( array( 'error_data' => array( array( 'code' => 17, 'description' => 'not a bale user' ) ) ), 400 );
		Ashna_Failover::reset_all();
		$r = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'bale' );
		check( 'Bale "not a Bale user" → readable error', is_wp_error( $r ) && '' !== $r->get_error_message() );
		foreach ( array( http_ok( '<html>oops</html>', 500 ), http_ok( 'null' ) ) as $resp ) {
			$current = $resp;
			Ashna_Failover::reset_all();
			$r = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'bale' );
			check( 'Bale broken answer → error, no warnings', is_wp_error( $r ) );
		}

		ashna_t_settings( array( 'bale_mode' => 'bot', 'bale_bot_token' => '123:abc' ) );
		$phone = random_phone();
		$u     = make_user( 'balebot', 'subscriber', array( 'ashna_phone' => $phone, 'ashna_bale_chat_id' => '998877' ) );
		$current = http_ok( array( 'ok' => true, 'result' => array( 'message_id' => 1 ) ) );
		Ashna_Failover::reset_all();
		$r = Ashna_Gateway_Manager::dispatch_otp( $phone, 'phone', 'bale' );
		check( 'Bale bot mode with a known chat ID', ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
		Ashna_Failover::reset_all();
		$r = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'bale' );
		check( 'Bale bot mode without a chat ID → readable error', is_wp_error( $r ) && '' !== $r->get_error_message() );

		ashna_t_settings( array( 'msgway_api_key' => 'k', 'msgway_messenger_template_id' => 7, 'msgway_messenger_mode' => 'smart' ) );
		$current = http_ok( array( 'status' => 'success', 'referenceID' => '9' ) );
		Ashna_Failover::reset_all();
		$r = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'messenger' );
		check( 'Rahpayam messengers success', ! is_wp_error( $r ) && 'messenger' === $r['channel'], is_wp_error( $r ) ? $r->get_error_message() : $r );
		$current = http_ok( array( 'status' => 'error', 'error' => array( 'code' => 'x', 'message' => 'template not found' ) ), 200 );
		Ashna_Failover::reset_all();
		$r = Ashna_Gateway_Manager::dispatch_otp( random_phone(), 'phone', 'messenger' );
		check( 'Rahpayam error answer → readable error', is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'template' ) );
		remove_filter( 'pre_http_request', $filter );
		Ashna_Failover::reset_all();

		remove_filter( 'pre_wp_mail', '__return_true' );
		$mail  = null;
		$catch = function ( $null, $atts ) use ( &$mail ) {
			$mail = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $catch, 10, 2 );
		ashna_t_settings( array( 'email_subject' => 'کد {code} برای {site_name}', 'email_from_name' => 'فروشگاه', 'email_from_address' => 'noreply@example.test' ) );
		$r = Ashna_Gateway_Manager::dispatch_otp( 'buyer@example.test', 'email' );
		check( 'e-mail code sent', ! is_wp_error( $r ) && 'email' === $r['channel'] );
		check( 'subject placeholders filled', $mail && false !== strpos( $mail['subject'], (string) $r['code'] ) && false !== strpos( $mail['subject'], get_bloginfo( 'name' ) ), $mail ? $mail['subject'] : null );
		$headers = $mail ? implode( "\n", (array) $mail['headers'] ) : '';
		check( 'HTML e-mail with the code and the configured sender', $mail && false !== strpos( $mail['message'], (string) $r['code'] ) && false !== stripos( $headers, 'text/html' ) && false !== strpos( $headers, 'noreply@example.test' ), $headers );
		remove_filter( 'pre_wp_mail', $catch, 10 );
		add_filter( 'pre_wp_mail', '__return_true' );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

/* =========================================================================
 * G. Password, passkey robustness
 * ======================================================================= */
scenario(
	'Special characters survive every input path (quotes, backslashes, ZWNJ, emoji, plus-addresses)',
	function () {
		// Names typed at sign-up.
		browser_guest();
		ashna_t_settings( array( 'require_name_on_register' => 'optional', 'require_email_on_register' => 'optional', 'login_mode' => 'both' ) );
		$phone          = random_phone();
		list( , $code ) = request_code( $phone );
		$first          = "علی\u{200C}رضا";
		$r              = verify( $phone, $code, array( 'full_name' => $first . " O'Brien-\\Smith 🙂", 'user_email' => "o'brien+shop@example.com" ) );
		$u              = get_user_by( 'id', (int) data_of( $r, 'user_id' ) );
		check( 'ZWNJ in a Persian first name is kept', $u && $first === $u->first_name, $u ? $u->first_name : $r['raw'] );
		check( "apostrophe / backslash / emoji in the last name are stored once, unslashed", $u && "O'Brien-\\Smith 🙂" === $u->last_name, $u ? $u->last_name : null );
		check( "an e-mail with ' and + is kept as typed", $u && "o'brien+shop@example.com" === $u->user_email, $u ? $u->user_email : null );

		// E-mail OTP to a plus-address.
		browser_guest();
		list( $r, $code ) = request_code( 'QA+Tag@Example.com' );
		check( 'plus-address e-mail gets a code (case-insensitive)', ok_json( $r ) && '' !== (string) $code, $r['raw'] );

		// Admin texts and custom CSS saved through the settings form.
		as_admin();
		$settings                  = Ashna_Helper::get_settings();
		$settings['form_title']    = "ورود به «فروشگاه» O'Reilly & Co";
		$settings['form_subtitle'] = 'مسیر C:\\temp\\new و "نقل‌قول"';
		$settings['custom_css']    = '.ashna-otp-card::before { content: "\\2014"; } .a > .b { width: calc(100% - 2px); }';
		$r                         = ajax( 'ashna_admin_save_settings', array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ), 'ashna' => $settings ) );
		check( 'settings form saves', ok_json( $r ), $r['raw'] );
		check( 'title with « » \' & stored exactly', $settings['form_title'] === Ashna_Helper::get_option( 'form_title' ), Ashna_Helper::get_option( 'form_title' ) );
		check( 'subtitle keeps its backslashes and quotes', $settings['form_subtitle'] === Ashna_Helper::get_option( 'form_subtitle' ), Ashna_Helper::get_option( 'form_subtitle' ) );
		check( 'custom CSS keeps \\2014, > and %', $settings['custom_css'] === Ashna_Helper::get_option( 'custom_css' ), Ashna_Helper::get_option( 'custom_css' ) );
		browser_guest();
		$html = do_shortcode( '[ashna_otp_form]' );
		check( 'the form prints the title escaped once', false !== strpos( $html, 'O&#039;Reilly &amp; Co' ) && false === strpos( $html, '&amp;amp;' ) && false === strpos( $html, "O\\'Reilly" ), substr( $html, 0, 0 ) );
		check( 'the form markup is still well-formed', array() === html_problems( $html ), html_problems( $html ) );
		$flag = new ReflectionProperty( 'Ashna_Frontend', 'inline_css_added' ); // Once per request; earlier scenarios rendered forms.
		$flag->setAccessible( true );
		$flag->setValue( null, false );
		Ashna_Frontend::instance()->enqueue_assets( true );
		$css = wp_styles()->get_data( 'ashna-otp-frontend', 'after' );
		check( 'custom CSS reaches the page intact', is_array( $css ) && false !== strpos( implode( "\n", $css ), 'content: "\\2014"' ), $css );

		// A password made of quotes and backslashes, set the way WordPress sets it (profile, reset,
		// WooCommerce: the posted value as-is, i.e. slashed).
		ashna_t_settings( array( 'allow_password_login' => 1 ) );
		$pass = "p'a\"s\\s w0rd\\";
		$pu   = make_user( 'quoteqa', 'subscriber' );
		wp_set_password( wp_slash( $pass ), $pu->ID );
		check( 'control: wp-login.php accepts it', ! is_wp_error( wp_authenticate( $pu->user_login, wp_slash( $pass ) ) ) );
		browser_guest();
		$r = ajax( 'ashna_password_login', array( 'identifier' => $pu->user_login, 'password' => $pass, 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'Ashna accepts the same password with \' " \\ and a space', ok_json( $r ) && get_current_user_id() === $pu->ID, $r['raw'] );
		browser_guest();
		$r = ajax( 'ashna_password_login', array( 'identifier' => $pu->user_login, 'password' => stripslashes( $pass ), 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'a near-miss (backslashes dropped) is refused', ! ok_json( $r ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

scenario(
	'Password sign-in: phone, e-mail, username; lockout and unlock',
	function () {
		ashna_t_settings( array( 'allow_password_login' => 1, 'max_verify_attempts' => 3 ) );
		$phone = random_phone();
		$u     = make_user( 'pwqa', 'subscriber', array( 'ashna_phone' => $phone ), 'S3cret-pass!' );
		foreach ( array( $phone, $u->user_email, $u->user_login ) as $id ) {
			browser_guest();
			$r = ajax( 'ashna_password_login', array( 'identifier' => $id, 'password' => 'S3cret-pass!', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
			check( 'password sign-in with ' . ( $id === $phone ? 'phone' : ( false !== strpos( $id, '@' ) ? 'e-mail' : 'username' ) ), ok_json( $r ) && get_current_user_id() === $u->ID, $r['raw'] );
		}
		// Lockouts: the base settings whitelist the test IP, so switch that off here.
		ashna_t_settings( array( 'whitelisted_identifiers' => '' ) );
		$pw = function ( $id, $pass ) {
			return ajax( 'ashna_password_login', array( 'identifier' => $id, 'password' => $pass, 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		};
		browser_guest();
		for ( $i = 0; $i < 3; $i++ ) {
			$pw( $phone, 'nope' );
		}
		$r = $pw( $phone, 'S3cret-pass!' );
		check( 'after 3 wrong passwords the account is locked', ! ok_json( $r ) && 0 === get_current_user_id(), $r['raw'] );
		$rows = Ashna_Security::get_active_lockouts();
		$acct = Ashna_Security::account_lock_key( $u );
		check( 'the lockout list names the phone and the account', isset( $rows[ $phone ], $rows[ $acct ] ), implode( ', ', array_keys( $rows ) ) );
		Ashna_Security::unlock_target( $phone );
		check( 'unlocking only the phone keeps the account locked', ! ok_json( $pw( $phone, 'S3cret-pass!' ) ) );
		Ashna_Security::unlock_target( $acct );
		$r = $pw( $phone, 'S3cret-pass!' );
		check( 'admin unlock lets the user in again', ok_json( $r ), $r['raw'] );

		// Rotating identifiers (phone, e-mail, username in other letter cases) shares one counter.
		browser_guest();
		$pw( $phone, 'nope' );
		$pw( strtoupper( $u->user_email ), 'nope' );
		$pw( $u->user_login, 'nope' );
		$r = $pw( $u->user_email, 'S3cret-pass!' );
		check( 'switching between phone / e-mail / username does not reset the counter', ! ok_json( $r ) && 0 === get_current_user_id(), $r['raw'] );
		foreach ( array_keys( Ashna_Security::get_active_lockouts() ) as $target ) {
			Ashna_Security::unlock_target( $target );
		}
		$r = $pw( $u->user_login, 'S3cret-pass!' );
		check( 'unlocked account signs in with the username', ok_json( $r ), $r['raw'] );

		// One address guessing across many (non-existent) accounts is locked after 4× the limit.
		browser_guest();
		for ( $i = 0; $i < 12; $i++ ) {
			$pw( 'nobody' . $i . '_' . wp_generate_password( 5, false ), 'nope' );
		}
		$r = $pw( $u->user_login, 'S3cret-pass!' );
		check( '12 failures on different accounts from one IP lock that IP', ! ok_json( $r ) && false !== strpos( (string) data_of( $r, 'message', '' ), 'مسدود' ), $r['raw'] );
		foreach ( array_keys( Ashna_Security::get_active_lockouts() ) as $target ) {
			Ashna_Security::unlock_target( $target );
		}
		$r = $pw( $u->user_login, 'S3cret-pass!' );
		check( 'after unlocking the IP the right password works', ok_json( $r ), $r['raw'] );
		ashna_t_settings( array( 'whitelisted_identifiers' => $GLOBALS['ashna_base']['whitelisted_identifiers'] ) );
		ashna_t_settings( array( 'allow_password_login' => 0 ) );
		browser_guest();
		$r = ajax( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'S3cret-pass!', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'password sign-in switched off → refused', ! ok_json( $r ) );

		// wp-login.php takeover with password sign-in off: staff keep their password, customers do not.
		ashna_t_settings( array( 'allow_password_login' => 0, 'wp_login_integration' => 1 ) );
		$boss = make_user( 'qaboss', 'administrator', array(), 'Boss-pass-9!' );
		browser_guest();
		$r = ajax( 'ashna_password_login', array( 'identifier' => $boss->user_login, 'password' => 'Boss-pass-9!', 'context' => 'wp-login', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( 'takeover on: an administrator signs in with the password on wp-login.php (no lock-out)', ok_json( $r ) && get_current_user_id() === $boss->ID, $r['raw'] );
		browser_guest();
		$r = ajax( 'ashna_password_login', array( 'identifier' => $phone, 'password' => 'S3cret-pass!', 'context' => 'wp-login', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( '…a customer still cannot (code-only, generic answer)', ! ok_json( $r ) && 0 === get_current_user_id() && false === strpos( (string) data_of( $r, 'message', '' ), 'غیرفعال' ), $r['raw'] );
		browser_guest();
		$r = ajax( 'ashna_password_login', array( 'identifier' => $boss->user_login, 'password' => 'Boss-pass-9!', 'context' => 'shortcode', 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ) );
		check( '…and other forms keep password sign-in off, even for staff', ! ok_json( $r ) );
		$html = Ashna_Frontend::get_login_form_html( array( 'context' => 'wp-login' ) );
		check( 'wp-login.php form offers "staff sign-in with password"', false !== strpos( $html, 'ورود مدیران سایت با رمز عبور' ) && false !== strpos( $html, 'ashna-step-password' ) && array() === html_problems( $html ), html_problems( $html ) );
		check( 'password fields have linked labels and autocomplete hints', 1 === preg_match( '/<label class="ashna-label" for="(ashna-pw-\d+)-pass">/', $html, $m ) && false !== strpos( $html, 'id="' . $m[1] . '-pass"' ) && false !== strpos( $html, 'autocomplete="current-password"' ) );
		check( 'other forms do not show it', false === strpos( Ashna_Frontend::get_login_form_html( array( 'context' => 'shortcode' ) ), 'ashna-step-password' ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

scenario(
	'Passkey endpoints with hostile or broken input',
	function () {
		browser_guest();
		$n = function () {
			return wp_create_nonce( 'ashna_otp_nonce' );
		};
		$r = ajax( 'ashna_passkey_register_options', array( 'nonce' => $n() ) );
		check( 'guests cannot register a passkey', ! ok_json( $r ) || '' === $r['raw'] );
		$garbage = array(
			array(),
			array( 'credential_id' => 'x', 'client_data_json' => '%%%', 'authenticator_data' => '***', 'signature' => '!!!' ),
			array( 'credential_id' => str_repeat( 'A', 5000 ), 'client_data_json' => base64_encode( '{"type":"webauthn.get","challenge":"zz","origin":"https://evil.test"}' ), 'authenticator_data' => 'AAAA', 'signature' => 'AAAA' ),
			array( 'credential_id' => 'abc', 'client_data_json' => base64_encode( 'not json' ), 'authenticator_data' => base64_encode( str_repeat( "\0", 40 ) ), 'signature' => 'AAAA' ),
		);
		$all_refused = true;
		foreach ( $garbage as $post ) {
			$r = ajax( 'ashna_passkey_login_verify', array_merge( $post, array( 'nonce' => $n() ) ) );
			if ( ok_json( $r ) || 0 !== get_current_user_id() ) {
				$all_refused = false;
			}
		}
		check( 'broken / forged assertions are refused without PHP warnings', $all_refused );
		$r = ajax( 'ashna_passkey_login_options', array( 'nonce' => $n(), 'identifier' => '<script>' ) );
		check( 'login options with junk identifier still answers', ok_json( $r ) );

		$u = make_user( 'pkqa', 'subscriber' );
		wp_set_current_user( $u->ID );
		$r = ajax( 'ashna_passkey_register_verify', array( 'nonce' => $n(), 'credential_id' => 'id', 'client_data_json' => base64_encode( '{"type":"webauthn.create","challenge":"wrong"}' ), 'public_key_spki' => 'AAAA' ) );
		check( 'registration without a pending challenge refused', ! ok_json( $r ) );
		ajax( 'ashna_passkey_register_options', array( 'nonce' => $n() ) );
		$r = ajax( 'ashna_passkey_register_verify', array( 'nonce' => $n(), 'credential_id' => 'id', 'client_data_json' => '%%', 'public_key_spki' => '%%' ) );
		check( 'garbage registration data refused', ! ok_json( $r ) );
		$r = ajax( 'ashna_passkey_delete', array( 'nonce' => $n(), 'credential_id' => 'does-not-exist' ) );
		check( 'deleting a key you do not have → error', ! ok_json( $r ) );
		Ashna_Passkey::save_user_passkey( $u->ID, array( 'id' => 'mine', 'public_key_pem' => 'PEM', 'label' => 'x' ) );
		$other = make_user( 'pkother', 'subscriber' );
		Ashna_Passkey::save_user_passkey( $other->ID, array( 'id' => 'theirs', 'public_key_pem' => 'PEM', 'label' => 'y' ) );
		$r = ajax( 'ashna_passkey_delete', array( 'nonce' => $n(), 'credential_id' => 'theirs' ) );
		check( 'nobody can delete another user\'s passkey', ! ok_json( $r ) && isset( Ashna_Passkey::get_user_passkeys( $other->ID )['theirs'] ) );
		$r = ajax( 'ashna_passkey_delete', array( 'nonce' => $n(), 'credential_id' => 'mine' ) );
		check( 'deleting your own passkey works', ok_json( $r ) && empty( Ashna_Passkey::get_user_passkeys( $u->ID ) ) );
		browser_guest();
	}
);

/* =========================================================================
 * H. Admin AJAX permissions
 * ======================================================================= */
scenario(
	'Admin AJAX: only administrators with a valid nonce',
	function () {
		$endpoints = array(
			'ashna_admin_save_settings'        => array( 'ashna' => Ashna_Helper::get_settings() ),
			'ashna_admin_test_gateway'         => array( 'recipient' => '09120000000', 'channel' => 'sms', 'gateway_id' => 'sandbox' ),
			'ashna_admin_unlock_target'        => array( 'target' => '09120000000' ),
			'ashna_admin_import_settings'      => array( 'json_data' => wp_json_encode( Ashna_Helper::get_settings() ) ),
			'ashna_admin_reset_settings'       => array(),
			'ashna_admin_reset_gateway_health' => array(),
			'ashna_admin_end_attack_mode'      => array(),
			'ashna_admin_trust_proxy'          => array( 'ip' => '203.0.113.250' ),
			'ashna_admin_fetch_arvan_ranges'   => array(),
		);
		$sub        = make_user( 'qa_sub', 'subscriber' );
		$editor     = make_user( 'qa_editor', 'editor' );
		$leaks      = array();
		$before     = get_option( 'ashna_otp_settings' );
		foreach ( array( $sub, $editor ) as $who ) {
			wp_set_current_user( $who->ID );
			foreach ( $endpoints as $action => $post ) {
				$r = ajax( $action, array_merge( $post, array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ) ) ) );
				if ( ok_json( $r ) ) {
					$leaks[] = $who->roles[0] . ': ' . $action;
				}
			}
		}
		check( 'subscribers and editors are refused on all 9 endpoints', empty( $leaks ), $leaks );
		check( 'settings untouched by refused calls', get_option( 'ashna_otp_settings' ) == $before ); // phpcs:ignore Universal.Operators.StrictComparisons

		as_admin();
		$no_nonce = array();
		foreach ( $endpoints as $action => $post ) {
			$r = ajax( $action, $post );
			if ( ok_json( $r ) ) {
				$no_nonce[] = $action;
			}
		}
		check( 'administrators without a nonce are refused (CSRF)', empty( $no_nonce ), $no_nonce );

		$arvan = function () {
			return http_ok( "185.143.232.0/22\n94.101.182.0/27\nnot-a-range\n2a0c:e740::/32\n" );
		};
		add_filter( 'pre_http_request', $arvan );
		$r = ajax( 'ashna_admin_fetch_arvan_ranges', array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ) ) );
		remove_filter( 'pre_http_request', $arvan );
		check( 'ArvanCloud ranges fetched and filtered', ok_json( $r ) && array( '185.143.232.0/22', '94.101.182.0/27', '2a0c:e740::/32' ) === data_of( $r, 'ranges' ), $r['raw'] );
		$r = ajax( 'ashna_admin_test_gateway', array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ), 'recipient' => '09120000000', 'channel' => 'sms', 'gateway_id' => 'sandbox' ) );
		check( 'admin test send works', ok_json( $r ), $r['raw'] );
		$r = ajax( 'ashna_admin_trust_proxy', array( 'nonce' => wp_create_nonce( 'ashna_admin_nonce' ), 'ip' => 'evil<script>' ) );
		check( 'trusting an invalid IP is refused', ! ok_json( $r ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
		browser_guest();
	}
);

/* =========================================================================
 * I. Cron, WooCommerce hooks, users screen, Elementor
 * ======================================================================= */
scenario(
	'Scheduled clean-up and health probe',
	function () {
		global $wpdb;
		ashna_t_settings( array( 'log_retention_days' => 7 ) );
		$old = Ashna_Logger::insert( array( 'recipient' => '09129990000', 'status' => 'expired' ) );
		$new = Ashna_Logger::insert( array( 'recipient' => '09129990001', 'status' => 'expired' ) );
		$wpdb->update( Ashna_Logger::table_name(), array( 'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 9 * DAY_IN_SECONDS ) ), array( 'id' => $old ) );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no'), (%s, %s, 'no')", 'ashna_otp_cap_expiredmarker', (string) ( time() - 10 ), 'ashna_otp_cap_freshmarker', (string) ( time() + 3600 ) ) ); // phpcs:ignore
		do_action( 'ashna_otp_daily_cleanup' );
		$ids = $wpdb->get_col( 'SELECT id FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore
		check( 'logs older than the retention period are deleted, newer kept', ! in_array( (string) $old, $ids, true ) && in_array( (string) $new, $ids, true ) );
		$markers = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ashna\\_otp\\_cap\\_%'" ); // phpcs:ignore
		check( 'expired captcha markers purged, fresh ones kept', ! in_array( 'ashna_otp_cap_expiredmarker', $markers, true ) && in_array( 'ashna_otp_cap_freshmarker', $markers, true ) );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'ashna_otp_cap_freshmarker'" ); // phpcs:ignore

		ashna_t_settings( array( 'active_sms_gateway' => 'kavenegar', 'kavenegar_api_key' => 'k', 'kavenegar_template' => 't', 'gateway_probe' => 1 ) );
		$head = function () {
			return new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' );
		};
		add_filter( 'pre_http_request', $head );
		do_action( 'ashna_otp_gateway_probe' );
		remove_filter( 'pre_http_request', $head );
		check( 'probe marks an unreachable gateway', 'open' === Ashna_Failover::state( 'kavenegar' ) );
		Ashna_Failover::reset_all();
		check( 'cron events registered', (bool) wp_next_scheduled( 'ashna_otp_daily_cleanup' ) && (bool) wp_next_scheduled( 'ashna_otp_gateway_probe' ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

scenario(
	'WooCommerce hooks, users screen, profile fields',
	function () {
		ashna_fixture_woocommerce();
		browser_guest();
		$tpl = apply_filters( 'wc_get_template', '/orig/form-login.php', 'myaccount/form-login.php', array(), '', '' );
		check( 'My Account login replaced for guests', false !== strpos( $tpl, 'templates/wc-myaccount-login.php' ) );
		ob_start();
		$_GET = array( 'redirect_to' => home_url( '/cart/' ) );
		include ASHNA_OTP_PATH . 'templates/wc-myaccount-login.php';
		$html = ob_get_clean();
		$_GET = array();
		check( 'My Account form keeps an explicit ?redirect_to', false !== strpos( $html, 'data-redirect="' . esc_url( home_url( '/cart/' ) ) . '"' ) && empty( html_problems( $html ) ) );
		ob_start();
		include ASHNA_OTP_PATH . 'templates/wc-myaccount-login.php';
		$html = ob_get_clean();
		check( '…and sets none otherwise (Dokan can still send vendors to their dashboard)', false !== strpos( $html, 'data-redirect=""' ) );
		ob_start();
		do_action( 'woocommerce_before_checkout_form' );
		$box = ob_get_clean();
		check( 'checkout quick-login box for guests', false !== strpos( $box, 'ashna-wc-checkout-banner' ) && empty( html_problems( $box ) ) );
		$shown = new ReflectionProperty( 'Ashna_WooCommerce', 'checkout_box_shown' );
		$shown->setAccessible( true );
		$block = apply_filters( 'render_block_woocommerce/checkout', '<div class="wp-block-woocommerce-checkout"></div>', array() );
		check( 'box printed once even if the page has both checkouts', 0 === substr_count( $block, 'ashna-wc-checkout-banner' ) );
		$shown->setValue( null, false );
		$block = apply_filters( 'render_block_woocommerce/checkout', '<div class="wp-block-woocommerce-checkout"></div>', array() );
		check( 'block checkout (WooCommerce 8.3+ default) gets the box above the block', 0 === strpos( $block, '<div class="ashna-wc-checkout-banner"' ) && false !== strpos( $block, '<div class="wp-block-woocommerce-checkout"></div>' ) && empty( html_problems( $block ) ) );
		check( 'the toggle announces its state to screen readers', false !== strpos( $block, 'aria-expanded="false"' ) );
		$shown->setValue( null, false );
		ashna_t_settings( array( 'wc_checkout_otp_box' => 0 ) );
		check( 'box switched off → block untouched', '<div class="wp-block-woocommerce-checkout"></div>' === apply_filters( 'render_block_woocommerce/checkout', '<div class="wp-block-woocommerce-checkout"></div>', array() ) );
		ashna_t_settings( array( 'wc_checkout_otp_box' => 1 ) );

		require_once __DIR__ . '/fixtures/wc-features-stub.php';
		do_action( 'before_woocommerce_init' );
		$declared = \Automattic\WooCommerce\Utilities\FeaturesUtil::$declared;
		check( 'declares HPOS (custom order tables) compatibility', isset( $declared['custom_order_tables'] ) && true === $declared['custom_order_tables'][0] && ASHNA_OTP_FILE === $declared['custom_order_tables'][1], $declared );
		check( 'declares cart/checkout blocks compatibility', isset( $declared['cart_checkout_blocks'] ) && true === $declared['cart_checkout_blocks'][0] );

		$phone = random_phone();
		$u     = make_user( 'wcbuyer', 'subscriber', array( 'ashna_phone' => $phone ) );
		wp_set_current_user( $u->ID );
		check( 'My Account template untouched for members', '/orig/form-login.php' === apply_filters( 'wc_get_template', '/orig/form-login.php', 'myaccount/form-login.php', array(), '', '' ) );
		ob_start();
		do_action( 'woocommerce_before_checkout_form' );
		check( 'no checkout box for members', '' === trim( ob_get_clean() ) );
		check( 'checkout billing phone prefilled from the verified phone', $phone === apply_filters( 'woocommerce_checkout_get_value', '', 'billing_phone' ) );

		as_admin();
		$cols = apply_filters( 'manage_users_columns', array() );
		check( 'users list gets the mobile column', isset( $cols['ashna_phone'] ) );
		check( 'mobile column shows the phone escaped', false !== strpos( apply_filters( 'manage_users_custom_column', '', 'ashna_phone', $u->ID ), $phone ) );
		ob_start();
		do_action( 'edit_user_profile', $u );
		$profile = ob_get_clean();
		check( 'profile screen fields render', false !== strpos( $profile, 'name="ashna_phone"' ) && empty( html_problems( $profile ) ) );
		browser_guest();
	}
);

scenario(
	'Elementor widgets (minimal Elementor stand-in)',
	function () {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			require __DIR__ . '/fixtures/elementor-stub.php';
		}
		require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/class-ashna-elementor-login-widget.php';
		require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php';
		foreach ( array( 'Ashna_Elementor_Login_Widget', 'Ashna_Elementor_Modal_Button_Widget' ) as $class ) {
			$w = new $class();
			check( "{$class}: name/title/icon/categories", '' !== $w->get_name() && '' !== $w->get_title() && is_array( $w->get_categories() ) );
			$w->qa_register_controls();
			check( "{$class}: controls registered", count( $w->qa_controls ) > 3, count( $w->qa_controls ) );
			browser_guest();
			ob_start();
			$w->qa_render();
			$html = ob_get_clean();
			check( "{$class}: renders for guests with valid HTML", '' !== trim( $html ) && empty( html_problems( $html ) ), html_problems( $html ) );
			$u = make_user( 'el_' . substr( md5( $class ), 0, 4 ), 'subscriber' );
			wp_set_current_user( $u->ID );
			ob_start();
			$w->qa_render();
			check( "{$class}: renders for members", '' !== trim( ob_get_clean() ) );
			browser_guest();
		}
	}
);

/* =========================================================================
 * J. Lifecycle: deactivate / activate / upgrade / uninstall (last)
 * ======================================================================= */
scenario(
	'Time zones: site in Tehran / New York, another plugin changed PHP\'s default timezone',
	function () {
		$combos = array(
			array( 'Asia/Tehran', '', 'Asia/Tehran' ),          // Common in Iran: a date plugin calls date_default_timezone_set().
			array( 'America/New_York', '', 'Pacific/Auckland' ), // Negative site offset, PHP far ahead.
			array( '', '3.5', 'Asia/Tehran' ),                  // Manual "UTC+3:30" offset instead of a city.
		);
		// Earlier scenarios sent dozens of codes this minute; start from an empty log so the
		// site-wide burst detector (attack mode) measures only this scenario.
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore
		Ashna_Rate_Limiter::end_attack_mode();
		ashna_t_settings( array( 'whitelisted_identifiers' => '', 'max_ip_requests_per_minute' => 3, 'resend_cooldown' => 60 ) );
		foreach ( $combos as $i => $combo ) {
			list( $site_tz, $offset, $php_tz ) = $combo;
			ashna_t_option( 'timezone_string', $site_tz );
			ashna_t_option( 'gmt_offset', '' === $offset ? 0 : $offset );
			date_default_timezone_set( $php_tz ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
			$label = ( $site_tz ? $site_tz : 'UTC+' . $offset ) . ' / PHP ' . $php_tz;
			$ip    = '198.51.100.' . ( 20 + $i );
			$srv   = array( 'REMOTE_ADDR' => $ip );

			check( $label . ': local clock matches current_time()', abs( Ashna_Helper::local_now() - current_time( 'timestamp' ) ) <= 1 && gmdate( 'Y-m-d H:i', Ashna_Helper::local_now() ) === current_time( 'Y-m-d H:i' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			// Sends from this address two hours ago must not count toward the per-minute limit.
			for ( $k = 0; $k < 4; $k++ ) {
				Ashna_Logger::insert( array( 'recipient' => random_phone(), 'ip_address' => $ip, 'status' => 'sent', 'created_at' => gmdate( 'Y-m-d H:i:s', Ashna_Helper::local_now() - 2 * HOUR_IN_SECONDS ) ) );
			}
			browser_guest();
			$phone          = random_phone();
			list( $r, $code ) = request_code( $phone, array(), $srv );
			check( $label . ': old sends do not count toward the per-minute limit', ok_json( $r ), $r['raw'] );
			$r = ajax( 'ashna_verify_otp', array( 'identifier' => $phone, 'code' => (string) $code, 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ), $srv );
			track_user_id( data_of( $r, 'user_id' ) );
			check( $label . ': a fresh code is not "expired"', ok_json( $r ), $r['raw'] );

			browser_guest();
			list( $r ) = request_code( $phone, array(), $srv );
			$wait      = (int) data_of( $r, 'remaining', 0 );
			check( $label . ': resend cooldown counts seconds, not hours', ! ok_json( $r ) && $wait > 0 && $wait <= 60, array( $wait, $r['raw'] ) );
		}
		date_default_timezone_set( 'UTC' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set

		// The clock goes back an hour (DST ends): rows written "later" than now are from before the change.
		$wpdb->query( 'DELETE FROM ' . Ashna_Logger::table_name() ); // phpcs:ignore
		Ashna_Rate_Limiter::end_attack_mode();
		ashna_t_settings( array( 'attack_mode_threshold' => 3 ) );
		ashna_t_option( 'timezone_string', 'Europe/Berlin' );
		$ip    = '192.0.2.77';
		$phone = random_phone();
		$later = gmdate( 'Y-m-d H:i:s', Ashna_Helper::local_now() + 40 * MINUTE_IN_SECONDS );
		for ( $k = 0; $k < 4; $k++ ) {
			Ashna_Logger::insert( array( 'recipient' => 0 === $k ? $phone : random_phone(), 'ip_address' => $ip, 'status' => 'sent', 'created_at' => $later, 'expires_at' => gmdate( 'Y-m-d H:i:s', Ashna_Helper::local_now() + 42 * MINUTE_IN_SECONDS ) ) );
		}
		$stale = Ashna_Logger::get_latest_record( $phone );
		check( 'after the clock goes back: a code issued "later" is treated as expired', is_wp_error( Ashna_Security::verify_otp( $phone, '00000' ) ) && $stale );
		browser_guest();
		list( $r, $code ) = request_code( $phone, array(), array( 'REMOTE_ADDR' => $ip ) );
		check( '…no 60-minute resend wait and no per-minute block from the pre-change rows', ok_json( $r ), $r['raw'] );
		$r = ajax( 'ashna_verify_otp', array( 'identifier' => $phone, 'code' => (string) $code, 'nonce' => wp_create_nonce( 'ashna_otp_nonce' ) ), array( 'REMOTE_ADDR' => $ip ) );
		track_user_id( data_of( $r, 'user_id' ) );
		check( '…and the new code works', ok_json( $r ), $r['raw'] );
		Ashna_Rate_Limiter::maybe_enter_attack_mode();
		check( 'pre-change rows do not switch on attack mode', ! Ashna_Rate_Limiter::attack_mode_active() );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

scenario(
	'Data limits (MySQL column sizes) and several forms on one page',
	function () {
		$long = str_repeat( 'a', 180 ) . '@example.com'; // 192 characters: valid per is_email(), longer than the column.
		check( 'an e-mail longer than the 191-character column is refused up front', 'invalid' === Ashna_Helper::parse_identifier( $long )['type'] );
		check( '…a 191-character one is accepted', 'email' === Ashna_Helper::parse_identifier( str_repeat( 'a', 179 ) . '@example.com' )['type'] );

		$id = Ashna_Logger::reserve( '09120000000', str_repeat( '1', 80 ), str_repeat( '2', 80 ) );
		check( 'reservation with over-long IP strings still stored', $id > 0 );
		$ok  = Ashna_Logger::complete( $id, array( 'channel' => 'sms', 'gateway' => str_repeat( 'g', 90 ), 'status' => 'failed', 'response_message' => str_repeat( 'خطای درگاه ', 2000 ) ) );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Ashna_Logger::table_name() . ' WHERE id = %d', $id ) ); // phpcs:ignore
		check( 'a huge gateway error is trimmed to fit (MySQL would refuse the whole UPDATE)', $ok && $row && mb_strlen( $row->response_message ) <= 2000 && strlen( $row->gateway ) <= 50 && strlen( $row->ip_address ) <= 64, $row ? array( mb_strlen( $row->response_message ), strlen( $row->gateway ) ) : null );
		check( 'trimming never splits a Persian character', $row && false !== mb_check_encoding( $row->response_message, 'UTF-8' ) );
		Ashna_Logger::delete( $id );

		$before = get_option( 'ashna_active_lockouts', array() );
		for ( $i = 0; $i < 620; $i++ ) {
			Ashna_Security::trigger_ip_lockout( '198.18.' . intdiv( $i, 250 ) . '.' . ( $i % 250 ) );
		}
		$list = get_option( 'ashna_active_lockouts', array() );
		check( 'the lockout list stays bounded during a distributed attack (≤ 500 entries)', is_array( $list ) && count( $list ) <= 500 && isset( $list['198.18.2.119'] ), is_array( $list ) ? count( $list ) : $list );
		check( '…while the newest lock still blocks', is_wp_error( Ashna_Security::check_lockout( 'x', '198.18.2.119' ) ) );
		foreach ( array_keys( $list ) as $target ) {
			Ashna_Security::unlock_target( $target );
		}
		update_option( 'ashna_active_lockouts', $before, false );

		// Shortcode twice + modal button + the global modal on the same page.
		browser_guest();
		ashna_t_settings( array( 'enable_global_modal' => 1 ) );
		ob_start();
		echo do_shortcode( '[ashna_otp_form][ashna_otp_form title="دوم"][ashna_otp_button]' ); // phpcs:ignore
		Ashna_Frontend::instance()->render_global_modal();
		$page = ob_get_clean();
		check( 'two forms + modal on one page: no duplicate IDs, well-formed', array() === html_problems( $page ), html_problems( $page ) );
		check( 'each form is a separate instance', substr_count( $page, 'class="ashna-otp-wrapper' ) + substr_count( $page, 'ashna-otp-wrapper ' ) >= 2 || substr_count( $page, 'data-context=' ) >= 2 );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

scenario(
	'Lifecycle: deactivation, activation, upgrade from the previous schema, uninstall',
	function () {
		global $wpdb;
		$table = Ashna_Logger::table_name();
		Ashna_Activator::deactivate();
		check( 'deactivation removes the cron events', ! wp_next_scheduled( 'ashna_otp_daily_cleanup' ) && ! wp_next_scheduled( 'ashna_otp_gateway_probe' ) );
		Ashna_Activator::activate();
		check( 'activation schedules them again', (bool) wp_next_scheduled( 'ashna_otp_daily_cleanup' ) && (bool) wp_next_scheduled( 'ashna_otp_gateway_probe' ) );

		// Previous schema (2.3.0): no ip_subnet column.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
		$wpdb->query( "CREATE TABLE {$table} ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, recipient varchar(191) NOT NULL, channel varchar(32) NOT NULL DEFAULT 'sms', gateway varchar(50) NOT NULL DEFAULT 'sandbox', otp_code varchar(20) NOT NULL DEFAULT '', otp_hash varchar(255) NOT NULL DEFAULT '', status varchar(32) NOT NULL DEFAULT 'sent', attempts smallint(5) unsigned NOT NULL DEFAULT 0, ip_address varchar(64) NOT NULL DEFAULT '', response_message text NULL, expires_at datetime NOT NULL, verified_at datetime NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY recipient_idx (recipient) )" ); // phpcs:ignore
		$wpdb->insert( $table, array( 'recipient' => '09120000777', 'status' => 'expired', 'expires_at' => current_time( 'mysql' ), 'created_at' => current_time( 'mysql' ) ) );
		update_option( 'ashna_otp_db_version', '2.3.0' );
		Ashna_Activator::maybe_upgrade();
		$id  = Ashna_Logger::reserve( '09120000778', '198.51.100.4', '198.51.100.0/24' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
		check( 'upgrade adds ip_subnet and keeps old rows', $row && '198.51.100.0/24' === $row->ip_subnet && '1' === (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE recipient = '09120000777'" ) ); // phpcs:ignore
		check( 'DB version recorded', Ashna_Activator::DB_VERSION === get_option( 'ashna_otp_db_version' ) );

		$settings_before = get_option( 'ashna_otp_settings' );
		update_option( 'ashna_otp_settings', array_merge( (array) $settings_before, array( 'delete_data_on_uninstall' => 0 ) ) );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ashna-otp/index.php' );
		}
		include ASHNA_OTP_PATH . 'uninstall.php';
		check( 'uninstall keeps data by default', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) && false !== get_option( 'ashna_otp_settings' ) );
		update_option( 'ashna_otp_settings', array_merge( (array) $settings_before, array( 'delete_data_on_uninstall' => 1 ) ) );
		update_option( 'ashna_otp_attack_mode', array( 'until' => time() + 60 ) );
		include ASHNA_OTP_PATH . 'uninstall.php';
		$left = array(
			'table'    => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ),
			'settings' => false !== get_option( 'ashna_otp_settings' ),
			'attack'   => false !== get_option( 'ashna_otp_attack_mode' ),
		);
		check( '"delete data" uninstall removes the table and options', null === $left['table'] && ! $left['settings'] && ! $left['attack'], $left );
		check( 'uninstall removes the cron events', ! wp_next_scheduled( 'ashna_otp_daily_cleanup' ) && ! wp_next_scheduled( 'ashna_otp_gateway_probe' ) );

		update_option( 'ashna_otp_settings', $settings_before );
		Ashna_Activator::activate();
		check( 're-activation rebuilds everything', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) && (bool) wp_next_scheduled( 'ashna_otp_daily_cleanup' ) );
	}
);

ashna_fixture_remove_roles();
scenario(
	'Recovery switch: ASHNA_DISABLE_LOGIN_TAKEOVER brings the classic wp-login.php back (runs last)',
	function () {
		ashna_t_settings( array( 'allow_password_login' => 0, 'wp_login_integration' => 1 ) );
		check( 'takeover active before the constant', Ashna_Auth::login_takeover_active() );
		define( 'ASHNA_DISABLE_LOGIN_TAKEOVER', true );
		check( 'constant switches the takeover off', ! Ashna_Auth::login_takeover_active() );
		check( 'the staff-password shortcut disappears with it (the classic form is back)', false === strpos( Ashna_Frontend::get_login_form_html( array( 'context' => 'wp-login' ) ), 'ashna-step-password' ) );
		ashna_t_settings( $GLOBALS['ashna_base'] );
	}
);

ashna_t_finish( 'scenarios' );
