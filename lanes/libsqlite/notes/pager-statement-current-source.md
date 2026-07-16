# libsqlite pager statement recovery current-source

## Behavior

Adds `SQLiteVfsFileWriter::applyMasterJournalStatementPageRecoveryFromCurrentSource()`.
The VFS apply path now hydrates the master-journal bytes and attached database
images from the current filesystem source before applying statement-journal
page recovery. This prevents a caller from recovering a copied Application
database from stale supplied database bytes while still preserving outer
rollback journals and the master journal for the surrounding transaction.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalRecoveryCurrentSourceTest.php`
  - `1 test files, 62 assertions, 0 failures`
- Example smoke: `php lanes/libsqlite/examples/application-pager-statement-current-source.php`
- Syntax: `php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php`,
  `php -l lanes/libsqlite/tests/SQLitePagerStatementJournalRecoveryCurrentSourceTest.php`,
  and `php -l lanes/libsqlite/examples/application-pager-statement-current-source.php`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-overlap

This does not repeat accepted batch75/batch80 master-journal statement recovery
planning, nor the accepted rollback-journal commit/apply or savepoint rollback
VFS writers. The new surface is the current-source VFS hydration boundary:
database bytes and master-journal membership are read from native PHP file
handles immediately before applying the existing statement-page recovery plan.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLitePagerStatementRecoveryPlan` and `SQLiteVfsFileWriter` atomic operation
support.
