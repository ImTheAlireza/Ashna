"""Build the license-bound, protectable sources for the distribution ZIP.

The repository's modular PHP sources remain the development/no-license build.
The licensed distribution instead contains a generated runtime: all ordinary
functionality classes live *inside* a strict vendor-license branch, and their
original, unguarded files are not shipped. Elementor widget classes remain lazy
and each has its own branch because their parent is unavailable without Elementor.

This is source preparation, not encoding. The resulting PHP files must still be
protected by the vendor and replaced byte-for-byte in the distribution ZIP.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
GUARD = 'includes/class-ashna-license-guard.php'
CORE = 'includes/class-ashna-protected-core.php'
AUTOLOADER = 'includes/class-ashna-autoloader.php'
WIDGETS = (
    'includes/integrations/elementor/class-ashna-elementor-login-widget.php',
    'includes/integrations/elementor/class-ashna-elementor-modal-button-widget.php',
)
PROTECT = (GUARD, CORE, *WIDGETS)

# Declarations are conditional, so parents/interfaces must precede subclasses.
CORE_SOURCES = (
    'includes/class-ashna-plugin.php',
    'includes/core/class-ashna-activator.php',
    'includes/core/class-ashna-helper.php',
    'includes/core/class-ashna-ip.php',
    'includes/core/class-ashna-jwt.php',
    'includes/core/class-ashna-logger.php',
    'includes/core/class-ashna-passkey.php',
    'includes/core/class-ashna-rate-limiter.php',
    'includes/core/class-ashna-security.php',
    'includes/core/class-ashna-session.php',
    'includes/gateways/interface-ashna-gateway.php',
    'includes/gateways/abstract-ashna-gateway.php',
    'includes/gateways/class-ashna-gateway-msgway.php',
    'includes/gateways/class-ashna-gateway-msgway-messenger.php',
    'includes/gateways/class-ashna-gateway-bale.php',
    'includes/gateways/class-ashna-gateway-email.php',
    'includes/gateways/class-ashna-gateway-farazsms.php',
    'includes/gateways/class-ashna-gateway-ippanel.php',
    'includes/gateways/class-ashna-gateway-kavenegar.php',
    'includes/gateways/class-ashna-gateway-manager.php',
    'includes/gateways/class-ashna-gateway-melipayamak.php',
    'includes/gateways/class-ashna-gateway-sandbox.php',
    'includes/gateways/class-ashna-gateway-smsir.php',
    'includes/gateways/class-ashna-failover.php',
    'includes/services/class-ashna-auth.php',
    'includes/services/class-ashna-frontend.php',
    'includes/services/class-ashna-sso.php',
    'includes/services/class-ashna-woocommerce.php',
    'includes/admin/class-ashna-admin.php',
    'includes/integrations/class-ashna-integrations.php',
    'includes/integrations/class-ashna-integration-dokan.php',
    'includes/integrations/class-ashna-integration-um.php',
    'includes/integrations/elementor/class-ashna-elementor.php',
)
MARKER = '/* ASHNA_LICENSED_CONTENT */'

# PHP paths are evaluated from the generated core in includes/, not from the
# original per-class directory. Keep WP's resolved theme path (including child,
# parent and theme-compat) while expressing the include through a WP path API.
REWRITES = {
    'includes/services/class-ashna-frontend.php': (
        ('include $theme_template;', 'include plugin_dir_path( $theme_template ) . basename( $theme_template );', 1),
        ("include ASHNA_OTP_PATH . 'templates/login-form.php';", "include __DIR__ . '/../templates/login-form.php';", 1),
    ),
    'includes/services/class-ashna-sso.php': (
        ("include ASHNA_OTP_PATH . 'templates/sso-phone-prompt.php';", "include __DIR__ . '/../templates/sso-phone-prompt.php';", 1),
    ),
    'includes/gateways/class-ashna-gateway-email.php': (
        ("include ASHNA_OTP_PATH . 'templates/email-otp.php';", "include __DIR__ . '/../templates/email-otp.php';", 1),
    ),
    'includes/admin/class-ashna-admin.php': (
        ("include ASHNA_OTP_PATH . 'includes/admin/views/settings-page.php';", "include __DIR__ . '/admin/views/settings-page.php';", 1),
        ("include ASHNA_OTP_PATH . 'includes/admin/views/logs-page.php';", "include __DIR__ . '/admin/views/logs-page.php';", 1),
    ),
    'includes/integrations/elementor/class-ashna-elementor.php': tuple(
        ("require_once ASHNA_OTP_PATH . 'includes/integrations/elementor/" + Path(widget).name + "';",
         "require_once __DIR__ . '/integrations/elementor/" + Path(widget).name + "';", 2)
        for widget in WIDGETS
    ),
}


def source_body(relative):
    text = (ROOT / relative).read_text(encoding='utf-8')
    if not text.startswith('<?php\n'):
        raise ValueError(f'{relative}: expected an ordinary PHP opening tag')
    if re.search(r'(?m)^\s*(?:namespace|declare\s*\()', text):
        raise ValueError(f'{relative}: namespaces/declare require manual review before merging')
    if '__DIR__' in text or '__FILE__' in text:
        raise ValueError(f'{relative}: moving magic file paths requires manual review')
    for old, new, expected in REWRITES.get(relative, ()):
        if text.count(old) != expected:
            raise ValueError(f'{relative}: expected {expected} occurrences of {old!r}')
        text = text.replace(old, new)
    return f'\n// ---- Source: {relative} ----\n' + text[len('<?php\n'):] + '\n'


def validate_protection_input(name, content):
    if not content or len(content) >= 1_000_000:
        raise ValueError(f'{name}: protectable PHP must be nonempty and smaller than 1 MB')
    try:
        content.decode('utf-8')
    except UnicodeDecodeError as error:
        raise ValueError(f'{name}: raw UTF-8 PHP is required; do not re-encode protected files') from error
    if not content.startswith(b'<?php') or re.search(rb'<\?(?!php\b)', content, re.IGNORECASE) or b'__halt_compiler' in content:
        raise ValueError(f'{name}: standard PHP tags only; no __halt_compiler')
    if re.match(rb'<\?php\s*//(?:ICB0|[0-9A-F]{5})\b', content, re.IGNORECASE):
        raise ValueError(f'{name}: appears already ionCube encoded; do not re-encode it')
    includes = re.findall(rb'^\s*(?:include|require)(?:_once)?\s+([^;]+);', content, re.MULTILINE)
    for expression in includes:
        if not re.search(rb'\b(?:ABSPATH|__DIR__|plugin_dir_path)\b', expression):
            raise ValueError(f'{name}: local include/require needs a supported base path: {expression!r}')


def wrap(body):
    template = (ROOT / 'scripts/templates/rtl-protected-wrapper.php').read_text(encoding='utf-8')
    if template.count(MARKER) != 1:
        raise ValueError('Protected wrapper marker must be unique')
    result = template.replace(MARKER, body).encode('utf-8')
    validate_protection_input('generated wrapper', result)
    return result


def validate_sources():
    # Fail the build if a new runtime class is added without being classified.
    found = {'includes/class-ashna-plugin.php', 'includes/admin/class-ashna-admin.php'}
    for directory in ('core', 'services', 'gateways', 'integrations'):
        for path in (ROOT / 'includes' / directory).rglob('*.php'):
            if path.name != 'index.php':
                found.add(path.relative_to(ROOT).as_posix())
    expected = set(CORE_SOURCES) | set(WIDGETS)
    if found != expected:
        raise ValueError(f'Runtime source classification changed; missing={sorted(expected-found)}, unclassified={sorted(found-expected)}')
    if len(expected) != len(CORE_SOURCES) + len(WIDGETS):
        raise ValueError('Duplicate source files in protected-runtime classification')


def licensed_files():
    """Return generated/reused distribution files, keyed by plugin-relative path."""
    validate_sources()
    core = wrap(''.join(source_body(path) for path in CORE_SOURCES))
    result = {
        AUTOLOADER: (ROOT / 'scripts/templates/ashna-licensed-autoloader.php').read_bytes(),
        CORE: core,
        GUARD: (ROOT / GUARD).read_bytes(),
    }
    for path in WIDGETS:
        result[path] = wrap(source_body(path))
    for path in PROTECT:
        validate_protection_input(path, result[path])
    return result
