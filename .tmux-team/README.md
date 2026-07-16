# Tmux Team

This directory contains durable prompts and notes for the supervised porting team described in `goal.md`.

Runtime logs are written to `.tmux-team/logs/` and are ignored by Git. All newly
started subagents/workers must use `gpt-5.5` with
`model_reasoning_effort="xhigh"` on the fast/priority service tier unless the
user explicitly replaces that rule.

Start a capped set of workers with:

```sh
scripts/start-tmux-team.sh
```

Check session status with:

```sh
scripts/check-tmux-team.sh
```

The current visible pool target is 10-11 active isolated lane workers in tmux,
resource-gated by CPU, memory, disk, and independent work availability.
