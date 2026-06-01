#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

printf 'Workspace: %s\n' "$ROOT"
printf 'Git: '
git status --short --branch
printf '\nMemory:\n'
free -h
printf '\nTmux sessions:\n'
tmux list-sessions 2>/dev/null || printf 'No tmux sessions.\n'
printf '\nTmux windows:\n'
tmux list-windows -a 2>/dev/null || printf 'No tmux windows.\n'
printf '\nVisible dev worker counts:\n'
printf 'dev windows: '
tmux list-windows -a 2>/dev/null | awk '$0 ~ / port-dev-/ {count++} END {print count + 0}'
printf 'gitoxide dev windows: '
tmux list-windows -a 2>/dev/null | awk '$0 ~ / port-dev-gitoxide-/ {count++} END {print count + 0}'
printf 'lightningcss dev windows: '
tmux list-windows -a 2>/dev/null | awk '$0 ~ / port-dev-lightningcss-/ {count++} END {print count + 0}'
printf 'libsqlite dev windows: '
tmux list-windows -a 2>/dev/null | awk '$0 ~ / port-dev-sqlite-/ {count++} END {print count + 0}'
printf 'isolated lane workers: '
ps -ewwo args | awk '/^bash scripts\/run-isolated-lane-worker\.sh / {c++} END {print c+0}'
printf 'dev codex workers: '
ps -ewwo args | awk '/codex-linux-x64/ && /worktrees\/port-dev-/ {c++} END {print c+0}'
printf 'long sleepers: '
pgrep -af 'sleep 900|sleep 600|sleep 1200|Sleeping 900' |
  awk '!/pgrep -af/ && !/check-tmux-team/ {c++} END {print c+0}'
printf '\nPort worker panes:\n'
for session in port-gitoxide port-lightningcss port-markerpdf port-libsqlite port-readability port-pandoc port-quadrable port-syncthing port-difftastic port-rclone port-dolt port-dolt-runner port-esbuild port-auditor port-integrator port-evaluator port-watchdog; do
  if tmux has-session -t "$session" 2>/dev/null; then
    printf '\n== %s ==\n' "$session"
    tmux capture-pane -pt "$session" -S -8 2>/dev/null || true
  fi
done
