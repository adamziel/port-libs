# Final Numbered Production Suffix Cleanup Dynamic

Consolidated two remaining pager master-journal reader-cache production entry
methods on `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`:

- `variantNext192()` is now `attachedMemberJournalTokenFence()`.
- `variantNext253()` is now `sourceProvenanceChangeCounterFence()`.
- `variantNext574()` is now `currentSourceVdbeIfBranchFence()`.
- `variantNext575()` through `variantNext590()` now share
  `currentSourceVdbeStatementBranchFence()`.

Direct tests and WordPress examples now call the stable descriptive methods.
Returned status, dependency strings, operation labels, and proof keys are
preserved so existing generated evidence remains observable-compatible.

Verification for this handoff:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext192Test.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next192.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next253.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext192Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext253Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext*Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next192.php --self-test`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next253.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this only removes
numbered production method exposure in an existing canonical pager class.
