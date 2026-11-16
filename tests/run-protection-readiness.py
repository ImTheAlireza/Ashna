"""Check that the files match RTL-Theme's file-protection submission rules.

RTL-Theme (rtl-theme.com/designer → ارسال محصول) accepts plain PHP files up to
1 MB, without short tags and without __halt_compiler(), and rejects any
include/require that has no explicit path base. It also says the main plugin
file and the file that holds the license Loader must NOT be sent for
protection.

So this test pins the submission contract:

  ashna-core.php     -> the only file that goes to the protection service
  ashna.php          -> main file: has `Plugin Name`, never protected
  ashna-license.php  -> holds the Loader, never protected

Run: python3 tests/run-protection-readiness.py
"""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
SUBMITTED = ROOT / 'ashna-core.php'
PLAIN = [ROOT / 'ashna.php', ROOT / 'ashna-license.php']

# Bases RTL-Theme documents as portable in protected files.
SAFE_BASES = ('__DIR__', '__FILE__', 'plugin_dir_path(', 'plugin_dir_url(', 'ABSPATH',
              'WP_PLUGIN_DIR', 'WP_CONTENT_DIR', 'get_template_directory(',
              'get_stylesheet_directory(')
INCLUDE = re.compile(r'\b(?:require|include)(?:_once)?\b[ \t]*(?:\([ \t]*)?([^;]*);', re.I)
SHORT_TAG = re.compile(r'<\?(?!php|=)', re.I)
BLOCK_COMMENT = re.compile(r'/\*.*?\*/', re.S)
LINE_COMMENT = re.compile(r'^[ \t]*(?://|\*|#(?!\[)).*$', re.M)


def code_only(text):
    """Drop comments: doc examples must not look like real includes."""
    return LINE_COMMENT.sub('', BLOCK_COMMENT.sub('', text))

problems = []


def fail(message):
    problems.append(message)


def check_submitted(path):
    if not path.is_file():
        fail(f'{path.name}: missing')
        return
    raw = path.read_bytes()
    size = len(raw)
    if size == 0:
        fail(f'{path.name}: empty file')
    if size > 1024 * 1024:
        fail(f'{path.name}: {size} bytes exceeds the 1 MB per-file limit')
    text = raw.decode('utf-8')
    code = code_only(text)
    if SHORT_TAG.search(text):
        fail(f'{path.name}: short open tag is not supported')
    if '__halt_compiler' in code:
        fail(f'{path.name}: __halt_compiler() cannot be protected')
    if 'Plugin Name:' in text:
        fail(f'{path.name}: carries the plugin header; the main file must never be submitted')
    if 'RTL_LICENSE_CLASS' in text or 'RTL_LICENSE_HASH' in text:
        fail(f'{path.name}: contains the license Loader; the Loader file must never be submitted')
    for expression in INCLUDE.findall(code):
        if not expression.lstrip().startswith(SAFE_BASES):
            fail(f'{path.name}: pathless include/require -> {expression.strip()[:60]}')
    print(f'  {path.name}: {size} bytes, {len(INCLUDE.findall(code))} include/require statements, all with an explicit base')


def check_plain(path, needles):
    text = path.read_text()
    for needle in needles:
        if needle not in text:
            fail(f'{path.name}: expected {needle!r} to stay in the readable file')
    # Only the shipped main file may carry a plugin header; the no-license
    # template becomes that main file in its own build.
    if path.parent == ROOT and path.name != 'ashna.php' and 'Plugin Name:' in text:
        fail(f'{path.name}: second plugin header would be read by WordPress')


print('RTL-Theme protection readiness')
check_submitted(SUBMITTED)
check_plain(ROOT / 'ashna.php', ['Plugin Name:', 'ashna-license.php'])
check_plain(ROOT / 'ashna-license.php', ['RTL_LICENSE_CLASS', 'RTL_LICENSE_HASH',
                                         "require_once $ashna_core", 'ashna-core.php'])
check_plain(ROOT / 'scripts/templates/ashna-no-license.php', ['Requires PHP:', 'ashna-core.php'])

if problems:
    print('\nFAIL')
    for problem in problems:
        print(' - ' + problem)
    sys.exit(1)

print('PASS submission contract: only ashna-core.php is protected; ashna.php and '
      'ashna-license.php stay readable and are never sent.')
