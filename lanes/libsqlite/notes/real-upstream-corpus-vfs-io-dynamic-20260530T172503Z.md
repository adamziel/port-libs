# real-upstream-corpus-vfs-io-dynamic-20260530T172503Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T172503Z-0`

Accepted base: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr5.test`
  - `ioerr5-1.$locking_mode-$iFail.1` through `.4`: persistent commit I/O errors with an open read cursor keep the pager in error state while memory reclamation must not write dirty data back to the database image.
  - `ioerr5-2.$locking_mode-$iFail.1` and `.3a/.3b`: persistent write errors during memory release require rollback/error handling before a successful commit path.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr6.test`
  - `1.1` and `1.2`: first SHM/write-full failure returns full error and leaves `PRAGMA integrity_check` at `ok`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pagerfault.test`
  - `pagerfault-29` and `pagerfault-30`: repeated xWrite/xUnlock failures during hot-journal rollback leave locking state unknown, then recovery succeeds either by retry or by close/reopen.

## Patch

- Added `SQLiteVfsIoTrafficPlan::dynamicFaultRecovery()` to model these VFS/pager dynamic fault outcomes.
- Extended `SQLiteRealUpstreamVfsIoDynamicCorpusTest.php` with a dynamic recovery matrix covering 112 distinct upstream-derived fault rows plus focused citation and guard checks.

## Evidence

- Before patch focused VFS IO dynamic gate:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`
  - `3 test files, 2109 assertions, 0 failures`
- Focused changed file after patch:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`
  - `1 test files, 2818 assertions, 0 failures`
- Combined focused VFS IO dynamic gate after patch:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`
  - `3 test files, 4813 assertions, 0 failures`

## Delta

- Focused PHP PASS cases: `+4`
- Focused assertions: `+2704`
- Mapped denominator rows: unchanged; this is accepted-test growth only.

## Dependency closure

No new support component is needed. The patch reuses the existing VFS I/O traffic planner and extends it with bounded PHP-native recovery state modeling. No live service, upstream runner mutation, or shared checkout edit is required.

## Non-overlap

This does not repeat accepted VFS file writer, rollback-journal apply/commit, lock-byte, process-lock, sync-plan/apply, pager/WAL checkpoint, JSON, B-tree, or SQL executor clusters. The added coverage is specifically dynamic fault recovery from `ioerr5.test`, `ioerr6.test`, and `pagerfault.test`.
