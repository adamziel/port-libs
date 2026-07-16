#!/usr/bin/env bash
set -euo pipefail
umask 0022

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

TMUX_SESSION="${TMUX_SESSION:-main}"
INTERVAL_SECONDS="${WATCHDOG_INTERVAL_SECONDS:-60}"
TARGET_TOTAL="${PORT_LIBS_TARGET_DEV_WORKERS:-14}"
TARGET_MARKERPDF="${MARKERPDF_TARGET_WORKERS:-8}"
TARGET_PANDOC="${PANDOC_TARGET_WORKERS:-6}"
BASE_REF="${PORT_LIBS_REFILL_BASE_REF:-origin/main}"
MIN_FREE_KIB="${WATCHDOG_MIN_FREE_KIB:-80000000}"
MIN_MEM_AVAILABLE_KIB="${WATCHDOG_MIN_MEM_AVAILABLE_KIB:-2000000}"
MAX_LOAD_1M="${WATCHDOG_MAX_LOAD_1M:-25}"
ONCE="${WATCHDOG_ONCE:-0}"
DRY_RUN="${WATCHDOG_DRY_RUN:-0}"
LOG_DIR="$ROOT/.tmux-team/logs"
TMP_DIR="$ROOT/.tmux-team/tmp"
HANDOFF_DIR="$TMP_DIR/handoff-candidates"
LOG_FILE="${WATCHDOG_LOG_FILE:-$LOG_DIR/markerpdf-pandoc-refill-watchdog.log}"
LOCK_FILE="${WATCHDOG_LOCK_FILE:-$TMP_DIR/markerpdf-pandoc-refill-watchdog.lock}"

mkdir -p "$LOG_DIR" "$TMP_DIR" "$HANDOFF_DIR"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  printf '%s another markerpdf/pandoc refill watchdog already holds %s; exiting\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$LOCK_FILE" >&2
  exit 0
fi

require_file() {
  local path="$1"
  if [[ ! -f "$path" ]]; then
    printf 'required file not found: %s\n' "$path" >&2
    exit 1
  fi
}

require_command() {
  local command_name="$1"
  if ! command -v "$command_name" >/dev/null 2>&1; then
    printf 'required command not found: %s\n' "$command_name" >&2
    exit 1
  fi
}

require_command awk
require_command date
require_command df
require_command flock
require_command ps
require_command tmux
require_file "$ROOT/scripts/refill-markerpdf-workers.sh"
require_file "$ROOT/scripts/refill-pandoc-workers.sh"

timestamp() {
  date -u +%Y-%m-%dT%H:%M:%SZ
}

log_line() {
  printf '%s %s\n' "$(timestamp)" "$*" | tee -a "$LOG_FILE"
}

active_dev_codex_count() {
  ps -eo args= |
    awk '$1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ /\/\.tmux-team\/worktrees\/port-dev-/ {count++} END {print count + 0}'
}

active_lane_count() {
  local lane="$1"
  ps -eo args= |
    awk -v lane="$lane" '
      $1 == "bash" && ($2 == "scripts/run-isolated-lane-worker.sh" || $2 ~ /\/scripts\/run-isolated-lane-worker\.sh$/) && $3 == lane {
        print $5
      }
      $1 == "node" && $2 == "/usr/local/bin/codex" && $0 ~ "/\\.tmux-team/worktrees/port-dev-" lane "-" {
        pattern = "port-dev-" lane "-[^/ ]+-[0-9TZ]+"
        if (match($0, pattern)) {
          session = substr($0, RSTART, RLENGTH)
          sub(/-[0-9]{8}T[0-9]{6}Z$/, "", session)
          print session
        }
      }
    ' |
    sort -u |
    wc -l |
    tr -d ' '
}

lane_window_count() {
  local lane="$1"
  tmux list-windows -t "$TMUX_SESSION" -F '#W' 2>/dev/null |
    awk -v prefix="port-dev-${lane}-" 'index($0, prefix) == 1 {count++} END {print count + 0}'
}

ready_count() {
  find "$HANDOFF_DIR" -maxdepth 1 -type f -name 'port-*.ready' 2>/dev/null |
    wc -l |
    tr -d ' '
}

