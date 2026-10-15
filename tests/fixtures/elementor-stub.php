<?php
/**
 * Minimal Elementor stand-in for smoke-testing Ashna's widgets (tests/scenarios.php).
 *
 * Only the API surface the widgets use: Controls_Manager constants, Widget_Base section /
 * control registration and get_settings_for_display() (control defaults). Elementor itself
 * is not installed in the sandbox; this proves the widget code runs, not the editor UI.
 */

// phpcs:ignoreFile

namespace Elementor;

class Controls_Manager {
	const TAB_CONTENT = 'content';
	const TAB_STYLE   = 'style';
	const TEXT        = 'text';
	const TEXTAREA    = 'textarea';
	const SELECT      = 'select';
	const COLOR       = 'color';
	const SLIDER      = 'slider';
	const SWITCHER    = 'switcher';
	const URL         = 'url';
	const CHOOSE      = 'choose';
}

class Plugin {
	public static $instance = null;
}

abstract class Widget_Base {
	public $qa_controls = array();
	public $qa_sections = array();

	public function __construct( $data = array(), $args = null ) {}

	public function start_controls_section( $id, $args = array() ) {
		$this->qa_sections[] = $id;
	}

	public function end_controls_section() {}

	public function add_control( $id, $args = array() ) {
		$this->qa_controls[ $id ] = $args;
	}

	public function get_settings_for_display( $key = null ) {
		$settings = array();
		foreach ( $this->qa_controls as $id => $args ) {
			$settings[ $id ] = isset( $args['default'] ) ? $args['default'] : '';
		}
		return null === $key ? $settings : ( isset( $settings[ $key ] ) ? $settings[ $key ] : null );
	}

	public function qa_register_controls() {
		$this->register_controls();
	}

	public function qa_render() {
		$this->render();
	}

	abstract protected function register_controls();

	abstract protected function render();
}
