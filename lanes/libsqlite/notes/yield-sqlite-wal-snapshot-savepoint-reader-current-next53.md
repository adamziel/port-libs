# yield-sqlite-wal-snapshot-savepoint-reader-current-next53

- Implemented `SQLiteWalSavepointCheckpointPlan::readerCurrentNextAfterRelease()` for WAL reader current/next behavior after `RELEASE savepoint`.
- Behavior: released savepoint frames remain in the WAL, current readers keep their pinned read-mark snapshot, next readers see released savepoint frames or checkpointed database bytes according to the checkpoint mode and read-mark state.
- Added `SQLiteWalSnapshotSavepointReaderCurrentNext53Test.php` with 65 focused PASS assertions.
- Added `application-wal-snapshot-savepoint-reader-current-next53.php` smoke for copied `wp_options` import diagnostics.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSnapshotSavepointReaderCurrentNext53Test.php`
  - Result: `1 test files, 65 assertions, 0 failures`.
- Non-overlap: this does not repeat accepted WAL byte truncation, savepoint rollback apply, checkpoint transaction, VFS rollback/sync/file-writer, or reader-pin-after-rollback clusters. It covers `RELEASE` merging WAL savepoint frames while preserving current-reader and next-reader visibility boundaries.
- Dependency closure: no new support component needed; the slice reuses `SQLiteSavepointStack`, `SQLiteWal`, read-mark planning, and durable checkpoint result helpers.
