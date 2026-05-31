#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

AGENT_BIN="${AGENT_BIN:-codex}"
source "$ROOT/scripts/agent-fast-profile.sh"
INTERVAL_SECONDS="${WATCHDOG_INTERVAL_SECONDS:-60}"
RESTART_LOAD_LIMIT="${WATCHDOG_RESTART_LOAD_LIMIT:-13}"
WATCHDOG_USE_ISOLATED_LANE_WORKERS="${WATCHDOG_USE_ISOLATED_LANE_WORKERS:-1}"
WATCHDOG_DRY_RUN_RESTART="${WATCHDOG_DRY_RUN_RESTART:-0}"
WATCHDOG_DRY_RUN_LANE_RESTART="${WATCHDOG_DRY_RUN_LANE_RESTART:-}"
LOG_DIR="$ROOT/.tmux-team/logs"
TMP_DIR="$ROOT/.tmux-team/tmp"
HOLD_DIR="$TMP_DIR/integration-holds"
HANDOFF_DIR="$TMP_DIR/handoff-candidates"
BACKPRESSURE_DIR="$TMP_DIR/handoff-backpressure"
SLICE_QUEUE_DIR="$TMP_DIR/watchdog-lane-slices"
INTEGRATION_HOLD_MAX_SECONDS="${INTEGRATION_HOLD_MAX_SECONDS:-900}"
HANDOFF_GRACE_SECONDS="${WATCHDOG_HANDOFF_GRACE_SECONDS:-90}"
HANDOFF_MAX_CANDIDATES="${WATCHDOG_HANDOFF_MAX_CANDIDATES:-12}"
mkdir -p "$LOG_DIR" "$TMP_DIR" "$HOLD_DIR" "$HANDOFF_DIR" "$BACKPRESSURE_DIR" "$SLICE_QUEUE_DIR" audits

LOCK_FILE="${WATCHDOG_LOCK_FILE:-$TMP_DIR/port-watchdog.lock}"
exec 9>"$LOCK_FILE"
if [[ -z "$WATCHDOG_DRY_RUN_LANE_RESTART" ]] && ! flock -n 9; then
  printf '%s another port-watchdog loop already holds %s; exiting\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$LOCK_FILE" >&2
  exit 0
fi

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
  local pane_pid descendant_ids
  pane_pid="$(tmux display-message -p -t "${session}:0" '#{pane_pid}' 2>/dev/null || true)"
  [[ -n "$pane_pid" ]] || return 1
  descendant_ids="$(descendants "$pane_pid" | paste -sd, -)"
  [[ -n "$descendant_ids" ]] || return 1
  ps -o comm=,args= -p "$descendant_ids" 2>/dev/null |
    awk '
      $1 == "codex" { found = 1 }
      $1 == "node" && $0 ~ /\/usr\/local\/bin\/codex/ { found = 1 }
      END { exit !found }
    '
}

integrator_intake_active() {
  tmux has-session -t port-integrator 2>/dev/null || return 1
  session_has_codex port-integrator
}

isolated_ready_count() {
  local ready count=0
  for ready in "$HANDOFF_DIR"/port-*.ready; do
    [[ -f "$ready" ]] || continue
    if grep -q '^patch=' "$ready" && grep -q '^metadata=' "$ready"; then
      count=$((count + 1))
    fi
  done
  printf '%s\n' "$count"
}

nonisolated_ready_count() {
  local ready count=0
  for ready in "$HANDOFF_DIR"/port-*.ready; do
    [[ -f "$ready" ]] || continue
    if ! grep -q '^patch=' "$ready" || ! grep -q '^metadata=' "$ready"; then
      count=$((count + 1))
    fi
  done
  printf '%s\n' "$count"
}

integration_hold_active() {
  local session="$1"
  local hold_file="$HOLD_DIR/${session}.hold"
  local now mtime age
  [[ -f "$hold_file" ]] || return 1
  now="$(date +%s)"
  mtime="$(stat -c '%Y' "$hold_file" 2>/dev/null || printf '0')"
  age=$((now - mtime))
  if (( age > INTEGRATION_HOLD_MAX_SECONDS )); then
    rm -f "$hold_file"
    printf '%s expired stale integration hold for %s after %ss\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$age"
    return 1
  fi
  printf '%s holding %s for integrator intake (%ss old): %s\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$age" "$hold_file"
  return 0
}

