# OneDrive Cleanup Command Closure Rework

Micro-slice: `closure-refill-20260525T174156Z`

This rework responds to the rclone handoff notes under the main checkout's
`.tmux-team/tmp/handoff-candidates/port-rclone-*.needs-lane-rework.md`.

Current accepted HEAD already contains the rejected cleanup-command behavior:

- command-mode `cleanup` validates exactly one non-empty remote argument before
  feature checks, traversal, type checks, provider/version work, or the disabled
  no-versions bypass;
- RC `operations/cleanup` requires `fs` before cleanup work, including disabled
  no-versions cleanup, and ignores command-style `remoteArgs` once `fs` exists;
- disabled no-versions cleanup exits before feature/traversal/provider work;
- enabled cleanup checks the CleanUpper feature before dry-run/traversal/type
  errors and stops non-object entries before provider/version cleanup;
- per-object OneDrive version delete/list failures are logged while later object
  cleanup continues.

The fresh closure-refill patch is intentionally additive metadata/evidence only:
it preserves accepted manifest/status evidence, updates the local manifest count
to the current 510 focused PHP behavior tests, and records this clean rework
without replaying stale note/context hunks that conflicted on main.

Dependency closure: no new support component is needed. This reuses the existing
bounded native OneDrive version-cleanup model and RC parameter model, avoids live
Graph calls, token stores, OAuth/browser state, provider config, process
environments, cloud remotes, and credential-bearing inputs.

Focused verification evidence:

- `php -l lanes/rclone/src/OneDriveCleanupCommand.php` passed.
- `php -l lanes/rclone/tests/OneDriveCleanupCommandTest.php` passed.
- `php -l lanes/rclone/examples/wordpress-onedrive-cleanup-command-preflight.php` passed.
- `php tools/run-tests.php lanes/rclone/tests/OneDriveCleanupCommandTest.php`
  passed with 1 selected test file, 133 assertions, and 0 failures.
- `php tools/run-tests.php lanes/rclone/tests` passed with 35 selected test
  files, 4090 assertions, and 0 failures.
- Local cleanup-command example smoke passed for source, secret-input, command
  disabled-arity, and RC disabled-fs guards.
- `jq empty lanes/rclone/UPSTREAM_TEST_MANIFEST.json lanes/rclone/lane-status.json`
  passed.
- `git diff --check -- lanes/rclone` passed.

Root harness status: not run - isolated micro-slice.
