# WAL Hot-Journal Savepoint Checkpoint Current Source Next228

## Behavior

Adds the durable checkpoint-source admission fence after the accepted next224 reset publication. The plan admits the next228 current source only when database sync, WAL reset sync, hot-journal directory sync, SHM epoch lock, and savepoint-release barriers all match the published source token, writer generation, checkpoint mode, and database digest. Reopened Application readers must also report the durability barrier and must not pin the old source.

Blocked barriers keep writers waiting when a receipt is missing, the database digest is stale, WAL reset mode does not match, the hot journal was not unlinked durably, the SHM lock is not exclusive, or the savepoint remains open. Blocked readers keep the checkpoint source non-reusable when they have not reopened, missed the durability barrier, pin the old source, or use a stale token/generation.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext228Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next228.php --self-test`

## Non-Overlap

This slice validates post-publication durability barriers and reopened-reader admission. It does not repeat next224 reset publication receipts, next218 reset admission, next212 reader-pin accounting, next209 writer fences, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning.

## Dependency Closure

No new support component is needed. The slice reuses next224 reset publication metadata plus lane-local deterministic durability barrier and reader reopen receipts.
