# rclone march no-traverse evidence - 2026-05-23T03:30:15Z

## Scope

- Lane: `rclone`
- Slice: native PHP planning for upstream `fs/march --no-traverse` destination object lookups used by copy/sync transfer phases.
- Upstream references checked: `.upstream-cache/rclone/fs/march/march.go` `processJob`, plus `TestCopyNoTraverse`, `TestCopyNoTraverseDeadlock`, and `TestSyncNoTraverse` in `.upstream-cache/rclone/fs/sync/sync_test.go`.

## Key Decisions

- `SyncPlan::copyChanged()` now accepts `noTraverse` and reports bounded `noTraverseStats`.
- In no-traverse mode, included source objects probe the destination by exact remote path via the provider object lookup abstraction.
- Source directories are recorded as source-only recursion entries and are not destination object probes.
- `noCheckDest` bypasses all destination probes.
- Destination listing is not used for matching in no-traverse mode, including the `ignoreCaseSync` path.
- The WordPress backup example probes only filtered WXR, SQL, and upload artifacts, leaving cache objects unlisted and untouched.

## Verification

- Rclone lane tests: `22` test files, `276` tests, `2440` assertions, `0` failures.
- Touched example `lanes/rclone/examples/wordpress-no-traverse-copy.php`: `4` destination object probes, `2` matches, `2` misses, `targetListUsed=false`; copied `database/site.sql`, `wp-content/uploads/2026/05/hero.jpg`, and `wp-content/uploads/2026/05/hero.webp`.
- Touched prior example `lanes/rclone/examples/wordpress-files-from-no-traverse-restore.php`: `source=filesFrom`, `4` explicit lookups, `0` provider List calls, `0` provider ListR calls, `3` listed objects, missing WXR skipped.
- JSON validation: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json` and `lanes/rclone/lane-status.json` valid.
- Whitespace: `git diff --check -- lanes/rclone` passed.
- Root suite: `php tools/run-tests.php` passed with `178` test files, `17211` assertions, `0` failures.

## Blockers

- No rclone-local blocker.
- Full live rclone provider/mount parity remains out of scope for this slice: provider `TestIntegration`, FUSE mount packages, Docker-backed serve/docker coverage, and `fstest/test_all` provider remotes remain intentionally excluded.
