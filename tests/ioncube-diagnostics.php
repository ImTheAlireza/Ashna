<?php
/** Message fixtures only: does not execute ionCube or a vendor license. */
define('ABSPATH', __DIR__ . '/');
function add_action() {}
require __DIR__ . '/../tools/signa-activation-diagnostics/signa-activation-diagnostics.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$cases = array(
    array('The file /home/PRIVATE-TOKEN/RTL_License_c1595a7238d64f3a.php was encoded by the ionCube Encoder for PHP 8.2 and cannot run under PHP 7.4.',
        array('reason' => 'php_version_incompatible', 'mentions_expected_license_file' => true, 'encoded_for_php' => '8.2', 'rejected_runtime_php' => '7.4')),
    array('ionCube Encoder for PHP version 8.2.29: cannot be run with PHP 7.4.33 PRIVATE-TOKEN',
        array('reason' => 'php_version_incompatible', 'encoded_for_php' => '8.2.29', 'rejected_runtime_php' => '7.4.33')),
    array('The file C:\\PRIVATE-TOKEN\\license.php requires the ionCube PHP Loader 16.0 or later to be installed.',
        array('reason' => 'loader_update_required', 'required_loader_version' => '16.0')),
    array('This file requires ionCube Loader version 15.5.0 or later. PRIVATE-TOKEN',
        array('reason' => 'loader_update_required', 'required_loader_version' => '15.5.0')),
    array('The file /home/PRIVATE-TOKEN/license.php encoded as type [1/82] cannot be decoded by this version of the ionCube Loader.',
        array('reason' => 'encoding_not_decodable', 'encoding_type' => '1/82')),
    array('This file requires a newer version of the ionCube Loader. PRIVATE-TOKEN',
        array('reason' => 'loader_update_required')),
    array('ionCube: The file /home/PRIVATE-TOKEN/license.php is corrupted.',
        array('reason' => 'encoded_file_corrupt')),
    array('ionCube: The file /home/PRIVATE-TOKEN/license.php has expired.',
        array('reason' => 'encoded_file_expired')),
    array('ionCube: The license PRIVATE-TOKEN has expired.',
        array('reason' => 'license_expired')),
    array('ionCube: This script is not licensed to run on PRIVATE-TOKEN.',
        array('reason' => 'host_restriction')),
    array('Script error: the ionCube Loader for PHP needs to be installed. PRIVATE-TOKEN',
        array('reason' => 'loader_not_installed')),
    array('ionCube: An unknown failure occurred at /home/PRIVATE-TOKEN. API_KEY=PRIVATE-TOKEN',
        array('reason' => 'unrecognized_loader_error')),
    array('ionCube: Encoder for PHP 8.2123456789 cannot run under PHP 7.4123456789 PRIVATE-TOKEN',
        array('reason' => 'php_version_incompatible')),
    array('ionCube: encoded as type [123456/123456] PRIVATE-TOKEN',
        array('reason' => 'unrecognized_loader_error')),
);
foreach ($cases as $i => $case) {
    $result = Signa_Activation_Diagnostics::safe_error(array('type' => E_CORE_ERROR, 'message' => $case[0], 'file' => 'Unknown', 'line' => 0));
    check($result['is_fatal'] && $result['category'] === 'ioncube', 'Lost Fatal/ionCube classification');
    $details = $result['ioncube_details'];
    $expected = array_merge(array('mentions_expected_license_file' => false), $case[1]);
    ksort($expected); ksort($details);
    check($details === $expected, 'Unexpected fields/case ' . $i . ': ' . json_encode($details));
    $json = json_encode($result);
    foreach (array('PRIVATE-TOKEN', '/home/', 'API_KEY', 'message') as $secret) {
        check(strpos($json, $secret) === false, 'Private data copied into report');
    }
}
$result = Signa_Activation_Diagnostics::safe_error(array('type' => E_NOTICE, 'message' => 'translation notice', 'file' => '/home/PRIVATE-TOKEN/functions.php', 'line' => 12));
check(!isset($result['ioncube_details']) && !$result['is_fatal'], 'Unrelated Notice treated as Loader error');
echo 'PASS all ' . count($cases) . " ionCube message fixtures\n";
