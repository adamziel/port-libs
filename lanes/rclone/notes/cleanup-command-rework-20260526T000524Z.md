# OneDrive Cleanup Command Rework Refresh - 2026-05-26T000524Z

## Rework Scope

The main handoff directory still contains stale rclone cleanup-command rework markers whose patches no longer apply cleanly to the accepted baseline. This refresh preserves the accepted rclone cleanup-command evidence and adds only an additive lane-local status/manifest note so the integrator can accept a fresh patch without replaying older manifest/status conflicts.

The accepted lane implementation already covers the requested cleanup-command behavior:

- command-mode cleanup rejects missing, extra, and empty remote arguments before feature checks, traversal, type checks, provider/version work, or disabled no-versions bypasses;
- a single non-empty command remote reaches the disabled no-versions short-circuit and the feature gate without provider/version work;
- RC `operations/cleanup` requires `fs` before cleanup work, including disabled no-versions cleanup;
- RC cleanup ignores command-style `remoteArgs` once `fs` is present for both enabled cleanup and disabled cleanup;
- explicit `stoppedAt` diagnostics cover `command-arity`, `disabled-no-versions`, `feature-gate`, `walk`, `type-check`, `rc-fs`, and `complete`;
- provider/version work remains a deterministic local simulation and does not use live OneDrive services.

## Upstream Evidence

This remains a credential-free static mapping of:

- `cmd/cleanup/cleanup.go` command wiring;
- `fs/operations/operations.go` `CleanUp`;
- `fs/operations/rc.go` `operations/cleanup`;
- `fs/features.go` `CleanUpper` feature wiring;
- `backend/onedrive/onedrive.go` `CleanUp`, `deleteVersions`, `deleteVersion`, and `no_versions` call sites.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native OneDrive version-cleanup and RC parameter models, and deliberately avoids live Graph calls, token stores, process environments, OAuth browser state, cloud remotes, provider config, and provider credentials.

## Verification Evidence

Focused verification for this refresh passed:

- PHP syntax checks for the cleanup command source, test, and WordPress example;
- focused `OneDriveCleanupCommandTest.php`: 16 behavior tests, 180 assertions, 0 failures;
- full focused rclone lane tests: 35 test files, 4137 assertions, 0 failures;
- local WordPress cleanup-command example smoke;
- `jq empty` for the lane manifest and status;
- `git diff --check -- lanes/rclone`.

The no-argument root harness is intentionally not run for this isolated micro-slice.