handoff_grace_active() {
  local session="$1"
  local marker="$HANDOFF_DIR/${session}.ready"
  local now mtime age candidate_count
  now="$(date +%s)"
  if is_truthy "$WATCHDOG_USE_ISOLATED_LANE_WORKERS"; then
    if [[ -f "$marker" ]]; then
      if grep -q '^patch=' "$marker" && grep -q '^metadata=' "$marker"; then
        return 0
      fi
      rm -f "$marker"
      printf '%s removed stale shared-checkout handoff marker for %s in isolated-worker mode: %s\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$marker"
    fi
    candidate_count="$(isolated_ready_count)"
    if (( candidate_count >= HANDOFF_MAX_CANDIDATES )); then
      printf 'timestamp=%s\nsession=%s\nreason=isolated-handoff-queue-full\ncandidate_count=%s\ncandidate_limit=%s\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$candidate_count" "$HANDOFF_MAX_CANDIDATES" > "$BACKPRESSURE_DIR/${session}.hold"
      printf '%s isolated handoff queue full (%s/%s); holding %s idle until intake catches up: %s\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$candidate_count" "$HANDOFF_MAX_CANDIDATES" "$session" "$BACKPRESSURE_DIR/${session}.hold"
      return 0
    fi
    rm -f "$BACKPRESSURE_DIR/${session}.hold"
    return 1
  fi
  if [[ ! -f "$marker" ]]; then
    candidate_count="$(find "$HANDOFF_DIR" -maxdepth 1 -type f -name 'port-*.ready' 2>/dev/null | wc -l | tr -d ' ')"
    if (( candidate_count >= HANDOFF_MAX_CANDIDATES )); then
      # Full intake means finished workers are more valuable idle than restarted into new shared-checkout edits.
      printf 'timestamp=%s\nsession=%s\nreason=handoff-queue-full\ncandidate_count=%s\ncandidate_limit=%s\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$candidate_count" "$HANDOFF_MAX_CANDIDATES" > "$BACKPRESSURE_DIR/${session}.hold"
      printf '%s handoff candidate queue full (%s/%s); holding %s idle until intake catches up: %s\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$candidate_count" "$HANDOFF_MAX_CANDIDATES" "$session" "$BACKPRESSURE_DIR/${session}.hold"
      return 0
    fi
    rm -f "$BACKPRESSURE_DIR/${session}.hold"
    printf 'timestamp=%s\nsession=%s\nreason=no-codex-handoff-grace\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" > "$marker"
    printf '%s giving %s a %ss handoff grace before restart: %s\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$HANDOFF_GRACE_SECONDS" "$marker"
    return 0
  fi
  mtime="$(stat -c '%Y' "$marker" 2>/dev/null || printf '0')"
  age=$((now - mtime))
  if (( age < HANDOFF_GRACE_SECONDS )); then
    printf '%s keeping %s in handoff grace (%ss/%ss): %s\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$age" "$HANDOFF_GRACE_SECONDS" "$marker"
    return 0
  fi
  if integrator_intake_active; then
    printf '%s keeping %s handoff queued for active integrator (%ss old): %s\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$age" "$marker"
    return 0
  fi
  rm -f "$marker"
  printf '%s handoff grace expired for %s after %ss; restarting if load allows\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$age"
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

capacity_controller_lock_file() {
  printf '%s\n' "${CAPACITY_CONTROLLER_LOCK_FILE:-$TMP_DIR/port-capacity-controller.lock}"
}

capacity_controller_lock_is_held() {
  local lock_file
  lock_file="$(capacity_controller_lock_file)"
  ( exec 8>"$lock_file"; ! flock -n 8 )
}

capacity_controller_loop_lines() {
  pgrep -af '(^|/)run-capacity-controller-loop\.sh([[:space:]]|$)' || true
}

capacity_controller_loop_count() {
  capacity_controller_loop_lines | awk 'NF { count++ } END { print count + 0 }'
}

report_capacity_controller_hold() {
  local reason="$1"
  local lines
  lines="$(capacity_controller_loop_lines)"
  printf '%s not starting port-capacity-controller: %s; supervisor action required. Lock: %s\n' \
    "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$reason" "$(capacity_controller_lock_file)"
  if [[ -n "$lines" ]]; then
    printf '%s\n' "$lines"
  fi
}

main_writer_freeze_active() {
  [[ -f "$TMP_DIR/clean-integration-freeze" ]]
}

session_has_capacity_controller_loop() {
  [[ "$(capacity_controller_loop_count)" != "0" ]]
}

load_allows_restart() {
  local load1
  load1="$(awk '{print $1}' /proc/loadavg)"
  awk -v load_value="$load1" -v limit="$RESTART_LOAD_LIMIT" 'BEGIN { exit !(load_value < limit) }'
}

is_truthy() {
  case "${1:-}" in
    1|true|TRUE|yes|YES|on|ON) return 0 ;;
    *) return 1 ;;
  esac
}

