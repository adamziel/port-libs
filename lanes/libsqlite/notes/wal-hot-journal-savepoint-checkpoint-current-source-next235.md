# WAL Hot-Journal Savepoint Checkpoint Current Source Next235

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next235`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` coverage for the durable publication fence after accepted next232 reader-slot admission. Reopened readers stay on the checkpoint current source only when the database, restarted WAL, deleted hot journal, and containing directory receipts all match the same source token, writer generation, schema cookie, WAL salt, checkpoint pages, and lock receipt.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next235.php` models copied `wp_options` readers staying on the current source only after the checkpoint database image, restarted WAL sidecar, deleted hot journal, and containing directory are durably published.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext235Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next235.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext235Test.php`
  - `1 test files, 80 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next235.php`
  - `application-wal-hot-journal-savepoint-checkpoint-current-source-next235 self-test passed`

Expected dashboard delta: `phpPass +80` focused PASS lines. Mapped upstream coverage is unchanged; this is focused WAL hot-journal/savepoint/checkpoint current-source behavior over existing inventory rather than a new manifest-backed upstream row.

Non-overlap: next235 validates durable publication receipts after next232 reader-slot admission. It does not repeat WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, file-writer byte application, WAL-index reopen receipts, reader-slot admission, B-tree, JSON, SELECT, or encoding surfaces.

Dependency closure: no new support component is needed. The slice reuses next232 reader-slot admission plus native VFS file, journal-delete, WAL reset, and directory-sync receipt evidence.
