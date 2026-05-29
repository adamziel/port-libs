# Pager Hot-Journal Savepoint Statement Current Source Next97

## Behavior

Adds a pager-only current-source recovery plan for a WordPress plugin import
that starts from dirty database bytes, applies hot rollback-journal pages when
the super-journal/reserved-lock gates allow recovery, captures savepoint
before-images, rolls back a failed statement from statement before-images, and
starts the retry statement from the corrected current source.

This is intentionally distinct from the accepted WAL hot-journal statement
slice: no WAL frame truncation, WAL reader-pin, WAL checkpoint, or VFS writer
application is modeled here.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointStatementCurrentSourceNext97Test.php`
- Result: `1 test files, 80 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-hot-journal-savepoint-statement-current-source-next97.php`
- Result: JSON smoke reports `hotRecovered: true`, `hasRetryActivePlugins:
  true`, and `hasFailedActivePlugins: false`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP page
image planning concepts and remains lane-local under `lanes/libsqlite/**`.
