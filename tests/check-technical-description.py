"""Check product documentation against implemented defaults; NOT a load/security test."""
from pathlib import Path
from html.parser import HTMLParser
import re

ROOT = Path(__file__).resolve().parents[1]
settings = (ROOT / 'includes/core/class-ashna-helper.php').read_text()
security = (ROOT / 'includes/core/class-ashna-security.php').read_text()
auth = (ROOT / 'includes/services/class-ashna-auth.php').read_text()
text = (ROOT / 'docs/product-technical-description-fa.md').read_text()
readme = (ROOT / 'README.md').read_text()
landing = (ROOT / 'parsna-landing/index.html').read_text()
fa = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')
for key, expected in [('max_requests_per_hour', 5), ('max_ip_requests_per_hour', 15),
                      ('resend_cooldown', 60), ('max_verify_attempts', 5),
                      ('lockout_duration', 900), ('otp_expiry', 120)]:
    match = re.search(r"'" + key + r"'\s*=>\s*(\d+)", settings)
    assert match and int(match.group(1)) == expected, f'Review documented default: {key}'
    assert str(expected).translate(fa) in text, f'Missing default in technical description: {key}'
for claim in ['WP_Session_Tokens', 'JWT', 'SMS Bombing', 'Brute Force', 'ساعتی',
              'دقیقه', 'اتمیک', 'لیست سفید', 'trust_proxy_headers', 'failover', 'none']:
    assert claim in text, f'Missing technical explanation/caveat: {claim}'
for document in (text, readme, landing):
    assert 'SMS Bombing' in document and 'nonce' in document and 'JWT' in document
    assert '۱۵' in document and '۶۰' in document
assert auth.index('Ashna_Security::can_request_otp( $identifier )') < auth.index('Ashna_Gateway_Manager::dispatch_otp( $identifier, $type )')
assert security.index('self::is_whitelisted( $recipient, $ip )', security.index('function can_request_otp')) < security.index('// 1. Check resend cooldown')
assert "'failed' === $latest->status" in security
assert 'created_at >= %s' in security and 'HOUR_IN_SECONDS' in security

class SectionParser(HTMLParser):
    def __init__(self):
        super().__init__(); self.found = False
    def handle_starttag(self, tag, attrs):
        if tag == 'section' and dict(attrs).get('id') == 'ashna-technical': self.found = True
parser = SectionParser(); parser.feed(landing)
assert parser.found and 'href="#ashna-technical"' in landing
for claim in ['Dokan', 'MemberPress', 'Paid Memberships Pro', 'Ultimate Member',
              'circuit breaker', '۱۵ ثانیه', '۶۰ ثانیه', 'ترتیبی', 'نتیجهٔ نامعلوم',
              'p50/p95/p99', 'هنوز اجرا نشده', 'ashna_authenticate_user',
              'ashna_otp_login_redirect', 'ashna_otp_user_registered']:
    assert claim in text, f'Missing latency/integration scope: {claim}'
session = (ROOT / 'includes/core/class-ashna-session.php').read_text()
for hook in ['ashna_authenticate_user', 'wp_authenticate_user', 'wp_login']:
    assert hook in session
for hook in ['ashna_otp_login_redirect', 'ashna_otp_user_registered']:
    assert hook in auth
manager = (ROOT / 'includes/gateways/class-ashna-gateway-manager.php').read_text()
assert 'sleep(' not in manager and 'usleep(' not in manager
for driver in ['smsir', 'kavenegar', 'melipayamak', 'farazsms', 'ippanel', 'bale']:
    source = (ROOT / f'includes/gateways/class-ashna-gateway-{driver}.php').read_text()
    timeouts = re.findall(r"'timeout'\s*=>\s*(\d+)", source)
    assert timeouts and all(int(value) == 15 for value in timeouts), f'Review documented HTTP timeout: {driver}'
print('PASS: latency defaults and third-party integration boundaries match the inspected sources; roadmap items are not advertised as delivered.')
print('PASS: technical copy covers native sessions and OTP abuse controls with actual hourly defaults, exceptions and limitations.')
