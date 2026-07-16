#!/usr/bin/env bash
set -euo pipefail
umask 0022

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TMUX_SESSION="${TMUX_SESSION:-main}"
TARGET_TOTAL="${PORT_LIBS_TARGET_DEV_WORKERS:-11}"
TARGET_MARKERPDF="${MARKERPDF_TARGET_WORKERS:-8}"
MAX_STARTS="${MARKERPDF_MAX_REFILL_STARTS:-4}"
LOCK_FILE="$ROOT/.tmux-team/tmp/refill-markerpdf-workers.lock"
LOG_DIR="$ROOT/.tmux-team/logs"
INDEX_FILE="$ROOT/.tmux-team/tmp/markerpdf-dynamic-domain-index"
LAUNCHED_FILE="$ROOT/.tmux-team/tmp/markerpdf-launched-slices.tsv"
HANDOFF_CANDIDATES_DIR="$ROOT/.tmux-team/tmp/handoff-candidates"
HANDOFF_CONSUMED_DIR="$ROOT/.tmux-team/tmp/handoff-consumed"
mkdir -p "$LOG_DIR" "$ROOT/.tmux-team/tmp" "$HANDOFF_CANDIDATES_DIR" "$HANDOFF_CONSUMED_DIR"

source "$ROOT/scripts/agent-fast-profile.sh"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  exit 0
fi

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
log="$LOG_DIR/refill-markerpdf-workers-$stamp.log"
exec >>"$log" 2>&1

BASE_REF="${MARKERPDF_REFILL_BASE_REF:-origin/main}"
BASE_SHA="$(git rev-parse --verify "$BASE_REF")"

CURRENT_TMUX_WINDOW=""
if [[ -n "${TMUX_PANE:-}" ]]; then
  CURRENT_TMUX_WINDOW="$(tmux display-message -p -t "$TMUX_PANE" '#W' 2>/dev/null || true)"
fi

active_dev_codex_count() {
  ps -eo args= |
    awk '$1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\/port-dev-/ {count++} END {print count + 0}'
}

active_markerpdf_count() {
  ps -eo args= |
    awk '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "markerpdf" {
        print $5
      }
      $1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\/port-dev-markerpdf-/ {
        match($0, /port-dev-markerpdf-[^\/ ]+-[0-9TZ]+/)
        if (RSTART) {
          session = substr($0, RSTART, RLENGTH)
          sub(/-[0-9]{8}T[0-9]{6}Z$/, "", session)
          print session
        }
      }
    ' |
    sort -u |
    wc -l
}

active_markerpdf_window_count() {
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    awk '/^port-dev-markerpdf-/ {count++} END {print count + 0}'
}

active_session_exists() {
  local name="$1"
  ps -eo args= |
    awk -v name="$name" '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "markerpdf" && $5 == name {found=1}
      $1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\// && index($0, "/" name "-") {found=1}
      END {exit found ? 0 : 1}
    '
}

active_markerpdf_slice_contains() {
  local needle="$1"
  ps -eo args= |
    awk -v needle="$needle" '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "markerpdf" && index($4, needle) {found=1}
      END {exit found ? 0 : 1}
    '
}

ready_markerpdf_slice_contains() {
  local needle="$1"
  find "$HANDOFF_CANDIDATES_DIR" "$HANDOFF_CONSUMED_DIR" -maxdepth 3 -type f -name 'port-dev-markerpdf-*.ready' \
    -exec awk -F= -v needle="$needle" '$1 == "slice" && index($2, needle) {print $2}' {} + 2>/dev/null |
    grep -q .
}

ready_exists_for_session() {
  local name="$1"
  find "$HANDOFF_CANDIDATES_DIR" "$HANDOFF_CONSUMED_DIR" -maxdepth 3 -type f -name "${name}-*.ready" -print -quit 2>/dev/null |
    grep -q .
}

