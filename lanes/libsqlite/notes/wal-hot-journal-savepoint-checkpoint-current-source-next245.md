# WAL hot journal/savepoint/checkpoint current-source next245

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a reopened-reader cache admission step after the accepted next242 committed writer source. It validates reopened Application import readers against the committed source token, writer/reader generations, database digest, schema cookie, WAL salt/frame bounds, checkpoint-covered page cache, and hot-journal/savepoint/lock fences before serving reads.

Non-overlap: this does not repeat next242 writer commit receipt validation, durable WAL sidecar publication, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, or reader checkpoint snapshots.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next245.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next245.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; the slice reuses next242 committed writer receipts and native PHP metadata for reopened reader snapshots, page-cache coverage, WAL salt/frame visibility, and hot-journal/savepoint fences.

Expected dashboard delta: `phpPass` +86 from the focused PASS lines in `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Test.php`; mapped upstream coverage is unchanged.
