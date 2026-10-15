<?php
/**
 * Frontend Shortcodes, Assets, Global Modal & Full wp-login.php Replacement
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Frontend {

	/**
	 * Singleton instance
	 *
	 * @var Ashna_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Track if inline custom CSS was already attached
	 *
	 * @var bool
	 */
	private static $inline_css_added = false;

	/**
	 * Get instance
	 *
	 * @return Ashna_Frontend
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_shortcode( 'ashna_otp_login', array( $this, 'shortcode_login_form' ) );
		add_shortcode( 'ashna_otp_form', array( $this, 'shortcode_login_form' ) );
		add_shortcode( 'ashna_otp_button', array( $this, 'shortcode_modal_button' ) );

		add_filter( 'wp_resource_hints', array( $this, 'add_resource_hints' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_global_modal' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_standalone_page' ), 1 );

		if ( Ashna_Helper::get_option( 'wp_login_integration', 0 ) ) {
			add_action( 'login_init', array( $this, 'intercept_wp_login_page' ), 1 );
		}
	}

	/**
	 * Add preconnect hint for jsDelivr font CDN to reduce latency
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for.
	 * @return array
	 */
	public function add_resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href'        => 'https://cdn.jsdelivr.net',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	}

	/**
	 * Enqueue frontend CSS and JS with smart conditional loading
	 *
	 * @param bool $force Force enqueueing guest-only scripts (e.g. inside Elementor editor).
	 */
	public function enqueue_assets( $force = false ) {
		wp_enqueue_style(
			'ashna-vazirmatn-font',
			'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
			array(),
			'33.003'
		);

		wp_enqueue_style(
			'ashna-fonts',
			ASHNA_OTP_URL . 'assets/css/fonts.css',
			array( 'ashna-vazirmatn-font' ),
			ASHNA_OTP_VERSION
		);

		$css_ver = ASHNA_OTP_VERSION . '.' . ( file_exists( ASHNA_OTP_PATH . 'assets/css/frontend.css' ) ? filemtime( ASHNA_OTP_PATH . 'assets/css/frontend.css' ) : '1' );
		$js_ver  = ASHNA_OTP_VERSION . '.' . ( file_exists( ASHNA_OTP_PATH . 'assets/js/frontend.js' ) ? filemtime( ASHNA_OTP_PATH . 'assets/js/frontend.js' ) : '1' );

		wp_enqueue_style(
			'ashna-otp-frontend',
			ASHNA_OTP_URL . 'assets/css/frontend.css',
			array( 'ashna-vazirmatn-font', 'ashna-fonts' ),
			$css_ver
		);

		if ( ! self::$inline_css_added ) {
			$custom_css = trim( (string) Ashna_Helper::get_option( 'custom_css', '' ) );
			if ( ! empty( $custom_css ) ) {
				wp_add_inline_style( 'ashna-otp-frontend', wp_strip_all_tags( $custom_css ) );
			}
			self::$inline_css_added = true;
		}

		// Performance Optimization: Skip heavy 3rd-party Captcha SDKs & OTP JS for already logged-in users unless forced
		if ( is_user_logged_in() && ! $force ) {
			return;
		}

		$captcha_type = Ashna_Helper::get_option( 'captcha_type', 'none' );
		$site_key     = trim( (string) Ashna_Helper::get_option( 'captcha_site_key', '' ) );

		if ( ! is_user_logged_in() ) {
			if ( 'arcaptcha' === $captcha_type && ! empty( $site_key ) ) {
				wp_enqueue_script( 'ashna-arcaptcha', 'https://widget.arcaptcha.ir/1/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			} elseif ( 'turnstile' === $captcha_type && ! empty( $site_key ) ) {
				wp_enqueue_script( 'ashna-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			} elseif ( 'recaptcha_v3' === $captcha_type && ! empty( $site_key ) ) {
				wp_enqueue_script( 'ashna-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}
		}

		wp_enqueue_script(
			'ashna-otp-frontend',
			ASHNA_OTP_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'ashna-otp-frontend',
			'ashnaOtpParams',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ashna_otp_nonce' ),
				'otpLength'      => absint( Ashna_Helper::get_option( 'otp_length', 5 ) ),
				'captchaType'    => $captcha_type,
				'captchaSiteKey' => $site_key,
				'i18n'           => array(
					'networkError' => 'خطا در برقراری ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.',
					'sending'      => 'در حال ارسال...',
					'verifying'    => 'در حال بررسی...',
				),
			)
		);
	}

	/**
	 * Render dedicated Standalone Full-Page Canvas when visiting the configured standalone_page_id
	 */
	public function maybe_render_standalone_page() {
		$standalone_page_id = absint( Ashna_Helper::get_option( 'standalone_page_id', 0 ) );
		if ( $standalone_page_id <= 0 || ! is_page( $standalone_page_id ) ) {
			return;
		}

		// Allow Elementor editor or page builders to edit if needed
		if ( isset( $_GET['elementor-preview'] ) || is_customize_preview() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( is_user_logged_in() ) {
			$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
			wp_safe_redirect( $account_url );
			exit;
		}

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->render_standalone_canvas( $redirect_to, 'standalone-page' );
	}

	/**
	 * Completely replace default wp-login.php form when wp_login_integration is active
	 */
	public function intercept_wp_login_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		$passthrough_actions = array( 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'postpass', 'confirmaction', 'confirm_admin_email' );
		if ( in_array( $action, $passthrough_actions, true ) || isset( $_REQUEST['interim-login'] ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url();
			wp_safe_redirect( $redirect_to );
			exit;
		}

		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		// phpcs:enable

		$this->render_standalone_canvas( $redirect_to, 'wp-login' );
	}

	/**
	 * Output Full-Page Standalone Canvas HTML without theme header/footer
	 *
	 * @param string $redirect_to Redirect URL after login.
	 * @param string $context     Context identifier.
	 */
	private function render_standalone_canvas( $redirect_to = '', $context = 'wp-login' ) {
		$this->enqueue_assets( true );

		$primary_color   = Ashna_Helper::get_option( 'primary_color', '#2563eb' );
		$secondary_color = Ashna_Helper::get_option( 'secondary_color', '#4f46e5' );
		$card_position   = Ashna_Helper::get_option( 'card_position', 'center' );
		$bg_style        = Ashna_Helper::get_option( 'canvas_bg_style', 'mesh_light' );
		$bg_color        = Ashna_Helper::get_option( 'canvas_bg_color', '#f1f5f9' );
		$bg_image        = trim( (string) Ashna_Helper::get_option( 'canvas_bg_image', '' ) );
		$bg_pattern      = Ashna_Helper::get_option( 'bg_pattern', 'none' );
		$show_back_link  = (bool) Ashna_Helper::get_option( 'canvas_show_back_link', 1 );

		if ( 'mesh_dark' === $bg_style ) {
			$base_bg        = 'radial-gradient(circle at top right, #1e1b4b 0%, #0f172a 58%, #020617 100%)';
			$back_link_dark = true;
		} elseif ( 'brand_gradient' === $bg_style ) {
			$base_bg        = sprintf( 'radial-gradient(circle at top right, %1$s28 0%%, #f8fafc 58%%, %2$s1a 100%%)', esc_attr( $primary_color ), esc_attr( $secondary_color ) );
			$back_link_dark = false;
		} elseif ( 'custom_image' === $bg_style && ! empty( $bg_image ) ) {
			$base_bg        = sprintf( 'linear-gradient(rgba(15, 23, 42, 0.45), rgba(15, 23, 42, 0.45)), url(%s) center/cover no-repeat fixed', esc_url( $bg_image ) );
			$back_link_dark = true;
		} elseif ( 'solid' === $bg_style ) {
			$base_bg        = esc_attr( $bg_color );
			$back_link_dark = false;
		} else {
			$base_bg        = sprintf( 'radial-gradient(circle at top right, #e0e7ff 0%%, #f8fafc 55%%, %s 100%%)', esc_attr( $bg_color ) );
			$back_link_dark = false;
		}

		$pattern_layer = '';
		$pattern_size  = '';
		if ( 'dots' === $bg_pattern ) {
			$pattern_layer = 'radial-gradient(rgba(99, 102, 241, 0.22) 1.3px, transparent 1.3px), ';
			$pattern_size  = 'background-size: 20px 20px, auto;';
		} elseif ( 'grid' === $bg_pattern ) {
			$pattern_layer = 'linear-gradient(to right, rgba(148, 163, 184, 0.16) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.16) 1px, transparent 1px), ';
			$pattern_size  = 'background-size: 26px 26px, 26px 26px, auto;';
		} elseif ( 'waves' === $bg_pattern ) {
			$pattern_layer = 'repeating-radial-gradient(circle at 0 0, transparent 0, rgba(99, 102, 241, 0.065) 14px, transparent 28px), ';
		} elseif ( 'geometric' === $bg_pattern ) {
			$pattern_layer = 'linear-gradient(30deg, rgba(99, 102, 241, 0.075) 12%, transparent 12.5%, transparent 87%, rgba(99, 102, 241, 0.075) 87.5%), ';
			$pattern_size  = 'background-size: 32px 56px, auto;';
		}

		$canvas_bg_css = sprintf( 'background: %s%s; %s', $pattern_layer, $base_bg, $pattern_size );

		$align_items = 'center';
		if ( 'right' === $card_position ) {
			$align_items = 'flex-start';
		} elseif ( 'left' === $card_position ) {
			$align_items = 'flex-end';
		}

		status_header( 200 );
		nocache_headers();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?> dir="rtl">
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( get_bloginfo( 'name' ) . ' — ورود / ثبت‌نام' ); ?></title>
			<?php wp_print_styles( array( 'ashna-vazirmatn-font', 'ashna-fonts', 'ashna-otp-frontend' ) ); ?>
			<style>
				body.ashna-standalone-login {
					margin: 0;
					padding: 32px 5vw;
					min-height: 100vh;
					display: flex;
					flex-direction: column;
					align-items: <?php echo esc_attr( $align_items ); ?>;
					justify-content: center;
					<?php echo $canvas_bg_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					font-family: 'Vazirmatn', Tahoma, sans-serif;
					direction: rtl;
					box-sizing: border-box;
				}
				.ashna-standalone-login .ashna-otp-wrapper {
					width: 100%;
					margin: 0;
				}
				.ashna-back-to-site {
					margin-top: 22px;
					text-align: center;
				}
				.ashna-back-to-site a {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 8px 16px;
					border-radius: 99px;
					background: <?php echo $back_link_dark ? 'rgba(255,255,255,0.12)' : 'rgba(255,255,255,0.75)'; ?>;
					backdrop-filter: blur(8px);
					color: <?php echo $back_link_dark ? '#f8fafc' : '#475569'; ?>;
					text-decoration: none;
					font-size: 13px;
					font-weight: 600;
					border: 1px solid <?php echo $back_link_dark ? 'rgba(255,255,255,0.18)' : 'rgba(148,163,184,0.28)'; ?>;
					transition: all 0.2s ease;
				}
				.ashna-back-to-site a:hover {
					transform: translateY(-1px);
					background: <?php echo $back_link_dark ? 'rgba(255,255,255,0.2)' : '#ffffff'; ?>;
				}
			</style>
		</head>
		<body class="ashna-standalone-login">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::get_login_form_html(
				array(
					'redirect' => $redirect_to,
					'context'  => $context,
				)
			);
			?>
			<?php if ( $show_back_link ) : ?>
				<div class="ashna-back-to-site">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">&rarr; بازگشت به <?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
				</div>
			<?php endif; ?>
			<?php wp_print_scripts( array( 'jquery', 'ashna-arcaptcha', 'ashna-turnstile', 'ashna-recaptcha', 'ashna-otp-frontend' ) ); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Render login form template (can be overridden in theme/ashna/login-form.php)
	 *
	 * @param array $args Template arguments.
	 * @return string
	 */
	public static function get_login_form_html( $args = array() ) {
		$defaults = array(
			'title'              => Ashna_Helper::get_option( 'form_title', 'ورود / ثبت‌نام' ),
			'subtitle'           => Ashna_Helper::get_option( 'form_subtitle', 'برای ادامه، شماره موبایل یا ایمیل خود را وارد کنید.' ),
			'button_text'        => '',
			'verify_button_text' => '',
			'redirect'           => '',
			'primary_color'      => '',
			'card_bg_color'      => '',
			'text_color'         => '',
			'digit_box_style'    => '',
			'border_radius'      => null,
			'max_width'          => null,
			'form_layout'        => '',
			'card_position'      => '',
			'context'            => 'shortcode',
		);
		$args     = wp_parse_args( $args, $defaults );

		ob_start();
		$theme_template = locate_template( 'ashna/login-form.php' );
		if ( $theme_template ) {
			include $theme_template;
		} else {
			include ASHNA_OTP_PATH . 'templates/login-form.php';
		}
		return ob_get_clean();
	}

	/**
	 * Shortcode: [ashna_otp_login]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_login_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'          => '',
				'subtitle'       => '',
				'button_text'    => '',
				'redirect'       => '',
				'primary_color'  => '',
				'layout'         => '',
				'position'       => '',
				'show_if_logged' => 'no',
			),
			$atts,
			'ashna_otp_login'
		);

		if ( is_user_logged_in() && 'yes' !== $atts['show_if_logged'] ) {
			$user         = wp_get_current_user();
			$account_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : admin_url( 'profile.php' );
			$logout_link  = wp_logout_url( home_url( '/' ) );

			return sprintf(
				'<div class="ashna-otp-wrapper" dir="rtl"><div class="ashna-otp-card ashna-logged-in-box"><p>سلام <strong>%s</strong>، شما وارد حساب کاربری خود شده‌اید.</p><div class="ashna-logged-actions"><a href="%s" class="ashna-btn ashna-btn-primary">حساب کاربری</a><a href="%s" class="ashna-btn ashna-btn-outline">خروج</a></div></div></div>',
				esc_html( $user->display_name ),
				esc_url( $account_link ),
				esc_url( $logout_link )
			);
		}

		$this->enqueue_assets( true );

		return self::get_login_form_html(
			array(
				'title'         => $atts['title'],
				'subtitle'      => $atts['subtitle'],
				'button_text'   => $atts['button_text'],
				'redirect'      => $atts['redirect'],
				'primary_color' => $atts['primary_color'],
				'form_layout'   => $atts['layout'],
				'card_position' => $atts['position'],
				'context'       => 'shortcode',
			)
		);
	}

	/**
	 * Shortcode: [ashna_otp_button text="ورود / ثبت‌نام"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_modal_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'text'           => 'ورود / ثبت‌نام',
				'logged_in_text' => 'حساب کاربری',
				'class'          => '',
			),
			$atts,
			'ashna_otp_button'
		);

		$primary_color = Ashna_Helper::get_option( 'primary_color', '#2563eb' );

		if ( is_user_logged_in() ) {
			$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : admin_url( 'profile.php' );
			return sprintf(
				'<a href="%s" class="ashna-trigger-btn %s" style="--ashna-primary:%s;">%s</a>',
				esc_url( $account_url ),
				esc_attr( $atts['class'] ),
				esc_attr( $primary_color ),
				esc_html( $atts['logged_in_text'] )
			);
		}

		$this->enqueue_assets( true );

		return sprintf(
			'<button type="button" class="ashna-trigger-btn ashna-open-modal %s" style="--ashna-primary:%s;">%s</button>',
			esc_attr( $atts['class'] ),
			esc_attr( $primary_color ),
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Render global modal popup / slide-over drawer in footer for guests
	 */
	public function render_global_modal() {
		if ( is_user_logged_in() || ! Ashna_Helper::get_option( 'enable_global_modal', 1 ) ) {
			return;
		}

		$modal_style  = Ashna_Helper::get_option( 'modal_style', 'center' );
		$mobile_sheet = (bool) Ashna_Helper::get_option( 'modal_mobile_sheet', 1 );
		$modal_cls    = 'ashna-modal-overlay ashna-modal-style-' . sanitize_html_class( $modal_style );
		if ( $mobile_sheet ) {
			$modal_cls .= ' ashna-modal-mobile-sheet';
		}
		?>
		<div id="ashna-otp-modal" class="<?php echo esc_attr( $modal_cls ); ?>" aria-hidden="true" style="display:none;">
			<div class="ashna-modal-dialog" role="dialog" aria-modal="true">
				<div class="ashna-modal-sheet-handle" aria-hidden="true"></div>
				<button type="button" class="ashna-modal-close" aria-label="بستن">&times;</button>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo self::get_login_form_html( array( 'context' => 'modal' ) );
				?>
			</div>
		</div>
		<?php
	}
}
