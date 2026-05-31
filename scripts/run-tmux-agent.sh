#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SESSION_NAME="${1:?session name required}"
PROMPT_FILE="${2:?prompt file required}"
LOG_FILE="${3:?log file required}"
AGENT_BIN="${AGENT_BIN:-codex}"
AGENT_FAST_MODEL="${AGENT_FAST_MODEL:-gpt-5.5}"
AGENT_FAST_REASONING="${AGENT_FAST_REASONING:-xhigh}"
AGENT_FAST_SERVICE_TIER="${AGENT_FAST_SERVICE_TIER:-priority}"
RUN_TMUX_AGENT_STAY_OPEN="${RUN_TMUX_AGENT_STAY_OPEN:-1}"

cd "$ROOT" || exit 1
mkdir -p "$(dirname "$LOG_FILE")"

printf 'Starting %s at %s\n' "$SESSION_NAME" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
printf 'Prompt: %s\n' "$PROMPT_FILE"
printf 'Log: %s\n\n' "$LOG_FILE"

if [[ ! -f "$PROMPT_FILE" ]]; then
  printf 'Prompt file does not exist: %s\n' "$PROMPT_FILE" >&2
  if [[ "$RUN_TMUX_AGENT_STAY_OPEN" == "1" ]]; then
    exec bash
  fi
  exit 2
fi

if ! command -v "$AGENT_BIN" >/dev/null 2>&1; then
  printf 'Agent binary not found: %s\n' "$AGENT_BIN" >&2
  if [[ "$RUN_TMUX_AGENT_STAY_OPEN" == "1" ]]; then
    exec bash
  fi
  exit 2
fi

"$AGENT_BIN" \
  -m "$AGENT_FAST_MODEL" \
  -c "model_service_tier=\"$AGENT_FAST_SERVICE_TIER\"" \
  -c "model_reasoning_effort=\"$AGENT_FAST_REASONING\"" \
  -a never exec -C "$ROOT" -s danger-full-access - < "$PROMPT_FILE" > "$LOG_FILE" 2>&1
status=$?

printf '\n%s exited with status %s. Log tail:\n' "$SESSION_NAME" "$status"
if [[ -f "$LOG_FILE" ]]; then
  tail -80 "$LOG_FILE"
else
  printf 'No log file was written.\n'
fi

if [[ "$RUN_TMUX_AGENT_STAY_OPEN" == "1" ]]; then
  exec bash
fi

exit "$status"
