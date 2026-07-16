# WAL Hot Journal Savepoint Checkpoint Current Source Next262

## Scope

Adds a post-next260 reader-cache fence for hot-journal/savepoint/checkpoint current-source admission. After the current source is admitted, retry readers may reuse page-cache entries only when the entries match the current source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, reader name, and checkpoint dirty-page set. Stale cache entries that still point at the old hot-journal generation or stale WAL frame must be evicted before retry reads proceed.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext262Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next262.php`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext262Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next262.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This slice does not repeat next260 rollback-journal/savepoint/checkpoint admission, next251 WAL sidecar reset, next246 durable VFS handoff, WAL byte truncation, rollback-journal apply/commit, VFS sync/apply, SQL, JSON, encoding, or B-tree behavior. It only fences reader cache reuse after the admitted checkpoint current source.

## Dependency Closure

No new support component is needed. The slice reuses admitted current-source metadata and lane-local reader cache/retry-read receipts.
