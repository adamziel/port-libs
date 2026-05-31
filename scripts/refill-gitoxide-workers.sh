#!/usr/bin/env bash
set -euo pipefail
umask 0022

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TMUX_SESSION="${TMUX_SESSION:-main}"
TARGET="${GITOXIDE_TARGET_WORKERS:-5}"
MAX_STARTS="${GITOXIDE_MAX_REFILL_STARTS:-6}"
LOCK_FILE="$ROOT/.tmux-team/tmp/refill-gitoxide-workers.lock"
LOG_DIR="$ROOT/.tmux-team/logs"
INDEX_FILE="$ROOT/.tmux-team/tmp/gitoxide-dynamic-domain-index"
LAUNCHED_FILE="$ROOT/.tmux-team/tmp/gitoxide-launched-slices.tsv"
mkdir -p "$LOG_DIR" "$ROOT/.tmux-team/tmp"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  exit 0
fi

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
log="$LOG_DIR/refill-gitoxide-workers-$stamp.log"
exec >>"$log" 2>&1

BASE_REF="${GITOXIDE_REFILL_BASE_REF:-origin/main}"
BASE_SHA="$(git rev-parse --verify "$BASE_REF")"

active_count() {
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    awk '/^port-dev-gitoxide-/ {count++} END {print count + 0}'
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
  session="port-dev-gitoxide-${slug}-${now}"
  if window_exists "$session"; then
    return 1
  fi
  printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice" >> "$LAUNCHED_FILE"
  printf '%s starting %s / %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice"
  tmux new-window -t "$TMUX_SESSION" -n "$session" \
    "cd '$ROOT' && ISOLATED_BASE_SHA='$BASE_SHA' AGENT_FAST_MODEL='gpt-5.5' AGENT_FAST_REASONING='xhigh' AGENT_FAST_SERVICE_TIER='priority' bash scripts/run-isolated-lane-worker.sh gitoxide '$slice' '$session'; bash scripts/refill-gitoxide-workers.sh --once"
}

slices=(
  "fetch-sideband:gitoxide-protocol-v2-fetch-response-sideband-parity"
  "send-pack:gitoxide-send-pack-receive-status-parity"
  "receive-pack:gitoxide-receive-pack-transport-boundary-parity"
  "smart-http:gitoxide-smart-http-transport-cookie-proxy-parity"
  "ssh-transport:gitoxide-ssh-receive-pack-boundary-parity"
  "reference-txn:gitoxide-reference-transaction-lock-reflog-parity"
  "tree-merge:gitoxide-tree-merge-conflict-fixture-parity"
  "pack-index:gitoxide-pack-index-midx-prefix-parity"
  "partial-clone:gitoxide-partial-clone-promisor-hydration-parity"
  "credential:gitoxide-credential-helper-context-parity"
  "config-include:gitoxide-config-include-conditional-parity"
  "attrs-pathspec:gitoxide-attributes-pathspec-match-parity"
  "loose-object:gitoxide-loose-object-integrity-parity"
  "sparse-checkout:gitoxide-sparse-checkout-patternspec-parity"
  "tree-pathspec:gitoxide-tree-pathspec-walk-parity"
  "url-refspec:gitoxide-url-refspec-parse-normalize-parity"
  "merge-base:gitoxide-merge-base-graph-walk-parity"
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

printf '%s active_gitoxide=%s starts=%s target=%s base=%s\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$(active_count)" "$starts" "$TARGET" "$BASE_SHA"
