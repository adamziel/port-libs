# OneDrive Cleanup Command Rework Refresh - 2026-05-25 12:26 UTC

## Scope

This isolated rework resolves the stale rclone handoff conflicts for the OneDrive cleanup command slice without replaying older manifest/status rewrites. The current accepted worktree already contains the native cleanup command behavior that the rework notes requested, so this patch records the additive rebase evidence on top of the accepted metadata.

## Upstream Evidence

- `cmd/cleanup/cleanup.go`: command wiring expects exactly one remote argument before provider cleanup work.
- `fs/operations/operations.go`: cleanup checks the provider `CleanUp` feature before traversal/destructive work.
- `backend/onedrive/onedrive.go`: `CleanUp`, `deleteVersions`, and `deleteVersion` preserve current versions, delete older versions, and log per-object cleanup failures while allowing the walk to continue.
- `fs/features.go`: feature masking can hide cleanup support and must fail before dry-run, traversal, or type-specific provider work.

## Native Coverage Preserved

- `OneDriveCleanupCommand::validateRemoteArgs()` rejects missing, extra, and empty remotes before feature checks, traversal errors, or provider work.
- `noVersions=false` bypasses feature checks, traversal state, dry-run state, and non-object type checks.
- Feature-masked cleanup fails before dry-run skip accounting, traversal errors, type errors, or version cleanup.
- Enabled cleanup walks objects, preserves current versions, deletes or dry-run skips stale versions, and logs list/delete failures per object while continuing later objects.
- Enabled non-object entries fail before provider/version cleanup.

## WordPress Smoke

`examples/wordpress-onedrive-cleanup-command-preflight.php` remains the user-visible smoke path. It models WXR/media version cleanup, disabled cleanup bypasses, command arity preflights, type-error ordering, and Graph list/delete failures without reading OAuth state, provider config, token stores, process environments, cloud remotes, or live credentials.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native PHP OneDrive version-cleanup and command-state model; live Graph endpoints, OAuth token sources, and provider integration suites remain intentionally excluded.

## Verification Plan

- Lint changed/relevant PHP files.
- Run focused `OneDriveCleanupCommandTest.php`.
- Run the rclone lane tests only.
- Smoke the cleanup-command WordPress example locally.
- Validate lane JSON and run `git diff --check -- lanes/rclone`.
- Do not run the no-argument root harness from this isolated micro-slice.
