# pager-statement-journal-savepoint-current-source-next102

## Behavior

Adds `SQLitePagerStatementJournalSavepointCurrentSourceNextPlan` for the pager boundary where a failed statement journal is rolled back while an outer savepoint remains the current source. The plan verifies the current database page images, restores the failed statement's before-images, starts the next statement journal from the restored source, and optionally models `RELEASE` merging retry pages into the parent transaction.

Application smoke:

- `lanes/libsqlite/examples/application-pager-statement-journal-savepoint-current-source-next102.php`
- Covers a copied `wp_options` plugin import where a failed `active_plugins` statement is rolled back inside `plugin-batch-next102`, retried from restored page images, then released.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointCurrentSourceNext102Test.php`
- Result: `1 test files, 84 assertions, 0 failures`.
- `lane-status.json` `phpPass`: `39474 -> 39558` (`+84` focused PASS lines).
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `587 -> 588` for one focused pager statement-journal/savepoint current-source evidence row.

## Non-Overlap

This avoids accepted hot-journal statement recovery, pager savepoint journal filehandle recovery, VFS savepoint rollback apply, rollback-journal commit/apply, WAL byte truncation, super-journal commit, VFS file writer/locked writer/sync, and batch99 pager savepoint journal filehandle recovery. The new behavior is statement-journal rollback inside an active savepoint followed by retry-source capture and optional release.

## Dependency Closure

No new support component is needed. The slice reuses existing native pager page-image and statement-journal modeling; no external SQLite extension, live service, or shell-out is required.
