# Consolidate final numbered WAL/VFS pager methods, twenty-seventh pass

Session: `port-dev-sqlite-yield-consol-meth-walvfs-af`

## Scope

Consolidated the pager master-journal reader-cache VDBE virtual-table branch handoff method chain:

- Removed production methods `variantNext847()` through `variantNext862()`.
- Added the stable canonical `currentSourceVdbeVirtualTableBranchHandoff()` entry point backed by `vdbeVirtualTableBranchHandoffSpecs()`.
- Updated the direct test and Application smoke to call the canonical method instead of `variantNext862()`.

This is consolidation-only work. It preserves the accepted `next847` through `next862` behavior and does not change `phpPass` or mapped upstream coverage.

## Verification

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext847862Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next847-862.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext847862Test.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next847-862.php --self-test`
  - emitted the expected `pager-master-journal-reader-cache-current-source-next862` diagnostic with `read-options` reopened

## Dependency Closure

No new support component is needed. This reuses the existing pager reader-cache fence machinery and only removes numbered duplicate production wrappers.
