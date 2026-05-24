# Lane-Group Integrator Workflow

Timestamp: 2026-05-24T22:35:44Z

## Groups

- `content-docs`: `readability`, `pandoc`, `markerpdf`
- `style-bundle`: `lightningcss`, `esbuild`, `difftastic`
- `storage-data`: `libsqlite`, `dolt`, `quadrable`
- `sync-git`: `gitoxide`, `syncthing`, `rclone`

## Usage

Launch one tmux session per group when integration intake is backed up:

```sh
tmux new-session -d -s port-integrator-content-docs 'cd /home/claude/port-libs && scripts/run-lane-group-integrator-loop.sh content-docs'
tmux new-session -d -s port-integrator-style-bundle 'cd /home/claude/port-libs && scripts/run-lane-group-integrator-loop.sh style-bundle'
tmux new-session -d -s port-integrator-storage-data 'cd /home/claude/port-libs && scripts/run-lane-group-integrator-loop.sh storage-data'
tmux new-session -d -s port-integrator-sync-git 'cd /home/claude/port-libs && scripts/run-lane-group-integrator-loop.sh sync-git'
```

The loop defaults to a 900 second interval. Override with
`LANE_GROUP_INTEGRATOR_INTERVAL_SECONDS=<seconds>` for shorter or longer
cycles. Each session uses its own lock under `.tmux-team/tmp/` and writes logs
under `.tmux-team/logs/`.

## Intake Contract

Group integrators process only ready markers for their owned lanes or isolated
patches explicitly tagged for the group. They may run focused lane checks and
`git diff --check -- lanes/<lane>`, but they must not run duplicate
no-argument root harnesses, regenerate dashboards, stage lane files, commit
lane progress, or remove global ready markers.

Accepted-for-root candidates are queued in
`.tmux-team/tmp/group-integrator-queue/` or recorded in a timestamped
`audits/workflow-integrator-groups-*.md` note. The serialized global integrator
or clean-patch integrator still performs final apply/stage, root verification,
commit, dashboard/status publication, and marker cleanup.

## Known Limitations

- This workflow reduces review/intake contention only. The root harness and
  commit gate remain intentionally serialized.
- Dirty shared-checkout batches are still unsafe when a lane has accumulated
  broad unrelated work; group integrators must reject or defer those handoffs.
- Isolated clean patches remain owned by the clean-patch/root acceptor unless
  they are explicitly tagged for group pre-review.
- The existing `.tmux-team/prompts/integrator.md` behavior is preserved as the
  fallback authority for root acceptance.
