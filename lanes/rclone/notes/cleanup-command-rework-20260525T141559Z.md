# OneDrive Cleanup Command Rework Refresh - 2026-05-25T14:15Z

## Rework Target

The main-repo handoff directory still has stale rclone cleanup-command markers:

- `port-rclone-20260525T071643Z`
- `port-rclone-finisher-20260525T090717Z`
- `port-rclone-finisher-20260525T092801Z`
- `port-rclone-finisher-20260525T110120Z`

All four markers ask for the same lane-owner action: regenerate the OneDrive
cleanup-command evidence on the accepted rclone lane baseline without replaying
older manifest, status, and note rewrites.

## Resolution

The accepted HEAD for this isolated worktree already includes the native PHP
cleanup-command behavior from those stale patches:

- command remote-argument validation rejects missing, extra, and empty remotes
  before feature, traversal, type, provider, or version-cleanup work;
- disabled no-versions cleanup returns before feature, dry-run, traversal, type,
  provider, or version-cleanup work;
- masked cleanup support fails before traversal/type/provider work;
- enabled non-object type errors stop before provider/version cleanup;
- object cleanup preserves current versions, deletes or dry-run skips old
  versions, and logs per-object Graph delete/list failures while continuing
  later objects;
- the WordPress cleanup-command preflight covers all of those boundaries without
  reading OAuth state, provider remotes, config files, process environments, or
  live credentials.

This micro-slice is intentionally metadata-only and additive. It preserves the
accepted manifest/status evidence, records that the four stale rework requests
are satisfied by the current accepted tree, and reruns focused local verification.

## Verification Results

- `php -l lanes/rclone/src/OneDriveCleanupCommand.php`: passed.
- `php -l lanes/rclone/tests/OneDriveCleanupCommandTest.php`: passed.
- `php -l lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php`: passed.
- `php tools/run-tests.php lanes/rclone/tests/OneDriveCleanupCommandTest.php`: passed with 1 selected test file, 14 behavior tests, 93 assertions, and 0 failures.
- `php tools/run-tests.php lanes/rclone/tests`: passed with 35 selected test files, 508 behavior tests, 4050 assertions, and 0 failures.
- `php -r '$example = require "lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php"; if (($example["source"] ?? null) !== "onedrive-cleanup-command-preflight" || ($example["secretInputsRead"] ?? true) !== false) { fwrite(STDERR, "cleanup example smoke failed\n"); exit(1); } echo "cleanup example smoke passed\n";'`: passed.
- `jq empty lanes/rclone/lane-status.json lanes/rclone/UPSTREAM_TEST_MANIFEST.json`: passed.
- `git diff --check -- lanes/rclone`: passed.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This refresh reuses the existing bounded
native OneDrive cleanup/version-cleaner model and deterministic local fixtures.
Live Graph/OAuth/provider integration, mount/FUSE packages, and credential-
bearing provider tests remain intentionally excluded.
