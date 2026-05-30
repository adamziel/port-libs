# real-upstream-corpus-vfs-io-dynamic-20260530T171429Z-0

## Scope

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/atomic2.test`.
- Ported upstream scenario: `atomic2-2.0`, including injected `xWrite` and `xFileControl` I/O failures around F2FS-style atomic batch write commit.
- Focused behavior: if an I/O error is encountered before `COMMIT_ATOMIC_WRITE`, the pager retries through a legacy rollback-journal commit; after the commit-atomic boundary, pending fault injection is cleared and the committed row count plus integrity check remain valid.
- Focused assertions: `132` PASS lines / `746` assertions in the new PHP test file.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicBatchWriteFallbackTest.php`
  - Result: `1 test files, 746 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicBatchWriteFallbackTest.php`
  - Result: `4 test files, 1982 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicBatchWriteFallbackTest.php`
  - Result: no syntax errors.

## Non-Overlap

This slice avoids accepted VFS file writer, locked writer, process/file lock, sync plan/apply, rollback-journal apply/commit, super-journal commit, appendvfs layout, `io.test` quick-balance/atomic/safe-append/sequential traffic, `ioerr.test` pager boundaries, `walvfs.test`, B-tree, JSON, planner, trigger, and PRAGMA surfaces. The new surface is specifically upstream `atomic2.test` atomic-batch-write fallback after injected VFS callback failures.

## Dependency Closure

No new support component is needed. The patch extends the existing generic `SQLiteVfsIoTrafficPlan` with a lane-local model of the upstream atomic batch write fallback sequence and uses source-neutral application row-count/integrity terminology.