resource_hold_reason() {
  local load_1m mem_available root_free
  load_1m="$(awk '{print $1}' /proc/loadavg)"
  mem_available="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
  root_free="$(df -Pk "$ROOT" | awk 'NR == 2 {print $4}')"

  if ! awk -v load_value="$load_1m" -v max_value="$MAX_LOAD_1M" 'BEGIN {exit !(load_value <= max_value)}'; then
    printf 'load_1m=%s exceeds max=%s' "$load_1m" "$MAX_LOAD_1M"
    return 0
  fi
  if (( mem_available < MIN_MEM_AVAILABLE_KIB )); then
    printf 'mem_available_kib=%s below min=%s' "$mem_available" "$MIN_MEM_AVAILABLE_KIB"
    return 0
  fi
  if (( root_free < MIN_FREE_KIB )); then
    printf 'root_free_kib=%s below min=%s' "$root_free" "$MIN_FREE_KIB"
    return 0
  fi
  return 1
}

run_refill_once() {
  local total markerpdf pandoc markerpdf_windows pandoc_windows handoffs hold_reason
  local markerpdf_needed pandoc_needed

  total="$(active_dev_codex_count)"
  markerpdf="$(active_lane_count markerpdf)"
  pandoc="$(active_lane_count pandoc)"
  markerpdf_windows="$(lane_window_count markerpdf)"
  pandoc_windows="$(lane_window_count pandoc)"
  handoffs="$(ready_count)"

  log_line "status total_codex=$total markerpdf=$markerpdf/$TARGET_MARKERPDF pandoc=$pandoc/$TARGET_PANDOC windows_markerpdf=$markerpdf_windows windows_pandoc=$pandoc_windows ready_handoffs=$handoffs target_total=$TARGET_TOTAL base=$BASE_REF"

  if hold_reason="$(resource_hold_reason)"; then
    log_line "holding refill: $hold_reason"
    return 0
  fi

  if (( markerpdf < TARGET_MARKERPDF )); then
    markerpdf_needed=$((TARGET_MARKERPDF - markerpdf))
    log_line "refilling markerpdf needed=$markerpdf_needed"
    if [[ "$DRY_RUN" == "1" ]]; then
      log_line "dry-run: skipped markerpdf refill"
    else
      MARKERPDF_REFILL_BASE_REF="$BASE_REF" \
        MARKERPDF_REFILL_REASON=continuous-markerpdf-pandoc-watchdog \
        MARKERPDF_TARGET_WORKERS="$TARGET_MARKERPDF" \
        MARKERPDF_MAX_REFILL_STARTS="$markerpdf_needed" \
        PORT_LIBS_TARGET_DEV_WORKERS="$TARGET_TOTAL" \
        bash "$ROOT/scripts/refill-markerpdf-workers.sh"
    fi
  fi

  total="$(active_dev_codex_count)"
  pandoc="$(active_lane_count pandoc)"
  if (( pandoc < TARGET_PANDOC )); then
    pandoc_needed=$((TARGET_PANDOC - pandoc))
    log_line "refilling pandoc needed=$pandoc_needed"
    if [[ "$DRY_RUN" == "1" ]]; then
      log_line "dry-run: skipped pandoc refill"
    else
      PANDOC_REFILL_BASE_REF="$BASE_REF" \
        PANDOC_REFILL_REASON=continuous-markerpdf-pandoc-watchdog \
        PANDOC_TARGET_WORKERS="$TARGET_PANDOC" \
        PANDOC_MAX_REFILL_STARTS="$pandoc_needed" \
        PORT_LIBS_TARGET_DEV_WORKERS="$TARGET_TOTAL" \
        bash "$ROOT/scripts/refill-pandoc-workers.sh"
    fi
  fi

  log_line "after total_codex=$(active_dev_codex_count) markerpdf=$(active_lane_count markerpdf)/$TARGET_MARKERPDF pandoc=$(active_lane_count pandoc)/$TARGET_PANDOC windows_markerpdf=$(lane_window_count markerpdf) windows_pandoc=$(lane_window_count pandoc)"
}

iteration=0
log_line "starting markerpdf/pandoc refill watchdog interval=${INTERVAL_SECONDS}s once=$ONCE dry_run=$DRY_RUN lock=$LOCK_FILE"
while true; do
  iteration=$((iteration + 1))
  log_line "iteration=$iteration"
  run_refill_once
  if [[ "$ONCE" == "1" ]]; then
    break
  fi
  sleep "$INTERVAL_SECONDS"
done
