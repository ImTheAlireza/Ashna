"""Rebuild deliverables/ashna-landing.zip and refresh its checksum entry."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / 'ashna-landing'
OUT = ROOT / 'deliverables/ashna-landing.zip'
OUT.parent.mkdir(exist_ok=True)

files = []
with ZipFile(OUT, 'w', ZIP_DEFLATED, compresslevel=9) as bundle:
    for source in sorted(SRC.rglob('*')):
        if source.is_file() and '__pycache__' not in source.parts:
            name = 'ashna-landing/' + source.relative_to(SRC).as_posix()
            bundle.write(source, name)
            files.append(name)

with ZipFile(OUT) as bundle:
    assert bundle.testzip() is None
    assert sorted(bundle.namelist()) == sorted(files)

checksums = ROOT / 'deliverables/checksums.json'
entries = json.loads(checksums.read_text())
entries = [entry for entry in entries if entry['file'] != OUT.name]
entries.append({
    'file': OUT.name,
    'bytes': OUT.stat().st_size,
    'sha256': hashlib.sha256(OUT.read_bytes()).hexdigest(),
    'files': len(files),
})
checksums.write_text(json.dumps(entries, indent=2) + '\n')
print(json.dumps({'file': OUT.name, 'bytes': OUT.stat().st_size, 'files': len(files)}, ensure_ascii=False))
