# WAL Hot-Journal Savepoint Checkpoint Current Source Next188

## Scope

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded admission layer after the accepted next185 WAL generation guard. The new behavior admits prepared statements and readers only when their observed commit-hook counter and schema cookie still match the current WAL source after hot-journal recovery, savepoint rollback, and checkpoint publication.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext188Test.php`
- Result: `1 test files, 60 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next188.php`

## Non-Overlap

This composes next185 checkpoint sequence/salt generation admission and adds commit-hook/schema-cookie admission. It does not repeat WAL byte truncation, VFS savepoint apply, rollback-journal apply, checkpoint transaction planning, next182 statement root-page admission, or next185 salt/sequence checks.

## Dependency Closure

No new support component is needed. The slice reuses native WAL parsing, hot-journal/savepoint current-source planning, and existing schema-cookie metadata, adding only bounded commit-hook admission metadata.
