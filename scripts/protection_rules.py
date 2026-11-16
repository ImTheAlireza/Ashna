"""RTL-Theme file-protection rules, in one place.

Used by tests/run-protection-readiness.py (the contract test) and by
scripts/prepare-protection-batches.py (the upload batches), so both always
agree on what may be submitted.

Source: rtl-theme.com/designer → «ارسال محصول» → شرایط و محدودیت‌های محافظت فایل
  - plain PHP or mixed PHP/HTML, up to 1 MB per file, never empty
  - no short tags, no __halt_compiler()
  - include/require must have an explicit path base
  - the main plugin file and the file holding the license Loader are excluded
"""
from pathlib import Path
import re

MAX_BYTES = 1024 * 1024
SAFE_BASES = ('__DIR__', '__FILE__', 'plugin_dir_path(', 'plugin_dir_url(', 'ABSPATH',
              'WP_PLUGIN_DIR', 'WP_CONTENT_DIR', 'get_template_directory(',
              'get_stylesheet_directory(')
INCLUDE = re.compile(r'\b(?:require|include)(?:_once)?\b[ \t]*(?:\([ \t]*)?([^;]*);', re.I)
# A variable that was itself built from a portable base (e.g. `$partials_dir =
# __DIR__ . '/partials/'`) counts as an explicit base: RTL-Theme's rule is about
# paths that do not resolve relative to the file, not about literal strings.
ASSIGN_BASES = SAFE_BASES + ('locate_template(', 'get_theme_file_path(')
ASSIGN = re.compile(r'(\$[A-Za-z_]\w*)\s*=\s*(?:' +
                    '|'.join(re.escape(base) for base in ASSIGN_BASES) + ')')
LEADING_VAR = re.compile(r'^\$([A-Za-z_]\w*)')
SHORT_TAG = re.compile(r'<\?(?!php|=)', re.I)
BLOCK_COMMENT = re.compile(r'/\*.*?\*/', re.S)
LINE_COMMENT = re.compile(r'^[ \t]*(?://|\*|#(?!\[)).*$', re.M)
IONCUBE = re.compile(rb'^<\?php\s*//(?:ICB0|[0-9A-F]{5})')


def code_only(text):
    """Drop comments: documentation examples must not look like real code."""
    return LINE_COMMENT.sub('', BLOCK_COMMENT.sub('', text))


def is_silence_stub(path):
    """`<?php // Silence is golden.` — RTL-Theme rejects empty files."""
    code = code_only(Path(path).read_text(errors='ignore'))
    return not code.replace('<?php', '', 1).strip()


def check_file(path):
    """Return the list of rule violations for one file (empty list = OK)."""
    path = Path(path)
    problems = []
    if not path.is_file():
        return [f'{path}: missing']
    raw = path.read_bytes()
    if not raw:
        return [f'{path}: empty file']
    if len(raw) > MAX_BYTES:
        problems.append(f'{path}: {len(raw)} bytes exceeds the 1 MB limit')
    text = raw.decode('utf-8')
    code = code_only(text)
    if SHORT_TAG.search(text):
        problems.append(f'{path}: short open tag')
    if '__halt_compiler' in code:
        problems.append(f'{path}: __halt_compiler()')
    if 'Plugin Name:' in text:
        problems.append(f'{path}: plugin header (the main file is never submitted)')
    if 'RTL_LICENSE_CLASS' in text or 'RTL_LICENSE_HASH' in text:
        problems.append(f'{path}: license Loader (that file is never submitted)')
    safe_variables = {match.group(1) for match in ASSIGN.finditer(code)}
    for expression in INCLUDE.findall(code):
        stripped = expression.lstrip()
        if stripped.startswith(SAFE_BASES):
            continue
        variable = LEADING_VAR.match(stripped)
        if variable and ('$' + variable.group(1)) in safe_variables:
            continue
        problems.append(f'{path}: pathless include/require -> {stripped[:60]}')
    return problems


def is_ioncube(path):
    """Was this file replaced by RTL-Theme's protected build?"""
    with open(path, 'rb') as handle:
        return IONCUBE.match(handle.read(64)) is not None
