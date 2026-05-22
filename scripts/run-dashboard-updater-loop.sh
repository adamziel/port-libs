#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

AGENT_BIN="${AGENT_BIN:-codex}"
INTERVAL_SECONDS="${DASHBOARD_UPDATER_INTERVAL_SECONDS:-600}"
SESSION_NAME="${SESSION_NAME:-port-dashboard-updater}"
PROMPT_FILE="${PROMPT_FILE:-$ROOT/.tmux-team/prompts/dashboard-updater.md}"
LOG_DIR="$ROOT/.tmux-team/logs"
STATE_DIR="$ROOT/.tmux-team/tmp"

mkdir -p "$LOG_DIR" "$STATE_DIR" audits

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
  printf '%s dashboard updater loop\n' "$SESSION_NAME"
  printf 'Started: %s UTC\n' "$(date -u +%Y-%m-%dT%H:%M:%S)"
  printf 'Agent: %s (%s)\n' "$AGENT_BIN" "$(command -v "$AGENT_BIN")"
  printf 'Iteration: %s\n' "$iteration"
  printf 'Interval: %ss\n' "$INTERVAL_SECONDS"
  printf 'Git: %s %s, dirty paths: %s\n' \
    "$(git branch --show-current 2>/dev/null || printf '?')" \
    "$(git rev-parse --short HEAD 2>/dev/null || printf '?')" \
    "$(git status --porcelain=v1 | wc -l | tr -d ' ')"
  printf 'Current log: %s\n\n' "$log"

  printf 'Last dashboard updater result:\n'
  if [[ -s "$last_log" ]]; then
    tail -60 "$last_log"
  else
    printf 'none yet\n'
  fi

  printf '\nStatus: starting dashboard updater; full output goes to %s\n' "$log"

  if ! "$AGENT_BIN" -a never exec -C "$ROOT" -s danger-full-access - < "$PROMPT_FILE" > "$log" 2>&1; then
    status=$?
    printf '\nDashboard updater failed with status %s. Log: %s\n' "$status" "$log" >&2
    printf 'Last log lines:\n' >&2
    tail -160 "$log" >&2 || true
    exit "$status"
  fi

  cp "$log" "$last_log"
  printf '\nDashboard updater iteration %s completed. Sleeping %s seconds.\n' "$iteration" "$INTERVAL_SECONDS"
  sleep "$INTERVAL_SECONDS"
done
