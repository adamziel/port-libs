# real-upstream-corpus-vfs-io-dynamic-20260531T155415Z-0

Status: ready isolated libsqlite handoff from base `b396f617ce3725e2a3fde790e5dc3841675ab023`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/sharedlock.test`
- Covered sections:
  - `sharedlock-1.1` shared-cache table setup.
  - `sharedlock-1.2` same-connection insert during a read cursor keeps the table read-lock and blocks a peer writer.
  - `sharedlock-2.1` through `sharedlock-2.5` full-table `DELETE FROM t2 WHERE 1` and `DELETE FROM t2` use the OP_Clear path, take a table write-lock, block peer reads, and release the lock at commit.

Behavior added:

- Added `SQLiteVfsIoDynamicPlan::sharedCacheTableLockProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsSharedLockDynamicTest.php` with 500 dynamic read-lock retention cases and 500 dynamic OP_Clear write-lock cases, plus hydrated-source, malformed-input, non-overlap, and dependency-closure checks.
- Selected `phpPass` moves from `3137763` to `3138766` (+1003 focused PASS cases). Mapped denominator remains `1589 / 1589`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSharedLockDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSharedLockDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSharedLockDynamicTest.php`
  - `1 test files, 25017 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `jq empty lanes/libsqlite/lane-status.json`
  - passed
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- This owns `sharedlock.test` shared-cache table read-lock retention and OP_Clear table write-lock behavior only.
- It avoids accepted `lock.test`/`lock2.test`/`lock3.test`/`lock5.test`/`lock7.test` contention coverage, `lock4.test` deadlock, `nolock.test` URI suppression, `superlock.test`, WAL shared-cache checkpoint behavior, VFS writer/sync/rollback-journal apply, `ioerr*`, appendvfs, cksumvfs, mmap, diskfull, bigfile, and file-control clusters.

Dependency closure:

- No new support component is needed. The slice reuses the bounded generic `SQLiteVfsIoDynamicPlan` surface and the hydrated upstream `sharedlock.test` source as evidence.
