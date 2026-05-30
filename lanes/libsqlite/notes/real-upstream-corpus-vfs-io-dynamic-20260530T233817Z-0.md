# real-upstream-corpus-vfs-io-dynamic-20260530T233817Z-0

Added a focused real-upstream VFS I/O dynamic batch for `/home/claude/port-libs/.upstream-cache/libsqlite/test/journal2.test`.

## Upstream Sections

- `journal2.test` `journal2-1.1`: SAFE_DELETE VFS opens, closes, and deletes the rollback journal for a create-table transaction.
- `journal2.test` `journal2-1.2` through `journal2-1.4`: truncate-mode journal reuse preserves inserted rows without a delete call.
- `journal2.test` `journal2-1.5` through `journal2-1.9`: a second connection in delete mode receives `disk I/O error` when xDelete is attempted while a journal handle is open, keeps the journal visible, and preserves original rows until a truncate retry succeeds.
- `journal2.test` `journal2-1.10` through `journal2-1.21`: a large dirty transaction that hits xDelete/xWrite/xTruncate faults leaves a hot journal, lets the first connection roll back to integrity `ok`, and leaves the copied pre-recovery image corrupt.
- `journal2.test` `journal2-2.1` through `journal2-2.4`: switching from persistent rollback journal mode to WAL closes and deletes the journal.

## Patch

- Extended `SQLiteVfsIoDynamicPlan` with `safeDeleteJournalLifecycle()` for a source-neutral model of the upstream SAFE_DELETE VFS rollback-journal lifecycle.
- Added `SQLiteRealUpstreamCorpusVfsJournal2SafeDeleteDynamicTest.php` with 1,002 focused TestRunner PASS cases and 27,008 behavior assertions over the five upstream section groups.

## Non-Overlap

This does not repeat accepted `io.test`, `ioerr*.test`, `sysfault.test`, mmap, backup I/O error, auto-vacuum I/O error, walvfs, VFS lock/file-writer/sync/rollback-journal apply/commit, WAL checkpoint/savepoint, JSON, B-tree, SQL, or PRAGMA corpus batches. The owned upstream gap is `journal2.test` SAFE_DELETE rollback-journal operation ordering and hot-journal recovery.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` - pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal2SafeDeleteDynamicTest.php` - pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsJournal2SafeDeleteDynamicTest.php` - `1 test files, 27008 assertions, 0 failures`, 1,002 PASS lines.

## Dependency Closure

No new support component is required. The batch reuses the existing generic VFS I/O dynamic corpus planning surface and adds one bounded native PHP helper for upstream SAFE_DELETE journal behavior.
