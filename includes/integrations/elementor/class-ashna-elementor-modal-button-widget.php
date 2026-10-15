<?php
/**
 * Elementor Widget: Ashna Modal Trigger Button (Ideal for Headers & Menus)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Elementor_Modal_Button_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget unique name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ashna_otp_modal_button_widget';
	}

	/**
	 * Widget title in Elementor panel
	 *
	 * @return string
	 */
	public function get_title() {
		return 'دکمه پاپ‌آپ ورود سریع (آشنا)';
	}

	/**
	 * Widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-button';
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
	 * Search keywords
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'otp', 'button', 'modal', 'popup', 'ashna', 'آشنا', 'دکمه', 'ورود', 'پاپ آپ', 'هدر' );
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_button_content',
			array(
				'label' => 'تنظیمات دکمه ورود / حساب کاربری',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'guest_text',
			array(
				'label'       => 'متن دکمه (برای مهمانان)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'ورود / ثبت‌نام',
				'label_block' => true,
			)
		);

		$this->add_control(
			'logged_in_text',
			array(
				'label'       => 'متن دکمه (برای کاربران وارد شده)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'حساب کاربری',
				'label_block' => true,
			)
		);

		$this->add_control(
			'show_user_name',
			array(
				'label'        => 'نمایش نام کاربر پس از ورود',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => 'no',
			)
		);

		$this->add_control(
			'show_icon',
			array(
				'label'        => 'نمایش آیکون کاربر کنار دکمه',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'align',
			array(
				'label'   => 'چیدمان دکمه',
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'right'   => array(
						'title' => 'راست',
						'icon'  => 'eicon-text-align-right',
					),
					'center'  => array(
						'title' => 'وسط',
						'icon'  => 'eicon-text-align-center',
					),
					'left'    => array(
						'title' => 'چپ',
						'icon'  => 'eicon-text-align-left',
					),
					'stretch' => array(
						'title' => 'تمام‌عرض',
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default' => 'right',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_style',
			array(
				'label' => 'استایل ظاهری دکمه',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'btn_bg_color',
			array(
				'label'   => 'رنگ پس‌زمینه دکمه',
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => Ashna_Helper::get_option( 'primary_color', '#2563eb' ),
			)
		);

		$this->add_control(
			'btn_text_color',
			array(
				'label'   => 'رنگ متن دکمه',
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
			)
		);

		$this->add_control(
			'btn_border_radius',
			array(
				'label'      => 'گردی گوشه‌های دکمه (px)',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 10,
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render button widget
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		Ashna_Frontend::instance()->enqueue_assets( true );

		$bg_color    = ! empty( $settings['btn_bg_color'] ) ? $settings['btn_bg_color'] : Ashna_Helper::get_option( 'primary_color', '#2563eb' );
		$text_color  = ! empty( $settings['btn_text_color'] ) ? $settings['btn_text_color'] : '#ffffff';
		$radius      = isset( $settings['btn_border_radius']['size'] ) ? absint( $settings['btn_border_radius']['size'] ) : 10;
		$align       = ! empty( $settings['align'] ) ? $settings['align'] : 'right';
		$show_icon   = 'yes' === ( $settings['show_icon'] ?? 'yes' );
		$icon_markup = $show_icon
			? '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
			: '';

		$wrap_style = 'text-align:' . esc_attr( 'stretch' === $align ? 'center' : $align ) . ';';
		$btn_style  = sprintf(
			'--ashna-primary:%s;background-color:%s;color:%s !important;border-radius:%dpx;%s',
			esc_attr( $bg_color ),
			esc_attr( $bg_color ),
			esc_attr( $text_color ),
			$radius,
			'stretch' === $align ? 'width:100%;' : ''
		);

		echo '<div class="ashna-elementor-btn-wrap" dir="rtl" style="' . esc_attr( $wrap_style ) . '">';

		if ( is_user_logged_in() && ! Ashna_Elementor::is_editor_or_preview() ) {
			$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : admin_url( 'profile.php' );
			$label       = ! empty( $settings['logged_in_text'] ) ? $settings['logged_in_text'] : 'حساب کاربری';
			if ( 'yes' === ( $settings['show_user_name'] ?? 'no' ) ) {
				$user  = wp_get_current_user();
				$label = $user->display_name;
			}
			echo sprintf(
				'<a href="%s" class="ashna-trigger-btn" style="%s">%s<span>%s</span></a>',
				esc_url( $account_url ),
				esc_attr( $btn_style ),
				$icon_markup, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $label )
			);
		} else {
			$guest_label = ! empty( $settings['guest_text'] ) ? $settings['guest_text'] : 'ورود / ثبت‌نام';
			echo sprintf(
				'<button type="button" class="ashna-trigger-btn ashna-open-modal" style="%s">%s<span>%s</span></button>',
				esc_attr( $btn_style ),
				$icon_markup, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $guest_label )
			);
		}

		echo '</div>';
	}
}
