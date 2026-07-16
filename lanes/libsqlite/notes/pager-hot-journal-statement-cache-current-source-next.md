# Pager hot-journal statement cache current-source next104

Status: focused PHP behavior growth for `pager-hot-journal-statement-cache-current-source-next104`.

This slice adds `SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan`. It models the pager statement-cache boundary after hot rollback-journal recovery changes the current source token: active statement caches remain pinned until the current step finishes, reset/retry read statements are expired and can reprepare from recovered source pages, and write statements are blocked before retry when stale cache pages overlap recovered or dirty statement pages.

Application relevance: copied `wp_options` plugin import statements can leave an active reader, a retryable reader, and a writer cached across a hot-journal recovery. The smoke proves the active reader finishes on its pinned source while the next reader/writer see `SQLITE_SCHEMA` style cache expiry before using recovered root/statement rollback pages.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePagerHotJournalStatementCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerHotJournalStatementCacheCurrentSourceNext104Test.php
php -l lanes/libsqlite/examples/application-hot-journal-statement-cache-current-source-next104.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalStatementCacheCurrentSourceNext104Test.php
php lanes/libsqlite/examples/application-hot-journal-statement-cache-current-source-next104.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 74 assertions, 0 failures` with 74 PASS lines.

Application smoke: `application-hot-journal-statement-cache-current-source-next104 self-test passed`.

Dashboard delta: `phpPass` moves from `40110` to `40184` for the 74 verified PASS lines. Mapped upstream coverage remains `597 / 1589`; this is fresh focused PHP behavior over the already mapped pager hot-journal/statement-cache current-source inventory rather than a new upstream denominator row.

Non-overlap: this avoids accepted next100 hot-journal savepoint cache release reads, next97 hot-journal savepoint statement retry materialization, next93 hot-journal statement recovery, statement-journal savepoint next102, WAL savepoint byte truncation, VFS savepoint rollback/rollback-journal/super-journal/sync/lock clusters, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is prepared statement cache source-token expiry after hot-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local hot-journal recovery, statement journal rollback, and pager cache source-token concepts.

Next task: wire this cache expiry decision into broader prepared statement execution when the native pager applies hot-journal recovery during real statement stepping.
