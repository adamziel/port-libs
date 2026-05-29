# WAL Hot-Journal Savepoint Checkpoint Current Source Next186

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-apply WAL reader admission verifier layered on the accepted next183 current-source check. The new verifier requires the retained WAL payload to parse with checksum validation and to preserve the expected page size, checkpoint sequence, salts, byte order, and committed frames before a restarted reader can keep a current-source token.

## Evidence

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext186Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next186.php`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext186Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next186.php`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This slice does not repeat accepted next183 file-map reader token admission, next180 atomic publication, checkpoint transaction planning, WAL byte truncation, rollback-journal apply, VFS writer/sync application, or the accepted hot-journal savepoint checkpoint reader-token cluster. It adds retained WAL header/checksum source validation after the published hot-journal checkpoint apply.

## Dependency Closure

No new support component is needed. The slice reuses the existing native WAL parser/checksum validator plus accepted next183 current-source admission.
