# OneDrive Cleanup Command Rework Refresh - 2026-05-25T13:45Z

## Rework Target

The main-repo handoff directory still has stale rclone cleanup-command rework
markers for:

- `port-rclone-20260525T071643Z`
- `port-rclone-finisher-20260525T090717Z`
- `port-rclone-finisher-20260525T092801Z`
- `port-rclone-finisher-20260525T110120Z`

Each marker asks the lane to rebase OneDrive cleanup-command evidence on top of
newer accepted rclone manifest/status/notes content instead of replaying older
patches that rewrite the same files.

## Resolution

This worktree's accepted HEAD already contains the cleanup-command behavior that
the stale patches tried to add:

- command remote-argument arity validation rejects missing, extra, and empty
  remotes before feature, traversal, type, provider, or version-cleanup work;
- disabled no-versions cleanup exits before feature, traversal, dry-run, type,
  provider, or version-cleanup work;
- feature-masked cleanup fails before traversal/type/provider work;
- traversal and non-object type errors stop before provider version cleanup;
- object cleanup preserves current versions, deletes or dry-run skips old
  versions, and logs per-object Graph delete/list failures while continuing to
  later objects;
- the WordPress WXR cleanup preflight covers the same credential-free command
  boundaries without reading OAuth state, provider remotes, config files, process
  environments, or live credentials.

The rework patch is therefore additive: it preserves the accepted manifest and
status evidence, adds this note, and refreshes lane status wording for the
current `priority-refill-20260525T134529Z` micro-slice.

## Focused Evidence Plan

- Syntax-check changed PHP files.
- Run the focused cleanup command test file.
- Run focused rclone lane tests.
- Run the touched WordPress cleanup-command example smoke.
- Validate lane JSON and `git diff --check -- lanes/rclone`.

## Verification Results

- `php -l lanes/rclone/src/OneDriveCleanupCommand.php && php -l lanes/rclone/tests/OneDriveCleanupCommandTest.php && php -l lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php`: passed.
- `php tools/run-tests.php lanes/rclone/tests/OneDriveCleanupCommandTest.php`: passed with 1 selected test file, 93 assertions, and 0 failures.
- `php tools/run-tests.php lanes/rclone/tests`: passed with 35 selected test files, 4050 assertions, and 0 failures.
- `php -r '$example = require "lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php"; if (($example["source"] ?? null) !== "onedrive-cleanup-command-preflight" || ($example["secretInputsRead"] ?? true) !== false) { fwrite(STDERR, "cleanup example smoke failed\n"); exit(1); } echo "cleanup example smoke passed\n";'`: passed.
- `jq empty lanes/rclone/lane-status.json lanes/rclone/UPSTREAM_TEST_MANIFEST.json`: passed.
- `git diff --check -- lanes/rclone`: passed.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
OneDrive cleanup/version-cleaner abstractions and deterministic in-memory
fixtures. Live Graph/OAuth/provider integration, mount/FUSE packages, and
credential-bearing provider tests remain intentionally excluded.
