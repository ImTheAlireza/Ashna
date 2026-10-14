"""Cache official WP 6.8.3 and WooCommerce 9.9.5 sources for isolated session tests.
Requires authenticated gh. No WordPress install or vendor license is used.
"""
from pathlib import Path
import base64
import json
import subprocess
ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / '.cache/session-tests'
wp = ['compat.php', 'plugin.php', 'class-wp-hook.php', 'pluggable.php', 'class-wp-user.php',
      'class-wp-error.php', 'class-wp-role.php', 'class-wp-roles.php', 'capabilities.php',
      'user.php', 'formatting.php', 'class-phpass.php', 'class-wp-session-tokens.php', 'class-wp-user-meta-session-tokens.php']
wc = ['abstracts/abstract-wc-session.php', 'class-wc-session-handler.php', 'wc-user-functions.php']
for repo, ref, base, files, folder in [
    ('WordPress/WordPress', '6.8.3', 'wp-includes', wp, 'wp-includes'),
    ('woocommerce/woocommerce', '9.9.5', 'plugins/woocommerce/includes', wc, 'wc'),
]:
    for name in files:
        path = CACHE / folder / Path(name).name
        if path.exists() and path.stat().st_size > 100:
            continue
        response = subprocess.check_output(['gh', 'api', f'repos/{repo}/contents/{base}/{name}?ref={ref}'], text=True)
        record = json.loads(response)
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(base64.b64decode(record['content']))
print('PASS: official session-test sources cached under .cache/session-tests.')
