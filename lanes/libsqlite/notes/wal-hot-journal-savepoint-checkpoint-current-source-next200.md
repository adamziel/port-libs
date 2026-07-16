# WAL hot-journal savepoint checkpoint current-source next200

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-next194 durable-reader admission gate for retry-checkpoint readers.

Behavior covered:

- Requires a next194 sealed reader generation before durable readers are exposed.
- Verifies each sealed reader ticket has a matching durability receipt.
- Fences the receipts by publication token, reader epoch range, hot-journal recovery digest, savepoint generation, checkpoint database digest, WAL sync, database sync, directory sync, reopened-reader evidence, and savepoint release evidence.
- Blocks partial receipt sets so a copied Application import cannot expose one retry reader while another sealed reader lacks durable hot-journal/savepoint/checkpoint evidence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext200Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next200.php --self-test`

Dependency closure: no new support component is needed; this reuses existing WAL/checkpoint publication, savepoint, sync, and reader-generation receipt concepts.

Non-overlap: this does not repeat WAL byte truncation, checkpoint file-map publication, VFS writer/sync application, rollback-journal apply/commit, or next194 reopened reader ticket sealing. It adds the later durability receipt admission check.
