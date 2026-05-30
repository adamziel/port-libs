# Pager Master-Journal Reader-Cache Current Source Next240

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a current-source fence for prepared-statement schema-root tickets after master-journal recovery.

The new behavior keeps reader-cache rows only when the accepted next236 schema-reparse fence has already admitted the cache row and the cache/read statement schema-root token matches the current recovered source. Stale statement tickets reopen before Application resumes copied `wp_options` or `active_plugins` reads after a master-journal rollback.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext240Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next240.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext240Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next240.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +67`, from `119121` to `119188`, from the focused PASS lines in `SQLitePagerMasterJournalReaderCacheCurrentSourceNext240Test.php`. Mapped upstream coverage remains `642 / 1589`; this is focused pager/master-journal current-source behavior over existing pager inventory rather than a new manifest-backed upstream row.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal membership, reader-cache, read-transaction, and schema-reparse token primitives.

Non-overlap: avoids accepted next236 schema-reparse cache fencing, next234 application metadata, next235 change-counter, next233 read-transaction, next229 pager-cache source, next224 reader-lease, next218 cleanup-token, rollback-journal apply/commit, WAL checkpoint/savepoint byte truncation, VFS writer/sync/lock, B-tree, JSON table, SELECT executor, and encoding/collation clusters.
