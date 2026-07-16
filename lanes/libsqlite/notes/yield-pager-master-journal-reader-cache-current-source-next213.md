# Pager Master Journal Reader Cache Current Source Next213

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext213Plan`, a current-source pager reader-cache fence for master-journal recovery. After the accepted member-token/header, master file-token, raw master-journal bytes, and database file-token fences admit a cached page, next213 also requires the recovered database page-1 header digest to match before reusing a reader cache page or read ticket.

This prevents a Application import/copy reader from reusing `wp_options` page images when a master-journal recovery advances header state such as change-counter/version-valid-for while coarse database file tokens still look current.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext213Test.php`
- Result: `1 test files, 71 assertions, 0 failures`
- PASS-line delta: `+71` focused libsqlite PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next213.php --self-test`
- Result: `application-pager-master-journal-reader-cache-current-source-next213 self-test passed`

## Non-Overlap

This slice does not repeat accepted next212 database file-token checks, next209 raw master-journal bytes digest checks, next206 master file-token checks, next203 member-order checks, next196 member-header checks, next192 member-token checks, next191 delete/directory-sync fencing, or accepted WAL/rollback/VFS writer paths. It adds only the recovered database header-digest admission fence after those checks pass.

## Dependency Closure

No new support component is needed. The slice reuses bounded pager/master-journal reader-cache models already present in `lanes/libsqlite/src`; native header-ticket acquisition from a live file handle remains part of the broader pager/VFS transaction application path.
