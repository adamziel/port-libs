# Pager master-journal reader-cache suffix consolidation

## Scope

- Consolidated the numbered pager master-deleted reader-cache entry point into the stable production entry point `masterDeletedGenerationReaderCachePlan()`.
- Renamed the direct focused test and WordPress smoke file away from numbered current-source wording.
- Preserved accepted observable receipt metadata, including the existing status and dependency strings, so downstream generated-key assertions continue to validate the same behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterDeletedGenerationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-master-deleted-generation.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterDeletedGenerationTest.php`
  - `1 test files, 85 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-master-deleted-generation.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-master-deleted-generation self-test passed`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*Test.php`
  - `149 test files, 9983 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This cleanup reuses the existing lane-local pager master-journal membership and reader-cache source ticket primitives.
