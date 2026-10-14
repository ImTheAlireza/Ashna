<?php
/** Official WP requirement validator, with isolated metadata/UI/dependency stubs. */
define('WP_PLUGIN_DIR', dirname(__DIR__));
function get_plugin_data($file) {
    $source = file_get_contents($file);
    preg_match('/Requires PHP:\s*([0-9.]+)/', $source, $php);
    return array('Name' => 'Ashna OTP', 'RequiresPHP' => $php[1], 'RequiresWP' => '5.8');
}
function is_wp_version_compatible($version) { return true; }
function is_php_version_compatible($version) { return version_compare(PHP_VERSION, $version, '>='); }
function __($text) { return $text; }
function _x($text, $context) { return $text; }
function esc_url($url) { return $url; }
function wp_get_update_php_url() { return 'https://example.test/php'; }
function wp_get_update_php_annotation() { return ''; }
class WP_Error {
    public $code;
    public function __construct($code, $message) { $this->code = $code; }
}
class WP_Plugin_Dependencies {
    public static function initialize() {}
    public static function has_unmet_dependencies($plugin) { return false; }
}
// Runner extracts this unmodified function from the official cached WP source.
require __DIR__ . '/../.cache/license-tests/validate-requirements.php';
$headers = get_plugin_data(WP_PLUGIN_DIR . '/index.php');
if ($headers['RequiresPHP'] !== '8.2') { throw new RuntimeException('Wrong minimum PHP header'); }
$result = validate_plugin_requirements('index.php');
$compatible = version_compare(PHP_VERSION, '8.2', '>=');
if ($compatible ? $result !== true : !($result instanceof WP_Error && $result->code === 'plugin_php_incompatible')) {
    throw new RuntimeException('Wrong WordPress requirement validation result');
}
if (class_exists('Ashna_License_Bootstrap', false)) { throw new RuntimeException('Validation loaded the license bootstrap'); }
echo 'PASS requirement / PHP ' . PHP_VERSION . ' / ' . ($compatible ? 'allowed' : 'blocked') . "\n";
