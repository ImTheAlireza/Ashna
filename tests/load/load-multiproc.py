#!/usr/bin/env python3
"""TEST ONLY — multi-process load test: run many real PHP processes against one sandbox database.

The HTTP sandbox (WordPress Playground) serves one PHP request at a time, so it cannot represent
thousands of *simultaneous* visitors. This driver can: every worker is its own PHP process doing
complete OTP sign-in flows against the same database, so sessions, transients, counters and the
OTP log are written concurrently — the part that actually breaks under load.

usage:
  PHP_BINARY=.cache/phpwasm/node_modules/.bin/php-wasm-cli \\
  python tests/load/load-multiproc.py --workers=40 --concurrency=8 --flows=3 [--hostile=0] \\
      [--site=.cache/wp-cli-site] [--php=8.3] [--label=burst]
"""
from pathlib import Path
import argparse
import json
import os
import statistics
import subprocess
import sys
import time

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--workers', type=int, default=40, help='total worker processes')
parser.add_argument('--concurrency', type=int, default=8, help='how many run at the same time')
parser.add_argument('--flows', type=int, default=3, help='sign-in flows per worker')
parser.add_argument('--hostile', type=int, default=0, help='every N-th flow also fires an abusive request')
parser.add_argument('--site', default='.cache/wp-cli-site')
parser.add_argument('--php', default='8.3')
parser.add_argument('--first-user', type=int, default=0)
parser.add_argument('--stride', type=int, default=0, help='user index step between workers (default: flows)')
parser.add_argument('--label', default='run')
parser.add_argument('--mode', default='otp', help='otp | same-user')
parser.add_argument('--settings', default='', help='key=value,key=value applied before the run')
parser.add_argument('--reset', action='store_true', help='clear logs, lockouts and attack mode first')
parser.add_argument('--keep-logs', action='store_true')
parser.add_argument('--out', default='')
parser.add_argument('--seed', type=int, default=0, help='create that many load accounts in the site first')
parser.add_argument('--seed-only', action='store_true')
parser.add_argument('--purge', action='store_true', help='delete the load accounts instead of running')
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[2]
SITE = (ROOT / args.site).resolve()
PHP = os.environ.get('PHP_BINARY', 'php')
WORKER = ROOT / 'tests/load/load-worker.php'
STRIDE = args.stride or args.flows


BOOT_TEMPLATE = '''<?php
ob_start();
define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST'] = '127.0.0.1:9400';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require __DIR__ . '/wp-load.php';
$argv = array( 'worker', '%(index)d', '%(flows)d', '%(mode)s', '%(hostile)d', '%(ipbase)s' );
require %(worker)s;
'''


def run_one(index):
    """One PHP process: its own bootstrap file (so no globals leak between workers) and a whole
    batch of sign-in flows."""
    boot = SITE / ('_ashna_load_%d.php' % index)
    boot.write_text(BOOT_TEMPLATE % {
        'index': index,
        'flows': args.flows,
        'mode': args.mode,
        'hostile': args.hostile,
        'mode': args.mode,
        'ipbase': '203.%d.%d.' % (60 + index % 60, index % 250),
        'worker': repr(str(WORKER)),
    })
    started = time.time()
    try:
        proc = subprocess.run(
            [PHP, boot.name],
            capture_output=True, text=True, cwd=SITE, env={**os.environ, 'PHP': args.php},
        )
    finally:
        boot.unlink()
    wall = time.time() - started
    line = ''
    for candidate in reversed((proc.stdout or '').strip().splitlines()):
        if candidate.strip().startswith('{'):
            line = candidate.strip()
            break
    if not line:
        return {
            'worker': index,
            'crash': (proc.stderr or proc.stdout or 'no output')[-500:],
            'wall': round(wall, 2), 'flows': [], 'db_error': {},
        }
    data = json.loads(line)
    data['wall'] = round(wall, 2)
    return data


def run_php(code_files, args_line=''):
    """One-off PHP process in the site (same bootstrap shape as the workers)."""
    boot = SITE / '_ashna_load_once.php'
    body = (
        "<?php\n"
        "ob_start();\n"
        "define( 'WP_USE_THEMES', false );\n"
        "$_SERVER['HTTP_HOST'] = '127.0.0.1:9400';\n"
        "$_SERVER['REQUEST_URI'] = '/';\n"
        "$_SERVER['SERVER_NAME'] = '127.0.0.1';\n"
        "$_SERVER['REMOTE_ADDR'] = '127.0.0.1';\n"
        "require __DIR__ . '/wp-load.php';\n"
    )
    if args_line:
        body += "$argv = array( 'once'%s );\n" % args_line
    for f in code_files:
        body += "require %r;\n" % str(ROOT / f)
    boot.write_text(body)
    try:
        proc = subprocess.run([PHP, boot.name], capture_output=True, text=True, cwd=SITE, env={**os.environ, 'PHP': args.php})
    finally:
        boot.unlink()
    return proc


