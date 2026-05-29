# WAL hot-journal savepoint checkpoint current-source next228

- Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next228`.
- Behavior: adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-next224 durability barrier that admits reopened readers only after database sync, WAL reset sync, hot-journal unlink directory sync, SHM lock epoch, savepoint release, and current-source token/generation receipts all match.
- WordPress path: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next228.php` models a copied plugin import that reopens `wp_options` readers after durable checkpoint-source publication.
- Non-overlap: this does not repeat next224 sidecar publication receipts, next218 reset admission, WAL byte truncation, VFS writer/sync apply, rollback-journal commit/apply, checkpoint transactions, or older reader checkpoint snapshots. It covers only the post-publication durable-source barrier before reader reuse.
- Dependency closure: no new support component needed; this reuses lane-local WAL reset publication metadata, VFS sync receipts, SHM lock epochs, and savepoint release receipts.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext228Test.php`
  - `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next228.php --self-test`
