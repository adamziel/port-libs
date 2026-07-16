# real-upstream-corpus-pager-wal-dynamic-20260531T034147Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T034147Z`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Ported sections:
  - `wal2-1.2` through `wal2-1.12`: corrupt wal-index header fields force recovery before the reader observes the newly inserted rows.
  - `wal2-2.2` through `wal2-2.9`: a checksum-valid but stale wal-index header can yield the stale reader snapshot before a later corrupt header forces recovery to the fresh snapshot.

## PHP coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalIndexHeaderRecoveryDynamicTest.php`.
- Adds 1,000 dynamic TestRunner PASS cases plus one provenance case.
- Each dynamic case varies page size, database page count, inserted WAL frame count, stale snapshot boundary, checksum byte order, and the wal-index header field being perturbed.
- The cases exercise native `SQLiteWal` parsing, checksum validation, committed-frame recovery boundaries, reader snapshot page counts, stale-versus-current snapshot boundaries, and the recovery/read lock-path distinction from upstream `wal2.test`.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalIndexHeaderRecoveryDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalIndexHeaderRecoveryDynamicTest.php`
- Generic API guard: not run because `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This extends pager/WAL real upstream behavior without repeating accepted `wal2-15.*` checkpoint/fullfsync coverage, `walckptnoop.test`, `waloverwrite.test`, `walpersist.test`, `walhook.test`, WAL savepoint byte truncation, VFS writer/sync/lock/rollback clusters, WAL checkpoint transaction plans, rollback-journal commit/super-journal work, or pager master-journal reader-cache surfaces. The owned behavior is specifically `wal2.test` wal-index header corruption and stale-header snapshot recovery.

## Dependency closure

No new support component is needed. The slice reuses lane-local `SQLiteWal` parsing, checksum, reader-snapshot, and transaction-recovery-boundary primitives against real upstream `wal2.test` behavior.
