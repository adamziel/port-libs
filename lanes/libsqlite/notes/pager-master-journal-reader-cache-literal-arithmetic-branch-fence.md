# SQLite pager master-journal reader-cache current-source next703-718

Prepared next703-718 as the direct follow-on to completed next687-702 by extending the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class rather than adding numbered duplicate source classes.

- Scope is limited to pager master-journal reader-cache current-source fences for VDBE real-value, boolean, null-row, row-value, zeroblob, string8, concat, arithmetic, bitwise, and shift handoff receipts.
- The slice requires the next702 int64 branch handoff state before publishing next718 shift-right handoff state.
- Focused coverage: `SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php`
- Matching example: `wordpress-pager-master-journal-reader-cache-literal-arithmetic-branch-fence.php`

Validation:

```sh
php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext702Test.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php
php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-literal-arithmetic-branch-fence.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext702Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php
php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-literal-arithmetic-branch-fence.php
git diff --check
```
