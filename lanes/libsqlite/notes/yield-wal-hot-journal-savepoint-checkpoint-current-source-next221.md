# WAL hot-journal savepoint checkpoint current-source next221

- Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next221`.
- Behavior: adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-next217 current-source barrier that admits the next WAL source only after hot-journal deletion, restarted WAL generation, SHM read-mark reset, savepoint closure, exclusive-lock receipt, and directory sync receipts all match the checkpoint token.
- WordPress path: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next221.php` models a copied plugin import that recovers a hot journal, checkpoints a savepoint batch, then publishes the next source after sidecar cleanup is durable.
- Non-overlap: this does not repeat next217 durable reader receipt admission, next211 page digest admission, next208 reader slot validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning. It covers only the post-admission sidecar retirement barrier.
- Dependency closure: no new support component needed; it reuses lane-local WAL checkpoint metadata, VFS sidecar path conventions, lock receipts, and directory sync receipt modeling.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext221Test.php`
  - `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next221.php`
