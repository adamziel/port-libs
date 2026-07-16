# OneDrive Cleanup Command Rework Refresh - 2026-05-25T14:30Z

## Rework Target

The main-repo handoff directory still contains stale rclone cleanup-command
rework markers for older candidates:

- `port-rclone-20260525T071643Z`
- `port-rclone-finisher-20260525T090717Z`
- `port-rclone-finisher-20260525T092801Z`
- `port-rclone-finisher-20260525T110120Z`

Those patches fail to apply because accepted rclone lane metadata has moved
forward. The requested lane action is to preserve accepted manifest/status
evidence and regenerate the cleanup-command evidence additively from the current
accepted HEAD.

## Resolution

This worktree already contains the native PHP cleanup-command behavior targeted
by those stale handoffs:

- remote argument validation rejects missing, extra, and empty command remotes
  before feature checks, traversal, type checks, provider calls, or version
  cleanup;
- disabled no-versions cleanup exits before feature checks, dry-run handling,
  traversal, type checks, provider calls, or version cleanup;
- masked cleanup support fails before traversal, type checks, or provider work;
- enabled non-object type errors stop before provider/version cleanup;
- per-object Graph delete and version-list errors are logged while cleanup
  continues to later objects;
- the WordPress WXR cleanup preflight exercises those boundaries without live
  OneDrive credentials, OAuth state, provider config, process environments, or
  provider remotes.

No older manifest, status, or notes rewrites were replayed. This refresh adds a
fresh lane-local note plus status wording so the integrator can drop the stale
cleanup-command candidates and use this clean additive patch.

## Verification Results

- `php -l lanes/rclone/src/OneDriveCleanupCommand.php`: passed.
- `php -l lanes/rclone/tests/OneDriveCleanupCommandTest.php`: passed.
- `php -l lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php`:
  passed.
- `jq empty lanes/rclone/lane-status.json lanes/rclone/UPSTREAM_TEST_MANIFEST.json`:
  passed.
- `php tools/run-tests.php lanes/rclone/tests/OneDriveCleanupCommandTest.php`:
  passed with 1 selected test file, 14 behavior tests, 93 assertions, and 0
  failures.
- `php tools/run-tests.php lanes/rclone/tests`: passed with 35 selected test
  files, 508 behavior tests, 4050 assertions, and 0 failures.
- Local example smoke for
  `wordpress-onedrive-cleanup-command-preflight.php`: passed.
- `git diff --check -- lanes/rclone`: passed.

## Blocker And Next Task

No rclone-local PHP blocker remains for this cleanup-command rework. Full
provider parity remains intentionally open for live OneDrive Graph/OAuth,
credential-backed provider remotes, mount/FUSE packages, Docker-backed serve
coverage, and upstream fstest/test_all remote matrices.

Next task: map another bounded credential-free OneDrive/provider lifecycle
cluster, such as a feature-mask edge case or narrow command wiring boundary that
does not require OAuth, provider config, live Graph access, or secret-bearing
inputs.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native OneDrive cleanup/version-cleaner model and deterministic local fixtures.
Live Graph/OAuth/provider integration, mount/FUSE packages, and credential-
bearing provider tests remain intentionally excluded.
