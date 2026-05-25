# OneDrive Cleanup Command RC Rework Refresh - 2026-05-25T16:37Z

## Rework Target

The main handoff directory still contains stale rclone cleanup-command patches
that fail to apply because accepted rclone manifest/status/notes evidence has
moved forward. This refresh keeps the accepted evidence and makes the remaining
RC cleanup boundary additive.

## Behavior Delta

The existing native cleanup command already covers command remote arity,
disabled no-versions bypasses, feature-mask ordering, type-error ordering,
per-object version-list/delete logging, dry-run skips, and the WordPress WXR
cleanup preflight. This rework adds focused RC evidence for `operations/cleanup`:

- the RC path requires the `fs` parameter before cleanup feature, traversal,
  provider, version-cleanup, or disabled no-versions logic;
- command-style `remoteArgs` are ignored by the RC path once `fs` is present;
- unsupported cleanup features still fail before provider/version work;
- no live OneDrive provider, OAuth/token store, cloud remote, process
  environment, or credential-bearing input is read.

## Verification Plan

- PHP lint changed cleanup source/test/example files.
- Run the focused cleanup command test.
- Run the focused rclone lane test directory.
- Run the local WordPress cleanup-command example smoke.
- Validate lane JSON and run `git diff --check -- lanes/rclone`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native
OneDrive version-cleaner and cleanup command model plus deterministic local
fixtures.
