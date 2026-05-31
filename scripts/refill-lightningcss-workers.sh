#!/usr/bin/env bash
set -euo pipefail
umask 0022

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TMUX_SESSION="${TMUX_SESSION:-main}"
TARGET="${LIGHTNINGCSS_TARGET_WORKERS:-43}"
MAX_STARTS="${LIGHTNINGCSS_MAX_REFILL_STARTS:-4}"
source "$ROOT/scripts/agent-fast-profile.sh"
LOCK_FILE="$ROOT/.tmux-team/tmp/refill-lightningcss-workers.lock"
LOG_DIR="$ROOT/.tmux-team/logs"
INDEX_FILE="$ROOT/.tmux-team/tmp/lightningcss-dynamic-domain-index"
LAUNCHED_FILE="$ROOT/.tmux-team/tmp/lightningcss-launched-slices.tsv"
mkdir -p "$LOG_DIR" "$ROOT/.tmux-team/tmp"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  exit 0
fi

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
log="$LOG_DIR/refill-lightningcss-workers-$stamp.log"
exec >>"$log" 2>&1

BASE_REF="${LIGHTNINGCSS_REFILL_BASE_REF:-origin/main}"
BASE_SHA="$(git rev-parse --verify "$BASE_REF")"

active_count() {
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    awk '/^port-dev-lightningcss-/ {count++} END {print count + 0}'
}

window_exists() {
  local name="$1"
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null | grep -Fx "$name" >/dev/null 2>&1
}

start_worker() {
  local slug="$1"
  local slice="$2"
  local now session
  now="$(date -u +%Y%m%dT%H%M%SZ)"
  session="port-dev-lightningcss-${slug}-${now}"
  if window_exists "$session"; then
    return 1
  fi
  printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice" >> "$LAUNCHED_FILE"
  printf '%s starting %s / %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice"
  tmux new-window -t "$TMUX_SESSION" -n "$session" \
    "cd '$ROOT' && ISOLATED_BASE_SHA='$BASE_SHA' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' bash scripts/run-isolated-lane-worker.sh lightningcss '$slice' '$session'; LIGHTNINGCSS_TARGET_WORKERS='$TARGET' LIGHTNINGCSS_MAX_REFILL_STARTS='$MAX_STARTS' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' bash scripts/refill-lightningcss-workers.sh --once"
}

slices=(
  "bundle-import:lightningcss-bundle-resolution-import-graph-parity"
  "source-map:lightningcss-source-map-vlq-offsets-parity"
  "css-modules:lightningcss-css-modules-local-global-compose-parity"
  "cssom:lightningcss-cssom-declaration-read-write-parity"
  "custom-at-rules:lightningcss-custom-at-rules-parser-visitor-parity"
  "targets-prefix:lightningcss-target-prefixing-browser-boundary-parity"
  "media-query:lightningcss-media-query-range-layer-parity"
  "property-values:lightningcss-property-values-color-font-grid-parity"
)

idx=0
if [[ -s "$INDEX_FILE" ]]; then
  idx="$(<"$INDEX_FILE")"
fi
if ! [[ "$idx" =~ ^[0-9]+$ ]]; then
  idx=0
fi

starts=0
while [[ "$(active_count)" -lt "$TARGET" && "$starts" -lt "$MAX_STARTS" ]]; do
  entry="${slices[$((idx % ${#slices[@]}))]}"
  slug="${entry%%:*}"
  base_slice="${entry#*:}"
  idx=$((idx + 1))
  printf '%s\n' "$idx" > "$INDEX_FILE"
  slice="${base_slice}-$(date -u +%Y%m%dT%H%M%SZ)"
  start_worker "$slug" "$slice" || true
  starts=$((starts + 1))
done

printf '%s active_lightningcss=%s starts=%s target=%s base=%s profile=%s/%s/%s\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$(active_count)" "$starts" "$TARGET" "$BASE_SHA" \
  "$AGENT_FAST_MODEL" "$AGENT_FAST_REASONING" "$AGENT_FAST_SERVICE_TIER"
