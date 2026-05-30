# WAL Hot-Journal Savepoint Checkpoint Current Source Next170

- Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next170`.
- Behavior: after hot-journal recovery and rollback-to-savepoint, restart/truncate checkpoints now fence reader-cache entries by WAL generation. A cache page whose bytes still match the current snapshot is invalidated when the checkpoint publishes a restarted or truncated WAL generation, while passive/reader-blocked checkpoints retain matching current-generation cache pages.
- Application path: interrupted plugin/import writes against a copied `wp_options` database can recover a hot rollback journal, roll back a savepoint, checkpoint the committed WAL, and force reader reopen when the WAL salt/checkpoint sequence changes.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext170Test.php`
  - Result: `1 test files, 81 assertions, 0 failures`.
  - PASS-line delta: `+81`.
- Example smoke:
  - `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next170.php`
  - Result: JSON status `wal-hot-journal-savepoint-checkpoint-current-source-next170`, `walAction` `restart_wal`, `generationChanged` `true`, invalidated cache pages `[1,2,3]`.
- Dependency closure: no new support component needed; the slice reuses existing WAL parsing, durable checkpoint results, and reader snapshot page images.
- Non-overlap: avoids accepted next161 reader source-token fencing, WAL byte truncation, VFS savepoint rollback, WAL checkpoint transaction, rollback-journal apply/commit, super-journal commits, and master-journal cache slices. This slice is specifically the generation fence for identical-image reader cache after restart/truncate checkpoint.
