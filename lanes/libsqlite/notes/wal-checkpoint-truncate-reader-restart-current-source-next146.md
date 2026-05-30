# WAL checkpoint truncate reader restart current-source next146

Status: focused PHP behavior growth for `wal-checkpoint-truncate-reader-restart-current-source-next146`.

This slice adds `SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateReaderRestartCurrentSourceNext()`. It models the current-source boundary after a savepoint rollback leaves an old WAL reader pinned, a released-reader TRUNCATE checkpoint removes the old WAL sidecar, and the next opened reader must restart at frame 0 on the fresh WAL header/current database image before any retry writer appends a new generation. A stale reader WAL source is reported as blocked and requiring reopen.

Application smoke: `application-wal-checkpoint-truncate-reader-restart-current-source-next146.php` covers a copied `wp_options` import that rolls back plugin-setting frames, waits for the old reader to drain, truncates the WAL, and reopens the reader against the checkpointed database/fresh WAL source.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointTruncateReaderRestartCurrentSourceNext146Test.php` -> no syntax errors
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-truncate-reader-restart-current-source-next146.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointTruncateReaderRestartCurrentSourceNext146Test.php` -> `1 test files, 77 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-checkpoint-truncate-reader-restart-current-source-next146.php --self-test` -> self-test passed

Expected dashboard movement: `phpPass` +77, from `64226` to `64303`, from the independent PASS lines in `SQLiteWalCheckpointTruncateReaderRestartCurrentSourceNext146Test.php`. Mapped upstream coverage remains `606 / 1589`; this is focused PHP behavior coverage over existing WAL/pager inventory rather than a newly mapped upstream manifest row.

Non-overlap: avoids accepted next142 append-after-truncate, batch141 WAL reader checkpoint truncate savepoint behavior, WAL checkpoint hot-journal truncate next138, reader checkpoint savepoint next139, WAL byte truncation, VFS savepoint rollback/write/sync/lock application, rollback-journal commit/apply, and checkpoint transaction planning. The new surface is specifically the reopened reader current-source restart at frame 0 after released TRUNCATE and before retry append.

Dependency closure: no new support component is needed; this reuses native PHP WAL parsing, savepoint rollback frame truncation, SHM read-mark checkpoint admission, durable checkpoint result, and fresh WAL header helpers.
