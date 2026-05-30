# Real Upstream Corpus VFS IO Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T185459Z-0`

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
- Ported sections: `ioerr-1`, `ioerr-2`, `ioerr-4`, `ioerr-7`, `ioerr-10`, `ioerr-12`, `ioerr-13`, and `ioerr-14`.

## Behavior

Adds `SQLiteVfsIoDynamicPlan::ioErrorRecoveryProfile()` for bounded native PHP modeling of SQLite VFS I/O fault recovery behavior:

- transaction I/O errors with autovacuum read-error suppression;
- VACUUM temporary-header/autovacuum excluded fault positions;
- overflow record-header read errors;
- hot-journal rollback replay boundaries;
- statement-journal playback after constraint failure;
- autovacuum pointer-map I/O error consistency checks;
- multi-file commit journal count preservation.

The new focused test file adds 542 distinct TestRunner PASS cases and 10,806 focused behavior assertions from real upstream `ioerr.test` scenarios.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrRecoveryDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrRecoveryDynamicTest.php`
  - Result: `1 test files, 10806 assertions, 0 failures`

## Non-Overlap

This avoids accepted VFS ioerr2 coverage, VFS reopen-fault coverage, VFS file writer/sync/lock/rollback-journal application, WAL checkpoint/savepoint byte truncation, `io.test` default-page/atomic-pager-cache behavior, and all JSON/B-tree/SQL/PRAGMA trigger corpus batches. The new surface is the broader upstream `ioerr.test` recovery matrix for VFS I/O failures and recovery checkpoints.

## Dependency Closure

No new support component is needed. The patch extends the existing native PHP VFS I/O dynamic planner and reuses the existing focused TestRunner harness.
