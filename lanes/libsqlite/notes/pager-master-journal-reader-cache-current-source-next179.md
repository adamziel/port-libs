# Pager master-journal reader-cache current-source next179

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next179`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It models the pager boundary after a current master journal is read through VFS path aliases: reader-cache pages are admitted only after the raw master-journal members are resolved to canonical paths and the canonical member digest proves the same attached rollback journals. Reordered or aliased raw member names can retain or refresh clean cache pages, while dirty pages, stale source/epoch tickets, pinned stale images, wrong canonical member sets, and stale read tickets force reader reopen before the next read/write.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next179.php` covers a copied `wp_options` database where schema and `active_plugins` cache pages survive VFS alias canonicalization, while plugin settings from an old source reopen before the import continues.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext179Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next179.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext179Test.php`
  - `1 test files, 96 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next179.php`
  - `application-pager-master-journal-reader-cache-current-source-next179 self-test passed`

Expected dashboard delta: `phpPass` moves from `83912` to `84008` from 96 newly passing focused PASS lines. Mapped upstream coverage remains `614 / 1589`; this is focused pager/VFS canonical-path behavior over already mapped master-journal reader-cache inventory.

Non-overlap: avoids accepted next170 rollback-journal source digest/page-set fencing, next174 canonical member ordering plus rollback-source rebasing, next175 rollback-journal checksum quarantine, next176 current-to-next source rollover, next176 reader-cache rollover acceptance, VFS writer/sync/lock, rollback-journal commit/apply, WAL checkpoint/savepoint, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically VFS canonical pathname admission for master-journal members before reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal reader-cache planning with a bounded VFS canonical pathname map.

Next task: wire canonical master-journal path admission into broader pager open/recovery flows when a native pager transaction executor owns reader-cache tickets directly.
