# Reader-cache schema fence suffix cleanup

Consolidated the pager master-journal reader-cache production wrappers
`variantNext284`, `variantNext285`, `variantNext287`, `variantNext288`,
`variantNext289`, and `variantNext291` into stable descriptive methods on
`SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.

The numeric fence ordinals, status text, operation labels, and array keys are
preserved so existing generated reader-cache evidence remains observable.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext*Test.php`
  - `107 test files, 7565 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a production
suffix consolidation inside the existing pager reader-cache implementation.

Non-overlap: this patch only renames internal reader-cache schema/transaction
fence entry points and does not touch STAT4, JSON table, compound SELECT, VFS,
WAL, or release-runner behavior.
