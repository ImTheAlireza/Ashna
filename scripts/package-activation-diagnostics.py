"""Build the standalone temporary diagnostic plugin; never rebuild Ashna itself."""
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib
import json

root = Path(__file__).resolve().parents[1]
source = root / 'tools/ashna-activation-diagnostics'
output = root / 'deliverables/ashna-activation-diagnostics.zip'
output.parent.mkdir(exist_ok=True)
names = ['ashna-activation-diagnostics.php', 'uninstall.php', 'README.md']
with ZipFile(output, 'w', compression=ZIP_DEFLATED) as archive:
    for name in names:
        info = ZipInfo('ashna-activation-diagnostics/' + name, (2026, 10, 13, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, (source / name).read_bytes())
with ZipFile(output) as archive:
    assert archive.testzip() is None
    for name in names:
        assert archive.read('ashna-activation-diagnostics/' + name) == (source / name).read_bytes()
record = {'file': output.name, 'bytes': output.stat().st_size,
          'sha256': hashlib.sha256(output.read_bytes()).hexdigest(), 'files': len(names)}
manifest = root / 'deliverables/checksums.json'
entries = json.loads(manifest.read_text()) if manifest.exists() else []
entries = [entry for entry in entries if entry['file'] != output.name]
entries.append(record)
manifest.write_text(json.dumps(entries, indent=2) + '\n')
print(json.dumps(record, indent=2))
