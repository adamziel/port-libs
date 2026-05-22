#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SESSION_NAME="${1:?session name required}"
PROMPT_FILE="${2:?prompt file required}"
LOG_FILE="${3:?log file required}"
AGENT_BIN="${AGENT_BIN:-codex}"

cd "$ROOT" || exit 1
mkdir -p "$(dirname "$LOG_FILE")"

printf 'Starting %s at %s\n' "$SESSION_NAME" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
printf 'Prompt: %s\n' "$PROMPT_FILE"
printf 'Log: %s\n\n' "$LOG_FILE"

if [[ ! -f "$PROMPT_FILE" ]]; then
  printf 'Prompt file does not exist: %s\n' "$PROMPT_FILE" >&2
  exec bash
fi

if ! command -v "$AGENT_BIN" >/dev/null 2>&1; then
  printf 'Agent binary not found: %s\n' "$AGENT_BIN" >&2
  exec bash
fi

"$AGENT_BIN" -a never exec -C "$ROOT" -s danger-full-access - < "$PROMPT_FILE" > "$LOG_FILE" 2>&1
status=$?

printf '\n%s exited with status %s. Log tail:\n' "$SESSION_NAME" "$status"
if [[ -f "$LOG_FILE" ]]; then
  tail -80 "$LOG_FILE"
else
  printf 'No log file was written.\n'
fi

exec bash
