#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

GROUP="${1:-${LANE_GROUP:-}}"
if [[ -z "$GROUP" ]]; then
  printf 'usage: %s <content-docs|style-bundle|storage-data|sync-git>\n' "$0" >&2
  exit 2
fi

case "$GROUP" in
  content-docs|style-bundle|storage-data|sync-git) ;;
  *)
    printf 'unknown lane group: %s\n' "$GROUP" >&2
    exit 2
    ;;
esac

AGENT_BIN="${AGENT_BIN:-codex}"
AGENT_FAST_MODEL="${AGENT_FAST_MODEL:-gpt-5.5}"
AGENT_FAST_REASONING="${AGENT_FAST_REASONING:-xhigh}"
AGENT_FAST_SERVICE_TIER="${AGENT_FAST_SERVICE_TIER:-priority}"
INTERVAL_SECONDS="${LANE_GROUP_INTEGRATOR_INTERVAL_SECONDS:-900}"
SESSION_NAME="${SESSION_NAME:-port-integrator-${GROUP}}"
PROMPT_FILE="${PROMPT_FILE:-$ROOT/.tmux-team/prompts/integrator-${GROUP}.md}"
LOG_DIR="$ROOT/.tmux-team/logs"
STATE_DIR="$ROOT/.tmux-team/tmp"
QUEUE_DIR="$STATE_DIR/group-integrator-queue"

mkdir -p "$LOG_DIR" "$STATE_DIR" "$QUEUE_DIR" audits

LOCK_FILE="${LANE_GROUP_INTEGRATOR_LOCK_FILE:-$STATE_DIR/${SESSION_NAME}.lock}"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  printf '%s another %s loop already holds %s; exiting\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$SESSION_NAME" "$LOCK_FILE" >&2
  exit 0
fi

if ! command -v "$AGENT_BIN" >/dev/null 2>&1; then
  printf 'Agent binary not found: %s\n' "$AGENT_BIN" >&2
  exit 1
fi

if [[ ! -f "$PROMPT_FILE" ]]; then
  printf 'Prompt file does not exist: %s\n' "$PROMPT_FILE" >&2
  exit 1
fi

iteration=0
last_log="$STATE_DIR/${SESSION_NAME}-last.log"

while true; do
  iteration=$((iteration + 1))
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  log="$LOG_DIR/${SESSION_NAME}-${stamp}.log"

  clear || true
  printf '%s lane-group integrator loop\n' "$SESSION_NAME"
  printf 'Started: %s UTC\n' "$(date -u +%Y-%m-%dT%H:%M:%S)"
  printf 'Group: %s\n' "$GROUP"
  printf 'Agent: %s (%s)\n' "$AGENT_BIN" "$(command -v "$AGENT_BIN")"
  printf 'Iteration: %s\n' "$iteration"
  printf 'Interval: %ss\n' "$INTERVAL_SECONDS"
  printf 'Prompt: %s\n' "$PROMPT_FILE"
  printf 'Git: %s %s, dirty paths: %s\n' \
    "$(git branch --show-current 2>/dev/null || printf '?')" \
    "$(git rev-parse --short HEAD 2>/dev/null || printf '?')" \
    "$(git status --porcelain=v1 | wc -l | tr -d ' ')"
  printf 'Current log: %s\n\n' "$log"

  printf 'Ready markers:\n'
  find "$STATE_DIR/handoff-candidates" -maxdepth 1 -type f -name '*.ready' -print 2>/dev/null | sort || true

  printf '\nLast group integrator result:\n'
  if [[ -s "$last_log" ]]; then
    tail -80 "$last_log"
  else
    printf 'none yet\n'
  fi

  printf '\nStatus: starting %s; full output goes to %s\n' "$SESSION_NAME" "$log"

  if ! "$AGENT_BIN" \
    -m "$AGENT_FAST_MODEL" \
    -c "model_service_tier=\"$AGENT_FAST_SERVICE_TIER\"" \
    -c "model_reasoning_effort=\"$AGENT_FAST_REASONING\"" \
    -a never exec -C "$ROOT" -s danger-full-access - < "$PROMPT_FILE" > "$log" 2>&1; then
    status=$?
    printf '\nLane-group integrator failed with status %s. Log: %s\n' "$status" "$log" >&2
    printf 'Last log lines:\n' >&2
    tail -160 "$log" >&2 || true
    exit "$status"
  fi

  cp "$log" "$last_log"
  printf '\nLane-group integrator iteration %s completed. Sleeping %s seconds.\n' "$iteration" "$INTERVAL_SECONDS"
  sleep "$INTERVAL_SECONDS"
done
