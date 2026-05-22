# Tmux Team

This directory contains durable prompts and notes for the supervised porting team described in `goal.md`.

Runtime logs are written to `.tmux-team/logs/` and are ignored by Git. Start a capped set of workers with:

```sh
scripts/start-tmux-team.sh
```

Check session status with:

```sh
scripts/check-tmux-team.sh
```

The default cap is three implementation workers plus one auditor. Increase `MAX_WORKERS` only after checking CPU and memory headroom.

