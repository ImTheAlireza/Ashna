"""Check that the files match RTL-Theme's file-protection submission rules.

RTL-Theme (rtl-theme.com/designer → ارسال محصول) accepts plain PHP files up to
1 MB, without short tags and without __halt_compiler(), and rejects any
include/require without an explicit path base. It also says the main plugin
file and the file that holds the license Loader must NOT be sent.

So this test pins the submission contract:

  ashna-core.php + includes/**   -> the files that go to the protection service
  ashna.php                      -> main file: has `Plugin Name`, never protected
  ashna-license.php              -> holds the Loader, never protected

It also proves the entry guard is in place: a direct `require` of ashna-core.php
boots nothing (tests/run-license-bootstrap.py verifies the behaviour on PHP).

Run: python3 tests/run-protection-readiness.py
"""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts'))
import protection_rules as rules  # noqa: E402

problems = []
core = ROOT / 'ashna-core.php'


def relative(path):
    return path.relative_to(ROOT).as_posix()


print('RTL-Theme protection readiness')

# 1. Every submitted file must satisfy the service's limits.
submitted = [core] + [path for path in sorted((ROOT / 'includes').rglob('*.php'))
                      if not rules.is_silence_stub(path)]
total = 0
for path in submitted:
    problems += rules.check_file(path)
    total += path.stat().st_size
print(f'  submitted: {len(submitted)} files, {total} bytes, '
      f'largest {max(p.stat().st_size for p in submitted)} bytes')
if total > 5 * 1024 * 1024:
    problems.append('submitted files exceed the 5 MB total of an extracted batch set')

# 2. The entry file carries the guard the protected build relies on.
core_text = core.read_text()
for needle in ('Ashna_License_Bootstrap::granted()', 'ASHNA_NO_LICENSE_BUILD', 'return;'):
    if needle not in core_text:
        problems.append(f'ashna-core.php: guard is incomplete, missing {needle!r}')
for expression in rules.INCLUDE.findall(rules.code_only(core_text)):
    if not expression.lstrip().startswith(rules.SAFE_BASES):
        problems.append(f'ashna-core.php: pathless include/require -> {expression.strip()[:60]}')

# 3. The readable files must stay readable and out of the submission.
expectations = {
    'ashna.php': ['Plugin Name:', 'ashna-license.php'],
    'ashna-license.php': ['RTL_LICENSE_CLASS', 'RTL_LICENSE_HASH',
                          "require_once $ashna_core", 'public static function granted()'],
}
for name, needles in expectations.items():
    text = (ROOT / name).read_text()
    for needle in needles:
        if needle not in text:
            problems.append(f'{name}: expected {needle!r}')
    if 'Plugin Name:' in text and name != 'ashna.php':
        problems.append(f'{name}: second plugin header would be read by WordPress')
    if 'RTL_LICENSE_CLASS' in text and name != 'ashna-license.php':
        problems.append(f'{name}: the Loader block must live only in ashna-license.php')

# 4. The license-free template opts out of the guard instead of shipping a Loader.
template = (ROOT / 'scripts/templates/ashna-no-license.php').read_text()
if 'ASHNA_NO_LICENSE_BUILD' not in template:
    problems.append('ashna-no-license.php: must define ASHNA_NO_LICENSE_BUILD')

if problems:
    print('\nFAIL')
    for problem in problems:
        print(' - ' + problem)
    sys.exit(1)

print(f'PASS submission contract: {len(submitted)} files may be protected; ashna.php and '
      'ashna-license.php stay readable and are never sent; the entry guard is in place.')
