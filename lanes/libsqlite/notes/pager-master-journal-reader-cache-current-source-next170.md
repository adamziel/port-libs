# Pager Master Journal Reader Cache Current Source Next170

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next170`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext170Plan`. It extends the accepted next161/next164 reader-cache current-source fences without repeating them: after current master-journal membership is established, the plan parses the current rollback journal and rejects reader-cache pages whose cache key was built from a stale rollback-journal source digest, page count, initial database size, or journal page-number set.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next170.php` covers a copied `wp_options` recovery where an `active_plugins` cache page still matches recovered bytes but was keyed from an older rollback journal source, so the next read misses cache and the next write journals from the current rollback source.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext170Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext170Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext170Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext170Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next170.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next170.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext170Test.php`
  - `1 test files, 102 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next170.php`
  - `application-pager-master-journal-reader-cache-current-source-next170 self-test passed`

Non-overlap: avoids accepted next161 master-journal membership digest-only cache fencing and next164 header/change-counter/schema-cookie fencing. It also avoids accepted VFS/WAL rollback apply, savepoint byte truncation, checkpoint transaction, and batch158 next166 pager coverage by focusing only on rollback-journal source identity as the reader-cache admission key before the next source.

Dependency closure: no new support component is needed; this reuses existing lane-local `SQLiteRollbackJournal` parsing plus pager reader-cache current-source planning.

Next task: wire this source-fence evidence into broader pager recovery/open flows once the lane has a native pager transaction executor that owns reader cache entries directly.
