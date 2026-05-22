#!/usr/bin/env bash
set -euo pipefail

for session in port-gitoxide port-lightningcss port-markerpdf port-libsqlite port-readability port-pandoc port-quadrable port-syncthing port-difftastic port-rclone port-dolt port-dolt-runner port-esbuild port-auditor port-integrator port-evaluator port-watchdog; do
  if tmux has-session -t "$session" 2>/dev/null; then
    tmux kill-session -t "$session"
    printf 'Stopped %s\n' "$session"
  fi
done