def pct(values, p):
    if not values:
        return 0
    values = sorted(values)
    return values[min(len(values) - 1, int(p / 100 * len(values)))]


def main():
    if args.purge:
        proc = run_php(['tests/load/seed-users.php'], ", '--purge'")
        print('purge:', (proc.stdout or proc.stderr).strip().splitlines()[-1] if (proc.stdout or proc.stderr).strip() else 'no output')
        return

    if args.reset:
        proc = run_php(['tests/load/reset-state.php'], ",'--keep-logs'" if args.keep_logs else '')
        print('reset:', (proc.stdout or proc.stderr).strip().splitlines()[-1] if (proc.stdout or proc.stderr).strip() else 'no output')

    if args.settings:
        proc = run_php(['tests/load/set-settings.php'], ", %r" % args.settings)
        print('settings:', (proc.stdout or proc.stderr).strip().splitlines()[-1] if (proc.stdout or proc.stderr).strip() else 'no output')

    if args.seed:
        proc = run_php(['tests/load/seed-users.php'], ", '%d', '0'" % args.seed)
        print('seed:', (proc.stdout or proc.stderr).strip().splitlines()[-1] if (proc.stdout or proc.stderr).strip() else 'no output')
        if args.seed_only:
            return

    results = []
    pending = list(range(args.first_user, args.first_user + args.workers * STRIDE, STRIDE))
    started = time.time()
    batches = [pending[i:i + args.concurrency] for i in range(0, len(pending), args.concurrency)]
    for n, batch in enumerate(batches, 1):
        results.extend(run_one(i) for i in batch)
        done = sum(len(r.get('flows', [])) for r in results)
        print(f'\r  batch {n}/{len(batches)} — {done} flows done', end='', flush=True)
    wall = time.time() - started
    print()

    flows = [f for r in results for f in r.get('flows', [])]
    ok = [f for f in flows if f.get('result') == 'ok']
    mismatched = [f for f in flows if f.get('result') == 'mismatch']
    no_code = [f for f in flows if f.get('result') == 'no-code']
    crashes = [r for r in results if r.get('crash')]
    db_errors = sorted({r['db_error'].get('last', '') for r in results if r.get('db_error', {}).get('last')})

    report = {
        'label': args.label,
        'workers': args.workers,
        'concurrency': args.concurrency,
        'flowsPerWorker': args.flows,
        'hostileEvery': args.hostile,
        'totalFlows': len(flows),
        'successfulSignIns': len(ok),
        'sessionMismatch': len(mismatched),
        'noCodeIssued': len(no_code),
        'workerCrashes': len(crashes),
        'wallSeconds': round(wall, 1),
        'flowsPerSecond': round(len(ok) / wall, 2) if wall else 0,
        'askMs': {'p50': pct([f.get('ask_ms', 0) for f in flows], 50), 'p95': pct([f.get('ask_ms', 0) for f in flows], 95), 'max': max([f.get('ask_ms', 0) for f in flows] or [0])},
        'verifyMs': {'p50': pct([f.get('verify_ms', 0) for f in flows], 50), 'p95': pct([f.get('verify_ms', 0) for f in flows], 95), 'max': max([f.get('verify_ms', 0) for f in flows] or [0])},
        'meanWorkerWall': round(statistics.mean([r.get('wall', 0) for r in results]), 2),
        'peakMemoryMb': max([r.get('peak_mb', 0) for r in results] or [0]),
        'databaseErrors': db_errors[:6],
        'failureExamples': (mismatched + no_code)[:5],
        'crashExamples': [{'worker': c['worker'], 'tail': c['crash'][-200:]} for c in crashes[:3]],
        'nonOkCodes': {},
    }
    for f in flows:
        if f.get('result') != 'ok':
            key = f.get('ask', '') + '/' + f.get('verify', '')
            report['nonOkCodes'][key] = report['nonOkCodes'].get(key, 0) + 1

    print(json.dumps(report, indent=2, ensure_ascii=False))
    if args.out:
        out = Path(args.out)
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(json.dumps(report, indent=2, ensure_ascii=False))
        print('\nwritten to ' + str(out))
    sys.exit(1 if (mismatched or crashes) else 0)


main()
