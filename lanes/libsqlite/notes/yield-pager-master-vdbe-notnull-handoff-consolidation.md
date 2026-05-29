## Pager Master VDBE Not-Null Handoff Consolidation

- Scope: `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.
- Consolidated numbered production wrappers `variantNext639()` through `variantNext653()` into `vdbeNotNullBranchHandoffFenceSpecs()` consumed by the stable `currentSourceVdbeNotNullBranchHandoffFence()` entry point.
- Updated the downstream opcode branch handoff test to assert the accepted descriptive dependency instead of a stale `current-source-next686` dependency.
- No new support component is needed; this reuses the existing pager master-journal reader-cache fence sequence helper.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeOpcodeBranchHandoffFenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeNotNullBranchHandoffFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeOpcodeBranchHandoffFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStatementAffinityComparisonBranchFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheStatementTransactionBranchFenceTest.php`
  - Result: `4 test files, 68 assertions, 0 failures`.
- Example self-tests:
  - `lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-notnull-branch-handoff-fence.php --self-test`
  - `lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-opcode-branch-handoff-fence.php --self-test`
  - `lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-statement-affinity-comparison-branch-fence.php --self-test`
  - `lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-statement-transaction-branch-fence.php --self-test`
  - Result: all exited `0`.

Root harness: not run - isolated micro-slice.