lane_slice_label() {
  local lane="$1"
  local queued_slice_file="$SLICE_QUEUE_DIR/${lane}.slice"
  local queued_slice
  if [[ -f "$queued_slice_file" ]]; then
    queued_slice="$(sed -n '1s/[[:space:]]*$//p' "$queued_slice_file" | tr -cs 'A-Za-z0-9._:-' '-')"
    if [[ -n "$queued_slice" ]]; then
      printf '%s\n' "$queued_slice"
      return
    fi
  fi
  printf 'watchdog-next-%s\n' "$(date -u +%Y%m%dT%H%M%SZ)"
}

run_or_print_respawn() {
  local session="$1"
  local command="$2"
  if is_truthy "$WATCHDOG_DRY_RUN_RESTART"; then
    printf 'dry-run: would respawn %s with: %s\n' "$session" "$command"
    return
  fi
  tmux respawn-pane -k -t "$session:0" "$command"
}

start_worker() {
  local session="$1"
  local prompt="$2"
  local stamp log
  if ! load_allows_restart; then
    printf '%s deferred restart of %s because load %s is >= restart limit %s\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$(awk '{print $1}' /proc/loadavg)" "$RESTART_LOAD_LIMIT"
    return
  fi
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  log="$LOG_DIR/${session}-watchdog-${stamp}.log"
  run_or_print_respawn "$session" \
    "cd '$ROOT' && exec env AGENT_BIN='$AGENT_BIN' '$ROOT/scripts/run-tmux-agent.sh' '$session' '$prompt' '$log'"
  printf '%s restarted %s with %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$log"
}

start_lane_worker() {
  local lane="$1"
  local session="$2"
  local prompt="$3"
  local stamp log slice
  if ! load_allows_restart; then
    printf '%s deferred restart of %s because load %s is >= restart limit %s\n' \
      "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$(awk '{print $1}' /proc/loadavg)" "$RESTART_LOAD_LIMIT"
    return
  fi
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  log="$LOG_DIR/${session}-watchdog-${stamp}.log"
  if is_truthy "$WATCHDOG_USE_ISOLATED_LANE_WORKERS"; then
    slice="$(lane_slice_label "$lane")"
    run_or_print_respawn "$session" \
      "cd '$ROOT' && exec env AGENT_BIN='$AGENT_BIN' AGENT_FAST_MODEL='$AGENT_FAST_MODEL' AGENT_FAST_REASONING='$AGENT_FAST_REASONING' AGENT_FAST_SERVICE_TIER='$AGENT_FAST_SERVICE_TIER' '$ROOT/scripts/run-isolated-lane-worker.sh' '$lane' '$slice' '$session'"
    printf '%s restarted %s as isolated lane worker slice %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$session" "$slice"
    return
  fi
  run_or_print_respawn "$session" \
    "cd '$ROOT' && exec env AGENT_BIN='$AGENT_BIN' '$ROOT/scripts/run-tmux-agent.sh' '$session' '$prompt' '$log'"
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

  if session_has_codex "$session"; then
    rm -f "$HANDOFF_DIR/${session}.ready"
    return
  fi

  if integration_hold_active "$session"; then
    return
  fi

  if handoff_grace_active "$session"; then
    return
  fi

  start_lane_worker "$lane" "$session" "$prompt"
}

