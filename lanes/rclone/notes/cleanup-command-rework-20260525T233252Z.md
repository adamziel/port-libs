# Cleanup command rework 2026-05-25T23:32:52Z

This isolated micro-slice addresses the stale rclone handoff rework notes by keeping the accepted OneDrive cleanup command and RC dispatch behavior intact while adding native, machine-readable ordering evidence.

Behavior delta:

- `OneDriveCleanupCommand::run()` now reports `stoppedAt` for `command-arity`, `disabled-no-versions`, `feature-gate`, `walk`, `type-check`, and `complete` outcomes.
- `OneDriveCleanupCommand::runRemoteControl()` now reports `stoppedAt=rc-fs` when the RC `fs` parameter is missing or empty before any cleanup work, including disabled no-versions cleanup.
- The WordPress cleanup-command preflight exposes these diagnostics so the command-vs-RC ordering can be smoke-checked without live OneDrive credentials.

Upstream evidence:

- Reuses the accepted static reads of `cmd/cleanup/cleanup.go`, `fs/operations/operations.go` `CleanUp`, `fs/operations/rc.go` `operations/cleanup`, `fs/features.go` `CleanUpper` wiring, and `backend/onedrive/onedrive.go` `CleanUp` / `deleteVersions` / `deleteVersion` / `no_versions` call sites.
- No live provider test, OAuth flow, token store, process environment, cloud remote, provider config, or credential-bearing input was read or executed.

Dependency closure:

- No new support component is needed.
- This reuses the existing bounded native OneDrive version cleanup model and RC parameter model.

Verification for this note is recorded in `lane-status.json` after the focused test rerun.
