# Pager master-journal reader-cache current-source next375-382

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next375-382` as the direct follow-on to merged next367-374.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next375 through next382 fences for statement vmstep, filter-hit, filter-miss, nsort, nautoindex, nfullscan, expired, and readonly reader-cache tokens. Reader tickets that predate these current statement counters must reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext382Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext374Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext382Test.php`
- `git diff --check`

Examples: no separate Application self-test is present for the consolidated next367-374/next375-382 pager slice; coverage is the focused PHP test fixture.

Non-overlap: builds only on accepted next367-374 statement/runtime reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA schema, or unrelated planner behavior.

Next slice: continue with pager master-journal reader-cache current-source statement/cache invalidation fences after next382.
