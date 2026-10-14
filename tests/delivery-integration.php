<?php
/** Real WP hook dispatcher, actual delivery/manager/drivers; HTTP/storage are fixtures. */
require __DIR__ . '/fixtures/session-bootstrap.php';
define('ASHNA_OTP_PATH', $root . '/');
require $root . '/includes/class-ashna-autoloader.php';
Ashna_Autoloader::register();
class Ashna_Helper {
    public static $settings;
    public static function generate_otp_code($length) { return '12345'; }
    public static function normalize_phone($phone) { return $phone; }
    public static function get_settings() { return self::$settings; }
    public static function get_option($key, $default = null) { return isset(self::$settings[$key]) ? self::$settings[$key] : $default; }
}
function add_option($key, $value, $deprecated = '', $autoload = true) {
    if (isset($GLOBALS['options'][$key])) { return false; }
    $GLOBALS['options'][$key] = $value; return true;
}
function wp_generate_uuid4() { return 'fixture-' . ++$GLOBALS['lease_counter']; }
class LeaseDatabase {
    public $options = 'wp_options';
    public function prepare($sql, $key, $value) { return array($key, $value); }
    public function query($args) {
        list($key, $value) = $args;
        if (isset($GLOBALS['options'][$key]) && $GLOBALS['options'][$key] === $value) { unset($GLOBALS['options'][$key]); return 1; }
        return 0;
    }
}
$wpdb = new LeaseDatabase();
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_retrieve_body($response) { return isset($response['body']) ? $response['body'] : ''; }
function wp_remote_retrieve_response_code($response) { return isset($response['response']['code']) ? $response['response']['code'] : 0; }
function wp_remote_post($url, $args = array()) {
    $args = apply_filters('http_request_args', array_merge(array('timeout' => 15), $args), $url);
    $pre = apply_filters('pre_http_request', false, $args, $url);
    if (false !== $pre) { return $pre; }
    $GLOBALS['http'][] = $args;
    $duration = $GLOBALS['duration'];
    if ($duration > 0) { usleep((int) (min($duration, $args['timeout']) * 1000000)); }
    if ($duration >= $args['timeout']) { return new WP_Error('http_request_failed', 'Transport timeout'); }
    $response = isset($GLOBALS['http_response_fixture']) ? $GLOBALS['http_response_fixture'] : array('response'=>array('code'=>200),'body'=>'{}');
    $response = apply_filters('http_response', $response, $args, $url);
    return isset($GLOBALS['http_response_fixture']) ? $response : true;
}
class DeliveryGateway implements Ashna_Gateway_Interface {
    private $id;
    public function __construct($id) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function get_title() { return $this->id; }
    public function send($recipient, $code) {
        $GLOBALS['calls'][] = $this->id;
        $spec = $GLOBALS['spec'][$this->id];
        $GLOBALS['duration'] = isset($spec['delay']) ? $spec['delay'] : 0;
        $result = true;
        for ($i = 0; $i < (isset($spec['requests']) ? $spec['requests'] : 1); $i++) {
            $result = wp_remote_post('https://sms.example.test/send', array('timeout' => 15));
            if (is_wp_error($result)) { return $result; }
        }
        if (isset($spec['throw'])) { throw new RuntimeException('Driver failure'); }
        return isset($spec['result']) ? $spec['result'] : $result;
    }
}
foreach (array('a', 'b', 'c', 'd') as $id) { Ashna_Gateway_Manager::register_gateway(new DeliveryGateway($id)); }
function reset_delivery() {
    $GLOBALS['transients'] = $GLOBALS['calls'] = $GLOBALS['http'] = array();
    $GLOBALS['lease_counter'] = 0;
    unset($GLOBALS['http_response_fixture']);
    $GLOBALS['spec'] = array('a' => array(), 'b' => array(), 'c' => array(), 'd' => array());
    Ashna_Helper::$settings = array('active_sms_gateway' => 'a', 'backup_sms_gateway_1' => 'b', 'backup_sms_gateway_2' => 'c', 'backup_sms_gateway_3' => 'd');
}
function send_fixture($forced = '') { return Ashna_Gateway_Manager::send_sms_with_failover('09123456789', '12345', $forced); }
reset_delivery();
check(send_fixture()['result'] === true && $GLOBALS['calls'] === array('a'), 'Success must stop the chain');
check($GLOBALS['http'][0]['timeout'] <= 4 && $GLOBALS['http'][0]['timeout'] > 3.8, 'Attempt timeout not clamped');
check(!has_filter('http_request_args', array('Ashna_Delivery', 'http_args')), 'HTTP filter leaked');
echo "PASS first-success stop and scoped 4-second HTTP timeout\n";
reset_delivery();
$GLOBALS['spec']['a']['result'] = new WP_Error('http_request_failed', 'Failed');
for ($i=0; $i<3; $i++) { check(send_fixture()['gateway_id'] === 'b', 'Backup not used'); }
check($GLOBALS['calls'] === array('a','b','a','b','b'), 'Open circuit was retried');
check(send_fixture('a')['gateway_id'] === 'a' && end($GLOBALS['calls']) === 'a', 'Admin forced test did not bypass open circuit');
echo "PASS immediate failover, breaker after two failures, open-circuit skip and explicit admin probe\n";
$key = array_keys($GLOBALS['transients'])[0];
$GLOBALS['transients'][$key]['until'] = time()-1;
$GLOBALS['spec']['a'] = array();
$before = count($GLOBALS['calls']);
$GLOBALS['options'][$key . '_probe'] = (time()+20) . ':another-worker';
check(send_fixture()['gateway_id'] === 'b' && count($GLOBALS['calls']) === $before+1, 'Concurrent recovery probe was not excluded');
$GLOBALS['options'][$key . '_probe'] = (time()-1) . ':crashed-worker';
check(send_fixture()['gateway_id'] === 'a', 'Expired lease was not recovered');
check(!isset($GLOBALS['transients'][$key]) && !isset($GLOBALS['options'][$key . '_probe']), 'Successful recovery did not close circuit/release lease');
echo "PASS half-open exclusion, stale lease recovery and successful circuit reset\n";
reset_delivery();
Ashna_Helper::$settings['delivery_attempt_timeout'] = 1;
Ashna_Helper::$settings['delivery_total_timeout'] = 3;
foreach (array('a','b','c','d') as $id) { $GLOBALS['spec'][$id]['delay'] = 5; }
$start = microtime(true); $result = send_fixture(); $duration = microtime(true)-$start;
check(is_wp_error($result['result']) && $duration < 3.35 && $duration > 2.8, 'Global budget not respected: ' . $duration);
check(count($GLOBALS['http']) === 3 && count($GLOBALS['calls']) === 3, 'Started another send after budget exhaustion');
echo 'PASS simulated slow transport: total=' . round($duration,3) . "s for a 3s budget; no fourth HTTP send\n";
reset_delivery();
Ashna_Helper::$settings['delivery_attempt_timeout'] = 1;
$GLOBALS['spec']['a'] = array('requests' => 3, 'delay' => 0.6);
$start=microtime(true); $result=send_fixture(); $duration=microtime(true)-$start;
check($result['gateway_id'] === 'b' && count($GLOBALS['http']) === 3 && $duration < 1.3, 'Multiple HTTP calls received separate full attempt budgets');
check($GLOBALS['http'][1]['timeout'] < 0.5, 'Remaining attempt budget not applied');
echo "PASS driver-internal retries share one attempt deadline\n";
reset_delivery();
$GLOBALS['spec']['a']['throw'] = true;
check(send_fixture()['gateway_id'] === 'b', 'Driver exception did not fail over safely');
check(!has_filter('http_request_args', array('Ashna_Delivery', 'http_args')) && !has_filter('pre_http_request', array('Ashna_Delivery', 'pre_http')), 'Exception leaked HTTP hooks');
echo "PASS driver-exception failover and exception-safe HTTP scope cleanup\n";
reset_delivery();
$GLOBALS['spec']['a']['result'] = new WP_Error('http_request_failed','Failed'); send_fixture(); send_fixture();
Ashna_Helper::$settings['smsir_api_key'] = 'changed-config-fixture';
$GLOBALS['spec']['a'] = array();
check(send_fixture()['gateway_id'] === 'a', 'Config change reused stale health');
foreach (array_keys($GLOBALS['transients']) as $key) { check(strpos($key,'09123456789') === false && strpos($key,'changed-config') === false, 'Sensitive health key'); }
echo "PASS configuration-isolated circuit state without recipient/credential keys\n";

