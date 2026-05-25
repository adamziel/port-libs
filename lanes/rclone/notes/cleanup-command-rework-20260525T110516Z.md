# OneDrive Cleanup Command Rework Refresh

Date: 2026-05-25
Lane: rclone
Micro-slice: priority-finisher-20260525T110516Z

## Rework Target

The main handoff directory still contains stale rclone cleanup-command patches
that failed to apply after newer accepted OneDrive lifecycle evidence landed.
This refresh rebases that exact credential-free cleanup command behavior onto
the current accepted HEAD without overwriting accepted manifest/status evidence.

## Result

The accepted worktree already contains the cleanup-command behavior requested by
the stale handoffs:

- command remote arity rejects missing, extra, and empty remotes before feature,
  traversal, type, or provider work;
- disabled `noVersions=false` cleanup exits before feature, traversal, provider,
  dry-run, and object-type checks;
- feature-masked cleanup fails before traversal/type/provider work;
- enabled non-object entries fail before provider/version cleanup;
- object cleanup preserves current versions, deletes or dry-run skips old
  versions, logs per-object delete/list failures, and continues to later
  objects;
- the WordPress WXR cleanup preflight stays credential-free and records no
  secret/provider input reads.

No source behavior needed to be changed for this rework refresh. The patch is
intentionally lane-local metadata plus this note so integration can accept a
fresh additive patch instead of replaying stale manifest/status contexts.

## Focused Evidence To Run

- `php -l lanes/rclone/src/OneDriveCleanupCommand.php`
- `php -l lanes/rclone/tests/OneDriveCleanupCommandTest.php`
- `php -l lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php`
- `php tools/run-tests.php lanes/rclone/tests/OneDriveCleanupCommandTest.php`
- `php tools/run-tests.php lanes/rclone/tests`
- `php -r '$example = require "lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php"; if (($example["secretInputsRead"] ?? true) !== false) { exit(1); }'`
- `jq empty lanes/rclone/UPSTREAM_TEST_MANIFEST.json lanes/rclone/lane-status.json`
- `git diff --check -- lanes/rclone`

## Dependency Closure

No new support component is needed. This rework reuses the existing bounded
native OneDrive version-cleaner and cleanup-command simulator, deliberately
avoids live OneDrive OAuth/provider tests, and does not read token stores,
provider config, process environments, cloud remotes, or other secret-bearing
inputs.

## Next Task

Map another bounded credential-free OneDrive/provider lifecycle cluster, such as
a feature-mask edge case or a narrow command wiring boundary not requiring
OAuth/live Graph access.
