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
printf '\nPort worker panes:\n'
for session in port-gitoxide port-lightningcss port-markerpdf port-libsqlite port-readability port-pandoc port-quadrable port-syncthing port-difftastic port-rclone port-dolt port-esbuild port-auditor port-evaluator; do
  if tmux has-session -t "$session" 2>/dev/null; then
    printf '\n== %s ==\n' "$session"
    tmux capture-pane -pt "$session" -S -8 2>/dev/null || true
  fi
done
