# WAL Hot-Journal Savepoint Checkpoint Current Source Next230

## Behavior

Adds the reopened-reader next-source ticket fence after the accepted next227 publish receipt shape. The plan admits next230 readers only when every ticket matches the published source token, next-source epoch, checkpoint frame, checkpoint cookie, schema cookie, scope name, page digests, and hidden hot-journal/WAL-tail state. Duplicate reader tickets and stale ticket metadata hold the reopened current source.

The WordPress example models wp_options and autoload readers consuming checkpointed savepoint scopes only after hot-journal visibility and WAL tail visibility have both been fenced.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext230Test.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next230.php --self-test`

## Non-Overlap

This slice verifies reader tickets after next227 publish receipts. It does not repeat next227 publish sealing, next226 file-state receipts, next218 reset admission, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, or standalone SHM read-mark diagnostics.

## Dependency Closure

No new support component is needed. The slice reuses next227 publish receipts plus deterministic reader ticket metadata for page visibility, checkpoint cookies, and hot-journal/WAL-tail fences.
