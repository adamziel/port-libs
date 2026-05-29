# Pager Master Numbered Method Consolidation Forty-Sixth Pass

Consolidated the pager master-journal reader-cache statement-affinity and
statement-transaction branch-fence production wrapper range into two descriptive
canonical entry points on `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`:

- `currentSourceVdbeStatementAffinityComparisonBranchFence()`
- `currentSourceVdbeStatementTransactionBranchFence()`

Direct tests and WordPress smokes now call the descriptive canonical helpers and
were renamed to descriptive unsuffixed file names. No numbered compatibility
shims were added, and the exact user-named user-named removed suffix production suffix
remains absent.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStatementAffinityComparisonBranchFenceTest.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStatementTransactionBranchFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-statement-affinity-comparison-branch-fence.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-statement-transaction-branch-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext718Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStatementAffinityComparisonBranchFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStatementTransactionBranchFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext751766Test.php` -> `4 test files, 73 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-statement-affinity-comparison-branch-fence.php --self-test`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-statement-transaction-branch-fence.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
method consolidation inside the existing pager-master reader-cache helper.