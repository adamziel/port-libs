# Pager master-journal reader-cache current source next219

## Behavior

Adds a pager reader-cache current-source fence for the recovered database page count after master-journal recovery. A reader-cache page can only be reused after the existing next217 database-header admission if its cached database page count matches the current recovered source and the requested page number is still inside the current database. This covers copied Application SQLite databases where a crash recovery truncates the database and stale pinned readers still hold cache entries for pages that no longer exist.

## Non-overlap

This slice builds after next217 database-header admission and does not repeat raw master-journal bytes, member journal token/header/order, master file-token, database file-token, database-header digest, rollback-journal apply, WAL checkpoint/savepoint, VFS writer, VFS lock, or super-journal commit behavior.

## Evidence

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext219Test.php`

Expected focused result after this patch: `1 test files, 70 assertions, 0 failures`.

Application smoke:

`php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next219.php --self-test`

Expected smoke result: `application-pager-master-journal-reader-cache-current-source-next219 self-test passed`.

## Dependency closure

No new support component is needed. The slice reuses the lane-local pager master-journal reader-cache current-source chain and only adds a page-count admission field for cache/read tickets.
