## Consolidate Final Numbered Methods WAL/VFS Forty-Fourth Pass

Consolidated one remaining pager-master reader-cache production wrapper in
`SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.

- Renamed the index-schema reader-cache wrapper to the stable descriptive
  `currentSourceReaderCacheIndexSchemaFence()`.
- Updated the direct production caller `variantNext284()` to use the
  descriptive method.
- Left no compatibility shim for the old numbered production method.
- The exact user-named suffix remains absent from the libsqlite source, tests,
  examples, and notes scan.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext302Test.php`
  - `1 test files, 30 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component is needed. This is a production
method-name consolidation in an existing pager helper family.