reset_delivery();
$GLOBALS['spec']['a']['result'] = new WP_Error('recipient_rejected','Invalid recipient');
for ($i=0;$i<3;$i++) { send_fixture(); }
check($GLOBALS['calls'] === array('a','b','a','b','a','b') && empty($GLOBALS['transients']), 'Recipient errors poisoned shared circuit');
echo "PASS recipient/config-type errors do not poison the shared provider circuit\n";

reset_delivery();
Ashna_Helper::$settings = array_merge(Ashna_Helper::$settings, array('active_sms_gateway'=>'smsir','smsir_api_key'=>'fixture','smsir_template_id'=>1));
$GLOBALS['duration'] = 0;
$GLOBALS['http_response_fixture'] = array('response'=>array('code'=>200),'body'=>'{"status":1}');
check(send_fixture()['gateway_id'] === 'smsir' && $GLOBALS['http'][0]['timeout'] <= 4, 'Actual SMS.ir driver escaped clamped HTTP args');
$GLOBALS['http_response_fixture'] = array('response'=>array('code'=>503),'body'=>'{"status":0,"message":"Unavailable"}');
$GLOBALS['spec']['b']['result'] = true;
for ($i=0;$i<3;$i++) { check(send_fixture()['gateway_id'] === 'b', '503 did not fail over'); }
check(count($GLOBALS['http']) === 6, 'Actual driver HTTP 503 did not open the circuit after two failures');
check(!has_filter('http_response',array('Ashna_Delivery','http_response')), 'HTTP observer leaked');
echo "PASS actual SMS.ir request/parser with mocked HTTP responses: 4s clamp, 503 breaker, backup and observer cleanup\n";

