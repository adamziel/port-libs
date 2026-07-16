# Pager Master Consolidation Thirty-Second Pass

Consolidated the pager master-journal reader-cache `variantNext271` through `variantNext282` production method chain into stable descriptive sequence entry points:

- `currentSourcePagerHeaderSchemaFence()`
- `currentSourceReaderCacheSchemaPayloadFence()`

The canonical dispatcher now routes those arities through the stable methods, and the next remaining direct production caller starts from `currentSourceReaderCacheSchemaPayloadFence()` instead of a numbered wrapper.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext261Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext302Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext334Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next959-974.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing pager reader-cache fence sequence helper.