close_completed_ready_windows() {
  local name
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    while IFS= read -r name; do
      [[ "$name" == port-dev-markerpdf-* ]] || continue
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
  session="port-dev-markerpdf-${slug}-${now}"
  if window_exists "$session"; then
    return 1
  fi
  printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice" >> "$LAUNCHED_FILE"
  printf '%s starting %s / %s base=%s reason=%s\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice" "$BASE_SHA" "${MARKERPDF_REFILL_REASON:-manual}"
  tmux new-window -t "$TMUX_SESSION" -n "$session" \
    "cd '$ROOT' && ISOLATED_BASE_SHA='$BASE_SHA' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' bash scripts/run-isolated-lane-worker.sh markerpdf '$slice' '$session'"
}

slices=(
  "pdftext-dictionary:markerpdf-pdftext-dictionary-core-boundary-current-base"
  "malformed-cmap:markerpdf-malformed-cmap-filter-boundary-current-base"
  "object-xref:markerpdf-object-stream-xref-parser-current-base"
  "resource-inherit:markerpdf-page-resource-inheritance-current-base"
  "source-width:markerpdf-cmap-source-width-fallback-current-base"
  "image-xobject:markerpdf-image-xobject-boundary-current-base"
  "outline-meta:markerpdf-outline-metadata-boundary-current-base"
  "table-geometry:markerpdf-table-geometry-boundary-current-base"
  "runtime-preflight:markerpdf-runtime-preflight-boundary-current-base"
  "inline-image:markerpdf-inline-image-tokenizer-boundary-current-base"
  "pdf-dictionary-layout:markerpdf-pdftext-dictionary-layout-order-boundary-current-base"
  "stream-filter-stack:markerpdf-stream-filter-stack-boundary-current-base"
  "font-width-advance:markerpdf-font-width-advance-boundary-current-base"
  "type3-charprocs:markerpdf-type3-charprocs-boundary-current-base"
  "annotations-links:markerpdf-annotations-links-boundary-current-base"
  "metadata-xmp:markerpdf-xmp-metadata-boundary-current-base"
  "inline-image-decode:markerpdf-inline-image-decode-boundary-current-base"
  "xref-classic-rebuild:markerpdf-classic-xref-rebuild-boundary-current-base"
  "encrypted-preflight:markerpdf-encrypted-permissions-preflight-current-base"
  "attachments:markerpdf-embedded-files-attachments-boundary-current-base"
  "xref-prev-chain:markerpdf-xref-prev-chain-incremental-update-current-base"
  "named-destinations:markerpdf-named-destinations-boundary-current-base"
  "page-labels:markerpdf-page-labels-boundary-current-base"
  "acroform-fields:markerpdf-acroform-fields-boundary-current-base"
  "dctdecode-filter:markerpdf-dctdecode-filter-boundary-current-base"
  "ccitt-fax-filter:markerpdf-ccitt-fax-filter-boundary-current-base"
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
while [[ "$(active_dev_codex_count)" -lt "$TARGET_TOTAL" && "$(active_markerpdf_count)" -lt "$TARGET_MARKERPDF" && "$starts" -lt "$MAX_STARTS" ]]; do
  tried=0
  while [[ "$tried" -lt "${#slices[@]}" ]]; do
    entry="${slices[$((idx % ${#slices[@]}))]}"
    slug="${entry%%:*}"
    base_slice="${entry#*:}"
    idx=$((idx + 1))
    printf '%s\n' "$idx" > "$INDEX_FILE"
    tried=$((tried + 1))
    if active_markerpdf_slice_contains "$base_slice"; then
      continue
    fi
    slice="${base_slice}-$(date -u +%Y%m%dT%H%M%SZ)"
    start_worker "$slug" "$slice" || true
    starts=$((starts + 1))
    break
  done
  [[ "$tried" -lt "${#slices[@]}" ]] || break
done

printf '%s active_dev_codex=%s active_markerpdf=%s markerpdf_windows=%s starts=%s target_total=%s target_markerpdf=%s base=%s profile=%s/%s/%s\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$(active_dev_codex_count)" "$(active_markerpdf_count)" "$(active_markerpdf_window_count)" "$starts" \
  "$TARGET_TOTAL" "$TARGET_MARKERPDF" "$BASE_SHA" "$AGENT_FAST_MODEL" "$AGENT_FAST_REASONING" "$AGENT_FAST_SERVICE_TIER"
