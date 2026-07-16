# WAL Hot-Journal Savepoint Checkpoint Current Source Next229

## Behavior

Adds a post-publication reopened-handle fence after the accepted next224 reset publication. The new plan admits the checkpoint current source only when reopened Application import readers match the published source token, writer generation, database digest, non-stale WAL digest, clean savepoint/hot-journal state, lock/sync receipts, and all checkpoint page digests.

Blocked handles keep the previous current source visible when a reader reuses an old source token, stale writer generation, previous WAL digest, dirty cache, open savepoint scope, visible hot journal, missing lock/sync receipt, or stale/missing page image.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext229Test.php`
- Result: `1 test files, 55 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next229.php`

## Non-Overlap

This slice validates reopened handle visibility after next224 publication. It does not repeat next224 reset publication receipts, next218 reset admission, next212 reader-pin frame accounting, next209 writer fences, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning.

## Dependency Closure

No new support component is needed. The slice reuses next224 publication receipts and reopened handle/page digest metadata.
