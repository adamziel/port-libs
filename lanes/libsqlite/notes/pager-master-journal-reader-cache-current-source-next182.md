# Pager master-journal reader-cache current-source next182

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next182`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext182Plan`. It models the pager reader-cache fence after the current master journal points at a rollback journal whose page count is unknown (`0xffffffff`) and whose page records must be scanned to EOF with checksum validation before current-source cache pages can be reused. Cache pages are retained or refreshed only when the master digest, rollback-journal byte digest, checksum nonce, EOF-scanned record count, page-number set, source id, epoch, and pinned image all match the checksum-validated current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next182.php` models copied `wp_options` import recovery where a schema reader-cache page survives the checksum-validated master-journal boundary, while a stale `active_plugins` cache row reopens before the next import write.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext182Test.php`
  - `1 test files, 99 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext182Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext182Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next182.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next182.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `85432` to `85531` from 99 newly passing focused PASS lines in this isolated worktree. Mapped upstream coverage remains `614 / 1589`; this is focused pager current-source behavior over existing master-journal/rollback-journal reader-cache inventory rather than a fresh manifest row.

Non-overlap: avoids accepted pager master-journal reader-cache next159/next161/next166/next170/next173/next174/next176/next178 behavior by adding only checksum nonce, checksum validation, unknown-page-count EOF scan, and record-set admission to the reader-cache current-source fence. It does not touch accepted WAL byte truncation, rollback-journal commit/apply, super-journal commit, VFS writer/sync/lock, B-tree page move/freeblock/overflow, JSON table, SQL SELECT text, or encoding/GLOB surfaces.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal checksum validation, unknown page-count parsing, and pager reader-cache current-source primitives.
