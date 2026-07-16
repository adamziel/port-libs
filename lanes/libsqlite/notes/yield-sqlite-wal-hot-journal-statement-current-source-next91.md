# WAL hot-journal statement current-source next91

## Behavior

Adds `SQLiteWalHotJournalSavepointReplayPlan::statementCurrentSourceNext91()` for the pager edge where a copied Application SQLite database has:

- a hot rollback journal that must be restored first;
- a current WAL image whose statement frames are still visible to the failed statement current source;
- a statement subjournal that rolls the failed statement page back before the next retry statement is opened.

This intentionally does not duplicate the accepted savepoint WAL byte-truncation path: savepoint replay truncates at the savepoint boundary, while this slice checkpoints the current WAL image first and then truncates to the failed statement journal's WAL start frame.

## Focused evidence

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalStatementCurrentSourceNext91Test.php`

Result: `1 test files, 66 assertions, 0 failures` with 66 PASS lines.

`php lanes/libsqlite/examples/application-wal-hot-journal-statement-current-source-next91.php --self-test`

Result: passed; the smoke reports hot-journal recovery, statement page restoration, next retry statement setup, and WAL prefix truncation for a copied `wp_options` import path.

## Dependency closure

No new support component is needed. This reuses the existing native rollback-journal parser/checksum validation, WAL parser/checksum recovery boundary, and `SQLiteSavepointStack` statement-journal rollback primitives.

## Non-overlap

Avoids accepted batch88 pager savepoint hot-journal recovery, WAL reader checkpoint/truncate handoff, VFS file writer/sync application, rollback-journal commit/super-journal commit, and savepoint WAL byte-truncation clusters. The new surface is the statement-current-source sequence after hot-journal plus current WAL checkpointing.
