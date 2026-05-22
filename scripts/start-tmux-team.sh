#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MAX_WORKERS="${MAX_WORKERS:-3}"
AGENT_BIN="${AGENT_BIN:-codex}"
MODEL_ARGS="${MODEL_ARGS:-}"
LANES=(gitoxide lightningcss markerpdf libsqlite readability pandoc quadrable syncthing difftastic rclone dolt esbuild)
LOG_DIR="$ROOT/.tmux-team/logs"
TMP_DIR="$ROOT/.tmux-team/tmp"
mkdir -p "$LOG_DIR" "$TMP_DIR" audits

if ! command -v tmux >/dev/null 2>&1; then
  printf 'tmux is required.\n' >&2
  exit 1
fi

if ! command -v "$AGENT_BIN" >/dev/null 2>&1; then
  printf 'Agent binary not found: %s\n' "$AGENT_BIN" >&2
  exit 1
fi

running_workers=0
for lane in "${LANES[@]}"; do
  session="port-${lane}"
  if tmux has-session -t "$session" 2>/dev/null; then
    running_workers=$((running_workers + 1))
  fi
done

started=0
for lane in "${LANES[@]}"; do
  if (( running_workers + started >= MAX_WORKERS )); then
    break
  fi

  session="port-${lane}"
  if tmux has-session -t "$session" 2>/dev/null; then
    continue
  fi

  prompt="$TMP_DIR/${session}.md"
  sed \
    -e "s/{{LANE}}/${lane}/g" \
    -e "s/{{SESSION}}/${session}/g" \
    "$ROOT/.tmux-team/prompts/worker-template.md" > "$prompt"

  log="$LOG_DIR/${session}-$(date -u +%Y%m%dT%H%M%SZ).log"
  tmux new-session -d -s "$session" "cd '$ROOT' && printf 'Starting %s at %s\nLog: %s\n' '$session' \"\$(date -u +%Y-%m-%dT%H:%M:%SZ)\" '$log'; $AGENT_BIN exec -C '$ROOT' -s danger-full-access -a never $MODEL_ARGS - < '$prompt' > '$log' 2>&1; status=\$?; printf '\n%s exited with status %s. Log tail:\n' '$session' \"\$status\"; tail -80 '$log'; exec bash"
  started=$((started + 1))
done

if ! tmux has-session -t port-auditor 2>/dev/null; then
  log="$LOG_DIR/port-auditor-$(date -u +%Y%m%dT%H%M%SZ).log"
  tmux new-session -d -s port-auditor "cd '$ROOT' && printf 'Starting auditor at %s\nLog: %s\n' \"\$(date -u +%Y-%m-%dT%H:%M:%SZ)\" '$log'; $AGENT_BIN exec -C '$ROOT' -s danger-full-access -a never $MODEL_ARGS - < '$ROOT/.tmux-team/prompts/auditor.md' > '$log' 2>&1; status=\$?; printf '\nport-auditor exited with status %s. Log tail:\n' \"\$status\"; tail -80 '$log'; exec bash"
fi

printf 'Started %d new implementation worker(s); max active workers: %s\n' "$started" "$MAX_WORKERS"
tmux list-sessions | sed -n '/^port-/p'

