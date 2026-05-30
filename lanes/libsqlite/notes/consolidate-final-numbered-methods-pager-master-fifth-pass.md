# Pager Master Numbered Method Consolidation Fifth Pass

Consolidated the pager master-journal reader-cache `variantNext943` through `variantNext958` production method chain into the stable `currentSourceVdbeLiteralArithmeticBranchConditionFence()` entry point on `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.

Direct pager-master callers in the focused test and Application smoke now call the descriptive canonical method instead of the numbered production method. The behavior, status markers, dependency strings, and ordinal fence evidence remain unchanged for compatibility with accepted assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext943958Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next943-958.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext943958Test.php`
  - `1 test files, 94 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next943-958.php --self-test`
  - emitted the expected next958 reader reopen diagnostic JSON

Dependency closure: no new support component is needed. This reuses the existing canonical pager master-journal reader-cache fence sequencing helper.
