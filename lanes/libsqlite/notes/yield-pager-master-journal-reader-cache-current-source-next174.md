# Pager Master-Journal Reader Cache Current Source Next174

Slice: `pager-master-journal-reader-cache-current-source-next174`

Behavior added:

- Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext174Plan`.
- Master-journal reader-cache admission now fences on a canonical member set digest, so a reader cache page is retained when the same attached rollback journals are listed in a different order.
- Real membership changes, rollback-journal source digest changes, journal page count/initial-size/page-set changes, dirty pages, pinned stale images, stale source ids, and stale epochs still invalidate cache entries before the next read/write.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext174Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next174.php`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext174Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext174Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next174.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted next160/next161/next165/next170 pager master-journal reader-cache behaviors by adding only canonical member-set admission for reordered master-journal membership.
- Does not touch accepted WAL byte truncation, rollback-journal commit/apply, VFS writer/sync/lock, B-tree page move/freeblock/overflow, JSON table, SQL SELECT text, or encoding/GLOB surfaces.

Dependency closure:

- No new support component is needed. The slice reuses lane-local rollback-journal parsing and pager reader-cache current-source planning.
