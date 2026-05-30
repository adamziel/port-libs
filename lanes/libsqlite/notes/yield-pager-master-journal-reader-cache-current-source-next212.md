# Pager Master Journal Reader Cache Current Source Next212

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a current-source pager reader-cache fence for master-journal recovery. After the accepted membership, member-token/header, master file-token, and raw master-journal bytes fences admit a cached page, next212 also requires the recovered database file token to match before reusing the reader cache or read ticket.

This prevents a Application import/copy reader from reusing `wp_options` page images when a master-journal recovery publishes a newer database file generation while the master journal metadata itself still looks current.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext212Test.php`
- Result: `1 test files, 71 assertions, 0 failures`
- PASS-line delta: `+71` focused libsqlite PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next212.php --self-test`
- Result: `application-pager-master-journal-reader-cache-current-source-next212 self-test passed`

## Non-Overlap

This slice does not repeat accepted next209 raw master-journal bytes digest checks, next206 master file-token checks, next203 member-order checks, next196 member-header checks, next192 member-token checks, next191 delete/directory-sync fencing, or accepted WAL/rollback/VFS writer paths. It adds only the recovered database file-token admission fence after those checks pass.

## Dependency Closure

No new support component is needed. The slice reuses bounded pager/master-journal reader-cache models already present in `lanes/libsqlite/src`; native VFS file-token acquisition remains a future wiring target for the broader pager/VFS transaction application path.
