<?php
/**
 * Elementor Page Builder Native Integration
 *
 * Registers the "Ashna" Elementor category and native widgets:
 * 1. Ashna_Elementor_Login_Widget (Inline Login / Registration Card)
 * 2. Ashna_Elementor_Modal_Button_Widget (Header / Popup Trigger Button)
 *
 * @package Ashna
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ashna_Elementor {

	/**
	 * Singleton instance
	 *
	 * @var Ashna_Elementor|null
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Ashna_Elementor
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
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );

		// Elementor 3.5.0+ hook with fallback for older Elementor versions
		if ( version_compare( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.5.0', '3.5.0', '>=' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		} else {
			add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets_legacy' ) );
		}

		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_assets' ) );
	}

	/**
	 * Register dedicated "Ashna" category in Elementor panel
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_category( $elements_manager ) {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}

		$elements_manager->add_category(
			'ashna-otp',
			array(
				'title' => 'احراز هویت آشنا',
				'icon'  => 'eicon-lock-user',
			)
		);
	}

	/**
	 * Register widgets in Elementor 3.5+
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/class-ashna-elementor-login-widget.php';
		require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php';

		$widgets_manager->register( new Ashna_Elementor_Login_Widget() );
		$widgets_manager->register( new Ashna_Elementor_Modal_Button_Widget() );
	}

	/**
	 * Register widgets in legacy Elementor (< 3.5)
	 */
	public function register_widgets_legacy() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/class-ashna-elementor-login-widget.php';
		require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php';

		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Ashna_Elementor_Login_Widget() );
		\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Ashna_Elementor_Modal_Button_Widget() );
	}

	/**
	 * Ensure Ashna frontend CSS is always loaded inside Elementor live preview iframe
	 */
	public function enqueue_preview_assets() {
		Ashna_Frontend::instance()->enqueue_assets( true );
	}

	/**
	 * Helper: Check if we are currently inside Elementor's editor or preview mode
	 *
	 * @return bool
	 */
	public static function is_editor_or_preview() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance;
		if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return true;
		}
		if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
			return true;
		}

		return false;
	}
}
