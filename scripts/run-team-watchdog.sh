#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

AGENT_BIN="${AGENT_BIN:-codex}"
INTERVAL_SECONDS="${WATCHDOG_INTERVAL_SECONDS:-60}"
LOG_DIR="$ROOT/.tmux-team/logs"
TMP_DIR="$ROOT/.tmux-team/tmp"
mkdir -p "$LOG_DIR" "$TMP_DIR" audits

LANES=(gitoxide lightningcss markerpdf libsqlite readability pandoc quadrable syncthing difftastic rclone dolt esbuild)

descendants() {
  local root="$1"
  local queue=("$root")
  local current children child
  while ((${#queue[@]})); do
    current="${queue[0]}"
    queue=("${queue[@]:1}")
    children="$(pgrep -P "$current" 2>/dev/null || true)"
    for child in $children; do
      printf '%s\n' "$child"
      queue+=("$child")
    done
  done
}

session_has_codex() {
  local session="$1"
  local pane_pid pid command
  pane_pid="$(tmux display-message -p -t "$session:0" '#{pane_pid}' 2>/dev/null || true)"
  [[ -n "$pane_pid" ]] || return 1

  while read -r pid; do
    [[ -n "$pid" ]] || continue
    command="$(ps -p "$pid" -o comm= 2>/dev/null || true)"
    [[ "$command" == codex ]] && return 0
  done < <(descendants "$pane_pid")

  return 1
}

session_has_evaluator_loop() {
  local pane_pid child_count
  pane_pid="$(tmux display-message -p -t port-evaluator:0 '#{pane_pid}' 2>/dev/null || true)"
  [[ -n "$pane_pid" ]] || return 1
  child_count="$(descendants "$pane_pid" | wc -l | tr -d ' ')"
  [[ "$child_count" != "0" ]]
}

session_has_dashboard_updater_loop() {
  local pane_pid child_count
  pane_pid="$(tmux display-message -p -t port-dashboard-updater:0 '#{pane_pid}' 2>/dev/null || true)"
  [[ -n "$pane_pid" ]] || return 1
  child_count="$(descendants "$pane_pid" | wc -l | tr -d ' ')"
  [[ "$child_count" != "0" ]]
}

start_worker() {
  local session="$1"
  local prompt="$2"
  local stamp log
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  log="$LOG_DIR/${session}-watchdog-${stamp}.log"
  tmux send-keys -t "$session:0" "AGENT_BIN='$AGENT_BIN' '$ROOT/scripts/run-tmux-agent.sh' '$session' '$prompt' '$log'" C-m
  printf '%s restarted %s with %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$log"
}

ensure_worker_session() {
  local lane="$1"
  local session="port-${lane}"
  local prompt="$TMP_DIR/${session}.md"

  sed \
    -e "s/{{LANE}}/${lane}/g" \
    -e "s/{{SESSION}}/${session}/g" \
    "$ROOT/.tmux-team/prompts/worker-template.md" > "$prompt"

  if ! tmux has-session -t "$session" 2>/dev/null; then
    tmux new-session -d -s "$session" "cd '$ROOT' && exec bash"
    printf '%s recreated %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session"
  fi

  if ! session_has_codex "$session"; then
    start_worker "$session" "$prompt"
  fi
}

ensure_auditor_session() {
  local prompt="$ROOT/.tmux-team/prompts/auditor.md"
  if ! tmux has-session -t port-auditor 2>/dev/null; then
    tmux new-session -d -s port-auditor "cd '$ROOT' && exec bash"
    printf '%s recreated port-auditor\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
  if ! session_has_codex port-auditor; then
    start_worker port-auditor "$prompt"
  fi
}

ensure_integrator_session() {
  local prompt="$ROOT/.tmux-team/prompts/integrator.md"
  if [[ ! -f "$prompt" ]]; then
    return
  fi
  if ! tmux has-session -t port-integrator 2>/dev/null; then
    tmux new-session -d -s port-integrator "cd '$ROOT' && exec bash"
    printf '%s recreated port-integrator\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
  if ! session_has_codex port-integrator; then
    start_worker port-integrator "$prompt"
  fi
}

ensure_prompt_session() {
  local session="$1"
  local prompt="$2"
  if [[ ! -f "$prompt" ]]; then
    return
  fi
  if ! tmux has-session -t "$session" 2>/dev/null; then
    tmux new-session -d -s "$session" "cd '$ROOT' && exec bash"
    printf '%s recreated %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session"
  fi
  if ! session_has_codex "$session"; then
    start_worker "$session" "$prompt"
  fi
}

ensure_evaluator_session() {
  if ! tmux has-session -t port-evaluator 2>/dev/null; then
    tmux new-session -d -s port-evaluator "AGENT_BIN='$AGENT_BIN' INTERVAL_SECONDS='${EVALUATOR_INTERVAL_SECONDS:-1200}' '$ROOT/scripts/run-evaluator-loop.sh'"
    printf '%s recreated port-evaluator\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    return
  fi
  if ! session_has_evaluator_loop; then
    tmux send-keys -t port-evaluator:0 "AGENT_BIN='$AGENT_BIN' INTERVAL_SECONDS='${EVALUATOR_INTERVAL_SECONDS:-1200}' '$ROOT/scripts/run-evaluator-loop.sh'" C-m
    printf '%s restarted port-evaluator loop\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
}

ensure_dashboard_updater_session() {
  if ! tmux has-session -t port-dashboard-updater 2>/dev/null; then
    tmux new-session -d -s port-dashboard-updater "AGENT_BIN='$AGENT_BIN' DASHBOARD_UPDATER_INTERVAL_SECONDS='${DASHBOARD_UPDATER_INTERVAL_SECONDS:-600}' '$ROOT/scripts/run-dashboard-updater-loop.sh'"
    printf '%s recreated port-dashboard-updater\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    return
  fi
  if ! session_has_dashboard_updater_loop; then
    tmux send-keys -t port-dashboard-updater:0 "AGENT_BIN='$AGENT_BIN' DASHBOARD_UPDATER_INTERVAL_SECONDS='${DASHBOARD_UPDATER_INTERVAL_SECONDS:-600}' '$ROOT/scripts/run-dashboard-updater-loop.sh'" C-m
    printf '%s restarted port-dashboard-updater loop\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
}

printf 'port-watchdog started at %s, interval %ss\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$INTERVAL_SECONDS"
while true; do
  for lane in "${LANES[@]}"; do
    ensure_worker_session "$lane"
  done
  ensure_prompt_session port-dolt-runner "$ROOT/.tmux-team/prompts/dolt-runner.md"
  ensure_auditor_session
  ensure_integrator_session
  ensure_evaluator_session
  ensure_dashboard_updater_session
  sleep "$INTERVAL_SECONDS"
done
