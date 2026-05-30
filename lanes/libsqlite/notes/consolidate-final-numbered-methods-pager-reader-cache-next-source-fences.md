# Pager Reader Cache Next Source Fence Consolidation

Consolidated four remaining numbered pager reader-cache production entry
methods on `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` into
stable descriptive names:

- `planReaderCacheNextSourceFence()`
- `planReaderCacheSourceDigestFence()`
- `planReaderCacheAttachedMasterMemberFence()`
- `planReaderCacheRollbackJournalSourceFence()`

The direct focused tests and WordPress examples were renamed to match the
stable entry names. Existing status strings, dependency labels, receipt keys,
and proof text remain unchanged so accepted observable evidence is preserved.

Verified locally:

- `php -l` for changed PHP files
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheNextSourceFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheSourceDigestFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheAttachedMasterMemberFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentRollbackJournalSourceFenceTest.php` -> `4 test files, 363 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*Test.php` -> `149 test files, 9989 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-next-source-fence.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-source-digest-fence.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-attached-master-member-fence.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-rollback-journal-source-fence.php`
- `git diff --check -- lanes/libsqlite` -> no whitespace errors

Dependency closure: no new support component is needed; this is a production
suffix cleanup over existing pager reader-cache primitives.
