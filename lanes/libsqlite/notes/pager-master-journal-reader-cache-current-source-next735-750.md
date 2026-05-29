# SQLite pager master-journal reader-cache current-source next735-750

Prepared next735-750 as the direct follow-on to completed next719-734 by extending the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class rather than adding numbered duplicate source classes.

- Scope is limited to pager master-journal reader-cache current-source fences for VDBE comparison branch, seek-hit, open-check, transaction, savepoint, checkpoint, journal-mode, vacuum, and incremental-vacuum handoff receipts.
- The slice preserves the next734 greater-than branch handoff and publishes next750 incremental-vacuum branch handoff state.
- Focused coverage: `SQLitePagerMasterJournalReaderCacheCurrentSourceNext750Test.php`
- Matching example: `wordpress-pager-master-journal-reader-cache-current-source-next735-750.php`

Validation:

```sh
php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext750Test.php
php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next735-750.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheLiteralArithmeticBranchFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext750Test.php
php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next735-750.php
git diff --check
```
