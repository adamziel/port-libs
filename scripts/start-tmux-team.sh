#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MAX_WORKERS="${MAX_WORKERS:-3}"
AGENT_BIN="${AGENT_BIN:-codex}"
AGENT_FAST_MODEL="${AGENT_FAST_MODEL:-gpt-5.5}"
AGENT_FAST_REASONING="${AGENT_FAST_REASONING:-xhigh}"
AGENT_FAST_SERVICE_TIER="${AGENT_FAST_SERVICE_TIER:-priority}"
LANES=(gitoxide lightningcss markerpdf libsqlite readability pandoc quadrable syncthing difftastic rclone dolt esbuild)
if [[ -n "${LANES_OVERRIDE:-}" ]]; then
  # Space-separated lane slugs, for example:
  # LANES_OVERRIDE="libsqlite readability pandoc" MAX_WORKERS=2 scripts/start-tmux-team.sh
  read -r -a LANES <<< "$LANES_OVERRIDE"
fi
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

TMUX_SOCKET_DIR="${TMPDIR:-/tmp}/tmux-$(id -u)"
mkdir -p "$TMUX_SOCKET_DIR"
chmod 700 "$TMUX_SOCKET_DIR"

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
  tmux new-session -d -s "$session" "AGENT_BIN='$AGENT_BIN' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' '$ROOT/scripts/run-tmux-agent.sh' '$session' '$prompt' '$log'"
  started=$((started + 1))
done

if ! tmux has-session -t port-auditor 2>/dev/null; then
  log="$LOG_DIR/port-auditor-$(date -u +%Y%m%dT%H%M%SZ).log"
  tmux new-session -d -s port-auditor "AGENT_BIN='$AGENT_BIN' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' '$ROOT/scripts/run-tmux-agent.sh' 'port-auditor' '$ROOT/.tmux-team/prompts/auditor.md' '$log'"
fi

if ! tmux has-session -t port-evaluator 2>/dev/null; then
  tmux new-session -d -s port-evaluator "AGENT_BIN='$AGENT_BIN' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' INTERVAL_SECONDS='${EVALUATOR_INTERVAL_SECONDS:-1200}' '$ROOT/scripts/run-evaluator-loop.sh'"
fi

printf 'Started %d new implementation worker(s); max active workers: %s\n' "$started" "$MAX_WORKERS"
tmux list-sessions | sed -n '/^port-/p'
