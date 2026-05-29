# WAL Hot-Journal Savepoint Checkpoint Current Source Next256

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded current-source admission guard for reopened WordPress readers after the accepted next252 post-truncate seal.

Behavior covered:

- requires the accepted next252 sealed source state before reader admission;
- verifies reopened reader receipts against database/WAL/journal paths, source token, source generation, database digest, checkpoint sequence, database change counter, schema cookie, readmark slot, WAL size, SHM mxFrame, hot-journal absence, closed savepoint scope, durable database/directory sync, and open read transaction state;
- requires all sealed readers and checkpoint-covered pages to be represented before advancing the current source;
- blocks duplicate receipt names, duplicate reader receipts, duplicate readmark slots, stale pages, stale source generations, hot journal reappearance, non-empty WAL bytes, unreset SHM state, open savepoints, and IO errors.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext256Test.php` passed: `1 test files, 94 assertions, 0 failures` with 94 PASS lines.
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next256.php` passes locally and emits admitted next256 status.
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext256Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next256.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This is not another WAL truncate, readmark reset, hot-journal unlink, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction, VFS sync, SELECT, JSON, encoding, or B-tree slice. It admits readers only after the already modeled next252 source seal.

Dependency closure:

No new support component is needed. The slice reuses lane-local current-source metadata and native PHP receipt validation.
