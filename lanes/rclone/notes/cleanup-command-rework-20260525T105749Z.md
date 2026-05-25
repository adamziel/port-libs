# OneDrive Cleanup Command Rework Refresh

Date: 2026-05-25
Lane: rclone
Micro-slice: priority-finisher-20260525T105749Z

## Rework Target

The main handoff directory had stale rclone cleanup-command patches that no longer applied cleanly after newer accepted OneDrive lifecycle evidence landed. The requested lane action was to rebase the credential-free OneDrive cleanup command behavior on top of the current accepted HEAD, preserve accepted manifest/status evidence, and leave a fresh additive lane patch.

## Result

The current accepted worktree already contains the cleanup-command behavior that the stale patches attempted to apply:

- command remote arity rejects missing, extra, and empty remote arguments before feature, traversal, type, or provider work;
- disabled `noVersions=false` cleanup exits before feature, traversal, provider, dry-run, and object-type checks;
- feature-masked cleanup fails before traversal/type/provider work;
- enabled non-object entries fail before provider/version cleanup;
- object cleanup preserves current versions, deletes or dry-run skips old versions, logs per-object delete/list failures, and continues to later objects;
- the WordPress WXR cleanup preflight remains credential-free and records no secret/provider inputs.

No source or test behavior needed to be changed for this rework refresh. This patch is intentionally metadata-only plus this note so the integrator can accept a fresh patch without replaying stale manifest/status contexts over newer accepted rclone evidence.

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

No new support component is needed. This rework reuses the existing bounded OneDrive version-cleaner and cleanup-command local simulator, deliberately avoids live OneDrive OAuth/provider tests, and does not read token stores, provider config, process environments, cloud remotes, or other secret-bearing inputs.

## Next Task

Map another bounded credential-free OneDrive/provider lifecycle cluster, such as a feature-mask edge case or a narrow command wiring boundary not requiring OAuth/live Graph access.
