# OneDrive Cleanup Command Rework Refresh - 2026-05-26T002947Z

## Rework Scope

This isolated continuous-dev slice resolves the remaining stale rclone cleanup-command rework markers additively on top of accepted HEAD. The older rejected patches conflicted in shared manifest/status/note context; the current baseline already contains the cleanup command and RC behavior, so this patch adds a narrow executable edge rather than replaying stale files.

Command-mode cleanup now treats whitespace-only remote arguments as invalid remote arity. That validation happens before feature checks, traversal, object type checks, provider/version work, and before disabled no-versions cleanup can bypass provider work. RC `operations/cleanup` remains `fs`-parameter driven and continues to ignore command-style `remoteArgs` once `fs` is present.

## Upstream Evidence

The behavior remains mapped from credential-free static reads of:

- `cmd/cleanup/cleanup.go` command argument wiring;
- `fs/operations/operations.go` `CleanUp`;
- `fs/operations/rc.go` `operations/cleanup`;
- `fs/features.go` `CleanUpper` feature masking;
- `backend/onedrive/onedrive.go` `CleanUp`, `deleteVersions`, `deleteVersion`, and `no_versions` call sites.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native OneDrive cleanup/version-cleanup and RC parameter models, and does not read process environments, provider config, OAuth state, token stores, cloud remotes, or live provider credentials.

## Verification Plan

Focused verification for this refresh is local only:

- PHP syntax checks for changed cleanup-command PHP files;
- focused `OneDriveCleanupCommandTest.php`;
- focused rclone lane tests;
- local WordPress cleanup-command example smoke;
- `jq empty` for lane manifest/status;
- `git diff --check -- lanes/rclone`.

The no-argument root harness is intentionally not run for this isolated micro-slice.
