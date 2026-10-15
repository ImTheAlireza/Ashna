<?php
/**
 * WooCommerce & WordPress User Profile Integration
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_WooCommerce {

	/**
	 * Singleton instance
	 *
	 * @var Ashna_WooCommerce|null
	 */
	private static $instance = null;

	/**
	 * The checkout sign-in box was printed in this request (classic hook or checkout block).
	 *
	 * @var bool
	 */
	private static $checkout_box_shown = false;

	/**
	 * Get instance
	 *
	 * @return Ashna_WooCommerce
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
		add_filter( 'wc_get_template', array( $this, 'override_myaccount_login_template' ), 99, 2 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_otp_box' ), 5 );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'prepend_checkout_otp_box' ), 10, 1 ); // Block checkout (default since WooCommerce 8.3).
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_checkout_billing_phone' ), 10, 2 );

		add_filter( 'manage_users_columns', array( $this, 'add_user_phone_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_user_phone_column' ), 10, 3 );
		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
	}

	/**
	 * Replace WooCommerce My Account login form with Ashna form if enabled
	 *
	 * @param string $located       Located template path.
	 * @param string $template_name Template name.
	 * @return string
	 */
	public function override_myaccount_login_template( $located, $template_name ) {
		if ( 'myaccount/form-login.php' === $template_name && ! is_user_logged_in() ) {
			if ( Ashna_Helper::get_option( 'wc_replace_myaccount', 1 ) ) {
				$custom_file = ASHNA_OTP_PATH . 'templates/wc-myaccount-login.php';
				if ( file_exists( $custom_file ) ) {
					return $custom_file;
				}
			}
		}
		return $located;
	}

	/**
	 * Render OTP quick login box on the classic (shortcode) WooCommerce Checkout for guests.
	 */
	public function render_checkout_otp_box() {
		echo $this->checkout_otp_box_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts below.
	}

	/**
	 * Same box above the block-based Checkout (rendered outside the block's React root).
	 *
	 * @param string $block_content Rendered checkout block.
	 * @return string
	 */
	public function prepend_checkout_otp_box( $block_content ) {
		if ( is_admin() || wp_is_json_request() ) {
			return $block_content;
		}
		return $this->checkout_otp_box_html() . $block_content;
	}

	/**
	 * Checkout sign-in box markup (empty for signed-in visitors, when disabled, or when already shown).
	 *
	 * @return string
	 */
	private function checkout_otp_box_html() {
		if ( self::$checkout_box_shown || is_user_logged_in() || ! Ashna_Helper::get_option( 'wc_checkout_otp_box', 1 ) ) {
			return '';
		}
		self::$checkout_box_shown = true;

		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
		$form_html    = Ashna_Frontend::get_login_form_html(
			array(
				'redirect' => $checkout_url,
				'context'  => 'wc-checkout',
			)
		);

		return '<div class="ashna-wc-checkout-banner" dir="rtl">'
			. '<div class="ashna-wc-checkout-notice">'
			. '<span>' . esc_html( 'برای ثبت سریع‌تر سفارش، می‌توانید با کد یکبارمصرف (بدون رمز عبور) وارد شوید:' ) . '</span>'
			. '<button type="button" class="ashna-btn ashna-btn-sm ashna-toggle-checkout-otp" aria-expanded="false">' . esc_html( 'ورود سریع با کد تایید (OTP)' ) . '</button>'
			. '</div>'
			. '<div class="ashna-wc-checkout-form-holder" style="display:none;margin-top:16px;">' . $form_html . '</div>' // Escaped inside templates/login-form.php.
			. '</div>';
	}

	/**
	 * Pre-fill billing_phone in WooCommerce checkout from ashna_phone
	 *
	 * @param mixed  $value Field value.
	 * @param string $input Field name.
	 * @return mixed
	 */
	public function prefill_checkout_billing_phone( $value, $input ) {
		if ( 'billing_phone' === $input && empty( $value ) && is_user_logged_in() ) {
			$phone = get_user_meta( get_current_user_id(), 'ashna_phone', true );
			if ( ! empty( $phone ) ) {
				return $phone;
			}
		}
		return $value;
	}

	/**
	 * Add mobile column to WP Users table
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_user_phone_column( $columns ) {
		$columns['ashna_phone'] = 'شماره موبایل (OTP)';
		return $columns;
	}

	/**
	 * Render mobile column in WP Users table
	 *
	 * @param string $output      Custom column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_user_phone_column( $output, $column_name, $user_id ) {
		if ( 'ashna_phone' === $column_name ) {
			$phone = get_user_meta( $user_id, 'ashna_phone', true );
			if ( empty( $phone ) ) {
				$phone = get_user_meta( $user_id, 'billing_phone', true );
			}
			if ( empty( $phone ) ) {
				$phone = get_user_meta( $user_id, 'digits_phone', true );
			}
			return ! empty( $phone ) ? '<code dir="ltr">' . esc_html( $phone ) . '</code>' : '—';
		}
		return $output;
	}

	/**
	 * Render Ashna phone & Bale chat ID fields on user profile screen
	 *
	 * @param WP_User $user User object.
	 */
	public function render_user_profile_fields( $user ) {
		$phone = get_user_meta( $user->ID, 'ashna_phone', true );
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user->ID, 'billing_phone', true );
		}
		if ( empty( $phone ) ) {
			$phone = get_user_meta( $user->ID, 'digits_phone', true );
		}
		$bale_chat_id = get_user_meta( $user->ID, 'ashna_bale_chat_id', true );
		$can_edit     = self::can_edit_verified_phone( $user->ID );
		?>
		<h2>اطلاعات ورود یکبارمصرف (آشنا)</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ashna_phone">شماره موبایل تایید شده</label></th>
				<td>
					<input type="text" name="ashna_phone" id="ashna_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" dir="ltr" placeholder="09123456789" <?php disabled( ! $can_edit ); ?> />
					<?php if ( $can_edit ) : ?>
						<p class="description">شماره موبایل کاربر برای ورود با کد یکبارمصرف (فرمت استاندارد: 09123456789). حساب‌های مدیریتی فقط با شماره‌ای که اینجا ثبت شده با کد وارد می‌شوند.</p>
					<?php else : ?>
						<p class="description">این شماره با ورود موفق با کد تایید شده است و فقط مدیر سایت می‌تواند آن را تغییر دهد.</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="ashna_bale_chat_id">شناسه چت بله (Bale Chat ID)</label></th>
				<td>
					<input type="text" name="ashna_bale_chat_id" id="ashna_bale_chat_id" value="<?php echo esc_attr( $bale_chat_id ); ?>" class="regular-text" dir="ltr" />
					<p class="description">اختیاری — در صورت استفاده از حالت «ربات بله (Bot API)» برای ارسال پیام مستقیم.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * The verified phone decides which account an OTP opens, so users may not change their
	 * own value (that would let anyone claim another person's number); site admins can.
	 *
	 * @param int $user_id Profile being edited.
	 * @return bool
	 */
	private static function can_edit_verified_phone( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}
		return get_current_user_id() !== (int) $user_id || current_user_can( 'manage_options' );
	}

	/**
	 * Save Ashna phone & Bale chat ID from user profile screen
	 *
	 * @param int $user_id User ID.
	 */
	public function save_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['ashna_phone'] ) && self::can_edit_verified_phone( $user_id ) ) {
			$raw_phone  = sanitize_text_field( wp_unslash( $_POST['ashna_phone'] ) );
			$normalized = ! empty( $raw_phone ) ? Ashna_Helper::normalize_phone( $raw_phone ) : '';
			if ( ! empty( $normalized ) ) {
				Ashna_Auth::sync_user_phone_meta( $user_id, $normalized );
			} else {
				update_user_meta( $user_id, 'ashna_phone', '' );
			}
		}

		if ( isset( $_POST['ashna_bale_chat_id'] ) ) {
			$chat_id = sanitize_text_field( wp_unslash( $_POST['ashna_bale_chat_id'] ) );
			update_user_meta( $user_id, 'ashna_bale_chat_id', $chat_id );
		}
		// phpcs:enable
	}
}
