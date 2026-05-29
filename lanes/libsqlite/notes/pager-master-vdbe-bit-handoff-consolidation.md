# Pager Master VDBE Bit Handoff Consolidation

Consolidated the pager master-journal reader-cache VDBE bit handoff production chain by removing the public numbered ordinal 623-637 wrapper methods from `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.

The canonical `currentSourceVdbeBitAndHandoffFence()` entry point now applies the same accepted ordinal 623-638 token fences through a descriptive private spec list. Direct callers already use the canonical method, so no numbered compatibility shim remains for this subfamily.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeBitAndHandoffFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-bit-and-handoff-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheVdbeBitAndHandoffFenceTest.php`
  - `1 test files, 15 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-vdbe-bit-and-handoff-fence.php`
  - emitted `pager-master-journal-reader-cache-current-source-next638` with `read-options` reopened and page `1` invalidated.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production helper-method consolidation inside the existing libsqlite pager-master PHP implementation.
