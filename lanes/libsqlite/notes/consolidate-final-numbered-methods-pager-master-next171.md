# Pager Master Journal Reader Cache Next171 Entry Consolidation

Consolidated the pager master-journal reader-cache `variantNext171()` production entry point into the descriptive stable `masterJournalRecoveryReaderCacheTicket()` method on `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.

Direct test and WordPress smoke callers now use the stable method name. Observable behavior is preserved: status strings, dependency markers, proof names, error text, and assertion labels still carry the accepted next171 evidence keys.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next171.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Test.php` -> `1 test files, 106 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next171.php --self-test`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*Test.php` -> `149 test files, 9989 assertions, 0 failures`

No new support component is needed; this reuses the existing lane-local pager master-journal membership and reader-cache ticket primitives.
