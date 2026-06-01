# real-upstream-corpus-vfs-io-dynamic-20260601T111330Z-0

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/shared2.test`
- Ported sections:
  - `shared2-1.1`: shared-cache `numbers` table setup with a primary-key index and 64 rows.
  - `shared2-1.2`: a read-uncommitted table-btree cursor is invalidated when a peer shared-cache connection deletes all rows mid-scan.
  - `shared2-1.3`: the same invalidation boundary applies to an index-btree scan.

## Local Changes

- Added `SQLiteVfsIoDynamicPlan::sharedCacheReadUncommittedDeleteProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsShared2ReadUncommittedDynamicTest.php` with 1,000 dynamic upstream-backed behavior cases plus source-citation, guard, non-overlap, and dependency-closure checks.

## Non-Overlap

This owns only `shared2.test` read-uncommitted cursor invalidation over table and index btrees. It avoids accepted `sharedlock.test` table-lock retention and OP_Clear coverage, `shmlock.test`, lock contention, lock4 deadlock, superlock, WAL readonly-lock behavior, VFS writer/sync/rollback-journal apply, ioerr fault recovery, and pager/WAL clusters.

## Dependency Closure

No new support component is needed. The slice reuses the bounded `SQLiteVfsIoDynamicPlan` surface and the hydrated upstream SQLite `shared2.test` source.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsShared2ReadUncommittedDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsShared2ReadUncommittedDynamicTest.php`: 1 test files, 31014 assertions, 0 failures. This adds 1,003 focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test files, 5 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite`: no whitespace errors.
- Root harness: not run - isolated micro-slice.
