# Pager Master Journal Reader Cache Consolidation

This slice consolidates the numbered `SQLitePagerMasterJournalReaderCacheCurrentSourceNextNNNPlan` production family into the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` class.

The canonical class exposes `plan()` for the latest accepted behavior and `variantNextNNN()` entry points for the direct migrated tests/examples. No separate numbered production classes remain in this family.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php` -> `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLitePagerMasterJournalReaderCacheCurrentSourceNext[0-9]+Test\.php' | sort -V)` -> `91 test files, 6657 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a source consolidation that preserves the existing pager master-journal reader-cache behavior variants.

Non-overlap: this only consolidates the `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` family. It does not touch pager hot-journal savepoint, B-tree, WAL, SQL planner, encoding, trigger, PRAGMA, VFS, dashboard, or progress files.
