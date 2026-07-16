# real-upstream-corpus-pager-wal-wal2-shm-header-dynamic-20260531T054408Z-0

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T054408Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- Covered scenario ranges: `wal2-1.2` through `wal2-1.12` corrupt wal-index
  header recovery and `wal2-2.2` through `wal2-2.9` stale but checksum-valid
  wal-index header snapshots.

Focused PHP coverage:

- Added `SQLiteRealUpstreamPagerWalWal2ShmHeaderDynamicTest.php`.
- Adds 1,000 dynamic TestRunner cases plus one hydrated-source citation case.
- Exercises WAL parsing/checksum validation, committed transaction grouping,
  SHM read-mark planning, corrupt header-copy rebuilding,
  stale-header/current-reader visibility, pinned reader checkpoint behavior,
  restart/truncate checkpoint transitions, and latest-versus-stale reader
  snapshot boundaries.

Non-overlap:

- This does not repeat accepted pager/WAL persist, overwrite, noop checkpoint,
  crash recovery, full-sync, readonly-SHM truncate, lock-recovery, setlk,
  snapshot-boundary, or previous generic dynamic WAL batches. It owns the
  `wal2.test` SHM header corruption/stale-header behavior cluster.
- It adds no metadata-only runner rows and no domain-specific API names.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalWal2ShmHeaderDynamicTest.php`
  -> no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalWal2ShmHeaderDynamicTest.php`
  -> `1 test files, 43001 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteWal`,
  `SQLiteWalHeader`, and `SQLiteShmIndex` primitives.

Next task:

- Continue reducing real pager/WAL default-memory and release/all-runner gaps,
  especially remaining WAL/pager pressure cases that require broader VFS
  transaction application rather than another standalone metadata admission.
