# WAL Hot-Journal Savepoint Checkpoint Current Source Next224

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
publication receipt layer after the accepted next218 RESTART/TRUNCATE reset
admission. It admits a new current source only when database, WAL, hot-journal,
and SHM sidecar receipts match the reset mode and all reader handles are either
reopened on the new source or invalidated as stale.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext224Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next224.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext224Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next224.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: +74 focused PASS lines from the new test file.
Mapped upstream coverage remains unchanged; this is focused current-source
publication behavior over already mapped WAL/checkpoint inventory.

Non-overlap: this sits above next218 reset admission and does not repeat next218
writer fences, next212 frame accounting, WAL byte truncation, VFS writer/sync
apply, rollback-journal commit/apply, checkpoint transaction planning, or
prepared-statement/root-page admission. The new behavior is the sidecar and
reader receipt gate that decides when a reset is visible as the next current
source.

Dependency closure: no new support component needed. The slice reuses lane-local
WAL reset admission metadata, sidecar receipts, and reader reopen/invalidation
receipts.
