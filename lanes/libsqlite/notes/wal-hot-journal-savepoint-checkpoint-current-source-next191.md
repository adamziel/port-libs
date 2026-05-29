# WAL Hot-Journal Savepoint Checkpoint Current Source Next191

## Scope

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded page-cache admission layer after the existing next188 current-source commit-hook guard. It retains cache entries only when they still match the current source token, epoch, commit hook, and schema cookie, and when the page was not touched by hot-journal recovery, savepoint rollback, or checkpoint publication.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext191Test.php`
- Result: `1 test files, 63 assertions, 0 failures`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next191.php`

## Non-Overlap

This slice adds page-cache reuse and invalidation after next188 commit-hook admission. It does not repeat WAL byte truncation, VFS apply, rollback-journal apply, checkpoint transaction planning, reader token retirement, salt/sequence admission, or commit-hook/schema-cookie checks.

## Dependency Closure

No new support component is needed. The slice composes existing current-source metadata with bounded page-cache rows and does not require a new VFS, pager, or upstream runner dependency.
