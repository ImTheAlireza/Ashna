#!/bin/bash
# TEST ONLY — the whole load campaign against the CLI sandbox site.
# Phases are separate so a slow phase cannot hide a failure in another.
set -u
cd "$(dirname "$0")/../.."
export PHP_BINARY="$PWD/.cache/phpwasm/node_modules/.bin/php-wasm-cli"
export PHP=8.3
OUT=.cache/load
mkdir -p "$OUT"

# Make sure the sandbox site carries the current plugin sources (it holds a copy, not a symlink).
python tests/load/sync-site.py .cache/wp-cli-site

LOAD_SETTINGS="attack_mode_threshold=0,resend_cooldown=0,max_requests_per_hour=500,max_requests_per_day=50,max_ip_requests_per_minute=200,max_ip_requests_per_hour=2000,max_subnet_requests_per_minute=0,daily_send_cap=0,captcha_type=none,active_sms_gateway=sandbox,show_debug_code_in_toast=1"

echo "== s1 baseline: 27 sign-ins, one at a time"
python tests/load/load-multiproc.py --reset --settings="$LOAD_SETTINGS" \
  --workers=9 --concurrency=1 --flows=3 --label=s1-baseline --out="$OUT/s1-baseline.json" \
  > "$OUT/s1.log" 2>&1

echo "== s2 crowd: 600 sign-ins, 8 at a time"
python tests/load/load-multiproc.py --reset --settings="$LOAD_SETTINGS" \
  --workers=150 --concurrency=8 --flows=4 --label=s2-crowd --out="$OUT/s2-crowd.json" \
  > "$OUT/s2.log" 2>&1

echo "== s3 burst: 480 sign-ins, 32 processes at a time"
python tests/load/load-multiproc.py --settings="$LOAD_SETTINGS" --keep-logs \
  --workers=96 --concurrency=32 --flows=5 --label=s3-burst --out="$OUT/s3-burst.json" \
  > "$OUT/s3.log" 2>&1

echo "== s4 abuse mixed into the crowd: 360 sign-ins, every 2nd flow also fires a hostile request"
python tests/load/load-multiproc.py --settings="$LOAD_SETTINGS" --keep-logs \
  --workers=120 --concurrency=8 --flows=3 --hostile=2 --label=s4-hostile --out="$OUT/s4-hostile.json" \
  > "$OUT/s4.log" 2>&1

echo "== s5 session storm: 160 logins of the SAME account from 10 processes at a time"
python tests/load/load-multiproc.py --settings="$LOAD_SETTINGS" --keep-logs \
  --workers=40 --concurrency=10 --flows=4 --mode=same-user --label=s5-session-storm --out="$OUT/s5-storm.json" \
  > "$OUT/s5.log" 2>&1

echo "== s5b session storm with the limits opened: 160 sign-ins of ONE account, 10 at a time"
# s5 is limited by the per-number daily cap (correct behaviour, but it hides the session mechanics).
# With the caps raised, the same storm runs to completion, so concurrent session creation for one
# account is really exercised.
STORM_SETTINGS="attack_mode_threshold=0,resend_cooldown=0,max_requests_per_hour=100000,max_requests_per_day=100000,max_ip_requests_per_minute=100000,max_ip_requests_per_hour=100000,max_subnet_requests_per_minute=0,daily_send_cap=0,captcha_type=none,active_sms_gateway=sandbox,show_debug_code_in_toast=1"
python tests/load/load-multiproc.py --settings="$STORM_SETTINGS" --reset \
  --workers=40 --concurrency=10 --flows=4 --mode=same-user --label=s5b-storm --out="$OUT/s5b-storm.json" \
  > "$OUT/s5b.log" 2>&1

ATTACK_SETTINGS="attack_mode_threshold=20,attack_mode_minutes=30,resend_cooldown=0,max_requests_per_hour=500,max_requests_per_day=50,max_ip_requests_per_minute=200,max_ip_requests_per_hour=2000,max_subnet_requests_per_minute=0,daily_send_cap=0,captcha_type=math,active_sms_gateway=sandbox,show_debug_code_in_toast=1"

echo "== s6 under attack mode: the plugin's own flood protection is on"
python tests/load/load-multiproc.py --settings="$ATTACK_SETTINGS" --keep-logs \
  --workers=24 --concurrency=6 --flows=2 --label=s6-attack-mode --out="$OUT/s6-attack-mode.json" \
  > "$OUT/s6.log" 2>&1

echo "== s6b attack proof: 30 sign-ins while the flood protection is on (expects all of them to pass)"
python tests/load/load-multiproc.py --settings="$ATTACK_SETTINGS" --keep-logs \
  --workers=10 --concurrency=3 --flows=3 --label=s6b-attack-proof --out="$OUT/s6b-attack-proof.json" \
  > "$OUT/s6b.log" 2>&1

echo "== s7 soak: 400 sign-ins at 6 at a time with the log table growing"
python tests/load/load-multiproc.py --settings="$LOAD_SETTINGS" --keep-logs \
  --workers=100 --concurrency=6 --flows=4 --label=s7-soak --out="$OUT/s7-soak.json" \
  > "$OUT/s7.log" 2>&1

echo "== final state"
python tests/load/load-multiproc.py --settings="$LOAD_SETTINGS" --workers=1 --concurrency=1 --flows=1 --label=s8-final --out="$OUT/s8-final.json" > "$OUT/s8.log" 2>&1
echo "CAMPAIGN DONE"
