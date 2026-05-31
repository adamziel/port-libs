#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

AGENT_BIN="${AGENT_BIN:-codex}"
source "$ROOT/scripts/agent-fast-profile.sh"
INTERVAL_SECONDS="${DASHBOARD_UPDATER_INTERVAL_SECONDS:-600}"
STABILITY_SECONDS="${DASHBOARD_UPDATER_STABILITY_SECONDS:-30}"
STABILITY_POLL_SECONDS="${DASHBOARD_UPDATER_STABILITY_POLL_SECONDS:-15}"
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

current_source_head() {
  git rev-parse refs/heads/main 2>/dev/null || git rev-parse HEAD
}

wait_for_stable_source() {
  local target="$1"
  local poll="$2"
  local candidate current stable_for

  if ((target <= 0)); then
    current_source_head
    return
  fi

  candidate="$(current_source_head)"
  stable_for=0
  printf 'Waiting for source main to stay stable for %ss. Initial: %s\n' "$target" "$candidate" >&2

  while ((stable_for < target)); do
    sleep "$poll"
    current="$(current_source_head)"
    if [[ "$current" == "$candidate" ]]; then
      stable_for=$((stable_for + poll))
      printf 'Stable for %ss/%ss at %s\n' "$stable_for" "$target" "${current:0:12}" >&2
    else
      printf 'Source moved after %ss: %s -> %s; restarting stability window\n' \
        "$stable_for" "${candidate:0:12}" "${current:0:12}" >&2
      candidate="$current"
      stable_for=0
    fi
  done

  printf '%s\n' "$candidate"
}

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
  printf 'Preflight stability window: %ss\n' "$STABILITY_SECONDS"
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

  printf '\n'
  stable_head="$(wait_for_stable_source "$STABILITY_SECONDS" "$STABILITY_POLL_SECONDS")"
  printf 'Stable source head selected for attempt: %s\n' "$stable_head"

  printf '\nStatus: starting dashboard updater; full output goes to %s\n' "$log"

  if ! "$AGENT_BIN" \
    -m "$AGENT_FAST_MODEL" \
    -c "model_service_tier=\"$AGENT_FAST_SERVICE_TIER\"" \
    -c "model_reasoning_effort=\"$AGENT_FAST_REASONING\"" \
    -a never exec -C "$ROOT" -s danger-full-access - < "$PROMPT_FILE" > "$log" 2>&1; then
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
