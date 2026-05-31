# real-upstream-corpus-vfs-io-dynamic-20260531T031953Z-0

## Scope

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/shmlock.test`.
- Ported sections:
  - `shmlock.test` `1.3`: deterministic SHM byte-range shared/exclusive lock and unlock conflict matrix.
  - `shmlock.test` `2.0` through `2.6`: unix-excl shared-reader saturation and exclusive-lock admission after readers drain.
  - `shmlock.test` `3.1` and `3.2`: two-client randomized shared-memory lock conflict oracle.

## Behavior Added

- Added `SQLiteVfsShmLockMatrixPlan`, a generic lane-local model for SQLite WAL-index SHM byte-range locks.
- Added `SQLiteRealUpstreamCorpusVfsShmLockDynamicTest.php` with a scripted upstream matrix, shared-reader saturation case, deterministic randomized two-client conflict chunks, and malformed-operation guards.
- The behavior is generic SQLite VFS/WAL locking only; no WordPress-specific source API is introduced.

## Non-Overlap

This slice does not repeat accepted VFS file writer, VFS sync/apply, rollback-journal apply/commit, process file locks, VFS lock-state, lock-byte URI SHM current-source, appendvfs, ioerr, sysfault, mmap, `io.test` sync/device matrices, WAL checkpoint transaction, or pager/WAL readonly-SHM refresh clusters. The owned upstream surface is `shmlock.test` SHM byte-range conflict semantics.

## Dependency Closure

No new external support component is needed. The slice adds a bounded native PHP SHM lock matrix planner under `lanes/libsqlite/src` and uses it directly from focused corpus tests.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsShmLockMatrixPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsShmLockDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsShmLockDynamicTest.php`
  - `1 test files, 7458 assertions, 0 failures`
  - 526 focused PASS lines