class Ashna_Gateway_Bale extends DeliveryGateway { public function __construct() { parent::__construct('bale'); } }
class Ashna_Logger { public static function insert($data) { $GLOBALS['delivery_log'] = $data; return 42; } }
reset_delivery();
Ashna_Helper::$settings['mobile_delivery_channel'] = 'both';
Ashna_Helper::$settings['delivery_total_timeout'] = 3;
$GLOBALS['spec']['a'] = array('requests'=>2,'delay'=>0.9);
$GLOBALS['spec']['bale'] = array('requests'=>2,'delay'=>0.9);
$start = microtime(true); $result = Ashna_Gateway_Manager::dispatch_otp('09123456789'); $duration = microtime(true)-$start;
check(!is_wp_error($result) && $result['channel'] === 'sms', 'Partial both delivery falsely claims Bale success');
check($GLOBALS['calls'] === array('a','bale') && count($GLOBALS['http']) === 4 && $duration < 3.35, 'SMS/Bale did not share the total budget');
check($GLOBALS['delivery_log']['status'] === 'sent' && $GLOBALS['delivery_log']['channel'] === 'sms', 'Partial channel log inaccurate');
check(!has_filter('http_request_args',array('Ashna_Delivery','http_args')), 'Dispatcher leaked budget filters');
echo "PASS actual OTP dispatcher shares one budget across SMS and Bale; partial success logs only the delivered channel\n";
