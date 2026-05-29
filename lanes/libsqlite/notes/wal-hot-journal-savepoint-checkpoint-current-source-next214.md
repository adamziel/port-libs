# WAL Hot-Journal Savepoint Checkpoint Current Source Next214

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded model for SQLite RESTART checkpoint admission after a hot-journal recovery and savepoint checkpoint sequence. The plan admits WAL restart only when:

- the inherited next212 PASSIVE checkpoint completed through the requested frame;
- all current-source readers are released;
- stale/mismatched readers are classified for reopen;
- the savepoint is closed and an exclusive checkpoint lock is held;
- database, WAL-header, and directory sync receipts are present;
- the WAL salt rotates before hot-journal deletion.

This models the WordPress import path where a copied SQLite database must not delete a hot journal or reset the WAL while current readers still pin the recovered source.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext214Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next214.php`
  - `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next214 self-test passed`

Expected lane-local pass movement: `phpPass 103870 -> 103946` (`+76` focused PASS lines). Root harness was not run for this isolated micro-slice.

## Non-Overlap

This slice adds RESTART checkpoint admission after next212 PASSIVE reader-pin completion. It does not repeat next212 PASSIVE reader pins, next209 writer fences, next206 statement consumers, checkpoint transaction planning, VFS savepoint rollback, rollback-journal commit/apply, WAL byte truncation, or WAL file writer wrappers.

## Dependency Closure

No new support component is needed. The patch reuses lane-local current-source digest metadata, WAL salt digests, reader release rows, and VFS sync/delete receipts.
