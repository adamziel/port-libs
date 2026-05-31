#!/usr/bin/env bash
set -euo pipefail
umask 0022

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TMUX_SESSION="${TMUX_SESSION:-main}"
TARGET="${LIGHTNINGCSS_TARGET_WORKERS:-43}"
TARGET_FLOOR="${LIGHTNINGCSS_TARGET_FLOOR:-0}"
if [[ "$TARGET_FLOOR" =~ ^[0-9]+$ && "$TARGET" =~ ^[0-9]+$ && "$TARGET" -lt "$TARGET_FLOOR" ]]; then
  TARGET="$TARGET_FLOOR"
fi
TARGET_CEILING="${LIGHTNINGCSS_TARGET_CEILING:-6}"
if [[ "$TARGET_CEILING" =~ ^[0-9]+$ && "$TARGET" =~ ^[0-9]+$ && "$TARGET_CEILING" -gt 0 && "$TARGET" -gt "$TARGET_CEILING" ]]; then
  TARGET="$TARGET_CEILING"
fi
MAX_STARTS="${LIGHTNINGCSS_MAX_REFILL_STARTS:-4}"
source "$ROOT/scripts/agent-fast-profile.sh"
LOCK_FILE="$ROOT/.tmux-team/tmp/refill-lightningcss-workers.lock"
LOG_DIR="$ROOT/.tmux-team/logs"
INDEX_FILE="$ROOT/.tmux-team/tmp/lightningcss-dynamic-domain-index"
LAUNCHED_FILE="$ROOT/.tmux-team/tmp/lightningcss-launched-slices.tsv"
HANDOFF_CANDIDATES_DIR="$ROOT/.tmux-team/tmp/handoff-candidates"
HANDOFF_CONSUMED_DIR="$ROOT/.tmux-team/tmp/handoff-consumed"
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

CURRENT_TMUX_WINDOW=""
if [[ -n "${TMUX_PANE:-}" ]]; then
  CURRENT_TMUX_WINDOW="$(tmux display-message -p -t "$TMUX_PANE" '#W' 2>/dev/null || true)"
fi

active_count() {
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    awk '/^port-dev-lightningcss-/ {count++} END {print count + 0}'
}

active_session_exists() {
  local name="$1"
  ps -eo args= |
    awk -v name="$name" '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "lightningcss" && $5 == name {found=1}
      $1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\// && index($0, "/" name "-") {found=1}
      END {exit found ? 0 : 1}
    '
}

ready_exists_for_session() {
  local name="$1"
  compgen -G "$HANDOFF_CANDIDATES_DIR/${name}-*.ready" >/dev/null ||
    compgen -G "$HANDOFF_CONSUMED_DIR/${name}-*.ready" >/dev/null
}

close_completed_ready_windows() {
  local name
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    while IFS= read -r name; do
      [[ "$name" == port-dev-lightningcss-* ]] || continue
      [[ "$name" != "$CURRENT_TMUX_WINDOW" ]] || continue
      if ! active_session_exists "$name" && ready_exists_for_session "$name"; then
        printf '%s closing completed ready pane %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$name"
        tmux kill-window -t "$TMUX_SESSION:$name" || true
      fi
    done
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
close_completed_ready_windows || true
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
