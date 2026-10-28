<?php
/** Direct inclusion/loader-bypass tests, using only isolated fake RTL files. */
namespace Elementor {
	class Widget_Base {}
}
namespace {
	error_reporting(E_ALL);
	set_error_handler(function ($level, $message, $file, $line) {
		if (error_reporting() & $level) { throw new \ErrorException($message, 0, $level, $file, $line); }
		return false;
	});
	$plugin = $argv[1];
	$scenario = $argv[2];
	$mode = $argv[3];
	define('ABSPATH', $plugin . '/wp/');
	define('ASHNA_OTP_PATH', $plugin . '/');
	define('ASHNA_LICENSE_ACTIVE', true); // An attacker-controlled flag is not trusted.
	$GLOBALS['ashna_license_active'] = true;
	$GLOBALS['license_calls'] = 0;
	$GLOBALS['theme_template'] = '';
	function plugin_dir_path($file) { return rtrim(dirname($file), '/\\') . '/'; }
	function locate_template($name) { return $GLOBALS['theme_template']; }
	function get_option($name, $default = false) { return $default; }
	function get_bloginfo($name) { return 'fixture'; }
	function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
	function check($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }

	// Even a fake, already-defined guard and an editable main file cannot
	// grant the independently checked runtime access to the product classes.
	class Ashna_License_Guard { public static function get_boot_callback() { return function () { return true; }; } }
	if ($scenario !== 'missing_class') {
		require $plugin . '/' . ($scenario === 'wrong_origin' ? 'other-license.php' : 'RTL_License_c1595a7238d64f3a.php');
	}
	require $plugin . '/includes/class-ashna-autoloader.php';
	Ashna_Autoloader::register();
	$thrown = 0;
	try {
		if ($mode === 'direct') {
			require_once $plugin . '/includes/class-ashna-protected-core.php';
			$kernel_exists = class_exists('Ashna_Plugin', false);
		} else {
			$kernel_exists = class_exists('Ashna_Plugin');
		}
	} catch (\Throwable $e) { ++$thrown; $kernel_exists = false; }
	foreach (array('class-ashna-elementor-login-widget.php', 'class-ashna-elementor-modal-button-widget.php') as $widget) {
		try { require_once $plugin . '/includes/integrations/elementor/' . $widget; } catch (\Throwable $e) { ++$thrown; }
	}
	$active = $scenario === 'active';
	check($kernel_exists === $active, 'Kernel autoload bypassed independent license gate');
	foreach (array('Ashna_Plugin', 'Ashna_Auth', 'Ashna_Session', 'Ashna_SSO', 'Ashna_Passkey', 'Ashna_Admin', 'Ashna_Frontend', 'Ashna_Gateway_Manager', 'Ashna_Gateway_Msgway_Messenger', 'Ashna_Elementor_Login_Widget', 'Ashna_Elementor_Modal_Button_Widget') as $class) {
		check(class_exists($class, false) === $active, $class . ' was unexpectedly declared or missing');
	}
	check(interface_exists('Ashna_Gateway_Interface', false) === $active, 'Gateway interface was unexpectedly declared or missing');
	$expected_calls = in_array($scenario, array('active', 'inactive', 'truthy', 'method_error'), true) ? 3 : 0;
	check($GLOBALS['license_calls'] === $expected_calls, 'Unexpected independent check count');
	check($thrown === ($scenario === 'method_error' ? 3 : 0), 'Unexpected exceptions');
	if ($active) {
		check(is_subclass_of('Ashna_Elementor_Login_Widget', 'Elementor\\Widget_Base'), 'Elementor login widget parent missing');
		check(is_subclass_of('Ashna_Elementor_Modal_Button_Widget', 'Elementor\\Widget_Base'), 'Elementor button widget parent missing');
		// Verify that the merged runtime uses the same local argument scope for
		// plugin fallback and any resolved theme path, even outside the WP root.
		check(Ashna_Frontend::get_login_form_html(array('context' => 'fallback')) === 'plugin:fallback', 'Plugin template path/scope changed');
		$GLOBALS['theme_template'] = dirname($plugin) . '/theme/ashna/login-form.php';
		check(Ashna_Frontend::get_login_form_html(array('context' => 'child')) === 'theme:child', 'Theme template path/scope changed');
	}
	echo 'PASS ' . $scenario . ' ' . $mode . ' protected boundaries / PHP ' . PHP_VERSION . "\n";
}