ensure_auditor_session() {
  if main_writer_freeze_active; then
    return
  fi
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
  if main_writer_freeze_active; then
    return
  fi
  if is_truthy "$WATCHDOG_USE_ISOLATED_LANE_WORKERS" && [[ "$(nonisolated_ready_count)" == "0" ]]; then
    return
  fi
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

ensure_clean_integrator_session() {
  if main_writer_freeze_active; then
    return
  fi
  if [[ "$(isolated_ready_count)" == "0" ]]; then
    return
  fi
  local prompt="$ROOT/.tmux-team/tmp/port-clean-integrator-iso-metadata-20260524T225446Z.md"
  if [[ ! -f "$prompt" ]]; then
    return
  fi
  if ! tmux has-session -t port-clean-integrator 2>/dev/null; then
    tmux new-session -d -s port-clean-integrator "cd '$ROOT' && exec bash"
    printf '%s recreated port-clean-integrator\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
  if ! session_has_codex port-clean-integrator; then
    start_worker port-clean-integrator "$prompt"
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
    tmux respawn-pane -k -t port-evaluator:0 \
      "cd '$ROOT' && exec env AGENT_BIN='$AGENT_BIN' INTERVAL_SECONDS='${EVALUATOR_INTERVAL_SECONDS:-1200}' '$ROOT/scripts/run-evaluator-loop.sh'"
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
    tmux respawn-pane -k -t port-dashboard-updater:0 \
      "cd '$ROOT' && exec env AGENT_BIN='$AGENT_BIN' DASHBOARD_UPDATER_INTERVAL_SECONDS='${DASHBOARD_UPDATER_INTERVAL_SECONDS:-600}' '$ROOT/scripts/run-dashboard-updater-loop.sh'"
    printf '%s restarted port-dashboard-updater loop\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
}

ensure_capacity_controller_session() {
  local loop_count lock_held
  loop_count="$(capacity_controller_loop_count)"
  lock_held=0
  if capacity_controller_lock_is_held; then
    lock_held=1
  fi

  if (( loop_count > 1 )); then
    report_capacity_controller_hold "multiple run-capacity-controller-loop.sh processes are active"
    return
  fi
  if (( loop_count > 0 && lock_held == 0 )); then
    report_capacity_controller_hold "run-capacity-controller-loop.sh is active without holding the controller lock"
    return
  fi

  if ! tmux has-session -t port-capacity-controller 2>/dev/null; then
    if (( lock_held == 1 || loop_count > 0 )); then
      report_capacity_controller_hold "controller lock or same-script loop is already active outside a managed tmux session"
      return
    fi
    tmux new-session -d -s port-capacity-controller "AGENT_BIN='$AGENT_BIN' CAPACITY_CONTROLLER_INTERVAL_SECONDS='${CAPACITY_CONTROLLER_INTERVAL_SECONDS:-60}' '$ROOT/scripts/run-capacity-controller-loop.sh'"
    printf '%s recreated port-capacity-controller\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    return
  fi
  if ! session_has_capacity_controller_loop; then
    if (( lock_held == 1 || loop_count > 0 )); then
      report_capacity_controller_hold "controller lock or same-script loop is already active"
      return
    fi
    tmux respawn-pane -k -t port-capacity-controller:0 \
      "cd '$ROOT' && exec env AGENT_BIN='$AGENT_BIN' CAPACITY_CONTROLLER_INTERVAL_SECONDS='${CAPACITY_CONTROLLER_INTERVAL_SECONDS:-60}' '$ROOT/scripts/run-capacity-controller-loop.sh'"
    printf '%s restarted port-capacity-controller loop\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  fi
}

if [[ -n "$WATCHDOG_DRY_RUN_LANE_RESTART" ]]; then
  lane="$WATCHDOG_DRY_RUN_LANE_RESTART"
  case " ${LANES[*]} " in
    *" $lane "*) ;;
    *)
      printf 'invalid watchdog dry-run lane: %s\n' "$lane" >&2
      exit 2
      ;;
  esac
  WATCHDOG_DRY_RUN_RESTART=1
  start_lane_worker "$lane" "port-${lane}" "$TMP_DIR/port-${lane}.md"
  exit 0
fi

printf 'port-watchdog started at %s, interval %ss\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$INTERVAL_SECONDS"
while true; do
  for lane in "${LANES[@]}"; do
    ensure_worker_session "$lane"
  done
  ensure_prompt_session port-dolt-runner "$ROOT/.tmux-team/prompts/dolt-runner.md"
  ensure_auditor_session
  ensure_integrator_session
  ensure_clean_integrator_session
  ensure_evaluator_session
  ensure_dashboard_updater_session
  ensure_capacity_controller_session
  sleep "$INTERVAL_SECONDS"
done
