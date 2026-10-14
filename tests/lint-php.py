"""Lint repository PHP sources without loading WordPress or the vendor license."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import os
import subprocess
ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', 'php')
files = [p for p in ROOT.rglob('*.php') if not set(p.relative_to(ROOT).parts) & {'.git', '.cache', '.venv', 'node_modules'}]
def lint(path):
    result = subprocess.run([PHP, '-l', str(path)], capture_output=True, text=True)
    output = result.stdout + result.stderr
    assert result.returncode == 0 and 'No syntax errors detected' in output, f'{path}: {output}'
with ThreadPoolExecutor(max_workers=4) as pool:
    list(pool.map(lint, files))
print(f'PASS: {len(files)} PHP files linted.')
