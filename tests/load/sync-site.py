#!/usr/bin/env python3
"""TEST ONLY — copy the working plugin into a sandbox site (the load/browser sites get a copy,
not a symlink, so they must be refreshed after every plugin edit).

usage: python tests/load/sync-site.py <site-dir> [<site-dir> …]
"""
import shutil
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def sync(site: Path) -> None:
    dest = site / 'wp-content/plugins/ashna'
    if dest.exists():
        shutil.rmtree(dest)
    dest.mkdir(parents=True)
    for folder in ['assets', 'includes', 'languages', 'templates']:
        src = ROOT / folder
        if src.exists():
            shutil.copytree(src, dest / folder)
    for name in ['uninstall.php', 'README.md', 'index.php']:
        shutil.copy2(ROOT / name, dest / name)
    shutil.copy2(ROOT / 'scripts/templates/ashna-no-license.php', dest / 'ashna.php')
    print(f'synced plugin -> {dest}')


if __name__ == '__main__':
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    for arg in sys.argv[1:]:
        sync(Path(arg).resolve())
