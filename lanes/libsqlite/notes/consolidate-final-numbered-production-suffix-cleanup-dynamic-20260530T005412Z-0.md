# Pager Reader-Cache Allocator Fence Consolidation

Consolidated the pager master-journal reader-cache `variantNext342()` through
`variantNext349()` production wrappers into the canonical metadata-driven fence
range helper. The returned proof numbers, operation labels, dependency text,
and stale-cache reason strings are preserved through
`readerCacheFenceRangeMetadata()`.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLitePagerMasterJournalReaderCache*Test.php' | sort)`
  - `149 test files, 9983 assertions, 0 failures`

Dependency closure: no new support component is needed; this reuses the
existing pager reader-cache current-source fence implementation.

Non-overlap: this patch is cleanup-only for numbered production wrappers in the
pager reader-cache allocator/runtime fence range. It does not claim new
`phpPass`, mapped coverage, or behavior beyond preserving the accepted pager
reader-cache family behavior.
