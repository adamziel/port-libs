#!/usr/bin/env bash
set -euo pipefail
umask 0022

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TMUX_SESSION="${TMUX_SESSION:-main}"
TARGET_TOTAL="${PORT_LIBS_TARGET_DEV_WORKERS:-11}"
TARGET_PANDOC="${PANDOC_TARGET_WORKERS:-3}"
MAX_STARTS="${PANDOC_MAX_REFILL_STARTS:-3}"
LOCK_FILE="$ROOT/.tmux-team/tmp/refill-pandoc-workers.lock"
LOG_DIR="$ROOT/.tmux-team/logs"
INDEX_FILE="$ROOT/.tmux-team/tmp/pandoc-dynamic-domain-index"
LAUNCHED_FILE="$ROOT/.tmux-team/tmp/pandoc-launched-slices.tsv"
HANDOFF_CANDIDATES_DIR="$ROOT/.tmux-team/tmp/handoff-candidates"
HANDOFF_CONSUMED_DIR="$ROOT/.tmux-team/tmp/handoff-consumed"
mkdir -p "$LOG_DIR" "$ROOT/.tmux-team/tmp" "$HANDOFF_CANDIDATES_DIR" "$HANDOFF_CONSUMED_DIR"

source "$ROOT/scripts/agent-fast-profile.sh"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  exit 0
fi

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
log="$LOG_DIR/refill-pandoc-workers-$stamp.log"
exec >>"$log" 2>&1

BASE_REF="${PANDOC_REFILL_BASE_REF:-origin/main}"
BASE_SHA="$(git rev-parse --verify "$BASE_REF")"

CURRENT_TMUX_WINDOW=""
if [[ -n "${TMUX_PANE:-}" ]]; then
  CURRENT_TMUX_WINDOW="$(tmux display-message -p -t "$TMUX_PANE" '#W' 2>/dev/null || true)"
fi

active_dev_codex_count() {
  ps -eo args= |
    awk '$1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\/port-dev-/ {count++} END {print count + 0}'
}

active_pandoc_count() {
  ps -eo args= |
    awk '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "pandoc" {
        print $5
      }
      $1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\/port-dev-pandoc-/ {
        match($0, /port-dev-pandoc-[^\/ ]+-[0-9TZ]+/)
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

active_pandoc_window_count() {
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    awk '/^port-dev-pandoc-/ {count++} END {print count + 0}'
}

active_session_exists() {
  local name="$1"
  ps -eo args= |
    awk -v name="$name" '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "pandoc" && $5 == name {found=1}
      $1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\// && index($0, "/" name "-") {found=1}
      END {exit found ? 0 : 1}
    '
}

ready_exists_for_session() {
  local name="$1"
  find "$HANDOFF_CANDIDATES_DIR" "$HANDOFF_CONSUMED_DIR" -maxdepth 3 -type f -name "${name}-*.ready" -print -quit 2>/dev/null |
    grep -q .
}

active_pandoc_slice_contains() {
  local needle="$1"
  ps -eo args= |
    awk -v needle="$needle" '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == "pandoc" && index($4, needle) {found=1}
      END {exit found ? 0 : 1}
    '
}

close_completed_ready_windows() {
  local name
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    while IFS= read -r name; do
      [[ "$name" == port-dev-pandoc-* ]] || continue
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
  session="port-dev-pandoc-${slug}-${now}"
  if window_exists "$session"; then
    return 1
  fi
  printf '%s\t%s\t%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice" >> "$LAUNCHED_FILE"
  printf '%s starting %s / %s base=%s reason=%s\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice" "$BASE_SHA" "${PANDOC_REFILL_REASON:-manual}"
  tmux new-window -t "$TMUX_SESSION" -n "$session" \
    "cd '$ROOT' && ISOLATED_BASE_SHA='$BASE_SHA' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' bash scripts/run-isolated-lane-worker.sh pandoc '$slice' '$session'"
}

slices=(
  "zip-package:pandoc-shared-zip-package-core-current-base"
  "opc-relationships:pandoc-opc-xml-relationships-core-current-base"
  "xml-html5-dom:pandoc-xml-html5-dom-core-current-base"
  "doctemplates:pandoc-doctemplates-core-current-base"
  "yaml-metadata:pandoc-yaml-metadata-core-current-base"
  "citation-csl:pandoc-citation-csl-core-current-base"
  "bibtex-csl:pandoc-bibtex-csl-core-current-base"
  "docx-openxml:pandoc-docx-openxml-core-current-base"
  "epub3-package:pandoc-epub3-package-core-current-base"
  "odf-package:pandoc-odf-open-document-core-current-base"
  "legacy-doc:pandoc-legacy-doc-cfb-core-current-base"
  "math-tex:pandoc-math-tex-conversion-core-current-base"
  "syntax-highlight:pandoc-syntax-highlighting-core-current-base"
  "charset-unicode:pandoc-charset-unicode-width-core-current-base"
  "table-geometry:pandoc-table-geometry-core-current-base"
  "archive-streams:pandoc-archive-compression-streams-current-base"
  "pdf-handoff:pandoc-pdf-engine-handoff-core-current-base"
  "runner-deps:pandoc-upstream-runner-deps-current-base"
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
while [[ "$(active_dev_codex_count)" -lt "$TARGET_TOTAL" && "$(active_pandoc_count)" -lt "$TARGET_PANDOC" && "$starts" -lt "$MAX_STARTS" ]]; do
  tried=0
  while [[ "$tried" -lt "${#slices[@]}" ]]; do
    entry="${slices[$((idx % ${#slices[@]}))]}"
    slug="${entry%%:*}"
    base_slice="${entry#*:}"
    idx=$((idx + 1))
    printf '%s\n' "$idx" > "$INDEX_FILE"
    tried=$((tried + 1))
    if active_pandoc_slice_contains "$base_slice"; then
      continue
    fi
    slice="${base_slice}-$(date -u +%Y%m%dT%H%M%SZ)"
    start_worker "$slug" "$slice" || true
    starts=$((starts + 1))
    break
  done
  [[ "$tried" -lt "${#slices[@]}" ]] || break
done

printf '%s active_dev_codex=%s active_pandoc=%s pandoc_windows=%s starts=%s target_total=%s target_pandoc=%s base=%s profile=%s/%s/%s\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$(active_dev_codex_count)" "$(active_pandoc_count)" "$(active_pandoc_window_count)" "$starts" \
  "$TARGET_TOTAL" "$TARGET_PANDOC" "$BASE_SHA" "$AGENT_FAST_MODEL" "$AGENT_FAST_REASONING" "$AGENT_FAST_SERVICE_TIER"
