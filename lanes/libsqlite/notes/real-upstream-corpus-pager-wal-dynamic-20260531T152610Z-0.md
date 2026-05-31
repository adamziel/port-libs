# real-upstream-corpus-pager-wal-dynamic-20260531T152610Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/walshared.test`, specifically `walshared-1.0` through `walshared-1.4`.

Behavior ported: WAL mode with shared-cache enabled admits two handles to the same database. While the first handle has an open write transaction that inserts and expands rows, `PRAGMA wal_checkpoint` on both the writer handle and the peer handle is blocked with `database table is locked`. After `COMMIT`, the peer handle sees `PRAGMA integrity_check` return `ok`.

Patch contents:

- `SQLitePagerWalDynamicPlan::walSharedCacheCheckpointPlan()` models the shared-cache WAL checkpoint lock boundary, validating cache/row/connection inputs and returning the checkpoint attempt results, visible row counts, WAL-frame estimate, and upstream dependency labels.
- `SQLiteRealUpstreamCorpusPagerWalSharedCacheDynamic20260531T152610ZTest.php` adds 1000 dynamic upstream-backed rows plus hydrated-source, row-count/non-overlap, and malformed-input checks.
- `lane-status.json` records selected evidence movement from `2936779` to `2937782` PASS cases. Mapped coverage stays `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSharedCacheDynamic20260531T152610ZTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSharedCacheDynamic20260531T152610ZTest.php`: `1 test files, 32018 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php $(rg -l "SQLitePagerWalDynamicPlan::" lanes/libsqlite/tests | sort)`: `13 test files, 314391 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.

Non-overlap: this covers `walshared.test` shared-cache checkpoint table-lock behavior. It avoids accepted `walsetlk` timeout/snapshot, `wal5` and `e_walckpt` checkpoint modes, `e_walauto` hook replacement, `walnoshm` exclusive conversion, VFS process locks, VFS writer/sync/lock-state, rollback-journal apply/commit, WAL byte truncation, and application-WAL recovery slices.

Dependency closure: no new support component is needed; this reuses lane-local pager/WAL dynamic planning and the hydrated upstream SQLite source checkout.

Root harness: not run - isolated micro-slice.
