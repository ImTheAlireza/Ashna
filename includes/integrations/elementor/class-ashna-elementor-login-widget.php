<?php
/**
 * Elementor Widget: Ashna Login & Registration Card
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Elementor_Login_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget unique name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ashna_otp_login_widget';
	}

	/**
	 * Widget title in Elementor panel
	 *
	 * @return string
	 */
	public function get_title() {
		return 'فرم ورود و ثبت‌نام یکبارمصرف (آشنا)';
	}

	/**
	 * Widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-lock-user';
	}

	/**
	 * Widget categories
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'ashna-otp', 'general' );
	}

	/**
	 * Search keywords in Elementor panel
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'otp', 'login', 'sms', 'ashna', 'ورود', 'ثبت نام', 'پیامک', 'آشنا', 'موبایل' );
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		// 1. Content Section
		$this->start_controls_section(
			'section_content',
			array(
				'label' => 'محتوا و تنظیمات فرم',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'form_title',
			array(
				'label'       => 'عنوان فرم',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => Ashna_Helper::get_option( 'form_title', 'ورود / ثبت‌نام' ),
				'placeholder' => 'ورود / ثبت‌نام',
				'label_block' => true,
			)
		);

		$this->add_control(
			'form_subtitle',
			array(
				'label'       => 'زیرعنوان فرم',
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => Ashna_Helper::get_option( 'form_subtitle', 'برای ادامه، شماره موبایل یا ایمیل خود را وارد کنید.' ),
				'rows'        => 2,
				'label_block' => true,
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => 'متن دکمه مرحله اول',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => Ashna_Helper::get_option( 'button_text', 'دریافت کد تایید' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'verify_button_text',
			array(
				'label'       => 'متن دکمه تایید کد',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => Ashna_Helper::get_option( 'verify_button_text', 'تایید و ورود به حساب' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'redirect_url',
			array(
				'label'       => 'لینک ریدایرکت سفارشی پس از ورود (اختیاری)',
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://example.com/my-account',
				'options'     => false,
				'label_block' => true,
			)
		);

		$this->add_control(
			'show_in_editor',
			array(
				'label'        => 'نمایش فرم در محیط ویرایشگر (هنگام لاگین بودن)',
				'description'  => 'فعال باشد تا هنگام طراحی در المنتور، به جای باکس «شما وارد شده‌اید»، خود فرم ورود را ببینید.',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		// 2. Style Section
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => 'طراحی و رنگ‌بندی کارت فرم',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'primary_color',
			array(
				'label'   => 'رنگ اصلی برند و دکمه‌ها',
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => Ashna_Helper::get_option( 'primary_color', '#2563eb' ),
			)
		);

		$this->add_control(
			'button_bg_mode',
			array(
				'label'   => 'حالت رنگ دکمه اصلی',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''         => 'پیش‌فرض تنظیمات افزونه',
					'solid'    => 'تک‌رنگ کلاسیک (Solid)',
					'gradient' => 'گرادینت دو رنگ (Gradient)',
				),
			)
		);

		$this->add_control(
			'secondary_color',
			array(
				'label'   => 'رنگ دوم گرادینت دکمه',
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => Ashna_Helper::get_option( 'secondary_color', '#4f46e5' ),
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'   => 'رنگ پس‌زمینه کارت فرم',
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => Ashna_Helper::get_option( 'card_bg_color', '#ffffff' ),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'   => 'رنگ متون فرم',
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => Ashna_Helper::get_option( 'text_color', '#111827' ),
			)
		);

		$this->add_control(
			'card_shadow',
			array(
				'label'   => 'عمق سایه کارت (Elevation)',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''       => 'پیش‌فرض تنظیمات افزونه',
					'none'   => 'بدون سایه (Flat)',
					'soft'   => 'سایه ملایم (Soft)',
					'medium' => 'سایه استاندارد (Medium)',
					'deep'   => 'سایه عمیق سه‌بعدی (Deep)',
					'glow'   => 'هاله نوری همرنگ برند (Glow)',
				),
			)
		);

		$this->add_control(
			'card_border_style',
			array(
				'label'   => 'استایل کادر دور کارت',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''           => 'پیش‌فرض تنظیمات افزونه',
					'subtle'     => 'کادر ظریف استاندارد (1px)',
					'none'       => 'بدون کادر (Borderless)',
					'top_accent' => 'نوار رنگی بالای کارت (Top Bar)',
					'glow'       => 'کادر درخشان برند (Glowing)',
				),
			)
		);

		$this->add_control(
			'form_layout',
			array(
				'label'   => 'چیدمان و اسکلت فرم',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''            => 'پیش‌فرض تنظیمات افزونه',
					'card'        => 'کارت تکی کلاسیک',
					'split_right' => 'دوتایی (فرم راست + بنر چپ)',
					'split_left'  => 'دوتایی (فرم چپ + بنر راست)',
				),
			)
		);

		$this->add_control(
			'card_position',
			array(
				'label'   => 'موقعیت افقی کارت در صفحه',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''       => 'پیش‌فرض تنظیمات افزونه',
					'center' => 'وسط‌چین (مرکز)',
					'right'  => 'شناور در سمت راست',
					'left'   => 'شناور در سمت چپ',
				),
			)
		);

		$this->add_control(
			'font_family',
			array(
				'label'   => 'خانواده فونت فرم',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''              => 'پیش‌فرض تنظیمات افزونه',
					'vazirmatn'     => 'وزیرمتن (Vazirmatn)',
					'iransans'      => 'ایران‌سنس (IRANSans)',
					'yekanbakh'     => 'یکان‌بخ / ایران‌یکان',
					'dana'          => 'دانا / انجمن (Dana)',
					'estedad'       => 'استعداد / شبنم (Estedad)',
					'theme_inherit' => 'ارث‌بری از فونت قالب (Inherit)',
				),
			)
		);

		$this->add_control(
			'input_style',
			array(
				'label'   => 'استایل فیلدهای ورودی',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''           => 'پیش‌فرض تنظیمات افزونه',
					'filled'     => 'کادر توپر مدرن (Filled)',
					'outlined'   => 'کادر خطی شفاف (Outlined)',
					'underlined' => 'خط زیرین مینیمال (Underlined)',
					'soft_pill'  => 'کپسولی گرد (Soft Pill)',
				),
			)
		);

		$this->add_control(
			'input_addon_style',
			array(
				'label'   => 'آیکون یا پرچم کنار فیلد موبایل',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''        => 'پیش‌فرض تنظیمات افزونه',
					'icon'    => 'آیکون هوشمند موبایل / ایمیل',
					'ir_flag' => 'پرچم ایران 🇮🇷 و پیش‌شماره (+98)',
					'none'    => 'ساده و بدون آیکون',
				),
			)
		);

		$this->add_control(
			'digit_box_style',
			array(
				'label'   => 'استایل باکس‌های کد تایید',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => Ashna_Helper::get_option( 'digit_box_style', 'box' ),
				'options' => array(
					'box'            => 'مربعی مدرن (Box)',
					'underline'      => 'خط زیرین مینیمال (Underline)',
					'pill'           => 'کپسولی گرد (Pill)',
					'separated_glow' => 'نئونی درخشان (Glow Box)',
					'connected'      => 'پیوسته یکپارچه (Connected)',
				),
			)
		);

		$this->add_control(
			'timer_style',
			array(
				'label'   => 'استایل تایمر شمارش معکوس',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''              => 'پیش‌فرض تنظیمات افزونه',
					'progress_bar'  => 'نوار پیشرفت افقی (Progress Bar)',
					'circular_ring' => 'حلقه گرافیکی دایره‌ای (Circular Ring)',
					'minimal_badge' => 'بج کپسولی مینیمال (Minimal Badge)',
					'simple_text'   => 'متن ساده کلاسیک (Simple Text)',
				),
			)
		);

		$this->add_control(
			'form_animation',
			array(
				'label'   => 'انیمیشن ورود فرم',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''            => 'پیش‌فرض تنظیمات افزونه',
					'fade_up'     => 'ظهور نرم از پایین (Fade Up)',
					'zoom_spring' => 'بزرگ‌نمایی فنری (Spring Scale)',
					'slide_rtl'   => 'حرکت کشویی افقی (Slide)',
					'none'        => 'بدون انیمیشن',
				),
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => 'گردی گوشه‌های کارت (px)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 32,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => absint( Ashna_Helper::get_option( 'border_radius', 16 ) ),
				),
			)
		);

		$this->add_control(
			'form_max_width',
			array(
				'label'      => 'حداکثر عرض کارت (px)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 320,
						'max' => 640,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => absint( Ashna_Helper::get_option( 'form_max_width', 420 ) ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend and Elementor editor
	 */
	protected function render() {
		$settings    = $this->get_settings_for_display();
		$is_editor   = Ashna_Elementor::is_editor_or_preview();
		$show_logged = ( 'yes' === ( $settings['show_in_editor'] ?? 'yes' ) && $is_editor );

		Ashna_Frontend::instance()->enqueue_assets( true );

		if ( is_user_logged_in() && ! $show_logged ) {
			$user         = wp_get_current_user();
			$account_link = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : admin_url( 'profile.php' );
			$logout_link  = wp_logout_url( home_url( '/' ) );

			echo sprintf(
				'<div class="ashna-otp-wrapper" dir="rtl"><div class="ashna-otp-card ashna-logged-in-box"><p>سلام <strong>%s</strong>، شما وارد حساب کاربری خود شده‌اید.</p><div class="ashna-logged-actions"><a href="%s" class="ashna-btn ashna-btn-primary">حساب کاربری</a><a href="%s" class="ashna-btn ashna-btn-outline">خروج</a></div></div></div>',
				esc_html( $user->display_name ),
				esc_url( $account_link ),
				esc_url( $logout_link )
			);
			return;
		}

		$redirect_url = ! empty( $settings['redirect_url']['url'] ) ? $settings['redirect_url']['url'] : '';
		$radius       = isset( $settings['border_radius']['size'] ) ? absint( $settings['border_radius']['size'] ) : null;
		$max_width    = isset( $settings['form_max_width']['size'] ) ? absint( $settings['form_max_width']['size'] ) : null;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Ashna_Frontend::get_login_form_html(
			array(
				'title'              => $settings['form_title'] ?? '',
				'subtitle'           => $settings['form_subtitle'] ?? '',
				'button_text'        => $settings['button_text'] ?? '',
				'verify_button_text' => $settings['verify_button_text'] ?? '',
				'redirect'           => $redirect_url,
				'primary_color'      => $settings['primary_color'] ?? '',
				'secondary_color'    => $settings['secondary_color'] ?? '',
				'button_bg_mode'     => $settings['button_bg_mode'] ?? '',
				'card_bg_color'      => $settings['card_bg_color'] ?? '',
				'text_color'         => $settings['text_color'] ?? '',
				'digit_box_style'    => $settings['digit_box_style'] ?? '',
				'border_radius'      => $radius,
				'max_width'          => $max_width,
				'form_layout'        => $settings['form_layout'] ?? '',
				'card_position'      => $settings['card_position'] ?? '',
				'card_shadow'        => $settings['card_shadow'] ?? '',
				'card_border_style'  => $settings['card_border_style'] ?? '',
				'font_family'        => $settings['font_family'] ?? '',
				'input_style'        => $settings['input_style'] ?? '',
				'input_addon_style'  => $settings['input_addon_style'] ?? '',
				'timer_style'        => $settings['timer_style'] ?? '',
				'form_animation'     => $settings['form_animation'] ?? '',
				'context'            => 'elementor',
			)
		);
	}
}
