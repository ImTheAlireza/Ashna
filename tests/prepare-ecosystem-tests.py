"""Fetch only pinned upstream PHP sources into ignored cache; never bundle vendor plugins."""
from pathlib import Path
import base64
import hashlib
import json
import subprocess
ROOT = Path(__file__).resolve().parents[1]
for row in json.loads((ROOT / 'tests/fixtures/ecosystem-sources.json').read_text()):
    destination = ROOT / '.cache/ecosystem' / row['name'] / row['path']
    if destination.is_file() and hashlib.sha256(destination.read_bytes()).hexdigest() == row['sha256']:
        continue
    payload = json.loads(subprocess.check_output(['gh', 'api', f"repos/{row['repo']}/contents/{row['path']}?ref={row['sha']}"]))
    content = base64.b64decode(payload['content'])
    assert hashlib.sha256(content).hexdigest() == row['sha256'], destination
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_bytes(content)
print('PASS: pinned Dokan/PMPro/UM policy sources verified; no proprietary MemberPress source fetched.')
