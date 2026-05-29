## consolidate-final-numbered-methods-wal-vfs-thirty-ninth-pass

- Consolidated `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` reader-cache rollback-journal source fence wrappers `267` through `270` into the stable `readerCacheRollbackJournalSourceFence()` production method.
- Migrated the direct focused test to `SQLitePagerMasterJournalReaderCacheRollbackJournalSourceFenceTest.php` and the WordPress smoke to `wordpress-pager-master-journal-reader-cache-rollback-source-fence.php`.
- Removed the four numbered direct test files and four numbered WordPress smoke files for the duplicate wrapper family.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheRollbackJournalSourceFenceTest.php`
  - `1 test files, 66 assertions, 0 failures`
  - `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-rollback-source-fence.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-rollback-source-fence self-test passed`
- Dependency closure: no new support component needed; the canonical method reuses existing pager master-journal reader-cache, recovery receipt, reader snapshot, spill-drain, and rollback-journal reader-source token behavior.
