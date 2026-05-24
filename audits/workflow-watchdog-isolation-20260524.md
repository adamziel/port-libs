# Workflow Watchdog Isolation - 2026-05-24

Scope: watchdog restart routing for normal lane workers in `/home/claude/port-libs`.

## Change

`scripts/run-team-watchdog.sh` now restarts `port-<lane>` sessions through
`scripts/run-isolated-lane-worker.sh` by default.

Default behavior:

```bash
WATCHDOG_USE_ISOLATED_LANE_WORKERS=1
```

Opt out to the historical shared-checkout restart path:

```bash
WATCHDOG_USE_ISOLATED_LANE_WORKERS=0
```

The isolated restart command is:

```bash
scripts/run-isolated-lane-worker.sh <lane> <slice> <session>
```

If a supervisor has queued an explicit slice label, the watchdog reads the first
line from:

```text
.tmux-team/tmp/watchdog-lane-slices/<lane>.slice
```

Otherwise it uses a conservative timestamped slice label:

```text
watchdog-next-YYYYMMDDTHHMMSSZ
```

The watchdog still applies the existing handoff grace, integration holds,
handoff queue backpressure, and load limit before restarting a lane worker.
Supervisor, evaluator, auditor, integrator, dashboard updater, capacity
controller, and `port-dolt-runner` remain shared-checkout coordination
processes.

## Dry Sanity Check

The watchdog supports a command-selection dry run that does not respawn tmux or
launch a worker:

```bash
WATCHDOG_DRY_RUN_LANE_RESTART=<lane> WATCHDOG_RESTART_LOAD_LIMIT=999 bash scripts/run-team-watchdog.sh
```

This prints the selected restart command and exits. Set
`WATCHDOG_USE_ISOLATED_LANE_WORKERS=0` with the same dry run to verify the
shared-checkout opt-out path.

## Handoff Contract

Isolated workers still produce handoff candidates through
`scripts/run-isolated-lane-worker.sh`. The launcher exports only
`lanes/<lane>/**` as a patch and writes metadata plus a `.ready` marker under
`.tmux-team/tmp/handoff-candidates/`. Workers run in detached worktrees and are
instructed not to commit or edit the shared checkout.

## Verification

Commands required for this change:

```bash
bash -n scripts/run-team-watchdog.sh scripts/run-isolated-lane-worker.sh
WATCHDOG_DRY_RUN_LANE_RESTART=pandoc WATCHDOG_RESTART_LOAD_LIMIT=999 bash scripts/run-team-watchdog.sh
WATCHDOG_DRY_RUN_LANE_RESTART=pandoc WATCHDOG_USE_ISOLATED_LANE_WORKERS=0 WATCHDOG_RESTART_LOAD_LIMIT=999 bash scripts/run-team-watchdog.sh
git diff --check -- scripts/run-team-watchdog.sh .tmux-team/prompts/isolated-worker-template.md .tmux-team/prompts/worker-template.md supervisor.md audits/workflow-watchdog-isolation-20260524.md
```
