<?php
/** Saved Elementor widget IDs: render existing pages without rewriting postmeta. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Ashna_Elementor_Legacy_Login_Widget extends Ashna_Elementor_Login_Widget {
	public function get_name() { return 'signa_otp_login_widget'; }
	public function show_in_panel() { return false; }
}
class Ashna_Elementor_Legacy_Modal_Button_Widget extends Ashna_Elementor_Modal_Button_Widget {
	public function get_name() { return 'signa_otp_modal_button_widget'; }
	public function show_in_panel() { return false; }
}
