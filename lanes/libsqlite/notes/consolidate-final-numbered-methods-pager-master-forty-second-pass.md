# Pager-master numbered method consolidation forty-second pass

Consolidated the pager master-journal reader-cache VDBE branch handoff run
from `variantNext799()` through `variantNext830()` into descriptive canonical
helpers on `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`:

- `currentSourceVdbeComparisonBranchHandoff()`
- `currentSourceVdbeAffinityConditionBranchHandoff()`

The helper specs preserve the accepted ordinal/status values and operation
names for direct tests while removing the numbered production wrappers.
Direct tests and WordPress examples that called `variantNext814()` or
`variantNext830()` now call the descriptive canonical helpers.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext799814Test.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext815830Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next799-814.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next815-830.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext783798Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext799814Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext815830Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext831846Test.php`
  passed with `4 test files, 122 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next799-814.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next815-830.php`

Dependency closure: no new support component is needed; this is a canonical
method consolidation over existing pager-master reader-cache behavior.
