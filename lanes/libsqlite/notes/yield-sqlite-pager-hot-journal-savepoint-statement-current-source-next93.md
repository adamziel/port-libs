# pager-hot-journal-savepoint-statement-current-source-next93

## Behavior

Adds a bounded pager recovery composition for Application-style imports where a
hot rollback journal is recovered first, the valid WAL prefix remains the
current reader source, and a failed statement inside a savepoint is rolled back
from statement-subjournal preimages before opening a retry statement journal.

The current-source guard verifies the dirty statement pages against the
post-hot-journal WAL reader image before composing the statement rollback. Stale
or missing WAL current-source images are rejected instead of silently restoring
the wrong page image.

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalStatementCurrentSourceNext93Test.php`
- First run: `1 test files, 62 assertions, 0 failures`

## Application smoke

- `php lanes/libsqlite/examples/application-hot-journal-savepoint-statement-current-source-next93.php --self-test`

## Non-overlap

This does not repeat accepted hot rollback admission, WAL byte truncation,
savepoint page-image rollback, statement-current-source-only, master-journal
statement recovery, pager master-journal hot rollback, or WAL checkpoint
restart/truncate visibility. It composes the existing primitives for the
narrower current-source edge where the dirty statement pages must be read from
the recovered WAL view after hot-journal recovery.

## Dependency closure

No new support component is needed. The slice reuses the existing
`SQLiteRollbackJournal`, `SQLiteWal`, and `SQLiteSavepointStack` bounded native
PHP primitives.
