# SQLite pager master-journal reader-cache current-source next719-734

Prepared next719-734 as the direct follow-on to completed next703-718 by extending the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class rather than adding numbered duplicate source classes.

- Scope is limited to pager master-journal reader-cache current-source fences for VDBE add-imm, bit-not, affinity, comparison, jump, once, branch, null-test, and equality handoff receipts.
- The slice requires the next718 shift-right branch handoff state before publishing next734 greater-than branch handoff state.
- Focused coverage: `SQLitePagerMasterJournalReaderCacheCurrentSourceNext734Test.php`
- Matching example: `application-pager-master-journal-reader-cache-current-source-next719-734.php`

Validation:

```sh
php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext734Test.php
php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next719-734.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext734Test.php
php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next719-734.php
git diff --check
```
