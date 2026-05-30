# real-upstream-corpus-pager-wal-dynamic-20260530T212322Z-0

Implemented real upstream pager/WAL coverage from `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`, section `wal2-14.1` through `wal2-14.3`.

The slice adds `SQLiteRealUpstreamPagerWalDynamicPlan::wal2CheckpointFullSyncCases()` and focused TestRunner coverage for `PRAGMA checkpoint_fullfsync` sync/fullsync count behavior across checkpoint modes, synchronous modes, page sizes, and WAL autocheckpoint settings. The upstream-derived expected sync count triples are `{10 0 4 0 6 0}`, `{10 6 4 3 6 3}`, and `{10 0 4 0 6 0}`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalFullSyncDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalFullSyncDynamicTest.php` passed with `1 test files, 21009 assertions, 0 failures` and `1001` PASS lines.

Non-overlap:

- Uses real upstream `wal2.test wal2-14.*` checkpoint fullsync behavior.
- Does not repeat accepted WAL byte truncation, rollback journal apply/commit, super-journal commit, VFS sync plan/apply, WAL checkpoint transaction plan, WAL mode/persist/readonly checkpoint, or existing `wal2-1..6` lock/recovery matrix coverage.

Dependency closure:

- No new support component is needed. The slice reuses the existing pager/WAL dynamic corpus plan and existing VFS sync-plan dependency marker for behavior evidence.
